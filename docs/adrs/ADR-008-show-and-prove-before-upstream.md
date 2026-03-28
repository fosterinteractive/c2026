# ADR-008: Show and Prove — Local Validation Before Upstream Proposals

**Status:** Accepted
**Date:** 2026-03-28
**Context:** FinDrop Canvas AI efficiency — session 5

## Decision

Every upstream proposal must be **validated locally as a working prototype** before filing on drupal.org. No proposal gets filed based on theory alone. The local implementation serves as:

1. **Proof of concept** — demonstrates the approach works within Canvas's actual architecture
2. **Measurement evidence** — provides before/after benchmarks with real page operations
3. **Demo material** — a running instance that maintainers can see (or reproduce from a recipe)
4. **Risk discovery** — uncovers integration issues, edge cases, and UX problems that paper proposals miss

## Context

The contribution strategy (ADR-001 through ADR-007) proposes significant architectural changes to how Canvas handles AI operations. These ideas sound good on paper, but Canvas is a complex system with a React frontend, PHP backend, tempstore state, and a multi-agent orchestration chain. Theoretical savings don't matter if the implementation breaks the editing experience or introduces regressions.

Additionally, upstream maintainers are more receptive to contributions that come with working code and measured results than to RFC-only proposals. A running demo is worth more than a well-written issue.

## Validation Requirements Per Proposal

### P4: Lightweight Edit Path (deterministic bypass)

**What to build locally:**
- Frontend: detect "component selected + recognized prop + explicit value" in AiWizard.tsx (or a parallel component)
- Backend: a `/canvas-ai/direct-edit` endpoint that invokes `update_component_data` directly
- Measure: token count (should be 0) and latency (should be <1s) vs agent path

**Show and prove:**
- Demo a content author making 5 edits in the chat panel — 3 route deterministically (instant), 2 route through AI
- Record the session with before/after token counts
- Show the UX: how does the user know which path was taken?

### P3a: Loop Iteration in BuildSystemPromptEvent

**What to build locally:**
- A custom event subscriber that reads loop iteration from the agent wrapper (may need reflection or a service decorator since the event doesn't expose it yet)
- Demonstrate that context injection can be skipped on loop > 1

**Show and prove:**
- ai_observability logs showing context injected once vs every loop
- Before/after token counts for a multi-loop edit operation

### P1: Native Region Scoping

**What to build locally:**
- Already built: `canvas_ai_scoping` module with `LayoutScopingSubscriber`
- Extend: add region index generation to the subscriber output
- Extend: test cross-region operations (move component to different section)

**Show and prove:**
- Side-by-side: edit with full layout vs scoped layout — same result, different token count
- Cross-region move operation works correctly with region index only
- Benchmark across 3+ page configurations (5, 15, 30 components)

### P2: Loop-Aware Context Injection

**What to build locally:**
- Fix the `ContextScopingSubscriber` (separator format bug)
- Add loop-awareness: skip context re-injection on loop > 1 (depends on P3a local work)

**Show and prove:**
- ai_observability logs showing context appears once in system prompt, not repeated
- Before/after per-loop token counts

### ADR-006: Selection-First Editing (context envelopes)

**What to build locally:**
- Extend `canvas_ai_scoping` to generate context envelopes: component data + neighbor summaries + section summary + page outline
- When `active_component_uuid` is set, replace full context with the envelope
- Measure envelope size vs full context size

**Show and prove:**
- Demo an edit operation where the agent receives only the component envelope (~500 tokens) instead of full page context (~20K)
- Verify the agent still produces correct results with reduced context
- **This is the critical test:** if the agent fails with only envelope context, ADR-006 needs revision

### ADR-007: Deterministic Surface Area (templates, presets, tokens)

**What to build locally:**
- Start small: demonstrate one template-based page build where slot population is deterministic
- Map the Canvas component catalog to identify which operations are inherently deterministic

**Show and prove:**
- Count: of the available Canvas components, how many have props that map to simple types (string, color, number)?
- Estimate: based on the component catalog, what percentage of real edits COULD be deterministic?
- This provides the data to validate or revise the 60/25/15 split estimate in ADR-006

## Validation Sequence

```
Week 1-2:  Fix ContextScopingSubscriber (P2 local)
           Extend LayoutScopingSubscriber with region index (P1 local)
           Build measurement harness (drush command for standard test scenarios)

Week 3-4:  Build P4 prototype (frontend detection + direct edit endpoint)
           Build P3a workaround (loop detection in custom subscriber)
           Run full benchmark suite across page configurations

Week 5-6:  Build context envelope prototype (ADR-006 local)
           Survey component catalog for deterministic prop mapping (ADR-007)
           Run envelope tests — verify agent quality with reduced context

Week 7-8:  Compile results into demo package
           Record demo sessions (screen recording + token logs)
           Update upstream proposals with measured evidence
           File first drupal.org issues (P4, P3a, P1) with working patches
```

## What "Show and Prove" Looks Like

For the Foster Interactive conversation and drupal.org issues:

1. **Benchmark report** — table of token counts across scenarios, before and after each optimization, reproducible via drush command
2. **Demo recording** — screen capture of a content author editing a Canvas page, showing deterministic edits (instant) vs AI-assisted edits (with reduced context), with token counter overlay
3. **Running instance** — DDEV recipe that anyone can `ddev demo-setup` to reproduce our results
4. **Patch files** — working patches against canvas_ai / ai_agents / ai_context that implement each proposal

## Consequences

- No upstream issue gets filed without a working local prototype and benchmark data
- The 8-week local validation phase precedes the upstream filing phase
- If a proposal fails local validation (e.g., agent produces bad results with context envelopes), we revise the ADR before filing upstream
- The demo package becomes the primary evidence for upstream discussions

## Risks

- **Local workarounds may not translate to clean upstream patches.** The local implementation may use hacks (reflection, string replacement) that aren't acceptable upstream. Mitigation: the local prototype proves the concept; the upstream patch is a clean reimplementation.
- **8 weeks of local work before any upstream filing.** Mitigation: this is investment in credibility. Filing with measured evidence gets faster reviews than filing with theory.
- **Context envelopes (ADR-006) might not work.** The agent may produce degraded results with only component-level context. Mitigation: this is exactly why we test locally first. If envelopes need more context, we adjust the layers.
