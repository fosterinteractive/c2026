# Handoff: Upstream Contribution Strategy for Canvas AI Efficiency

**Date:** 2026-03-27
**Author:** Alex / Claude (WS1 session 5)
**Branch:** `feat/ws1-efficiency-optimization`
**PR:** pending (strategy review first)

## What This Is

A complete plan for contributing 4 patches to 3 Drupal contrib modules (ai_agents, ai_context, canvas_ai) to fix structural token efficiency problems in the Canvas AI agent chain. A simple heading edit currently costs 111K LLM tokens due to architectural issues that can only be fully fixed upstream.

## Deliverables in This Branch

### New documents

| File | What | Status |
|------|------|--------|
| `docs/adrs/ADR-001` through `ADR-005` | Design principles (constitution) for all upstream work | Written, post-critic |
| `docs/adrs/README.md` | ADR index with principles-at-a-glance | Written |
| `docs/plans/upstream-contribution-strategy.md` | Full strategy with proposal specs, filing order, evidence, timeline | Written, post-critic revision |
| `~/.agent/diagrams/canvas-ai-efficiency-brief.html` | Visual brief for review (open in browser) | Generated |

### Existing documents (from prior sessions)

| File | What |
|------|------|
| `docs/proposals/canvas-ai-region-scoping.md` | Technical proposal for Foster Interactive (P1) |
| `docs/plans/ws1-efficiency-optimization.md` | WS1 workstream plan (v2, post-critic) |
| `docs/audit/canvas-agent-static-audit.md` | Static audit of all 12 AI agents |
| `.omc/plans/token-reduction-remaining-levers.md` | Revised efficiency plan |

### Working code (from prior sessions, uncommitted)

| Path | What | Status |
|------|------|--------|
| `web/modules/custom/canvas_ai_scoping/` | Layout scoping proof-of-concept module | Working (LayoutScopingSubscriber) |
| `canvas_ai_scoping/.../ContextScopingSubscriber.php` | Context stripping subscriber | Written but NOT FIRING (separator format bug) |
| Recipe config changes | max_loops, available_on_loop, orchestrator examples | Applied to recipe YAML, not to running site |

## The 5 Proposals (Filing Order)

1. **P4** — Lightweight Edit Path (canvas_ai) — deterministic edits bypass LLM
2. **P3a** — Loop Iteration in BuildSystemPromptEvent (ai_agents) — enables P2
3. **P1** — Native Region Scoping (canvas_ai) — scoped layout during edits
4. **P2** — Loop-Aware Context Injection (ai_context) — stop re-injecting context every loop
5. **P3b** — History Windowing (ai_agents) — cap orchestrator cross-turn history

## To Pick This Up

### Review the strategy
1. Open `~/.agent/diagrams/canvas-ai-efficiency-brief.html` in a browser for the visual overview
2. Read `docs/plans/upstream-contribution-strategy.md` for the full plan
3. Read `docs/adrs/` for the design principles

### Key decisions that need validation
- **Filing order:** P4 first (argues against LLM use) vs P1 first (lowest risk, proven). Strategy recommends P4 first for community positioning.
- **P2 framing:** Extends existing agent-aware selection (not replaces). Critic caught that original framing contradicted the codebase.
- **P4 scope:** Schema-driven classification of "simple edits" — need to survey Canvas component schemas to validate this is feasible.
- **Honest framing:** Strategy recommends being honest about AI context (tokens as evidence), not euphemistic. Critic validated this approach.

### Next steps — 3 parallel tracks (see ADR-008 for full schedule)

**Track A — PHP/Backend** (can start immediately)
1. Fix ContextScopingSubscriber separator format bug — enable `log_input: true`, check actual format
2. Build loop-aware context injection using existing `AgentStartedExecutionEvent::getLoopCount()` (note: returns 0 on first loop)
3. Instrument per-component token breakdown (debug subscriber)
4. Run 5x repeated measurements (heading edit + page build) — report mean + range
5. Investigate Sales Training Deck injection path (parent-child subcontext, add to `excluded_subcontext` for builders)
6. Clean up `\Drupal::logger()` debug calls

**Track B — TypeScript/Frontend** (can start immediately, independent of Track A)
1. Survey Canvas component catalog — map all props to types, estimate deterministic edit coverage
2. Build P4 pattern matcher prototype (edit/add disambiguation via keyword exclusion)
3. Build direct edit endpoint (`renderDirect()` + route + CSRF + permissions)

**Track C — Docs/Evidence** (can start immediately, no code dependencies)
1. Check ai_agents + ai_context drupal.org issue queues for existing efficiency discussions
2. Slop audit on `docs/proposals/canvas-ai-region-scoping.md` (per ADR-009)
3. Draft drupal.org issue descriptions for P4 and P1

Tracks A and B are fully independent. Track C feeds from A's measurement results once available.

## Critical Context

### Maintainer positioning (based on drupal.org issue queue research)
- **P4 is catch's sweet spot** — he advocates deterministic tooling over LLM approaches
- **P1 will get "makes sense"** if benchmarks are solid and there's an escape hatch
- **P2 needs reframing** — filtering belongs in subscriber, but must acknowledge existing agent-aware selection in `AiContextSelector::select()`
- **Never frame as "AI optimization"** — lead with architecture, use tokens as concrete evidence

### Critic findings (all addressed)
The proposal-critic found 2 critical + 5 major issues, all fixed in the current revision:
- C1: Evidence base was stale (already-applied changes presented as future work) → fixed with "Changes Already Applied" table
- C2: "70-85%" headline was unsubstantiated → replaced with scenario-specific math
- M1-M5: P2 framing, Sales Training Deck, P3a/P2 dependency, available_on_loop contradiction, framing strategy → all corrected

### What we measured

| Scenario | Tokens | Notes |
|----------|--------|-------|
| Page build (true original) | 253,593 | Pre-any-optimization |
| Page build (config changes) | 259,649 | Config alone doesn't help |
| Heading edit (section scoped) | 111,004 | Current state with layout scoping |

### What does NOT work
- Config-only changes (prompt trim, loop caps)
- `available_on_loop` (moves data, doesn't reduce it)
- `return_directly: 1` (breaks title/metadata generation)
- Workflow A collapsing (unsafe — can't distinguish edit from add)

## Environment
- Branch: `feat/ws1-efficiency-optimization`
- DDEV: running at c2026.ddev.site
- Anthropic key: set
- OpenAI key: NOT set (blocks embedding/indexing)
- ai_observability: enabled
- canvas_ai_scoping: enabled
