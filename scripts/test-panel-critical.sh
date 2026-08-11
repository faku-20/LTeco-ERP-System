#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

source scripts/lib/test-env.sh
load_lteco_test_env
require_lteco_test_db_name

docker compose exec -T \
  -e LTECO_ENV="$LTECO_ENV" \
  -e LTECO_TEST_DB_ALLOW="$LTECO_TEST_DB_ALLOW" \
  -e LTECO_TEST_DB_HOST="$LTECO_TEST_DB_HOST" \
  -e LTECO_TEST_DB_NAME="$LTECO_TEST_DB_NAME" \
  -e LTECO_TEST_DB_USER="$LTECO_TEST_DB_USER" \
  -e LTECO_TEST_DB_PASSWORD="${LTECO_TEST_DB_PASSWORD:-}" \
  panel php /var/www/html/tests/integration/B1PanelSafetyNetTest.php

docker compose exec -T \
  -e LTECO_ENV="$LTECO_ENV" \
  -e LTECO_TEST_DB_ALLOW="$LTECO_TEST_DB_ALLOW" \
  -e LTECO_TEST_DB_HOST="$LTECO_TEST_DB_HOST" \
  -e LTECO_TEST_DB_NAME="$LTECO_TEST_DB_NAME" \
  -e LTECO_TEST_DB_USER="$LTECO_TEST_DB_USER" \
  -e LTECO_TEST_DB_PASSWORD="${LTECO_TEST_DB_PASSWORD:-}" \
  panel php /var/www/html/tests/integration/Rel01PanelReliabilityTest.php

docker compose exec -T \
  -e LTECO_ENV="$LTECO_ENV" \
  -e LTECO_TEST_DB_ALLOW="$LTECO_TEST_DB_ALLOW" \
  -e LTECO_TEST_DB_HOST="$LTECO_TEST_DB_HOST" \
  -e LTECO_TEST_DB_NAME="$LTECO_TEST_DB_NAME" \
  -e LTECO_TEST_DB_USER="$LTECO_TEST_DB_USER" \
  -e LTECO_TEST_DB_PASSWORD="${LTECO_TEST_DB_PASSWORD:-}" \
  panel php /var/www/html/tests/integration/B1StorefrontPanelContractTest.php

docker compose exec -T \
  -e LTECO_ENV="$LTECO_ENV" \
  -e LTECO_TEST_DB_ALLOW="$LTECO_TEST_DB_ALLOW" \
  -e LTECO_TEST_DB_HOST="$LTECO_TEST_DB_HOST" \
  -e LTECO_TEST_DB_NAME="$LTECO_TEST_DB_NAME" \
  -e LTECO_TEST_DB_USER="$LTECO_TEST_DB_USER" \
  -e LTECO_TEST_DB_PASSWORD="${LTECO_TEST_DB_PASSWORD:-}" \
  panel php /var/www/html/tests/integration/B2CLegacyEcommerceRetirementTest.php

docker compose exec -T \
  -e LTECO_ENV="$LTECO_ENV" \
  -e LTECO_TEST_DB_ALLOW="$LTECO_TEST_DB_ALLOW" \
  -e LTECO_TEST_DB_HOST="$LTECO_TEST_DB_HOST" \
  -e LTECO_TEST_DB_NAME="$LTECO_TEST_DB_NAME" \
  -e LTECO_TEST_DB_USER="$LTECO_TEST_DB_USER" \
  -e LTECO_TEST_DB_PASSWORD="${LTECO_TEST_DB_PASSWORD:-}" \
  panel php /var/www/html/tests/integration/B3InventoryIntegrityTest.php

docker compose exec -T \
  -e LTECO_ENV="$LTECO_ENV" \
  -e LTECO_TEST_DB_ALLOW="$LTECO_TEST_DB_ALLOW" \
  -e LTECO_TEST_DB_HOST="$LTECO_TEST_DB_HOST" \
  -e LTECO_TEST_DB_NAME="$LTECO_TEST_DB_NAME" \
  -e LTECO_TEST_DB_USER="$LTECO_TEST_DB_USER" \
  -e LTECO_TEST_DB_PASSWORD="${LTECO_TEST_DB_PASSWORD:-}" \
  panel php /var/www/html/tests/integration/RepuestoCajaServiceTest.php

docker compose exec -T \
  -e LTECO_ENV="$LTECO_ENV" \
  -e LTECO_TEST_DB_ALLOW="$LTECO_TEST_DB_ALLOW" \
  -e LTECO_TEST_DB_HOST="$LTECO_TEST_DB_HOST" \
  -e LTECO_TEST_DB_NAME="$LTECO_TEST_DB_NAME" \
  -e LTECO_TEST_DB_USER="$LTECO_TEST_DB_USER" \
  -e LTECO_TEST_DB_PASSWORD="${LTECO_TEST_DB_PASSWORD:-}" \
  panel php /var/www/html/tests/integration/B3APostventaPartConsumptionTest.php

docker compose exec -T \
  -e LTECO_ENV="$LTECO_ENV" \
  -e LTECO_TEST_DB_ALLOW="$LTECO_TEST_DB_ALLOW" \
  -e LTECO_TEST_DB_HOST="$LTECO_TEST_DB_HOST" \
  -e LTECO_TEST_DB_NAME="$LTECO_TEST_DB_NAME" \
  -e LTECO_TEST_DB_USER="$LTECO_TEST_DB_USER" \
  -e LTECO_TEST_DB_PASSWORD="${LTECO_TEST_DB_PASSWORD:-}" \
  panel php /var/www/html/tests/integration/B4SecurityHardeningTest.php

docker compose exec -T \
  -e LTECO_ENV="$LTECO_ENV" \
  -e LTECO_TEST_DB_ALLOW="$LTECO_TEST_DB_ALLOW" \
  -e LTECO_TEST_DB_HOST="$LTECO_TEST_DB_HOST" \
  -e LTECO_TEST_DB_NAME="$LTECO_TEST_DB_NAME" \
  -e LTECO_TEST_DB_USER="$LTECO_TEST_DB_USER" \
  -e LTECO_TEST_DB_PASSWORD="${LTECO_TEST_DB_PASSWORD:-}" \
  panel php /var/www/html/tests/integration/B5DbLifecycleTest.php
