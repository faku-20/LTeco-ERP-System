#!/usr/bin/env bash
set -euo pipefail

cd /opt/ltecobike

docker compose exec -T panel php /var/www/html/lteco-panel/scripts/backup_cli.php "$@"
