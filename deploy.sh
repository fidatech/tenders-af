#!/usr/bin/env bash
#
# Bring the server up to date with GitHub. Run on the server, inside the project folder:
#     bash deploy.sh            (the branch "main")
#     bash deploy.sh development
#
# The GitHub workflow in .github/workflows/deploy.yml runs this over SSH after every push
# to main. The .env file, the uploaded files in storage/ and the database are never touched
# by Git; new migrations are run.
set -euo pipefail

cd "$(dirname "$0")"
BRANCH="${1:-main}"

# The PHP that runs the site must be 8.3 or newer. On a cPanel server the plain "php" of the
# shell is often older, so the newest EasyApache PHP that has the needed extensions (mbstring,
# pdo_mysql) is looked for first. To force one:
#     PHP_BIN=/opt/cpanel/ea-php84/root/usr/bin/php bash deploy.sh
PHP="${PHP_BIN:-}"
if [ -z "$PHP" ]; then
    for candidate in /opt/cpanel/ea-php85/root/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php; do
        # a version that is installed but lacks the extensions Laravel needs is passed over
        if [ -x "$candidate" ] && "$candidate" -r 'exit(extension_loaded("mbstring") && extension_loaded("pdo_mysql") ? 0 : 1);' 2>/dev/null; then PHP="$candidate"; break; fi
    done
fi
PHP="${PHP:-php}"
echo "== PHP: $($PHP -r 'echo PHP_VERSION;') ($PHP)"

# Composer is run with that same PHP. A composer.phar in the project folder is used when the
# server has no Composer of its own.
if [ -f composer.phar ]; then COMPOSER="$PHP composer.phar"
elif command -v composer2 >/dev/null 2>&1; then COMPOSER="$PHP $(command -v composer2)"
elif command -v composer >/dev/null 2>&1; then COMPOSER="$PHP $(command -v composer)"
else echo "!! Composer is not installed. See 'Putting it online' in README.md."; exit 1; fi

echo "== Fetching $BRANCH"
git fetch --prune origin "$BRANCH"

if [ -f vendor/autoload.php ] && [ -f .env ]; then $PHP artisan down --retry=30 || true; fi

git reset --hard "origin/$BRANCH"

echo "== Installing packages"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [ ! -f .env ]; then
    echo "!! There is no .env file yet. Copy .env.example to .env, fill it in, run 'php artisan key:generate', then run this script again."
    exit 1
fi

echo "== Database"
$PHP artisan migrate --force

echo "== Caches"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

$PHP artisan up
echo "== Done: $(git log -1 --format='%h %s')"
