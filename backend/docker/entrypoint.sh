#!/bin/sh
# Dev entrypoint: install deps if needed, wait for the DB, ensure JWT keys + schema +
# demo data exist, then serve the API via PHP's built-in web server.
set -e

cd /app

# backend/.env is git-ignored. On a fresh checkout, seed it from the committed template.
if [ ! -f .env ]; then
    echo "[entrypoint] .env missing — creating it from .env.example"
    cp .env.example .env
fi

echo "[entrypoint] ensuring dependencies..."
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

echo "[entrypoint] waiting for database..."
until php -r 'exit(@fsockopen("db", 5432) ? 0 : 1);' 2>/dev/null; do
    echo "  ...db not ready yet"
    sleep 2
done

echo "[entrypoint] generating JWT keypair if missing..."
php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

echo "[entrypoint] clearing cache..."
php bin/console cache:clear --no-interaction

echo "[entrypoint] running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[entrypoint] ensuring messenger transport table..."
# Only the Doctrine-backed transports support setup (the sync transport does not).
php bin/console messenger:setup-transports async --no-interaction || true
php bin/console messenger:setup-transports failed --no-interaction || true

echo "[entrypoint] loading demo data..."
php bin/console app:load-demo-data --no-interaction

echo "[entrypoint] starting API on :8000"
# variables_order=EGPCS exposes real environment variables to $_ENV in the web SAPI so
# that container/compose env vars (e.g. DATABASE_URL) authoritatively win over .env.
exec php -d variables_order=EGPCS -S 0.0.0.0:8000 -t public public/index.php
