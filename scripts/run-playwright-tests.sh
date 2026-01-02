#!/usr/bin/env bash
set -euo pipefail

export PLAYWRIGHT_HOST_PLATFORM_OVERRIDE="mac14-arm64"
export E2E_BASE_URL="${E2E_BASE_URL:-https://sm-palm-reading.local/}"

echo "Using base URL: ${E2E_BASE_URL}"
echo "Using platform override: ${PLAYWRIGHT_HOST_PLATFORM_OVERRIDE}"

npm test
