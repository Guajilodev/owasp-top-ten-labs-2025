#!/usr/bin/env bash
# Safe, non-deploying validation for production containment artifacts.

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly PROJECT_DIR
readonly PRODUCTION_DIR="${PROJECT_DIR}/deploy/production"
for script in \
  "${PRODUCTION_DIR}/nexolab-firewall" \
  "${PRODUCTION_DIR}/nexolab-reset" \
  "${PRODUCTION_DIR}/nexolab-storage" \
  "${PRODUCTION_DIR}/nexolab-runtime-verify" \
  "${PRODUCTION_DIR}/nexolab-backup" \
  "${PRODUCTION_DIR}/nexolab-rollback" \
  "${PRODUCTION_DIR}/nexolab-start" \
  "${PRODUCTION_DIR}/nexolab-report-failure" \
  "${PROJECT_DIR}/scripts/local-containment-firewall.sh" \
  "${PROJECT_DIR}/scripts/test-production-containment.sh"; do
  bash -n "$script"
done

"${PROJECT_DIR}/scripts/test-production-containment.sh"

printf 'Disposable production containment validation passed.\n'
