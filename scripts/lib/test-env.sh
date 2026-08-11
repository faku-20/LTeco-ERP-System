#!/usr/bin/env bash

load_lteco_test_env() {
  if [ -f .env.testing ]; then
    set -a
    # shellcheck disable=SC1091
    . ./.env.testing
    set +a
  fi

  export LTECO_ENV="${LTECO_ENV:-testing}"
  export LTECO_TEST_DB_ALLOW="${LTECO_TEST_DB_ALLOW:-1}"
  export LTECO_TEST_DB_HOST="${LTECO_TEST_DB_HOST:-host.docker.internal}"
  export LTECO_TEST_DB_PORT="${LTECO_TEST_DB_PORT:-3306}"
  export LTECO_TEST_DB_NAME="${LTECO_TEST_DB_NAME:-lteco_db_poo_test}"
  export LTECO_TEST_DB_USER="${LTECO_TEST_DB_USER:-lteco_test_user}"
  export LTECO_TEST_DB_PASSWORD="${LTECO_TEST_DB_PASSWORD:-${LTECO_TEST_DB_PASSWOR:-}}"
}

require_lteco_test_db_name() {
  case "${LTECO_TEST_DB_NAME:-}" in
    test_*|*_test|*_testing) ;;
    *) echo "Refusing non-test DB name: ${LTECO_TEST_DB_NAME:-<empty>}" >&2; exit 2 ;;
  esac

  case "${LTECO_TEST_DB_NAME:-}" in
    lteco_db|lteco_db_poo|production|prod)
      echo "Refusing production/commercial DB: ${LTECO_TEST_DB_NAME}" >&2
      exit 2
      ;;
  esac
}
