#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

PORT="${PORT:-80}"

sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data bootstrap/cache storage

if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
fi

if [ -n "${APP_KEY:-}" ] && [[ "${APP_KEY}" != base64:* ]]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(hash("sha256", (string) getenv("APP_KEY"), true));')"
fi

php artisan storage:link --force || true

attempt=0
until php artisan migrate --force; do
    attempt=$((attempt + 1))

    if [ "${attempt}" -ge 30 ]; then
        echo "Les migrations ont échoué après ${attempt} tentatives." >&2
        exit 1
    fi

    sleep 2
done

if [ "${SEED_DATABASE:-false}" = "true" ]; then
    users="$(php artisan tinker --execute='echo \App\Models\User::query()->count();' 2>/dev/null | tr -dc '0-9')"

    if [ -z "${users}" ]; then
        echo "Impossible de vérifier l'état de la base : seed ignoré." >&2
    elif [ "${users}" = "0" ]; then
        php artisan db:seed --force
    fi
fi

php artisan app:create-admin-user

php artisan config:cache
php artisan route:cache || true
php artisan view:cache

exec apache2-foreground
