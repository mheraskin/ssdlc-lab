#!/bin/sh
# Production entrypoint (DigitalOcean App Platform).
#   - default          → run migrations, ensure JWT keys, serve the API via FrankenPHP
#   - argument "worker" → run the Messenger consumer (the message broker worker component)
set -e

if [ "$1" = "worker" ]; then
    php bin/console messenger:setup-transports async --no-interaction || true
    exec php bin/console messenger:consume async failed --time-limit=3600 -v
fi

# Run pending database migrations against the managed PostgreSQL instance.
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
php bin/console messenger:setup-transports async --no-interaction || true

# JWT keys should be supplied as secrets/volume in production. As a fallback for a fresh
# environment, generate them if missing (so a first deploy still boots).
if [ ! -f config/jwt/private.pem ]; then
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
fi

# Hand off to FrankenPHP (serves ./public).
exec frankenphp run
