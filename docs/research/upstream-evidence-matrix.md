# Upstream Evidence Matrix — Claims vs Measured Data

**Date:** 2026-03-29
**Purpose:** Reconcile every number cited in the 3 drupal.org comments against measured evidence. Fix discrepancies before posting.

---

## P4: Deterministic Edit Path (Comment on #3549232)

| # | Claim in Comment | Measured Evidence | Status | Action |
|---|---|---|---|---|
| 1 | "15-30 seconds" AI path latency | Not directly measured; inferred from UX observation | **ESTIMATED** | Qualify as "observed latency" or measure |
| 2 | "5 LLM calls, 111K tokens" | ws1: 5 agent loops confirmed. Token count = **101K** (ws1 baseline), not 111K | **DISCREPANCY** | Correct to 101K or explain difference |
| 3 | "actual edit executes in <1ms" | Measured: median **3.2µs**, mean 209µs, 30-op batch in 6.26ms | **VERIFIED** (10x better than claimed) |  |
| 4 | "0 tokens, <100ms latency" | 0 tokens confirmed; latency **<7ms** measured | **VERIFIED** (14x better) |  |
| 5 | "23 Byte theme components" | Confirmed: **23 YAML files**, 22 with props, 17 with enums | **VERIFIED** |  |
| 6 | "40.1% of props are simple scalars or enums" | Census: **40.0% enum** (50/125), 8.8% boolean, 51.2% string. Total deterministic-addressable: **48.8%** | **MOSTLY VERIFIED** | Clarify: 40% enum props specifically |
| 7 | "41 PHPUnit tests, 107 assertions" | Now **126 tests, 376 assertions** | **OUTDATED** | Update to current numbers |
| 8 | "Playwright browser regression covering cold-start and compound" | Both specs exist and pass (cold-start is flaky) | **VERIFIED** | Note cold-start flakiness or omit |

### 111K vs 101K Discrepancy

The ws1-measurement-results.md baseline shows **101K** for a heading edit with no optimizations. The slop audit references "111K tokens (N=1)" from a different measurement session. Possible causes:
- Different page (more/fewer components)
- Different ai_context item set at time of measurement
- Output tokens included in one but not the other

**Resolution:** Use **101K** (the ws1 measurement with documented methodology). If 111K came from a different scenario, note that.

---

## P1: Region Scoping (Comment on #3545816)

| # | Claim in Comment | Measured Evidence | Status | Action |
|---|---|---|---|---|
| 1 | "12,438 bytes" full layout | ws1 layout budget: **11,558 bytes** | **DISCREPANCY** | Different page/measurement? Reconcile |
| 2 | "2,611 bytes" scoped layout | Not independently re-measured | **UNVERIFIED** | Re-measure on current site |
| 3 | "79% reduction" in layout | If 12,438 → 2,611, that's 79%. If 11,558 → X, may differ | **CONDITIONAL** | Re-measure with current data |
| 4 | "~125K to ~111K (~11% total reduction)" | ws1 baseline is 101K. Layout is 2,889 tokens (~10.3% of total). Removing it saves ~11% | **PLAUSIBLE** | Clarify: 11% is for layout-only optimization |
| 5 | "12 unit tests" for LayoutScopingSubscriber | Need to verify current count | **VERIFY** | Check test file |
| 6 | "Combined 69% for non-deterministic edits" | ws1: 101K → 31K = 69% with all optimizations | **VERIFIED** |  |

### Layout Size Re-measurement Needed

The layout sizes (12,438 / 2,611) may have been measured on a different page version. Should re-measure on the current FinDrop Travel page via drush.

---

## P2: Loop-Aware Context Injection (New ai_context Issue)

| # | Claim in Comment | Measured Evidence | Status | Action |
|---|---|---|---|---|
| 1 | "10-12K" context per loop | ws1: ai_context = **86,418 bytes (~21,604 tokens)** per injection. NOT 10-12K | **MAJOR DISCREPANCY** | The 10-12K was likely estimated; actual is 22K tokens |
| 2 | "40-168K wasted tokens" for page_builder | 22K × (5-15 - 1) = **88K-308K** wasted | **UNDER-REPORTED** | Update range |
| 3 | "52% reduction" from stripping context | ws1: 101K → 48K = **52.5%** | **VERIFIED** |  |
| 4 | "`available_on_loop` in `default_information_tools` is the direct precedent" | Confirmed: `canvas_template_builder_agent` has this config | **VERIFIED** | Can cite the exact YAML key |
| 5 | "Complementary to #3564706" | Logical argument, not measured | **ARCHITECTURAL** | Keep as-is |
| 6 | "Adjacent to #3524351" | Logical argument | **ARCHITECTURAL** | Keep as-is |

### The 10-12K → 22K Discrepancy

The comment says "10-12K tokens of context per loop." The actual measurement is **86,418 bytes (~21,604 tokens)** on the FinDrop demo site. This varies by site (depends on number and size of ai_context items), but our measured number is nearly 2x what the comment claims.

**Resolution:** The comment should say "tokens proportional to the total ai_context configuration" and cite the measured example: "On our test site with 8 context items: 22K tokens per re-injection."

---

## Fresh Measurement Data (2026-03-29)

### Deterministic Matcher Latency (N=30, live DDEV site)

| Metric | Value |
|--------|-------|
| Min | 1.2 µs |
| Median | 3.2 µs |
| Mean | 208.7 µs |
| P95 | 2.8 µs |
| Max | 5,332 µs (cold cache, first call) |
| Total (30 ops) | 6,260 µs (6.26 ms) |

Distribution: 80% under 50µs, 93% under 500µs. The single >1ms outlier is schema cache warm-up.

### Prop Type Census (live Byte theme, 23 components)

| Category | Count | % | Deterministic Coverage |
|----------|-------|---|------------------------|
| Enum props | 50 | 40.0% | Phase 1 (bare value) on orthogonal components |
| Boolean props | 11 | 8.8% | Phase 2 (toggle) |
| String/scalar props | 64 | 51.2% | Tier 1 only (explicit pattern) |
| **Total** | **125** | | **48.8% addressable by Phases 1+2** |

### Orthogonality (live Byte theme)

- 12/17 enum-bearing components are orthogonal (70.6%)
- 5 have collisions: card-icon (6), group (7), heading (1), hero-side-by-side (2), section (3)
- `heading` collision is only on "default" (text_size vs text_color) — trivial

### Component Inventory

- 23 YAML files total
- 22 with props (1 without: accordion-container)
- 17 with enum props (5 without: accordion, anchor, blockquote, footer, hero-blog)

---

## N=1 Weakness

All token measurements are single-operation (N=1) on one page (FinDrop Travel, ~15 components). This is a known limitation.

**Mitigation language:** "Measurements are from a single representative operation on our demo site (FinDrop Travel, 15 components, 8 ai_context items). Token counts will vary with page complexity and context configuration. The relative reductions (percentages) are more stable than absolute numbers."

---

## Recommended Number Corrections

| Comment | Current | Should Be | Reason |
|---------|---------|-----------|--------|
| P4 | "111K tokens" | "~101K tokens" | ws1 measured baseline |
| P4 | "41 tests, 107 assertions" | "126 tests, 376 assertions" | Updated count |
| P1 | "12,438 bytes" | Re-measure or qualify as "measured on [date]" | May be stale |
| P1 | "~125K to ~111K" | "~101K to ~90K" or re-measure with scoping | Baseline changed |
| P2 | "10-12K" per loop | "~22K tokens (86K bytes)" | ws1 measured |
| P2 | "40-168K wasted" | "88-308K wasted" | Updated from measured 22K per loop |
