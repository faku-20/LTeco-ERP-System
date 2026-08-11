#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

docker run --rm --user "$(id -u):$(id -g)" \
  -v "$PWD/storefront:/var/www/html" \
  -v "$PWD/docker:/repo/docker:ro" \
  -w /var/www/html \
  -e APP_ENV=testing \
  -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  -e APP_DEBUG=true \
  -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=:memory: \
  -e CATALOG_DB_CONNECTION=sqlite \
  -e CATALOG_DB_DATABASE=:memory: \
  -e CACHE_STORE=array \
  -e MAIL_MAILER=array \
  -e QUEUE_CONNECTION=sync \
  -e SESSION_DRIVER=array \
  -e STOREFRONT_ACCOUNTS_ENABLED=false \
  -e STOREFRONT_REGISTRATION_ENABLED=false \
  ltecobike-storefront-php:dev \
  php vendor/bin/phpunit --exclude-group external "$@"
