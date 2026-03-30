# Handoff: Show & Prove — Session 2

**Date:** 2026-03-29
**Branch:** `main` (ahead of origin by 5+ commits)
**Site:** https://c2026.ddev.site (DDEV running)
**Tests:** 128 unit tests, 390 assertions + 4 E2E specs

---

## What Was Accomplished This Session

### P0 — Code Bug Fixes (5 bugs, all resolved)

| # | Bug | Fix | Commit |
|---|-----|-----|--------|
| 1 | `is_numeric()` silently excluded spacing/margin/padding enum props | Changed to `$propDef['type'] === 'number'` check | `66d8257` |
| 2 | Duplicate `text_align` key in semantic map (lost alias) | Merged/deduplicated | `66d8257` |
| 3 | `level` prop hardcoded to range 1–6 | Now uses schema's actual enum range | `66d8257` |
| 4 | `is_text_centered` boolean alias collided with `text` prop | Added to semantic map with correct aliases | `66d8257` |
| 5 | Missing test for numeric-string enums (`["0","8","32"]`) | Added fixture to `ComponentSchemaLoaderTest` | `66d8257` |

### P1 — E2E Smoke Tests (both passing)

- Cold-start heading edit: select heading → `change heading to Hello` → verified instant 200 response
- Compound edit: `Change the heading to Hello and set the color to blue` → single 200 response, both props updated
- CSRF token compatibility verified: controller uses `canvas_ai.canvas_builder` seed, matches frontend

### P2 — Benchmark with N=10 Measured Runs

Commit: `dd61628`

| Metric | Value |
|--------|-------|
| Mean latency | 38.4ms |
| Median | 30.1ms |
| 95% CI | [22.7, 54.0]ms |
| Hit rate (20-edit mix) | 60.0% (12/20) |
| All HTTP 200 (latency) | Yes |
| All predictions correct | Yes |

Warm-up protocol: first 2 of 12 runs discarded. Measurement method: direct API POST via Playwright request context. Statistical model: Student's t, df=9.

Raw data: `docs/benchmarks/direct-edit-benchmark-2026-03-29.json`

### P5 — Demo Package (this session's final deliverables)

| File | Purpose |
|------|---------|
| `scripts/benchmark-direct-edit.sh` | One-command benchmark runner with prerequisite checks and JSON summary |
| `docs/demo-script.md` | Step-by-step presentation guide for drupal.org/DrupalCon audience |
| `docs/handoff/handoff-show-and-prove-session-2.md` | This file |

### Commits This Session

| Commit | Description |
|--------|-------------|
| `66d8257` | fix: P0 bugs — is_numeric filter, duplicate text_align, hardcoded level, alias collision |
| `dd61628` | feat: P2 benchmark — direct-edit latency N=10 and hit rate measurement |

---

## What Remains

### Immediate (next session)

| Priority | Task | Notes |
|----------|------|-------|
| P3 | Full E2E test suite (16 scenarios) | 11 deterministic + 5 rejection/fallback tests. Page Object Model extraction first — fixes flakiness. |
| P4 | Contribution patches | 3 patches: ai_context (loop_aware), canvas #3545816 (region scoping), canvas #3549232 (this module). See patch architecture in `docs/plans/2026-03-29-contribution-patches-drupal-plan.md`. |

### Measurements Still Needed

- **AI path wall-clock latency (N=5 measured)**: Replace "15–30s (observed)" with actual Playwright timing through the AI chain. Requires `OPENAI_API_KEY` or `ANTHROPIC_API_KEY` in `.ddev/.env`. Record per-run values, compute mean + SD.
- **Region scoping layout sizes**: Re-measure full layout vs scoped layout on current site. `docs/research/upstream-evidence-matrix.md` flags 12,438 / 11,558 byte discrepancy — re-measure so P1 upstream comment has current numbers.

### Upstream Comment Updates

`docs/research/drupal-org-ready-comments-v2.md` is ready but **do not post yet**. Blockers:

1. Replace "15–30s" with measured AI path latency (N>=5 Playwright timing)
2. Update test counts (now 128 unit tests, 390 assertions — comments still say 126/376)
3. Re-measure layout sizes for P1 comment
4. Fix 10–12K → 22K per loop in P2 comment (see evidence matrix)

**Filing order when ready:** P2 (ai_context new issue) → P1 (canvas #3545816) → P4 (canvas #3549232)

---

## Key Findings

### Bare Value Inference Is Limited to Raw Enum Values

Natural aliases like "blue" (→ `primary`) live in the enum alias map, not the reverse enum index. Typing bare `blue` returns 422; `set the color to blue` resolves correctly via Tier 1 pattern matching. This is intentional — including aliases in the reverse index would increase collision risk. Document this limitation explicitly in the upstream comment.

### Miss Latency Is Faster Than Hit Latency

Miss latency mean: 25ms. Hit latency mean: 29ms. Misses short-circuit at pattern matching. Hits continue through component validation, prop validation, and operation building. There is no penalty for a miss beyond the 25ms round-trip — the 422 is returned immediately and Canvas routes to the AI chain.

### Right-Skewed Latency Distribution

Median (30ms) is notably lower than mean (38ms). One outlier at 97ms, likely PHP opcode cache cold start. The CI [23, 54]ms reflects this skew. For public reporting, cite median alongside mean.

### Frontend Integration Already in Place

`AiWizard.tsx` line 734: `attemptDirectEdit()` POSTs to `/admin/api/canvas/direct-edit` before falling back to `/admin/api/canvas/ai` on 422. Our route matches exactly. No frontend patch is needed for the P4 contribution.

---

## Architecture Decisions Still Open

| Decision | Options | Recommendation from Previous Session |
|----------|---------|--------------------------------------|
| Semantic alias strategy | Hardcoded map + algorithmic fallback vs. fully algorithmic | Patch architect: fully algorithmic with config override |
| Boolean toggle scope | Include `is_text_centered` vs. exclude non-obvious booleans | canvas-critic: include with correct semantic map entry (done in P0) |
| `level` prop handling | Schema-derived range vs. hardcoded 1–6 | canvas-critic: schema-derived (done in P0) |

---

## Test Counts by Type

| Type | Count | Assertions |
|------|-------|------------|
| PHPUnit unit tests | 128 | 390 |
| E2E specs (Playwright) | 4 | — |
| — cold-start heading | 1 | — |
| — compound edit | 1 | — |
| — benchmark spec | 1 | — |
| — (additional) | 1 | — |

---

## Resources

| Resource | Location |
|----------|---------|
| Execution plan | `docs/plans/show-and-prove-execution-plan.md` |
| Patch architecture | `docs/plans/2026-03-29-contribution-patches-drupal-plan.md` |
| Evidence matrix | `docs/research/upstream-evidence-matrix.md` |
| Upstream comments (draft) | `docs/research/drupal-org-ready-comments-v2.md` |
| Benchmark data | `docs/benchmarks/direct-edit-benchmark-2026-03-29.json` |
| Benchmark results | `docs/benchmarks/direct-edit-benchmark-2026-03-29.md` |
| Demo script | `docs/demo-script.md` |
| Benchmark runner | `scripts/benchmark-direct-edit.sh` |
| catch-bot profiles | `~/claude/catch-bot/profiles/` |
| Canvas critic skills | `drupal-canvas/skills/canvas-*` |

---

## Previous Session Handoff

`docs/handoff/handoff-show-and-prove.md` — covers the 7 critic reviews, evidence gathered, and the full remediation plan that drove this session's work.
