#!/usr/bin/env bash

set -Eeuo pipefail

cd /opt/ltecobike

exec docker compose \
    -f docker-compose.yml \
    -f docker-compose.storefront.yml \
    "$@"
