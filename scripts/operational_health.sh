#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

docker compose exec -T panel php /var/www/html/lteco-panel/scripts/operational_health.php "$@"
