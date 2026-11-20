#!/usr/bin/env bash
#
# Runs on the server after GitHub has copied the new files into the project folder
# (.github/workflows/deploy.yml). It never touches the uploaded files in storage/ or the
# database's data: it runs new migrations and refreshes the caches.
#
# By hand, inside the project folder:   bash deploy.sh
set -euo pipefail
cd "$(dirname "$0")"
APP_DIR="$(pwd)"

# The PHP of the site must be 8.3 or newer. On cPanel the plain "php" of the shell is often
# older, so the newest EasyApache PHP with the needed extensions is used.
PHP="${PHP_BIN:-}"
if [ -z "$PHP" ]; then
    for candidate in /opt/cpanel/ea-php85/root/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php; do
        if [ -x "$candidate" ] && "$candidate" -r 'exit(extension_loaded("mbstring") && extension_loaded("pdo_mysql") ? 0 : 1);' 2>/dev/null; then PHP="$candidate"; break; fi
    done
fi
PHP="${PHP:-php}"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || { echo "!! PHP 8.3 or newer is needed; $PHP is $("$PHP" -r 'echo PHP_VERSION;'). Install it in WHM > EasyApache 4."; exit 1; }
echo "== PHP $("$PHP" -r 'echo PHP_VERSION;') ($PHP)"
for ext in mbstring pdo_mysql openssl tokenizer xml ctype fileinfo curl gd intl zip bcmath sodium; do
    "$PHP" -r "exit(extension_loaded('$ext') ? 0 : 1);" || echo "!! PHP extension missing: $ext (WHM > EasyApache 4 > PHP Extensions)"
done

# folders Laravel writes to
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R u+rwX,go-w storage bootstrap/cache

# the administrator of the setup, sent by the workflow for this run only
if [ -f .deploy-env ]; then set -a; . ./.deploy-env; set +a; rm -f .deploy-env; fi

FIRST=0; [ -f storage/app/.installed ] || FIRST=1

if [ "$FIRST" = 0 ]; then "$PHP" artisan down --retry=30 >/dev/null 2>&1 || true; fi
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

echo "== Database"
"$PHP" artisan migrate --force --no-interaction
if [ "$FIRST" = 1 ]; then
    echo "== First install: sectors, provinces and plans"
    "$PHP" artisan db:seed --force --no-interaction
fi
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    "$PHP" artisan app:create-admin --sync --name="${ADMIN_NAME:-Administrator}" --email="$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" --no-interaction
fi

echo "== Caches"
"$PHP" artisan storage:link --force >/dev/null 2>&1 || true
"$PHP" artisan optimize:clear >/dev/null
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache >/dev/null 2>&1 || true

# The domain's web folder shows the project's public/ folder. What was in public_html
# before is kept beside it, once.
WEB="${WEB_ROOT:-$HOME/public_html}"
if [ "$(readlink -f "$WEB" 2>/dev/null || true)" != "$APP_DIR/public" ]; then
    if [ -e "$WEB" ] && [ ! -L "$WEB" ]; then mv "$WEB" "$WEB.before-tenders-$(date +%Y%m%d%H%M)"; fi
    rm -f "$WEB"
    ln -s "$APP_DIR/public" "$WEB"
    echo "== $WEB now shows $APP_DIR/public"
fi

# The scheduler (tender alerts every ten minutes, the weekly digest, deadlines) runs every minute.
CRON="* * * * * cd $APP_DIR && $PHP artisan schedule:run >> /dev/null 2>&1"
if ! crontab -l 2>/dev/null | grep -qF "$APP_DIR && "; then
    if (crontab -l 2>/dev/null; echo "$CRON") | crontab - 2>/dev/null; then
        echo "== Cron added: schedule:run every minute"
    else
        echo "!! Could not add the cron line. Add it in cPanel > Cron Jobs (every minute):"
        echo "   cd $APP_DIR && $PHP artisan schedule:run >> /dev/null 2>&1"
    fi
fi

touch storage/app/.installed
echo "== Deployed $(date '+%Y-%m-%d %H:%M:%S')"
