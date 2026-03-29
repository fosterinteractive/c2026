# WS1 Measurement Results — Token Efficiency Optimization

**Date:** 2026-03-29
**Branch:** `feat/ws1-efficiency-optimization`
**Test page:** FinDrop Travel (canvas_page/8, ~15 components)
**Operation:** Heading text edit ("Change the heading to X")
**Model:** claude-sonnet-4-6 via Anthropic

---

## Summary

| Optimization | Tokens per edit | Reduction from baseline | Type |
|---|---|---|---|
| **Baseline (no optimizations)** | **101K** | — | Measured |
| + `available_on_loop: [1]` on `current_layout` | 92K | -9% | Config change (generic) |
| + Fixed ai_context parser (standalone line matching) | 48K | -52% | Code fix (generic) |
| + ContextScopingSubscriber (5/5 fingerprints, component selected) | 31K | -69% | Code fix (demo-specific) |
| **Tier 1 direct edit (deterministic)** | **0** | **-100%** | Code (generic) |

---

## Methodology

All measurements taken on a running DDEV instance with `ai_observability` and `canvas_ai_scoping` modules enabled. Token counts from `TokenBreakdownSubscriber` log entries (per-agent, per-loop system prompt size) plus `ai_observability` provider response token counts.

Each measurement is N=1 (single edit operation). The heading edit was chosen as a representative simple operation.

---

## Detailed Measurements

### Measurement 1: Baseline (no optimizations active)

**Config:** `current_layout` has no `available_on_loop`, `LoopAwareContextSubscriber` parser matching wrong separators, `ContextScopingSubscriber` fingerprints not matching.

| Agent | Loop | System Prompt | ai_context | Notes |
|-------|------|--------------|------------|-------|
| orchestrator | 0 | 8,023 tok | 2,355 | Routes to page_builder |
| orchestrator | 1 | 8,023 tok | 2,355 | Processes result |
| page_builder | 0 | 28,513 tok | 103 (mis-measured) | Full context in prompt |
| page_builder | 1 | 28,409 tok | 103 (mis-measured) | Context re-injected |
| page_builder | 2 | 28,409 tok | 103 (mis-measured) | Context re-injected |
| **Total** | | **~101K tok** | | |

**Key finding:** The `TokenBreakdownSubscriber` reported only 103 tokens of ai_context because it was matching a markdown table separator (50 dashes) instead of the real ai_context separator (47 dashes on a standalone line). The actual ai_context was 22,092 tokens, embedded in the "post-context" section.

### Measurement 2: + `available_on_loop: [1]`

**Config change:** Added `available_on_loop: [1]` to `current_layout` in `canvas_page_builder_agent` default_information_tools (matching what `canvas_template_builder_agent` already had).

| Agent | Loop | System Prompt | Change |
|-------|------|--------------|--------|
| page_builder | 0 | 25,553 tok | -2,960 (layout moved to chat history) |
| page_builder | 1 | 25,434 tok | -2,975 |
| page_builder | 2 | 25,434 tok | -2,975 |
| **Total** | | **~92K tok** | **-9K** |

**Savings:** Layout JSON (11,558 bytes, ~2,889 tokens) no longer re-injected into system prompt on loops 1+. Moved to chat history instead.

### Measurement 3: + Fixed ai_context parser

**Code fix:** `AiContextPromptParser::findBlock()` changed from `strpos()` (matches any 47+ dash run) to `preg_match_all()` with newline anchors (matches only standalone separator lines). This allowed `LoopAwareContextSubscriber` to correctly identify and strip the full 88K-byte ai_context block.

| Agent | Loop | System Prompt | Change |
|-------|------|--------------|--------|
| page_builder | 0 | 25,553 tok | (same — context needed on first loop) |
| page_builder | 1 | **3,461 tok** | **-21,973 (context stripped!)** |
| page_builder | 2 | **3,460 tok** | **-21,974 (context stripped!)** |
| **Total** | | **~48K tok** | **-44K from M2** |

**Savings:** 88,369 bytes (~22K tokens) of ai_context stripped on each subsequent loop. Builder loops 1+ now contain only agent instructions (3.5K tokens).

### Measurement 4: + ContextScopingSubscriber (component selected, 2/5 fingerprints)

**Test:** Clicked on a heading component in the Layers panel before sending the edit message. This sets `active_component_uuid`, triggering the `ContextScopingSubscriber`.

Only 2 of 5 fingerprints matched (Visuals & Imagery + Content Structure: Product Pages). The other 3 fingerprints were from entity metadata fields not included in the rendered content.

| Agent | Loop | System Prompt | Change |
|-------|------|--------------|--------|
| page_builder | 0 | **14,737 tok** | **-10,816 (2 items stripped from loop 0)** |
| page_builder | 1 | 3,470 tok | (same) |
| page_builder | 2 | 3,469 tok | (same) |
| **Total** | | **~38K tok** | **-10K from M3** |

### Measurement 5: + All 5 fingerprints fixed

**Code fix:** Updated 3 fingerprints to match strings actually present in rendered content:
- Key Facts: `'Mandatory Phrasing Rules'`
- Sales Deck: `'INTERNAL SALES TRAINING ONLY'`
- General Guidelines: `'Typography & Contrast Rules v2'`

| Agent | Loop | System Prompt | Change |
|-------|------|--------------|--------|
| page_builder | 0 | **7,868 tok** | **-6,869 (5 items stripped)** |
| page_builder | 1 | 3,470 tok | (same) |
| page_builder | 2 | 3,469 tok | (same) |
| **Total** | | **~31K tok** | **-7K from M4** |

**5 of 9 ai_context items stripped during edit operations:** Visuals & Imagery, Key Facts, Sales Training Deck, General Page Building Guidelines, Content Structure: Product Pages. Kept: Brand Guidelines, Writing Tone & Voice, Abbreviations/Spelling, Typography & Contrast Rules.

---

## Prompt Budget Decomposition

From raw system prompt dump analysis (page_builder loop 0, measurement 1):

| Segment | Bytes | Tokens | % of total |
|---------|-------|--------|------------|
| Agent instructions | 13,877 | 3,469 | 12.4% |
| **ai_context items (8 items)** | **86,418** | **21,604** | **77.1%** |
| Layout JSON (via get_current_layout) | 11,558 | 2,889 | 10.3% |
| Other (tool headers, separators) | 234 | 59 | 0.2% |
| **TOTAL** | **112,087** | **28,021** | **100%** |

ai_context items breakdown:

| ID | Item | Bytes | ~Tokens |
|----|------|-------|---------|
| 8 | Content Strategy: Product Pages v4 | 32,266 | 8,067 |
| 12 | Sales Training Deck | 15,331 | 3,833 |
| 6 | Key Facts & Value Propositions | 11,030 | 2,758 |
| 11 | Visuals & Imagery | 10,961 | 2,740 |
| 4 | Writing Tone & Voice | 6,724 | 1,681 |
| 2 | Brand Guidelines | 6,620 | 1,655 |
| 7 | Abbreviations, Spelling, Dates | 3,905 | 976 |
| 1 | Typography & Contrast Rules | 955 | 239 |

---

## What's Generic vs. Demo-Specific

### Generic (works on any Canvas site)

| Optimization | Type | Tokens saved |
|---|---|---|
| `available_on_loop: [1]` on `current_layout` | YAML config | ~3K/loop on loops 1+ |
| `LoopAwareContextSubscriber` | Event subscriber | All ai_context on loops 1+ |
| `AiContextPromptParser` fix | Parser bug fix | Enables the above to work |
| `DirectEditMatcher` + `ComponentSchemaLoader` | Service | 100% (0 tokens for deterministic edits) |
| `TokenBreakdownSubscriber` | Logging | Measurement infrastructure |

### Demo-specific (FinDrop only)

| Optimization | Type | Why demo-specific |
|---|---|---|
| `ContextScopingSubscriber` fingerprints | Hardcoded strings | Match FinDrop ai_context content |
| `LayoutScopingSubscriber` | Event subscriber | Works generically but layout format match depends on Canvas version |

### Upstream proposals (generic when merged)

| Proposal | Module | What it enables |
|---|---|---|
| P2: Loop-aware context injection | ai_context | Native `available_on_loop` for context items (replaces our subscriber) |
| P4: Deterministic edit routing | canvas_ai | Native Tier 1 pattern matching in Canvas core |
| ai_context Scope feature (#3564706) | ai_context | Operation-aware context filtering (replaces fingerprints) |

---

## Cost Impact

At Anthropic's Claude Sonnet pricing (~$3/M input tokens, ~$15/M output tokens):

| Scenario | Input tokens | Est. input cost |
|---|---|---|
| Original (101K per edit) | 101,000 | $0.30 |
| Optimized AI path (31K per edit) | 31,000 | $0.09 |
| Deterministic path (0 tokens) | 0 | $0.00 |

For a content author making 50 edits per session (est. 40% deterministic, 60% AI):
- **Before:** 50 × 101K = 5.05M tokens = **$15.15**
- **After:** 20 × 0 + 30 × 31K = 930K tokens = **$2.79**
- **Savings: 82% per session**

---

## Next Steps

1. **Upstream:** File the `available_on_loop` config fix as a patch against `canvas_ai` (the template builder already has it — the page builder was simply missing it)
2. **Upstream:** Contribute measurement data to P2 proposal (ai_context loop-aware injection)
3. **Frontend:** Integrate the direct edit endpoint into Canvas React UI for Tier 1 routing
4. **Tier 3:** Build the micro-classifier for ambiguous edits (~500 tokens vs 31K)
