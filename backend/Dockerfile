# Production image for the Symfony API (DigitalOcean App Platform target).
# Uses FrankenPHP — a production-grade PHP application server. This file is provided as
# deployment-ready configuration; the local lab uses Dockerfile.dev instead.

# --- vendor stage: install production dependencies only ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# --- runtime stage ---
FROM dunglas/frankenphp:1-php8.4 AS app
RUN install-php-extensions pdo_pgsql intl opcache zip
WORKDIR /app

ENV APP_ENV=prod
ENV APP_DEBUG=0
# FrankenPHP listens on :8000 (DO App Platform sets PORT; map accordingly).
ENV SERVER_NAME=:8000

COPY --from=vendor /app/vendor ./vendor
COPY . .

# .env is git-ignored / not baked in; derive a base file from the template. Real values
# (APP_SECRET, DATABASE_URL, MAILER_DSN, JWT_PASSPHRASE) are supplied as runtime env vars
# and win over these defaults.
RUN cp .env.example .env \
    && composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php bin/console cache:clear --env=prod --no-debug \
    && php bin/console cache:warmup --env=prod --no-debug

COPY docker/prod-entrypoint.sh /usr/local/bin/prod-entrypoint.sh
RUN chmod +x /usr/local/bin/prod-entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["prod-entrypoint.sh"]
