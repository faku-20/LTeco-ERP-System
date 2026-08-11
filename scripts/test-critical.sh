#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

export LTECO_ENV="${LTECO_ENV:-testing}"
# Safety invariant: LTECO_ENV=testing

scripts/test-panel-fast.sh
scripts/test-panel-critical.sh
scripts/test-storefront.sh --filter CheckoutFlowTest
