# ADR-001: Token Cost Must Scale with Operation Complexity, Not Page Complexity

**Status:** Accepted
**Date:** 2026-03-27
**Context:** FinDrop Canvas AI token efficiency audit (WS1)

## Decision

All AI-assisted CMS operations must consume tokens proportional to the **operation being performed**, not the **size of the content being operated on**.

A heading text change on a 30-component page must not cost more than the same change on a 3-component page.

## Context

Measured on FinDrop (Drupal CMS 2.0 + Canvas page builder):

| Operation | Tokens | Components | Cost driver |
|-----------|--------|------------|-------------|
| Full page build | 253,593 | 30 | Reasonable — building 30 components |
| Heading text edit | 111,004 | 30 | Unreasonable — editing 1 component |
| Heading text edit (scoped) | 108,839 | 30 | Still unreasonable — scoping layout isn't enough |

The 111K edit cost breaks down to:
- System prompt + ai_context items: 16-20K per call × 5 calls = 80-100K
- Layout JSON (scoped to section): 2.8K per call
- Chat history accumulation: 3-10K per call
- Tool definitions: 3-4K per call

**The layout (which scales with page complexity) is only ~12% of per-call cost.** The dominant costs — system prompt, context items, tool definitions — are fixed per call regardless of page size. But the number of calls and the context loaded per call are identical for a heading edit and a page build.

## Consequences

### For ai_context
- Context items must be operation-aware. Edit operations need fewer context items than build operations.
- The module must support scoping context by operation type.

### For ai_agents
- The agent framework must support reducing per-call overhead for simple operations.
- Loop-aware context injection: don't re-inject identical context on every loop.
- History windowing: don't accumulate unbounded conversation history.

### For canvas_ai
- Layout data sent to the LLM must be proportional to what's being edited, not the full page.
- Simple deterministic edits should bypass the LLM entirely.

### What This Does NOT Mean
- Complex operations should still get full context. This principle only constrains operations where the scope is clearly narrower than the full page.
- We are not arguing for degraded quality. The same edit quality must be maintained with less context waste.

## Alternatives Considered

**"Just reduce max_loops"** — Reduces worst-case cost but doesn't address per-call overhead. A 5-call edit at 20K/call still costs 100K.

**"Just optimize prompts"** — Measured: config-only changes (prompt trimming, loop caps) produced 259K vs 253K baseline. Negligible improvement.

**"Use cheaper models for edits"** — Doesn't address the architectural issue. Sends the same data, just processes it cheaper. Still wasteful.

## Evidence

All measurements taken with `ai_observability` enabled on FinDrop DDEV instance, March 2026. Methodology: Drupal watchdog log token counts from provider responses. Each scenario measured with identical prompts.
