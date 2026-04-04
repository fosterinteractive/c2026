# Handoff: Show & Prove — Session 2

**Date:** 2026-03-30
**Branch:** `feat/show-and-prove-session-2` (PR #12 against main)
**Site:** https://c2026.ddev.site (DDEV running, telemetry enabled)
**Tests:** 144 unit tests, 541 assertions + 20 Playwright E2E specs

---

## What Was Accomplished This Session

### 11 Commits (all on PR #12)

| Commit | What |
|--------|------|
| `7057e0c` | fix: semantic filtering for non-toggle booleans + perf regression tests *(from previous session)* |
| `06ed389` | fix: code bugs + upstream comment revision with evidence matrix *(from previous session)* |
| `6ba92c3` | docs: show-and-prove execution plan, patch architecture, and handoff *(from previous session)* |
| `66d8257` | fix: P0 bugs — is_numeric filter, duplicate text_align, hardcoded level, alias collision |
| `dd61628` | feat: P2 benchmark — direct-edit latency N=10 and hit rate measurement |
| `76fdba6` | feat: P3/P4/P5 — E2E test suite, contribution patches, demo package, visual guide |
| `e8dc053` | feat: AI path benchmark — 16.4s mean latency, 430x slower than direct-edit |
| `e8f6331` | feat: interactive pitch deck for Canvas direct-edit contribution |
| `99d7abf` | refactor: dynamic theme, config telemetry, reset patterns, edge case tests |
| `3c74abc` | feat: bare value alias index + config-driven synonym verbs |

### P0 — Code Bug Fixes (5 bugs, all resolved)

| Bug | Fix |
|-----|-----|
| `is_numeric()` excluded spacing/margin/padding enum props | Schema type check instead of value check |
| Duplicate `text_align` in semantic map | Removed duplicate, added `is_text_centered` |
| `level` prop hardcoded 1-6 | `getIntegerEnumValues()` — schema-derived |
| `is_text_centered` alias collision with `text` | Safe aliases: "text centered", "centered text" |
| Missing numeric-string enum test | Fixture with `["0","8","32"]` |

### P1 — E2E Smoke Tests (both passing)

- Cold-start heading edit: instant 200, zero AI requests
- Compound edit: single 200, both props updated
- CSRF token compatibility verified

### P2 — Benchmarks (measured, not estimated)

| Path | Mean | SD | 95% CI | N |
|------|------|----|--------|---|
| Direct-edit | 38ms | 22ms | [23, 54]ms | 10 |
| AI path | 16,358ms | 838ms | [15,318, 17,398]ms | 5 |
| **Speedup** | **430x** | | | |

Hit rate: 60% on 20 mixed edits (12 deterministic, 8 AI fallback). All predictions correct.

### P3 — E2E Test Suite

16 tests in `direct-edit-suite.spec.ts`: 15 pass, 1 skipped (tier 4 boolean toggle needs section component). Covers all 5 tiers + 5 rejection tests via API-level assertions.

### P4 — Contribution Patches

| Patch | Target | Size |
|-------|--------|------|
| `patch-1-canvas-layout-token.patch` | canvas | 2 lines |
| `patch-2-ai-context-loop-aware.patch` | ai_context | ~80 lines |
| `patch-3-deterministic-routing-architecture.md` | canvas_ai | Architecture doc (~800-1000 lines) |

### P5 — Demo Package

| Deliverable | Location |
|-------------|----------|
| Benchmark runner | `scripts/benchmark-direct-edit.sh` |
| Demo script | `docs/demo-script.md` |
| Visual guide (8 screenshots) | `docs/guides/` |
| Interactive pitch deck | `docs/pitch-deck/index.html` |
| TL;DR brief | Published: `zivtech.github.io/zivtech-demos/canvas-direct-edit-brief.html` |
| Pitch deck | Published: `zivtech.github.io/zivtech-demos/canvas-direct-edit-pitch.html` |

### Medium-Term Improvements (completed this session)

| Improvement | Impact |
|-------------|--------|
| **Dynamic theme discovery** (L2) | Unblocks upstream contrib — no more hardcoded `byte_theme` |
| **Config API telemetry** (L5) | Proper config schema, exportable, `canvas_ai_scoping.settings` |
| **Reset/clear/remove patterns** | "reset the color", "clear the link" — +3-5% hit rate |
| **Bare value alias index** | "blue", "white", "centered" resolve in Tier 3 — +5-8% hit rate |
| **Config-driven synonym verbs** | turn/switch/put added, i18n extensible via config |
| **9 edge case tests** | bare "default" ambiguity, unicode, compound splitter safety |
| **Telemetry enabled on demo site** | Watchdog logging token breakdowns and context stripping |

---

## What Remains

### Immediate (before merging PR #12)

- [ ] Review PR #12 — 11 commits, all tests green
- [ ] Update upstream comments (`docs/research/drupal-org-ready-comments-v2.md`) with measured AI path numbers (replace "15-30s" with "16.4s measured, N=5")
- [ ] Update test counts in comments (now 144/541, comments say 126/376)

### Next Session — Upstream Filing

| Priority | Task |
|----------|------|
| 1 | **Pre-file conversation with Canvas maintainers** — post a brief summary on the Canvas issue queue asking if deterministic routing direction is welcome |
| 2 | **File P2** (ai_context loop-aware injection) — strongest standalone case, establishes credibility |
| 3 | **File P1** (canvas #3545816 region scoping comment) — complementary to existing issue |
| 4 | **File P4** (canvas #3549232 deterministic routing comment) — most architecturally ambitious |

### Strategic — Shapes the Competitive Story

| Initiative | Why It Matters |
|------------|---------------|
| **Canvas Lite (API-key-free mode)** | 60-70% of editing works without AI. Canvas becomes usable on day one without API infrastructure. Competitive advantage vs Lovable/Manus (fully cloud-dependent). |
| **Canvas MCP server** | Route AI edits through user's desktop Claude/ChatGPT subscription ($20/mo flat) instead of site API keys ($3-15/MTok). Desktop tokens are effectively free. |
| **Prompt caching** | Loop-aware context makes system prompts stable after loop 0. Anthropic prompt caching could cut remaining AI cost by 90%. Full page build drops from ~$6 to ~$0.50. |
| **Model routing by complexity** | Simple AI edits → Haiku (fast, cheap). Complex operations → Sonnet. Matcher confidence score informs routing. |
| **Real-world telemetry** | Collect 100+ edits from actual demo site usage. Validate hit rate outside benchmark. Telemetry is now enabled and logging. |

### Hit Rate Improvement Roadmap

| Current | After alias index | After all improvements | Ceiling |
|---------|-------------------|----------------------|---------|
| 60% | ~68% (estimated) | ~75-80% | ~80% (remaining 20% genuinely needs AI) |

Remaining improvements not yet implemented:
- Partial value normalization ("heading responsive 4xl" → "heading-responsive-4xl")
- Negation patterns ("not centered" → align: left)

---

## Key Findings This Session

1. **430x speedup measured** — 38ms direct-edit vs 16.4s AI path. Not estimated, measured with N=10/N=5 and 95% CIs.

2. **Bare value alias gap closed** — "blue", "white", "centered" now resolve via `reverseAliasIndex`. Previously only raw enum values worked in Tier 3.

3. **Deep Chat doesn't support rapid-fire messages** — benchmark had to use API-level calls instead of UI interaction for N=10 measurements. Single UI proof-of-concept confirmed the path works end-to-end.

4. **ControllerBase::$configFactory collision** — `DirectEditController` extends `ControllerBase` which has its own `$configFactory` property. L5 fix uses `$scopingConfigFactory` to avoid the conflict.

5. **Desktop token arbitrage is real** — Claude Desktop Pro ($20/mo) vs API ($3-15/MTok). A Canvas MCP server could route AI edits through user subscriptions, making AI features effectively free for the site operator.

6. **The economic story is strong** — full page build cost drops from ~$15 (no optimization) → ~$6 (60% hit rate) → ~$1.60 (80% + loop-aware) → <$0.50 (+ prompt caching). Developer time for the same page: $25-75.

---

## Test Counts

| Type | Count | Assertions |
|------|-------|------------|
| PHPUnit unit tests | 144 | 541 |
| Playwright E2E: direct-edit-suite | 16 (15 pass, 1 skip) | — |
| Playwright E2E: cold-start | 1 | — |
| Playwright E2E: compound | 1 | — |
| Playwright E2E: benchmark | 1 | — |
| Playwright E2E: AI path benchmark | 1 | — |
| **Total** | **164** | **541+** |

---

## Resources

| Resource | Location |
|----------|----------|
| PR | https://github.com/fosterinteractive/c2026/pull/12 |
| Pitch deck (live) | https://zivtech.github.io/zivtech-demos/canvas-direct-edit-pitch.html |
| Brief (live) | https://zivtech.github.io/zivtech-demos/canvas-direct-edit-brief.html |
| Benchmark data (direct-edit) | `docs/benchmarks/direct-edit-benchmark-2026-03-29.json` |
| Benchmark data (AI path) | `docs/benchmarks/ai-path-benchmark-2026-03-29.json` |
| Evidence matrix | `docs/research/upstream-evidence-matrix.md` |
| Upstream comments (draft) | `docs/research/drupal-org-ready-comments-v2.md` |
| Patch architecture | `docs/plans/2026-03-29-contribution-patches-drupal-plan.md` |
| Analyst report (limitations + economics) | Captured in this handoff; full analysis was in-session |
| Previous session handoff | `docs/handoff/handoff-show-and-prove.md` |
