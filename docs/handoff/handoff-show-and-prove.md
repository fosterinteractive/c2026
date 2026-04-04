# Handoff: Show & Prove — Execution Session

**Date:** 2026-03-29
**Branch:** `main` (2 commits this session)
**Site:** `https://c2026.ddev.site` (DDEV running)
**Tests:** 126 passing, 376 assertions

---

## What Was Accomplished This Session

### Code Changes (2 commits)

| Commit | What |
|--------|------|
| `7057e0c` | `NON_TOGGLE_BOOLEAN_PROPS` constant excludes `align`/`reverse`/`flip` from BooleanToggleResolver; performance regression tests (per-tier <50ms, batch of 20 <1s) |
| `06ed389` | Fix `STRIP_FINGERPRINTS` dead constant (PHP fatal); fix duplicate `'full'` key (lost alias); evidence matrix; v2 upstream comments |

### Evidence Gathered

- **Orthogonality report** (live Byte theme): 12/17 orthogonal, 5 collision
- **Prop census**: 125 props — 40% enum, 8.8% boolean, 51.2% string = 48.8% deterministic-addressable
- **Latency**: N=30 matcher benchmark — median 3.2µs, batch of 30 in 6.26ms
- **Footer `align` bug**: confirmed in live data, fixed, verified after cache clear
- **Component inventory**: 23 YAML files, 22 with props, 17 with enums, 5 boolean-only

### Critical Discovery

**Canvas frontend already calls our endpoint.** `AiWizard.tsx` has `attemptDirectEdit()` at line 734 that POSTs to `/admin/api/canvas/direct-edit` before falling back to `/admin/api/canvas/ai` on 422. Our route matches exactly. No frontend patch needed.

### Documents Created

| File | Purpose |
|------|---------|
| `docs/research/upstream-evidence-matrix.md` | Every claim mapped to measured evidence with status |
| `docs/research/drupal-org-ready-comments-v2.md` | Revised comments: numbers corrected, limitations disclosed, filing order P2→P1→P4 |
| `docs/plans/show-and-prove-execution-plan.md` | 5-phase execution plan with success criteria |
| `docs/plans/2026-03-29-contribution-patches-drupal-plan.md` | Patch architecture for ai_context, canvas_ai, canvas (from drupal-planner) |

### 7 Critic Reviews Completed

| Critic | Target | Verdict | Key Findings |
|--------|--------|---------|-------------|
| comment-critic (opus) | v1 upstream comments | REVISE | 111K→101K discrepancy; 40.1% wrong population; i18n gap; filing order should be P2 first |
| drupal-critic (opus) | Code + comments | ACCEPT-W-RESERVATIONS | STRIP_FINGERPRINTS bug; str_replace fragility; byte_theme hardcoding; concrete class coupling |
| canvas-critic (opus) | Code with Canvas skills | ACCEPT-W-RESERVATIONS | `is_numeric()` filter excludes spacing props (M1); boolean alias collisions (M3); `level` hardcoded range (m2) |
| perf-bench-critic (opus) | Benchmark methodology | REVISE | N=1 insufficient; AI latency unmeasured; unfair 3-in-1 comparison; cherry-picked scenario |
| test-strategy (opus) | E2E test design | — | 16 scenarios mapped; Page Object Model extraction; 5 flakiness fixes; two-tier CI |
| patch-architect (opus) | Contribution patches | — | 3 patches designed; inject-then-strip eliminated; token-based layout API; dynamic theme |
| Maintainer research | Contributor positioning | — | 10 profiles available; no Canvas maintainer profiles; Dries frames Canvas as "step one" |

---

## Remediation Plan (Priority Order)

### P0 — Code Bugs (Before Anything Else)

| # | Issue | Source | Fix | Files |
|---|-------|--------|-----|-------|
| 1 | **`is_numeric()` silently excludes spacing/margin/padding enums** | canvas-critic M1 | Check `$propDef['type'] === 'number'` instead of `is_numeric()` on values | `ComponentSchemaLoader.php:395` |
| 2 | **Duplicate `text_align` key in semantic map** | canvas-critic m4 | Merge or remove duplicate | `ComponentSchemaLoader.php:487,533` |
| 3 | **`level` prop hardcoded to 1-6** | canvas-critic m2 | Use schema's actual enum range instead of hardcoded range | `DirectEditMatcher.php:499-505` |
| 4 | **`is_text_centered` boolean alias collides with `text` prop** | canvas-critic M3 | Add to semantic map with appropriate aliases | `ComponentSchemaLoader.php:479` |
| 5 | **Add test for numeric-string enums** | canvas-critic missing test | Fixture with `["0","8","32"]` in ComponentSchemaLoaderTest | `ComponentSchemaLoaderTest.php` |

### P1 — Phase 1: E2E Smoke Test (Critical Path)

| # | Task | Details |
|---|------|---------|
| 6 | Extract Page Object Model | `CanvasEditorPage` class from existing specs (fixes flakiness + enables all future tests) |
| 7 | Fix 5 flakiness issues | iframe race, `waitForTimeout` bombs, hardcoded IDs, login session reuse, tempstore race |
| 8 | Run E2E smoke test | Select heading → "change heading to Welcome" → verify instant response |
| 9 | Verify CSRF token compatibility | Frontend fetches from `/admin/api/canvas/token` with seed `canvas_ai.canvas_builder` — verify our controller uses same seed |

### P2 — Phase 2: Measurements (Fill N=1 Gaps)

| # | Task | Details |
|---|------|---------|
| 10 | Run N>=10 AI path token measurements | Heading edit through AI chain, record per-run tokens. Compute mean, SD, CI |
| 11 | Measure AI path wall-clock latency | Playwright timing, N>=10. Replace "15-30 seconds" with measured values |
| 12 | Add 3rd benchmark condition | Module on w/o deterministic routing (only P1+P2 active) — isolates each optimization |
| 13 | "Realistic editing session" scenario | 20 mixed edits, measure actual deterministic hit rate vs theoretical 48.8% |
| 14 | Document warm-up protocol | Discard first 2 runs, pin model version, document environment requirements |

### P3 — Phase 3: E2E Test Suite

| # | Task | Details |
|---|------|---------|
| 15 | 11 deterministic E2E tests | All 5 tiers through Canvas UI (no API key needed) |
| 16 | 5 rejection/fallback tests | Content generation, add intent, ambiguous, boundary, too-long |
| 17 | Benchmark spec with timing | N=5 per scenario, median + range, JSON + markdown artifacts |
| 18 | Loop-aware context tests | Parse watchdog for token breakdown (requires API key) |

### P4 — Phase 4: Contribution Patches

| # | Patch | Target | Key Changes |
|---|-------|--------|-------------|
| 19 | Patch 1 (1-3 lines) | `canvas` | Set `layout_data` token on `BuildSystemPromptEvent` via existing `setToken()` |
| 20 | Patch 2 (~50-80 lines) | `ai_context` | `loop_aware` boolean in per-agent config; skip injection at source |
| 21 | Patch 3 (~800-1000 lines) | `canvas_ai` | 5 new classes with dynamic theme discovery, algorithmic aliases, token-based layout |

**Architectural corrections from prototype → patches:**
- Hardcoded `byte_theme` → `ThemeHandlerInterface::getDefault()`
- Inject-then-strip → skip-at-source (eliminates `AiContextPromptParser` dependency)
- `str_replace` on JSON → `layout_data` token for structured modification
- `State` API for telemetry → proper config (exportable across environments)
- Hardcoded semantic alias map → algorithmic generation + optional config override

### P5 — Phase 5: Demo Package

| # | Task |
|---|------|
| 22 | `scripts/benchmark-direct-edit.sh` — one-command benchmark |
| 23 | `docs/demo-script.md` — step-by-step presentation guide |
| 24 | Update handoff note with final results |
| 25 | File upstream: P2 → P1 → P4 (filing order confirmed) |

---

## Key Architecture Decisions Still Needed

1. **Semantic alias strategy**: Keep hardcoded map + algorithmic fallback, or go fully algorithmic? The patch architect recommends algorithmic with config override. The canvas-critic found collision bugs in the current map.

2. **Boolean toggle scope**: Add `is_text_centered` to semantic map, or exclude non-obvious boolean props? The NON_TOGGLE filter handles `align`/`reverse`/`flip`, but `is_text_centered` is semantically a toggle — it just has a bad name.

3. **`level` prop handling**: Schema-derived range (generic, correct) vs hardcoded 1-6 (simple, works for known cases)? The canvas-critic recommends schema-derived.

4. **Benchmark sample size**: N=5 (fast, sufficient for order-of-magnitude) vs N=10 (statistically meaningful) vs N=30 (publication-grade)? Perf-critic recommends N>=10 with mean/SD/CI.

---

## Risk: What Could Block Phase 1

| Risk | Impact | Status |
|------|--------|--------|
| CSRF token mismatch | E2E blocked | **VERIFY** — controller uses `canvas_ai.canvas_builder` seed, needs to match frontend |
| `operationsHandler` expects different response shape | UI doesn't update | **CLEARED** — canvas-critic confirmed format matches |
| Tempstore not populated on first edit | 422 on first try | **MITIGATED** — controller accepts `layout` field to seed tempstore |
| Cold-start Playwright flakiness | Tests unreliable | **5 FIXES IDENTIFIED** — Page Object Model extraction is the priority |

---

## Session Metrics

- **7 Opus critic/architect agents** completed across the session
- **2 Sonnet executor agents** for code fixes
- **126 tests, 376 assertions** (up from 121/362)
- **4 new documents** created
- **3 code bugs fixed** (STRIP_FINGERPRINTS, duplicate key, align boolean)
- **3 more code bugs discovered** (is_numeric filter, level hardcoding, text_align duplicate) — ready to fix next session

---

## Upstream Comment Status

`docs/research/drupal-org-ready-comments-v2.md` is ready but **do not post yet**. The measurements need N>=10 runs (P2 above) and the `is_numeric()` bug needs fixing first (P0). The comments reference "126 tests" which will increase after P0 fixes.

Filing order when ready: **P2 (ai_context) → P1 (canvas #3545816) → P4 (canvas #3549232)**

---

## Maintainer Research

10 Drupal core contributor profiles documented:
- **catch** (most complete) — architectural hygiene, performance focus
- **Dries** — UX, ecosystem fit, frames Canvas as "step one"
- 8 others: alexpott, berdir, dawehner, effulgentsia, larowlan, nod_, Wim Leers, xjm

7 Canvas-specific skills available via drupal-critic routing (`drupal-canvas/skills/canvas-*`):
- component-definition, component-metadata, component-utils, data-fetching, styling-conventions, component-composability, component-upload
