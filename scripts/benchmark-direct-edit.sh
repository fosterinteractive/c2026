#!/usr/bin/env bash
# benchmark-direct-edit.sh — One-command benchmark runner for the direct-edit path.
#
# Usage:
#   ./scripts/benchmark-direct-edit.sh
#
# Output:
#   - Console: per-run results + summary
#   - File:    docs/benchmarks/direct-edit-benchmark-YYYY-MM-DD.json

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLAYWRIGHT_BIN="${REPO_ROOT}/web/modules/contrib/canvas/node_modules/@playwright/test"
SPEC="${REPO_ROOT}/tests/playwright/benchmark-direct-edit.spec.ts"
CONFIG="${REPO_ROOT}/tests/playwright/playwright.config.ts"
BENCHMARKS_DIR="${REPO_ROOT}/docs/benchmarks"
TODAY="$(date +%Y-%m-%d)"
OUTPUT_JSON="${BENCHMARKS_DIR}/direct-edit-benchmark-${TODAY}.json"

# ── Prerequisites ────────────────────────────────────────────────────────────

check_prerequisites() {
  echo "Checking prerequisites..."

  # ddev
  if ! command -v ddev &>/dev/null; then
    echo "ERROR: ddev not found. Install ddev first: https://ddev.readthedocs.io/en/stable/users/install/"
    exit 1
  fi

  # ddev running
  local status
  status="$(ddev status 2>/dev/null | grep -i 'running' || true)"
  if [[ -z "${status}" ]]; then
    echo "ERROR: DDEV project is not running. Run: ddev start"
    exit 1
  fi

  # node
  if ! command -v node &>/dev/null; then
    echo "ERROR: node not found. Install Node.js >= 20.19: https://nodejs.org"
    exit 1
  fi

  local node_major
  node_major="$(node --version | sed 's/v//' | cut -d. -f1)"
  if [[ "${node_major}" -lt 20 ]]; then
    echo "ERROR: Node.js >= 20.19 required (found $(node --version))"
    exit 1
  fi

  # playwright package
  if [[ ! -d "${PLAYWRIGHT_BIN}" ]]; then
    echo "ERROR: Playwright not found at ${PLAYWRIGHT_BIN}"
    echo "       Run: npm install in web/modules/contrib/canvas/ first"
    echo "       Or:  ddev demo-setup (full site setup)"
    exit 1
  fi

  # spec file
  if [[ ! -f "${SPEC}" ]]; then
    echo "ERROR: Benchmark spec not found: ${SPEC}"
    exit 1
  fi

  # benchmarks output directory
  mkdir -p "${BENCHMARKS_DIR}"

  echo "  ddev:       OK"
  echo "  node:       $(node --version)"
  echo "  playwright: OK (${PLAYWRIGHT_BIN})"
  echo "  spec:       ${SPEC}"
  echo ""
}

# ── Run ─────────────────────────────────────────────────────────────────────

run_benchmark() {
  echo "Running benchmark..."
  echo "  Spec:    benchmark-direct-edit.spec.ts"
  echo "  Config:  tests/playwright/playwright.config.ts"
  echo "  Output:  docs/benchmarks/direct-edit-benchmark-${TODAY}.json"
  echo ""

  cd "${REPO_ROOT}"

  # Run with list reporter for readable per-test output.
  # The spec writes its own JSON; we copy it into place after.
  npx --package="${PLAYWRIGHT_BIN}" playwright test \
    "${SPEC}" \
    --config="${CONFIG}" \
    --reporter=list
}

# ── Copy JSON output ─────────────────────────────────────────────────────────

collect_output() {
  # The benchmark spec writes to docs/benchmarks/ directly with a timestamped
  # filename. If today's file already exists from the run, report its location.
  if [[ -f "${OUTPUT_JSON}" ]]; then
    echo ""
    echo "JSON output: ${OUTPUT_JSON}"
  else
    # Fallback: find any benchmark JSON written in the last 5 minutes.
    local recent
    recent="$(find "${BENCHMARKS_DIR}" -name 'direct-edit-benchmark-*.json' -newer "${CONFIG}" 2>/dev/null | sort | tail -1)"
    if [[ -n "${recent}" ]]; then
      echo ""
      echo "JSON output: ${recent}"
    else
      echo ""
      echo "NOTE: No JSON output found in ${BENCHMARKS_DIR}."
      echo "      The spec may write to a different path. Check DIRECT_EDIT_TEST_BASE_URL env."
    fi
  fi
}

# ── Summary ──────────────────────────────────────────────────────────────────

print_summary() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "  Direct-Edit Benchmark — ${TODAY}"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

  if [[ -f "${OUTPUT_JSON}" ]]; then
    # Parse with node (no jq dependency needed).
    node - "${OUTPUT_JSON}" <<'EOF'
const data = JSON.parse(require('fs').readFileSync(process.argv[1], 'utf8'));
const l = data.latency && data.latency.stats;
const h = data.hitRate;
if (l) {
  console.log(`  Latency (N=${l.n})`);
  console.log(`    Mean:   ${l.mean}ms`);
  console.log(`    Median: ${l.median}ms`);
  console.log(`    95% CI: [${l.ci95Lower}, ${l.ci95Upper}]ms`);
  console.log(`    Min/Max: ${l.min}ms / ${l.max}ms`);
}
if (h) {
  const pct = (h.hitRatePercent !== undefined ? h.hitRatePercent : (h.hits / h.total * 100)).toFixed(0);
  console.log(`  Hit Rate`);
  console.log(`    Hits:  ${h.hits}/${h.total} (${pct}%)`);
  console.log(`    All predictions correct: ${h.allPredictionsCorrect}`);
}
EOF
  else
    echo "  (Run completed — see Playwright output above for results)"
    echo "  Tip: parse the JSON file manually for detailed stats"
  fi

  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo ""
}

# ── Main ─────────────────────────────────────────────────────────────────────

main() {
  echo ""
  echo "FinDrop — Direct-Edit Benchmark Runner"
  echo ""

  check_prerequisites
  run_benchmark
  collect_output
  print_summary
}

main "$@"
