# ADR-003: Agent Chains Must Have Aggregate Cost Ceilings

**Status:** Accepted
**Date:** 2026-03-27
**Context:** FinDrop Canvas AI token efficiency audit (WS1)

## Decision

Any agent framework that supports nested agent invocation (agent A calls agent B as a tool) **must enforce an aggregate token budget** across the entire request, not just per-agent loop limits.

`max_loops` is necessary but insufficient. A request's total cost is: `Σ(per_call_tokens × loops)` across all agents in the chain. Without an aggregate ceiling, nested chains create multiplicative cost explosions.

## Context

The Canvas AI orchestration chain:

```
canvas_ai_orchestrator (max_loops: 10)
  └── canvas_page_builder_agent (max_loops: 30)
```

Worst case: 10 × 30 = 300 effective LLM calls.

The SEO agent creates a deeper chain:

```
drupal_canvas_seo_agent (max_loops: 10)
  └── canvas_page_builder_agent (max_loops: 30)
```

Worst case: 10 × 30 = 300 calls, most of which are unnecessary — the SEO agent's Mode A (Schema.org generation) never needs the page builder.

**There is no mechanism to halt execution when cumulative token cost exceeds a budget.** A runaway agent chain burns tokens until `max_loops` is exhausted at every nesting level.

## Consequences

### For ai_agents module
- Add a `token_budget` configuration field to ai_agent entities (optional, default: unlimited for backwards compatibility).
- The agent runner must track cumulative input + output tokens across the entire request (all agents in the chain).
- When budget is exceeded: log a warning, return a graceful "budget exceeded" response, halt further execution.
- Expose the budget tracker as a service so custom modules can query remaining budget.

### For agent configuration
- Nested agents should have their own budgets that count against the parent's budget.
- `tool_usage_limits` (already supported) should be used to restrict nested agent invocations (e.g., SEO agent limited to 2 page_builder calls).

### For observability
- Per-request token summaries must be logged (total, per-agent breakdown).
- Budget utilization percentage should be trackable.

## Alternatives Considered

**"Just reduce max_loops"** — Addresses loop count but not per-loop cost. A 15-loop agent with 20K/loop still burns 300K tokens.

**"Use tool_usage_limits only"** — Limits specific tool invocations but doesn't cap total cost. An agent could burn its budget on non-tool LLM calls.

**"Client-side budget enforcement"** — Requires every consumer to implement their own tracking. Framework-level enforcement is more reliable and consistent.

## Evidence

Measured worst-case scenario: SEO agent (10 loops) × page_builder (30 loops) = 300 effective calls. At ~20K tokens/call, theoretical maximum: **6M tokens per request** ($72 at current Anthropic pricing). Actual measured page build: 253K tokens (well under theoretical max, but only because the agent converges early — no guarantee of convergence).
