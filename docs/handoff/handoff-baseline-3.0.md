# Handoff: Baseline 3.0 — Deterministic Pre-Cognition

**Date:** 2026-03-29
**Branch:** `main` (all work merged)
**Site:** `https://c2026.ddev.site`
**Tests:** 121 passing, 362 assertions

## What Was Completed

### Baseline 3.0 Phases 0-3 — All Merged to Main

| PR | Phase | What | Tests Added |
|----|-------|------|-------------|
| #9 | 0 | Telemetry + schema loader expansion (reverse enum index, boolean props, enum ordinals, orthogonality report) | +19 |
| #10 | 1+2 | Bare value type inference ("blue" → text_color) + boolean toggles ("show the header") | +21 |
| #11 | 3 | Relative adjustments ("bigger"/"smaller" via enum ordinal navigation) | +10 |

### Earlier Work (Same Session)

| PR | What |
|----|------|
| #3 | Tier 1+2 direct edit + Playwright tests + ADRs 1-10 |
| #7 | Region index for cross-region awareness |
| #5 | Context envelope builder (ADR-006) |
| #8 | Upstream docs cleanup (slop fixes + issue drafts) |

### Upstream Filing Prep

- `docs/research/drupal-org-ready-comments.md` — P4, P1, P2 ready to post on drupal.org
- Framed for Dries (UX + affordability) and catch (architectural hygiene + benchmarks)
- catch profile analysis done via catch-bot at `~/claude/catch-bot`

### Strategic Docs

- `docs/plans/baseline-3.0-deterministic-precognition.md` — Revised after triple meta-critic review (proposal-critic REVISE, drupal-critic ACCEPT-WITH-RESERVATIONS, perf-critic ACCEPT-WITH-RESERVATIONS)
- `docs/plans/baseline-3.0-drupal-architecture-addendum.md` — Drupal service design from drupal-planner

## Current Deterministic Match Chain

```
message arrives
  → Tier 1: explicit pattern ("change X to Y")     [shipped, Baseline 2.0]
  → Tier 2: compound split ("X and set Y")          [shipped, Baseline 2.0]
  → Phase 1: bare value inference ("blue")           [shipped, Baseline 3.0]
  → Phase 2: boolean toggle ("show the header")      [shipped, Baseline 3.0]
  → Phase 3: relative adjustment ("bigger")           [shipped, Baseline 3.0]
  → all failed → 422, frontend routes to AI agent chain
```

## Key Files

### Implementation
- `web/modules/custom/canvas_ai_scoping/src/Service/DirectEditMatcher.php` — Main matcher with all 5 resolution strategies
- `web/modules/custom/canvas_ai_scoping/src/Service/ComponentSchemaLoader.php` — Schema loading + reverse index, boolean props, enum ordinals
- `web/modules/custom/canvas_ai_scoping/src/Service/ComponentSchemaLoaderInterface.php` — 7 methods (3 original + 4 Phase 0)
- `web/modules/custom/canvas_ai_scoping/src/Service/ContextEnvelopeBuilder.php` — ADR-006 context envelopes
- `web/modules/custom/canvas_ai_scoping/src/Controller/DirectEditController.php` — Endpoint with telemetry + tempstore prop value reading
- `web/modules/custom/canvas_ai_scoping/src/EventSubscriber/LayoutScopingSubscriber.php` — Section scoping + region index + envelope dispatch

### Tests
- `tests/src/Unit/DirectEditMatcherTest.php` — 72 tests covering all 5 match tiers + rejections
- `tests/src/Unit/ComponentSchemaLoaderTest.php` — 19 tests covering reverse index, booleans, ordinals, orthogonality
- `tests/src/Unit/DirectEditControllerTest.php` — Controller + telemetry tests
- `tests/src/Unit/ContextEnvelopeBuilderTest.php` — 9 envelope tests
- `tests/src/Unit/LayoutScopingSubscriberTest.php` — 14 scoping + region index tests
- `tests/playwright/direct-edit-cold-start.spec.ts` — Tier 1 browser regression
- `tests/playwright/direct-edit-compound.spec.ts` — Tier 2 browser regression

### Docs
- `docs/plans/baseline-3.0-deterministic-precognition.md` — Full plan with critic revisions
- `docs/plans/baseline-3.0-drupal-architecture-addendum.md` — Service design
- `docs/research/drupal-org-ready-comments.md` — 3 upstream comments ready to post
- `docs/research/upstream-issue-drafts.md` — Draft context for the comments
- `docs/adrs/ADR-001 through ADR-010` — All architectural decision records

## Telemetry State

Telemetry is **enabled** on the DDEV site:
```bash
ddev drush state:get canvas_ai_scoping.telemetry_enabled  # returns 1
```

Last measured match latency: **5,893 microseconds (~6ms)** for a Tier 2 compound edit.

## Orthogonality Report (Real Byte Theme)

Run `ddev drush php:eval '$r = \Drupal::service("canvas_ai_scoping.component_schema_loader")->getOrthogonalityReport(); ...'` to regenerate.

- **12 orthogonal components** (Phase 1 bare value works): badge, button, card-logo, card-pricing, card-testimonial, card, cta, hero-billboard, icon, image, navbar, text
- **5 collision components** (Phase 1 rejects to next tier): card-icon (6 collisions), group (7), heading (1 — "default"), hero-side-by-side (2), section (3)

## Remaining Work

### Immediate Next Steps

1. **Phase 0 measurement run** — Run 30-50 representative edits with telemetry enabled to validate schema-derived coverage estimates against actual frequency data. Decision gate: if bare value messages are <3% of actual messages, consider building Tier 3 micro-classifier instead of further deterministic expansion.

2. **File upstream issues** — Post the 3 drupal.org comments from `docs/research/drupal-org-ready-comments.md`. Filing order: P4 first (comment on #3549232), then P1 (#3545816), then P2 (new ai_context issue).

### Conditional (After Measurement)

3. **Phase 5: Multi-component batch operations** — "Change all headings to blue" when a section is selected. 5-8 days, +2-4% coverage. Only if Phase 0 data supports it.

4. **Phase 6: Speculative resolution** — Resolve against all components in selected section when selection is imprecise. 3-5 days, +1-3%. Needs UX input on safety.

5. **Tier 3 micro-classifier** — Minimal LLM call (~500 tokens) with only component schema for ambiguous messages. Alternative to Phases 5-6 if measurement shows the deterministic ceiling is lower than estimated.

### Critic Findings to Address

From the triple meta-critic review (stored in context, not in files):

- **proposal-critic (REVISE)**: Orthogonality claim corrected in plan. Coverage estimates adjusted. Phase 0 measurement repositioned as prerequisite. All done.
- **drupal-critic (ACCEPT-WITH-RESERVATIONS)**: Footer `align` boolean is non-toggle semantics — needs semantic filtering in BooleanToggleResolver. `DirectEditControllerTest` needs updating as matcher constructor grows. Both are bounded fixes.
- **perf-critic (ACCEPT-WITH-RESERVATIONS)**: Weighted average revised to 8-18K. Need actual latency measurement (not just estimate). Need always-on elapsed_us (done in Phase 0). Need performance regression test.

## Known Issues

- Playwright cold-start test (`direct-edit-cold-start.spec.ts`) is flaky — intermittent timeout on editor load after tempstore wipe. The compound test (`direct-edit-compound.spec.ts`) is stable.
- `composer.lock` was intentionally cleaned out of the direct-edit work to avoid unrelated dependency churn.
- Session artifacts (`.omc/`, `.playwright-mcp/`) are untracked and should stay that way.
