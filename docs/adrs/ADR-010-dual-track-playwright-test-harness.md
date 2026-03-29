# ADR-010: Dual-Track Playwright Test Harness — Repo-Local and Upstream

**Status:** Accepted
**Date:** 2026-03-29
**Context:** FinDrop Canvas AI — direct edit Tier 1/Tier 2 browser regression

## Decision

Browser regression tests for the direct-edit path live in **two places**:

1. **Repo-local** (`tests/playwright/`) — environment-specific tests that run against the FinDrop DDEV instance, use `ddev drush` for setup, and validate the full integration including custom module behavior, tempstore state, and patch-applied Canvas UI.

2. **Upstream Canvas contrib** (future) — portable versions of the same scenarios, stripped of DDEV/FinDrop specifics, contributed to Canvas's own Playwright suite once the direct-edit endpoint is proposed upstream.

Both tracks are maintained. The repo-local harness is the **primary regression gate** for this project. The upstream port is a **contribution deliverable** tracked alongside the upstream patch proposals.

## Context

The direct-edit path (ADR-004, ADR-007) adds a deterministic bypass endpoint to Canvas. Validating it requires browser-level testing because:

- The routing decision happens in the Canvas React frontend (AiWizard/AiPanel)
- The tempstore seeding depends on the page editor lifecycle (CanvasBuilder::render)
- Cold-start behavior (no prior AI request) was the root cause of the Tier 1 bug
- Compound edits (Tier 2) must verify that both props update visually in the preview

PHPUnit covers the matcher and controller logic. But the critical integration — frontend routes to `/direct-edit` instead of `/ai`, tempstore is populated, preview updates — can only be verified in a real browser against the running editor.

### Why not upstream-only?

Canvas contrib's Playwright suite exists but doesn't cover the direct-edit endpoint (which doesn't exist upstream yet). We can't wait for upstream acceptance to have regression coverage. The local implementation is patched in and needs its own test harness now.

### Why not repo-local-only?

ADR-008 requires that every upstream proposal ships with evidence and working tests. When the direct-edit endpoint is proposed to Canvas, the Playwright scenarios are the strongest evidence that the feature works end-to-end. Keeping them portable from the start avoids a scramble at contribution time.

## What Lives Where

### Repo-local (`tests/playwright/`)

- Uses `ddev drush` for tempstore reset and login
- Hardcodes FinDrop editor paths (canvas_page/13)
- Asserts against FinDrop-specific content (Byte theme heading component)
- Runs via Canvas's bundled Playwright binary (no separate install)
- Config: `tests/playwright/playwright.config.ts`

**Specs:**
- `direct-edit-cold-start.spec.ts` — Tier 1: single-prop deterministic edit on cold tempstore
- `direct-edit-compound.spec.ts` — Tier 2: compound multi-prop edit via single direct-edit request

### Upstream (future, Canvas contrib `tests/playwright/`)

- Uses generic Canvas test fixtures (any page with a heading component)
- Authenticates via Drupal's standard test user setup
- No DDEV dependency — works with any Drupal test runner
- Covers the same user flows but against Canvas's own test content

## Porting Strategy

When preparing the upstream patch for the direct-edit endpoint:

1. Extract the core assertions (route hit, response shape, preview update) from each repo-local spec
2. Replace DDEV helpers with Canvas's existing Playwright test utilities
3. Replace FinDrop content references with Canvas test fixtures
4. Submit as part of the same MR that adds the endpoint

The repo-local specs remain as-is — they're the regression gate for this specific demo site's behavior, including patch interactions and custom module integration that upstream tests won't cover.

## Consequences

- Two test files to maintain per scenario (repo-local + upstream port)
- Repo-local tests may drift from upstream if Canvas's test utilities change — acceptable since repo-local is pinned to our patched Canvas version
- The porting step is explicit work that must be budgeted when preparing upstream proposals

## Risks

- **Maintenance burden of two harnesses.** Mitigated by keeping repo-local tests simple and scenario-focused. They're regression gates, not exhaustive suites.
- **Upstream Playwright conventions may differ.** Mitigated by porting at contribution time when we can read Canvas's existing test patterns, not guessing now.
- **Repo-local tests break on Canvas patch updates.** Expected and acceptable — if the patch changes, the tests should catch regressions. That's the point.
