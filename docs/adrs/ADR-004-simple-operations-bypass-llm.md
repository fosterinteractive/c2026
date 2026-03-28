# ADR-004: Deterministic Operations Should Bypass the LLM

**Status:** Proposed
**Date:** 2026-03-27
**Context:** FinDrop Canvas AI token efficiency audit (WS1)

## Decision

When a user's intent maps deterministically to a single tool call with known parameters, the system should execute that tool call directly without LLM involvement.

"Change the heading text to X" on a selected component → `update_component_data(uuid, {heading: "X"})`. Zero tokens.

## Context

The Canvas AI edit flow for "Change this heading to Take Control of Every Dollar":

1. User selects a component (provides `active_component_uuid`)
2. User types a clear instruction with explicit target and value
3. Orchestrator receives request → routes to page_builder_agent (1 LLM call)
4. Page builder reads layout (1 tool call) → identifies component → calls `update_component_data` (1 tool call) → confirms (1 LLM call)
5. Orchestrator processes response (1 LLM call)

**Total: 5 LLM calls, 111K tokens, for what is functionally a key-value update.**

A pattern detector could identify:
- Single component selected (UUID known)
- Single property referenced ("heading", "text", "color", "background")
- Explicit value provided (quoted text, color code, URL)
- No ambiguity requiring LLM reasoning

And route directly to the tool, consuming 0 LLM tokens.

## Consequences

### For canvas_ai
- Add a "simple edit detector" at the request entry point (CanvasBuilder controller or frontend).
- Define the set of "simple edit" patterns (prop type + value format → tool call mapping).
- Complex edits (ambiguous references, multi-component changes, style reasoning) still route through the agent chain.

### For the user experience
- Simple edits complete faster (no LLM latency).
- No change in capability — anything the detector can't handle falls through to the AI path.

### For the AI agents
- No changes required. The bypass happens before the agent chain is invoked.

## Risks

- **False positive detection** — routing a complex edit through the simple path produces wrong results. Mitigation: conservative detection with explicit fallback. When in doubt, use the AI path.
- **User expectation mismatch** — if simple edits are instant but complex edits take 10+ seconds, the inconsistency may confuse users. Mitigation: UI feedback indicating which path was taken.
- **Scope creep** — temptation to add more patterns to the detector until it becomes its own NLU system. Mitigation: strict scope — only patterns with 100% deterministic mapping.

## Alternatives Considered

**"Use a smaller/faster model for simple edits"** — Still burns tokens and adds latency. A regex-based detector is faster and free.

**"Let the orchestrator decide"** — The orchestrator already costs ~10K tokens just to route. By the time it decides the edit is simple, you've already spent the tokens.

**"Prompt-engineer the agent to be faster on simple edits"** — Measured: prompt optimizations produced negligible savings (259K vs 253K). The architecture, not the prompt, is the bottleneck.

## Evidence

111,004 tokens measured for "Change this heading to Take Control of Every Dollar" on a selected component. The tool call itself (`update_component_data`) executes in <100ms with zero tokens. The 111K tokens are entirely overhead from the agent chain processing a deterministic operation.

## Open Questions

1. Should the simple edit detector live in the frontend (TypeScript) or backend (PHP)?
   - Frontend: faster (no server round-trip), but duplicates tool logic
   - Backend: single source of truth, but still requires the HTTP request
2. What percentage of real-world edits would qualify as "simple"? Need usage data.
3. Should the detector be configurable (site builders can add/remove patterns)?
