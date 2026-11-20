#!/usr/bin/env bash
#
# One-time setup of Tenders.af on a cPanel account. Run it in cPanel > Terminal:
#
#     bash <(curl -fsSL https://raw.githubusercontent.com/fidatech/tenders-af/main/tools/cpanel-setup.sh)
#
# It makes, on this cPanel account:
#   - a key pair only for deploying, and lets it sign in over SSH
#   - the MySQL database and its user
#   - the mailbox the site sends from (noreply@DOMAIN)
# and saves everything GitHub needs to deploy as encrypted secrets of the repository.
# After that, every push to "main" puts the site online by itself.
#
# It asks for a GitHub token once (not stored): github.com > Settings > Developer settings >
# Fine-grained tokens > Generate new token > Only select repositories: tenders-af >
# Permissions > Repository > Secrets: Read and write.
#
# Running it again is safe: it keeps the key pair and the APP_KEY, and gives the database
# user, the mailbox and the administrator new passwords.
set -euo pipefail

REPO="fidatech/tenders-af"
DOMAIN="${DOMAIN:-tenders.af}"
APP_DIR="${APP_DIR:-$HOME/tenders-af}"
SAVE="$HOME/.tenders-setup.txt"

say() { printf '\n\033[1;32m== %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m!! %s\033[0m\n' "$*"; }
# a random password of letters and digits (head closing the pipe is expected, not an error)
secret() { local s; s="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c "${1:-24}" || true)"; printf '%s' "$s"; }

# ------------------------------------------------------------------ PHP (for encrypting)
PHP=""
for candidate in /opt/cpanel/ea-php85/root/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php "$(command -v php || true)"; do
    if [ -n "$candidate" ] && [ -x "$candidate" ] && "$candidate" -r 'exit(function_exists("sodium_crypto_box_seal") && function_exists("curl_init") ? 0 : 1);' 2>/dev/null; then PHP="$candidate"; break; fi
done
[ -n "$PHP" ] || { echo "No PHP with the sodium and curl extensions was found. In WHM > MultiPHP, install PHP 8.3 or 8.4 for this account and run this again."; exit 1; }

# ------------------------------------------------------------------ questions
echo "Tenders.af setup for the cPanel account \"$USER\" (domain $DOMAIN)."
read -r -s -p "GitHub token (it is not shown or stored): " GH_TOKEN; echo
[ -n "$GH_TOKEN" ] || { echo "A token is needed."; exit 1; }
read -r -p "Your name, for the first administrator: " ADMIN_NAME
read -r -p "Your email, to sign in to the admin panel: " ADMIN_EMAIL
[ -n "$ADMIN_NAME" ] && [ -n "$ADMIN_EMAIL" ] || { echo "Name and email are needed."; exit 1; }

DEFAULT_PORT="$(grep -hiE '^\s*Port\s+[0-9]+' /etc/ssh/sshd_config 2>/dev/null | awk '{print $2}' | head -n1 || true)"
DEFAULT_PORT="${DEFAULT_PORT:-22}"
read -r -p "SSH port of this server [$DEFAULT_PORT]: " SSH_PORT; SSH_PORT="${SSH_PORT:-$DEFAULT_PORT}"
DEFAULT_HOST="$(curl -fsS --max-time 8 https://api.ipify.org 2>/dev/null || hostname -f)"
read -r -p "Address GitHub uses to reach this server [$DEFAULT_HOST]: " SSH_HOST; SSH_HOST="${SSH_HOST:-$DEFAULT_HOST}"

# ------------------------------------------------------------------ deploy key
say "Key pair for deploying"
mkdir -p "$HOME/.ssh" && chmod 700 "$HOME/.ssh"
KEY="$HOME/.ssh/tenders_deploy"
[ -f "$KEY" ] || ssh-keygen -q -t ed25519 -N "" -C "github-deploy-tenders" -f "$KEY"
touch "$HOME/.ssh/authorized_keys" && chmod 600 "$HOME/.ssh/authorized_keys"
grep -qF "$(cut -d' ' -f2 "$KEY.pub")" "$HOME/.ssh/authorized_keys" || cat "$KEY.pub" >> "$HOME/.ssh/authorized_keys"
echo "The key may sign in to $USER@$SSH_HOST:$SSH_PORT."

# ------------------------------------------------------------------ database
say "MySQL database"
DB_NAME="${USER}_tenders"; DB_USER="${USER}_tenders"; DB_PASS="$(secret 28)"
uapi --output=json Mysql create_database name="$DB_NAME" >/dev/null 2>&1 || true
if ! uapi --output=json Mysql create_user name="$DB_USER" password="$DB_PASS" 2>/dev/null | grep -q '"status":1'; then
    uapi --output=json Mysql set_password user="$DB_USER" password="$DB_PASS" >/dev/null
fi
uapi --output=json Mysql set_privileges_on_database user="$DB_USER" database="$DB_NAME" privileges=ALL%20PRIVILEGES >/dev/null
uapi --output=json Mysql list_databases 2>/dev/null | grep -q "\"$DB_NAME\"" || { echo "The database $DB_NAME could not be made. Check cPanel > MySQL Databases."; exit 1; }
echo "Database $DB_NAME, user $DB_USER."

# ------------------------------------------------------------------ mailbox
say "Mailbox noreply@$DOMAIN"
MAIL_USER="noreply@$DOMAIN"; MAIL_PASS="$(secret 24)Aa9"
if ! uapi --output=json Email add_pop email=noreply domain="$DOMAIN" password="$MAIL_PASS" quota=500 2>/dev/null | grep -q '"status":1'; then
    uapi --output=json Email passwd_pop email=noreply domain="$DOMAIN" password="$MAIL_PASS" >/dev/null 2>&1 || warn "The mailbox could not be made; emails will not go out until it exists (cPanel > Email Accounts)."
fi
MAIL_HOST="mail.$DOMAIN"

# ------------------------------------------------------------------ application key and admin
OLD_KEY="$(grep -s '^APP_KEY=' "$SAVE" | cut -d= -f2- || true)"
APP_KEY="${OLD_KEY:-base64:$(head -c 32 /dev/urandom | base64)}"
ADMIN_PASSWORD="$(secret 16)"

# ------------------------------------------------------------------ GitHub secrets
say "Saving the secrets in GitHub ($REPO)"
export GH_TOKEN REPO
export S_SSH_HOST="$SSH_HOST" S_SSH_PORT="$SSH_PORT" S_SSH_USER="$USER" S_SSH_KEY="$(cat "$KEY")" S_APP_DIR="$APP_DIR"
export S_APP_URL="https://$DOMAIN" S_APP_KEY="$APP_KEY"
export S_DB_DATABASE="$DB_NAME" S_DB_USERNAME="$DB_USER" S_DB_PASSWORD="$DB_PASS"
export S_MAIL_HOST="$MAIL_HOST" S_MAIL_PORT="465" S_MAIL_USERNAME="$MAIL_USER" S_MAIL_PASSWORD="$MAIL_PASS" S_MAIL_FROM_ADDRESS="$MAIL_USER"
export S_ADMIN_NAME="$ADMIN_NAME" S_ADMIN_EMAIL="$ADMIN_EMAIL" S_ADMIN_PASSWORD="$ADMIN_PASSWORD"
"$PHP" <<'PHP'
<?php
$repo = getenv('REPO'); $token = getenv('GH_TOKEN');
$call = function (string $method, string $path, ?array $body = null) use ($token) {
    $ch = curl_init('https://api.github.com'.$path);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$token, 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28', 'User-Agent: tenders-setup', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body)]);
    $out = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, json_decode((string) $out, true)];
};
[$code, $key] = $call('GET', "/repos/$repo/actions/secrets/public-key");
if ($code !== 200) { fwrite(STDERR, "GitHub refused the token (HTTP $code). It needs 'Secrets: Read and write' on $repo.\n"); exit(1); }
$public = base64_decode($key['key']);
$names = ['SSH_HOST', 'SSH_PORT', 'SSH_USER', 'SSH_KEY', 'APP_DIR', 'APP_URL', 'APP_KEY', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
    'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'ADMIN_NAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD'];
foreach ($names as $name) {
    $value = (string) getenv('S_'.$name);
    [$code] = $call('PUT', "/repos/$repo/actions/secrets/$name", ['encrypted_value' => base64_encode(sodium_crypto_box_seal($value, $public)), 'key_id' => $key['key_id']]);
    echo ($code === 201 || $code === 204 ? '  saved   ' : '  FAILED  ').$name.($code === 201 || $code === 204 ? '' : " (HTTP $code)")."\n";
    if ($code !== 201 && $code !== 204) { exit(1); }
}
PHP

# ------------------------------------------------------------------ what to keep
umask 077
cat > "$SAVE" <<EOF
# Tenders.af setup, $(date '+%Y-%m-%d %H:%M'). Only readable by $USER.
APP_KEY=$APP_KEY
ADMIN_URL=https://$DOMAIN/admin/login
ADMIN_EMAIL=$ADMIN_EMAIL
ADMIN_PASSWORD=$ADMIN_PASSWORD
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
MAIL_USERNAME=$MAIL_USER
EOF

say "Done"
echo "GitHub now has everything it needs. The site goes online at the next push to 'main'."
echo
echo "  Admin panel:  https://$DOMAIN/admin/login"
echo "  Email:        $ADMIN_EMAIL"
echo "  Password:     $ADMIN_PASSWORD"
echo
echo "This is also saved in $SAVE (only you can read it). Change the password after the first sign-in."
