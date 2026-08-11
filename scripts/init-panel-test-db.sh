#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

source scripts/lib/test-env.sh
load_lteco_test_env
require_lteco_test_db_name

# Default test DB is defined in scripts/lib/test-env.sh as lteco_db_poo_test.
TEST_DB="${LTECO_TEST_DB_NAME}"
SOURCE_DB="${LTECO_SOURCE_DB_NAME:-lteco_db_poo}"
TEST_USER="${LTECO_TEST_DB_USER}"

if [ -z "${LTECO_TEST_DB_PASSWORD:-}" ]; then
  echo "Missing LTECO_TEST_DB_PASSWORD for dedicated test DB user." >&2
  exit 2
fi

if [ "$TEST_DB" = "$SOURCE_DB" ] || [ "$TEST_DB" = "lteco_db_poo" ] || [ "$TEST_DB" = "lteco_db" ]; then
  echo "Refusing to initialize production/commercial DB: $TEST_DB" >&2
  exit 2
fi

docker compose exec -T \
  -e LTECO_ENV="$LTECO_ENV" \
  -e LTECO_TEST_DB_ALLOW="$LTECO_TEST_DB_ALLOW" \
  -e LTECO_TEST_DB_HOST="$LTECO_TEST_DB_HOST" \
  -e LTECO_TEST_DB_NAME="$LTECO_TEST_DB_NAME" \
  -e LTECO_TEST_DB_USER="$LTECO_TEST_DB_USER" \
  -e LTECO_TEST_DB_PASSWORD="$LTECO_TEST_DB_PASSWORD" \
  panel sh -lc '
set -euo pipefail
TEST_DB="$1"
SOURCE_DB="$2"
TEST_USER="$3"

case "$TEST_DB" in
  test_*|*_test|*_testing) ;;
  *) echo "Refusing non-test DB name inside container: $TEST_DB" >&2; exit 2 ;;
esac
if [ "${LTECO_ENV:-}" != "testing" ] || [ "${LTECO_TEST_DB_ALLOW:-}" != "1" ]; then
  echo "Refusing reset without LTECO_ENV=testing and LTECO_TEST_DB_ALLOW=1." >&2
  exit 2
fi
if [ -z "${LTECO_TEST_DB_PASSWORD:-}" ]; then
  echo "Missing test DB password." >&2
  exit 2
fi

export MYSQL_PWD="${LTECO_TEST_DB_PASSWORD}"
mysql --ssl=0 -h "${LTECO_TEST_DB_HOST:-host.docker.internal}" -u "$TEST_USER" "$TEST_DB" \
  -e "SELECT DATABASE();" >/dev/null

drop_sql="$(mysql --ssl=0 -h "${LTECO_TEST_DB_HOST:-host.docker.internal}" -u "$TEST_USER" "$TEST_DB" -N -B \
  -e "SELECT CONCAT('\''DROP TABLE IF EXISTS '\'', CHAR(96), REPLACE(TABLE_NAME, CHAR(96), CONCAT(CHAR(96), CHAR(96))), CHAR(96), '\'';'\'') FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = '\''BASE TABLE'\''")"
if [ -n "$drop_sql" ]; then
  printf "SET FOREIGN_KEY_CHECKS=0;\n%s\nSET FOREIGN_KEY_CHECKS=1;\n" "$drop_sql" \
    | mysql --ssl=0 -h "${LTECO_TEST_DB_HOST:-host.docker.internal}" -u "$TEST_USER" "$TEST_DB"
fi

export MYSQL_PWD="${LTECO_DB_PASS:-}"
mysqldump --ssl=0 --no-data --skip-comments --routines --triggers \
  -h "${LTECO_DB_HOST:-host.docker.internal}" -u "${LTECO_DB_USER:-root}" "$SOURCE_DB" \
  | grep -viE "^(INSERT INTO|LOCK TABLES|UNLOCK TABLES)" \
  | sed -E "s/DEFINER=[^* ]+//g; s#/\\*!50017 DEFINER=[^*]+\\*/##g" \
  | MYSQL_PWD="${LTECO_TEST_DB_PASSWORD}" mysql --ssl=0 -h "${LTECO_TEST_DB_HOST:-host.docker.internal}" -u "$TEST_USER" "$TEST_DB"
' sh "$TEST_DB" "$SOURCE_DB" "$TEST_USER"

echo "Panel test DB initialized: $TEST_DB"
