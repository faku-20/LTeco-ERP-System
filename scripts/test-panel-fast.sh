#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
docker compose exec -T panel php /var/www/html/tests/run.php
