# ADR-002: Context Injection Must Be Loop-Aware

**Status:** Accepted
**Date:** 2026-03-27
**Context:** FinDrop Canvas AI token efficiency audit (WS1)

## Decision

Any system that injects context into LLM system prompts within a multi-loop agent framework **must be aware of the loop iteration** and avoid re-injecting identical content on every iteration.

Context that doesn't change between loops should be injected once (loop 1) and either:
- Moved to chat history for subsequent loops, or
- Omitted entirely with a reference marker ("Context loaded on first call — see above")

## Context

The Drupal `ai_agents` module runs agents in a loop (`AiAgentEntityWrapper::execute()`). On each iteration, `BuildSystemPromptEvent` fires, and the `ai_context` module's `SystemPromptSubscriber` appends all configured context items to the system prompt.

For the Canvas page builder agent with 8 context items:
- **Per-loop context cost:** 10-12K tokens
- **Typical edit loops:** 3-5
- **Total context cost:** 30-60K tokens for identical content repeated 3-5 times

The `available_on_loop` mechanism exists for `default_information_tools` but **not** for `ai_context` injection. `available_on_loop` skips tool re-execution on subsequent loops — the tool doesn't re-fetch data, but the loop-1 output remains in chat history and is transmitted on every call. The savings are from avoiding redundant tool calls, not from reducing transmitted data.

For `ai_context` items, the situation is different: `SystemPromptSubscriber` re-appends the same content to the system prompt on every loop, creating **actual duplication** in the transmitted payload. The LLM receives the same context items twice — once in the system prompt (re-injected) and once already present from the previous turn. This is the target for loop-aware injection: prevent the re-append, relying on the content already present from loop 1.

## Consequences

### For ai_agents module
- `BuildSystemPromptEvent` must include the current loop iteration number so subscribers can decide whether to inject.
- The event (or agent wrapper) should provide a mechanism to mark content as "inject once" vs "inject every loop."

### For ai_context module
- `SystemPromptSubscriber` must check loop iteration before injecting.
- Default behavior: inject on every loop (backwards compatible).
- New behavior (opt-in): inject on loop 1 only, with a configurable strategy for subsequent loops.

### For consuming modules
- Custom event subscribers (like our `canvas_ai_scoping`) can use loop awareness for their own optimizations.

## Risks

- **LLM may "forget" context from loop 1 as conversation grows.** Mitigation: for long-running agents (>10 loops), re-inject context at configurable intervals (e.g., every 5 loops).
- **Context items that change between loops** (e.g., based on keyword matching against new messages) must still re-inject. The loop-aware mechanism must allow per-item opt-out.

## Evidence

Measured on FinDrop: a heading edit involves ~5 API calls — 2 orchestrator calls (2 `always_include` items, ~1.2K context) and 3-4 page_builder loops (7 `always_include` items, ~7K context each).

The page_builder's context is re-injected on each of its 3-4 loops. Skipping loops 2-4 saves ~7K × 3 = **~21K tokens per edit operation (19% of 111K total).**

Note: the orchestrator calls have only 2 context items (~1.2K) — skipping those saves little. The high-value target is the page_builder's 7-item context block across its internal loops.

Previous versions of this document claimed 40-48K savings (36-43%). That was incorrect — it assumed all 5 calls were page_builder loops with 7 items each. The corrected figure accounts for the orchestrator/page_builder call distinction.
