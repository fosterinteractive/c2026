# Intent Testing: Tiered Deterministic Edit Routing

Test manifests for validating the three-tier waterfall routing system described in
`docs/proposals/tiered-deterministic-edit-routing.md`.

These manifests use the [drupal-intent-testing](https://github.com/scottfalconer/drupal-intent-testing/)
framework. Each manifest specifies an intent (what the user said and which component
was selected), the expected routing outcome (which tier handled it), and assertions
against the HTTP response.

---

## Prerequisites

- DDEV environment running: `ddev start`
- Site installed: `ddev demo-setup` (or at minimum `ddev drush si`)
- Canvas demo page exists with a heading component
- Admin credentials available (default: admin/admin for local dev)
- drupal-intent-testing runner installed (see the framework README)

---

## Setup: Find Your Heading UUID

Each manifest uses `{{HEADING_UUID}}` as a placeholder. Replace it with the UUID
of an actual heading component on the canvas demo page.

```shell
# List components on the canvas demo page
ddev drush canvas:list-components --page=/canvas-demo

# Or open the Canvas editor in the browser, inspect the DOM, and find:
# data-component-uuid="..." on a heading element
```

Once you have the UUID, you can either:

1. Replace `{{HEADING_UUID}}` in each manifest before running, or
2. Pass it as a variable to the test runner (see the framework docs for variable injection)

---

## Running the Tests

```shell
# Run all manifests in this directory
drupal-intent-testing run tests/intent-testing/ \
  --base-url=https://c2026.ddev.site \
  --var HEADING_UUID=<your-uuid-here>

# Run a single manifest
drupal-intent-testing run tests/intent-testing/tier1-heading-text-edit.yml \
  --base-url=https://c2026.ddev.site \
  --var HEADING_UUID=<your-uuid-here>

# Run only Tier 1 manifests (fast, zero-token, no AI key needed)
drupal-intent-testing run tests/intent-testing/ \
  --base-url=https://c2026.ddev.site \
  --var HEADING_UUID=<your-uuid-here> \
  --filter tier=1
```

The measurement baseline manifest (`measurement-baseline.yml`) makes a real AI
call and requires an API key in `.ddev/.env`:

```shell
# Set before running measurement-baseline.yml
ANTHROPIC_API_KEY=sk-ant-...   # or
OPENAI_API_KEY=sk-...
```

---

## Manifest Index

| File | Tier | What it tests | Expected status |
|------|------|---------------|----------------|
| `tier1-heading-text-edit.yml` | 1 | Plain text replacement via `change X to Y` pattern | 200 |
| `tier1-enum-color-change.yml` | 1 | Enum alias resolution: "blue" → "primary" | 200 |
| `tier1-reject-add-operation.yml` | 1 | ADD_KEYWORDS block "add", "create", "insert", "below" | 422 |
| `tier1-reject-ambiguous.yml` | 1 | Ambiguous creative instructions route to AI | 422 |
| `tier-boundary-make-keyword.yml` | 1 | "make" routes correctly: edit-intent (200) vs create-intent (422) | 200 / 422 |
| `tier-boundary-long-message.yml` | 1 | 500-char limit: at-limit passes (200), over-limit rejected (422), >2000 rejected (400) | 422 |
| `measurement-baseline.yml` | 4 | Full AI path token baseline; verifies TokenBreakdownSubscriber logging | 200 |

---

## What 200 vs 422 Means

The DirectEditController is designed as a **try-first** endpoint:

- **200**: The matcher resolved the message to a deterministic prop edit. The
  response includes `direct_edit: true`, `tokens_used: 0`, `matched_prop`, and
  `matched_value`. The frontend applies the change immediately.

- **422**: The matcher could not resolve the message (no match, add-intent, ambiguous
  value, unsupported component, or message too long). The frontend should route the
  request to the standard Canvas AI agent endpoint instead.

- **400**: The request was structurally invalid (missing fields, malformed UUID,
  message over 2000 chars). This is a client error, not a routing signal.

- **403**: Invalid CSRF token.

---

## Adding New Manifests

When adding coverage for a new tier or edge case:

1. Name the file descriptively: `tier1-<what>.yml`, `tier2-<what>.yml`,
   `tier-boundary-<what>.yml`, or `measurement-<what>.yml`.
2. Set `tier:` to the tier being tested (1, 2, 3, or 4).
3. Set `ai_agent_invoked: false` for Tier 1-3 pass cases.
4. Set `tokens_expected: 0` for Tier 1-2 pass cases.
5. Always include both `expected_http_status` and step-level `checkpoints`.
6. Reference the proposal doc in the `issue.reference` field.

---

## Relationship to the Phase 4 Plan

The proposal's Phase 4 specifies:

> "Use drupal-intent-testing to build a regression suite with intent manifests for
> each tier's boundary cases. Add structured logging to Tiers 1-3 for coverage
> analysis. Run a representative edit session (20-30 operations across different
> component types) and measure actual tier distribution."

The Tier 1 manifests here are the starting point for that regression suite.
As Tiers 2 and 3 are implemented (Phases 2-3 of the proposal), add manifests for:

- `tier2-compound-split.yml` — "change heading to X and set color to blue"
- `tier2-conflict-rejection.yml` — two fragments targeting the same prop
- `tier3-ambiguous-size.yml` — "make this bigger" resolved by micro-classifier
- `tier3-route-to-ai.yml` — "rewrite this to be more engaging" → `{"route": "ai"}`

The `measurement-baseline.yml` manifest should be run before and after each tier
is deployed to capture the token reduction delta.
