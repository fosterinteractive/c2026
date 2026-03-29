# WS2: Branching Sub-Task Orchestration

**Revision: v2 — Revised based on proposal-critic feedback (2026-03-27)**

**Status:** Draft
**Created:** 2026-03-26
**Estimated Scope:** MEDIUM (down from EXTRA-LARGE -- scoped to concrete improvements, not speculative framework work)
**Dependencies:** WS1 implementation (Phase 3 only). Research and design (Phases 1-2) can proceed in parallel with WS1.
**Blocks:** WS4 (deployment recipes need to know the final agent architecture)

---

## Changes from v1

1. **Collapsed Phase 1 (research) into a "What We Already Know" section** — the research document (`research-ai-agents-module.md`) already answers every question the old Phase 1 proposed to investigate. The plan no longer proposes re-discovering known information.
2. **Split "branching" into 4 distinct sub-problems** — parallel execution, conditional routing, data passing, automatic triggers. Each gets its own assessment and solution based on existing research findings.
3. **Added honest feasibility verdicts** for each option using the research document's evidence.
4. **Documented the existing BPMN ModelOwner integration** — `Agent.php` in ai_agents is a config-UI integration, not a runtime engine. Option C (BPMN-based workflow) moved to "considered and rejected."
5. **Moved Option D (PHP Fibers) to "considered and rejected"** — the research confirms zero Fiber usage in ai_agents and the plan's own risk table rated it HIGH/HIGH.
6. **Added a user-facing problem statement** — what user-observable problem does this solve? If the answer is "none yet," the plan says so honestly and scopes accordingly.
7. **Added cost-benefit analysis** — is this worth the complexity vs. current LLM-driven orchestration?
8. **Unblocked Phase 1-2 from WS1** — research/design can proceed in parallel. Only implementation (Phase 3) needs WS1 efficiency gains in place.

---

## Problem Statement

### User-Facing Problem

Currently, there is no documented user-observable failure caused by the lack of branching orchestration. The orchestrator prompt's 24 examples (8 rules) already handle conditional routing, mutual exclusivity, and multi-tool delegation. Pages build correctly. The LLM makes reasonable routing decisions.

The problems are operational, not functional:

1. **Cost:** Sequential execution of parallel-requested tools means total latency (and cost) is the SUM of all sub-agent latencies, not the MAX. A page build that requests title + metadata + template_builder waits for all three sequentially.
2. **SEO nesting waste:** The SEO agent can invoke the page builder for internal linking when it only needed schema generation. This is addressed in WS1 Step 4, but a cleaner solution would be framework-level conditional routing rather than prompt guardrails.
3. **Brittleness:** All orchestration logic lives in the LLM prompt. If the LLM makes a wrong routing decision (e.g., calling SEO agent when only title generation was needed), there is no framework-level safety net.

### Honest Assessment

Given that WS1 addresses the cost and nesting problems directly (token reduction + SEO tool_usage_limits), the incremental value of WS2 is:
- **Latency improvement from true parallel execution:** Potentially significant (3x speedup for parallel tool calls), but requires framework changes that may not be accepted upstream.
- **Automatic triggers:** Modest value -- automates "after page build, run SEO" which the orchestrator already does via prompt instructions.
- **Conditional routing at the framework level:** Low incremental value over the current LLM-driven approach, which works correctly.

**Recommendation:** Scope WS2 to the highest-value, lowest-risk improvements. Defer speculative framework changes to a future workstream when concrete failure cases are documented.

## What We Already Know (from research-ai-agents-module.md)

The research document, completed 2026-03-26, provides definitive answers to all framework capability questions. Key findings:

### Execution Model
- Tool execution is sequential: `AiAgentEntityWrapper.php` iterates through `$this->contextTools` in a `foreach` loop. No PHP concurrency.
- Sub-agent calls are synchronous and blocking: `AiAgentWrapper::execute()` creates a new `Task`, calls `determineSolvability()` then `solve()`. The parent waits.
- `max_loops` is per-agent, not aggregate. Nesting creates multiplicative worst cases.

### Extension Points
- **`BuildSystemPromptEvent`:** Can modify the system prompt before each LLM call. Used by ai_context for context injection. Can inject conditional instructions based on runtime state.
- **`AgentToolFinishedExecutionEvent`:** Fires after tool execution but is observe-only. Does NOT provide tool output, parent chat history, or ability to inject new tool calls. Cannot modify execution flow.
- **`AgentResponseEvent`:** Fires after LLM response. Can be used for logging and monitoring. Cannot modify the response or inject tools.
- **Artifact system (`InMemoryArtifactStorage`):** Request-scoped. Artifacts survive across loop iterations within a single agent but are per-agent-instance. `use_artifacts: 0` on all current tools. Artifacts are opt-in and require tool-level configuration.

### Existing BPMN Integration (discovered by critic, missed in v1)
`web/modules/contrib/ai_agents/src/Plugin/ModelerApiModelOwner/Agent.php` is a `ModelerApiModelOwner` plugin that bridges ai_agents to `modeler_api`/`bpmn_io`. It maps agents to BPMN start events, sub-agents to subprocesses, and tools to tasks. This is a **configuration and visualization layer** -- it renders agent hierarchies as BPMN diagrams for the config UI. It does NOT provide a runtime execution engine. BPMN gateways, conditional routing, and parallel execution are not implemented at the runtime level.

### What the Framework Cannot Do (without patching)
- Execute tools in parallel (no Fibers, no async in tool execution path)
- Inject tool calls into a running agent loop from an event subscriber
- Share state between sub-agents (each gets a fresh Task)
- Create branching/conditional execution paths at the framework level
- Set aggregate token budgets across agent chains (addressed by WS1 Step 8)

## The Four Sub-Problems

### Sub-Problem 1: Parallel Execution

**Definition:** Running independent sub-agents simultaneously so total latency is max(agent_times) instead of sum(agent_times).

**Current behavior:** The LLM requests 3 tools (title + metadata + template_builder), but the framework executes them sequentially. Total time: T_title + T_metadata + T_template.

**Feasibility verdict: NOT FEASIBLE without upstream framework changes.**

True parallel execution requires modifying `AiAgentEntityWrapper::determineSolvability()` to use PHP Fibers or async patterns for independent tool calls. The ai_agents module has zero concurrency code. The AI provider layer has `ChatFiberSupport` for provider-level parallelism, but this is not surfaced to tool execution.

**Recommendation:** Defer. File an upstream feature request with the ai_agents maintainer. Document the performance impact (measured wall-clock times from WS1 Phase 0 baseline) as evidence for the request. This is a multi-week framework change that should be contributed upstream, not maintained as a local patch.

### Sub-Problem 2: Conditional Routing

**Definition:** Framework-level "if X then agent A, else agent B" decisions, replacing LLM-prompt-based routing.

**Current behavior:** The orchestrator prompt's Rules 1-8 implement conditional routing via LLM intelligence. Rule 1: "If entity type is not canvas_page, respond with error." Rule 3: "page_builder and template_builder are mutually exclusive." Rule 5: "If title/description are empty, proactively call the respective agents."

**Feasibility verdict: ALREADY SOLVED by the current LLM-driven approach.**

The orchestrator's prompt-based routing works correctly. The LLM consistently follows the 8 rules and 24 examples. No failure cases have been documented where the LLM made a wrong routing decision.

Framework-level conditional routing (e.g., a PHP router that inspects page state and dispatches agents) would add complexity without measurable benefit. The LLM's routing is more flexible -- it can handle novel scenarios not covered by explicit rules.

**Recommendation:** No action needed. The current approach works. If specific mis-routing failures are documented in the future, reassess.

### Sub-Problem 3: Data Passing Between Agents

**Definition:** One agent's output feeds into another agent's input without flowing through the orchestrator's LLM for reinterpretation.

**Current behavior:** All sub-agent outputs flow back to the orchestrator. The orchestrator's LLM processes them and decides what to pass to the next agent. This works but costs orchestrator tokens for interpretation.

**Feasibility verdict: PARTIALLY FEASIBLE using the artifact system.**

The `InMemoryArtifactStorage` is request-scoped and survives across agent loop iterations. If `use_artifacts: 1` is enabled on relevant tools, tool outputs are stored as artifacts and can be referenced by subsequent tools via `ArtifactHelper::replaceArtifactArguments()`. However:
- Artifacts are keyed by tool output name, not by agent ID
- All current tools have `use_artifacts: 0`
- The system is designed for passing structured data between tools within a single agent, not between separate agent invocations
- Enabling artifacts requires per-tool configuration changes and prompt updates to reference artifact keys

**Recommendation:** Investigate enabling `use_artifacts: 1` for the page builder's output tools (`set_component_structure`, `update_component_data`) so the SEO agent can reference page content without re-querying. This is a targeted improvement, not a general data-passing solution. Estimated effort: 1-2 days of config changes + testing.

### Sub-Problem 4: Automatic Triggers

**Definition:** "When agent A finishes, automatically invoke agent B" without the orchestrator's LLM making the decision.

**Current behavior:** The orchestrator's LLM decides what to do after each sub-agent completes. This works but costs a full orchestrator LLM call per decision.

**Feasibility verdict: PARTIALLY FEASIBLE using `AgentToolFinishedExecutionEvent`, with significant limitations.**

An event subscriber can detect when a tool (sub-agent) finishes, but:
- The event does NOT provide the tool's output (cannot pass results to the triggered agent)
- The subscriber cannot inject a tool call into the parent's execution loop
- The subscriber CAN start a completely independent agent execution as a side effect, but the result would not flow back to the orchestrator

A viable pattern: subscribe to `AgentToolFinishedExecutionEvent`, detect when `canvas_page_builder_agent` finishes, trigger `drupal_canvas_seo_agent` as a fire-and-forget side effect for schema generation (Mode A only, which does not need to return results to the orchestrator). This would automate SEO schema generation without orchestrator involvement.

**Limitation:** The triggered agent runs outside the orchestrator's context. It cannot report results back, cannot ask clarifying questions, and the orchestrator does not know it ran. This is acceptable for idempotent operations like schema generation but not for operations that require coordination.

**Recommendation:** Implement a targeted automatic trigger for SEO schema generation after page build completion. This is the highest-value concrete improvement in WS2. Estimated effort: 2-3 days.

## Proposed Approach (Revised)

### Phase 1: Design Targeted Improvements

**Step 1: Design the automatic SEO trigger**

Based on Sub-Problem 4 analysis, design an event subscriber that:
1. Subscribes to `AgentToolFinishedExecutionEvent`
2. Detects when the orchestrator's `canvas_page_builder_agent` or `canvas_template_builder_agent` tool finishes
3. Checks whether the page already has schema.org JSON-LD (idempotency check)
4. If no schema exists, triggers `drupal_canvas_seo_agent` in Mode A (schema-only) as a fire-and-forget operation
5. The SEO agent runs independently, generates schema, and saves it via `add_schema_org_json`

Key design decisions:
- The trigger should NOT fire when the SEO agent itself invokes the page builder (prevent recursion). Use `callerAgentRunnerId` to detect nesting.
- The trigger should be configurable (enabled/disabled via settings)
- The trigger should log its activity for debugging

**Acceptance criteria:** Design document at `docs/research/ws2-seo-trigger-design.md`. Covers: event subscriber architecture, idempotency check, recursion prevention, configuration, logging.

**Step 2: Evaluate artifact-based data passing for SEO**

Test whether enabling `use_artifacts: 1` on the page builder's output tools allows the SEO agent to reference page content via artifact keys instead of re-querying with `get_component_content`.

1. Enable `use_artifacts: 1` on `set_component_structure` and `update_component_data` in the orchestrator's `tool_settings`
2. Verify artifacts are populated after page builder execution
3. Test whether the SEO agent can access these artifacts when invoked by the orchestrator in the same request
4. Measure token savings: artifact reference vs. `get_component_content` tool call

**Acceptance criteria:** Document whether artifact-based data passing works for the SEO use case. If yes, quantify token savings. If no, document why and close this option.

### Phase 2: Implementation (after WS1 efficiency gains are in place)

**Step 3: Implement automatic SEO schema trigger**

Build the event subscriber module (can be part of `canvas_ai_efficiency` from WS1 Step 8 or a new `canvas_ai_orchestration` module):

- `src/EventSubscriber/SeoSchemaTriggerSubscriber.php`
- Subscribe to `AgentToolFinishedExecutionEvent`
- Implement recursion check: skip if `callerAgentRunnerId` indicates we are inside an SEO agent invocation
- Implement idempotency check: query for existing schema.org data on the canvas page
- Trigger SEO agent in Mode A with a constrained prompt: "Generate Schema.org JSON-LD for this page. Use Mode A only. Do not invoke the page builder."
- Use `overrideFunctions()` to remove `canvas_page_builder_agent` from the triggered SEO agent's available tools (prevents nesting entirely)
- Log trigger events for observability

**Acceptance criteria:** After a page build completes, schema.org JSON-LD is automatically generated without orchestrator involvement. No recursion. Idempotent (running twice does not duplicate schema). Token cost of the automatic trigger measured and documented.

**Step 4: Implement artifact-based data passing (conditional on Step 2 results)**

If Step 2 shows artifact-based data passing is viable:
1. Enable `use_artifacts: 1` on relevant tool settings
2. Update SEO agent prompt to reference artifact data instead of re-querying
3. Measure token savings

If Step 2 shows it is not viable: skip this step, document findings.

**Acceptance criteria:** If implemented: SEO agent uses artifact data from page builder. Token savings measured and documented. If skipped: decision documented with evidence.

### Phase 3: Upstream Contribution

**Step 5: File upstream feature requests**

Based on WS1 and WS2 findings, file concrete issues with the ai_agents module:

1. **Parallel tool execution:** Request Fiber-based parallel execution for independent tools. Include wall-clock timing data from WS1 measurements showing the sequential execution cost.
2. **Aggregate token tracking:** Request a built-in token budget mechanism. Reference the custom subscriber from WS1 Step 8 as a proof of concept.
3. **Loop-aware context injection:** Request that `BuildSystemPromptEvent` include loop iteration context so subscribers can optimize for subsequent loops.

**Acceptance criteria:** Issues filed on drupal.org with evidence from WS1/WS2 measurements. Each issue includes a concrete use case, measured impact, and proposed solution approach.

## Considered and Rejected

### Option C: BPMN-Based Runtime Workflow Engine
The existing `Agent.php` `ModelerApiModelOwner` plugin maps agents to BPMN diagrams for configuration and visualization. It does NOT provide a runtime execution engine. Building a BPMN-driven execution engine that reads the graph at runtime and dispatches agent calls accordingly would be a multi-month project. The value proposition (visual workflow editor) does not justify the cost when the current LLM-driven orchestration works correctly and the improvements in this plan address the concrete operational issues.

### Option D: PHP Fibers for Parallel Tool Execution
The ai_agents module has zero Fiber usage. The AI provider layer has `ChatFiberSupport` but this is for provider-level parallelism, not tool execution. Implementing Fiber-based tool execution would require significant changes to `AiAgentEntityWrapper::determineSolvability()` -- the core execution loop. This is an upstream framework change, not a local patch. Filed as an upstream feature request in Step 5.

### "Do Nothing" Option
Seriously considered. The current LLM-driven orchestration works correctly. WS1 addresses the most expensive cost issues (token waste, SEO nesting). The incremental value of WS2 is the automatic SEO trigger (saves orchestrator tokens + reduces latency for schema generation) and potential artifact-based data passing. If these prove too complex, "do nothing beyond WS1" is an acceptable outcome. The plan is scoped so that each step delivers independent value and can be stopped at any point.

## Cross-References

- **WS1 (Efficiency):** WS1 Step 4 (SEO nesting mitigation via `tool_usage_limits`) is a prerequisite for WS2's automatic SEO trigger. WS1's token budget enforcement (Step 8) applies to the triggered SEO agent execution. WS1 measurements provide the evidence base for upstream feature requests.
- **WS3 (Markdown Config):** If WS2 modifies the SEO agent's prompt for the automatic trigger, the modified prompt should be the version migrated to markdown in WS3.
- **WS4 (Deploy):** If WS2 produces a custom module, it must be included in WS4's deployment recipes.

## Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Automatic SEO trigger causes recursion | LOW | HIGH | Explicit recursion check via `callerAgentRunnerId`. `overrideFunctions()` removes page builder from triggered SEO agent's tools. |
| Artifact system does not work across agent boundaries | MEDIUM | LOW | Step 2 evaluates this before committing to implementation. Fallback is to skip artifact-based data passing. |
| Automatic trigger fires at wrong time (mid-build) | MEDIUM | MEDIUM | Only trigger after the page builder tool FINISHES (not starts). Check for orchestrator context to ensure the build is complete. |
| Upstream feature requests are rejected | MEDIUM | LOW | The requests are filed for community benefit. Local improvements (automatic trigger, artifact usage) deliver value regardless. |

## Success Criteria

1. Automatic SEO schema generation trigger implemented and working (saves orchestrator tokens + latency for schema generation)
2. Artifact-based data passing evaluated with documented results (implemented if viable)
3. Upstream feature requests filed with evidence from WS1/WS2 measurements
4. No modifications to `web/modules/contrib/ai_agents/` core code
5. Token cost of automatic trigger measured and documented (target: cheaper than orchestrator-mediated SEO invocation)
6. All improvements deliver independent value -- no step depends on another step succeeding
