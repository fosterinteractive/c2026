# WS2: Branching Sub-Task Orchestration

**Status:** Draft
**Created:** 2026-03-26
**Estimated Scope:** EXTRA-LARGE (research-heavy, potentially requires contrib patches or a custom module)
**Dependencies:** WS1 (efficiency optimization must be complete first -- no point optimizing branching on an inefficient chain)
**Blocks:** WS4 (deployment recipes need to know the final agent architecture)

---

## Problem Statement

The Canvas AI orchestrator delegates to sub-agents sequentially via tool calls. While the orchestrator prompt shows examples of "parallel" calls (e.g., template_builder + title + metadata in Example 14), the actual execution is sequential -- the ai_agents framework processes tool calls one at a time. There is no conditional branching ("if FAQ content exists, generate FAQ schema"), no inter-agent coordination ("pass page builder results to SEO agent"), and no concept of reusable "skills" that compose into workflows.

## Current State

### How the Framework Processes Tool Calls

Based on analysis of the ai_agents module source code:

1. **`AiAgentEntityWrapper.php`** is the core agent runner. It manages the loop, processes tool calls, and handles `return_directly` / `default_information_tools`.

2. **Tool execution is sequential:** When the LLM returns multiple tool calls in a single response, the framework iterates through them one by one (`executeTool()` calls happen in a loop). There is no parallel execution.

3. **`AiAgentWrapper.php`** (the sub-agent-as-tool wrapper) creates a new `Task`, sets up the sub-agent, calls `determineSolvability()` then `solve()`. This is a blocking, synchronous call. The parent agent's loop waits for the sub-agent to complete before processing the next tool call.

4. **`return_directly`** (`AiAgentEntityWrapper.php:1010-1013`): When true, the sub-agent's output is returned directly to the caller without going back through the parent agent's LLM for interpretation. This is the closest thing to "fire and forget" -- but it still blocks the parent loop.

5. **`available_on_loop`** (`AiAgentEntityWrapper.php:910-926`): Controls when default_information_tools execute. This is loop-scoping, not branching, but it demonstrates the framework's awareness of iteration state.

6. **No DAG/pipeline primitives:** The framework has no concept of agent graphs, dependency chains, conditional routing, or parallel execution. Orchestration is entirely emergent from the LLM's tool-calling decisions.

7. **No shared state between sub-agents:** Each sub-agent gets a fresh `Task` object. There is no mechanism for one sub-agent to read another's output except through the parent orchestrator's chat history.

### What the Orchestrator Actually Does Today

Looking at `canvas_ai_orchestrator.yml`:

- The orchestrator prompt contains sophisticated routing logic (Rules 1-8, 24 examples)
- It delegates to 6 sub-agents via tool calls
- The LLM can request multiple tool calls in one response (e.g., title + metadata + template_builder)
- But the framework processes them sequentially, not in parallel
- Sub-agent responses flow back to the orchestrator, which decides what to do next
- This works but is slow (sequential) and expensive (orchestrator processes every response)

### Current Delegation Patterns

| Pattern | Current Behavior | Ideal Behavior |
|---------|-----------------|----------------|
| Template + Title + Metadata | LLM requests 3 tools, framework runs sequentially | True parallel execution |
| SEO -> Page Builder (link insertion) | SEO agent calls page builder as sub-agent (nested) | SEO produces instructions, page builder executes independently |
| Page build -> SEO schema | Sequential: build first, then schema | Automatic trigger when page build completes |
| Conditional schema type | SEO agent uses heuristics in its prompt | Framework-level conditional routing |

## Proposed Approach

### Phase 1: Research (must complete before any implementation)

**Step 1: Deep analysis of ai_agents module capabilities**

Perform a thorough code review of:
- `AiAgentEntityWrapper.php` -- full execution loop, how tool calls are dispatched
- `AgentHelper.php` -- any orchestration utilities
- `AiAgentBase.php` -- base class capabilities, state management
- The `Event` system -- `AgentStartedExecutionEvent`, `BuildSystemPromptEvent` -- can events enable coordination?
- `ArtifactInterface` / `InMemoryArtifactStorage` -- can artifacts pass data between agents?
- `StructuredResultData` -- can this carry structured output between agents?

Deliverable: A document at `docs/research/ws2-framework-capabilities.md` detailing exactly what the framework supports, what extension points exist, and what would require framework changes.

**Acceptance criteria:** Every relevant class and interface in `web/modules/contrib/ai_agents/src/` has been reviewed. Extension points (events, plugins, interfaces) are catalogued. The document answers: "Can we add branching without modifying the contrib module?"

**Step 2: Check upstream roadmap and community plans**

- Review the Drupal AI module issue queue for planned orchestration features
- Check if there are any contrib modules for agent pipelines/DAGs
- Look at the `bpmn_io` module (already installed in the recipe, line 62) -- can BPMN workflows coordinate agents?
- Check the `ai_agents` module's plugin architecture -- can custom agent types be added without patching?

Deliverable: Summary of upstream plans and available tools in `docs/research/ws2-upstream-analysis.md`.

**Acceptance criteria:** Issue queue reviewed. BPMN integration feasibility assessed. Plugin extensibility confirmed or denied.

### Phase 2: Design (after research)

**Step 3: Design branching patterns within framework constraints**

Based on research findings, design the branching approach. Likely options (to be validated by research):

**Option A: Enhanced Orchestrator Prompt (no code changes)**
- Restructure the orchestrator prompt to explicitly define conditional chains
- Use the orchestrator's LLM intelligence for routing decisions
- Add "pipeline" instructions: "After template_builder completes, immediately invoke seo_agent with the page content"
- Pro: Zero code changes. Con: Still sequential, still burns orchestrator tokens.

**Option B: Event-Driven Coordination (custom module)**
- Create a lightweight custom module (`canvas_ai_orchestration`)
- Subscribe to `AgentStartedExecutionEvent` / tool completion events
- Implement conditional triggers: "When page_builder finishes, auto-invoke seo_agent"
- Use the artifact system to pass data between agents
- Pro: True automation. Con: PHP code, harder to maintain.

**Option C: BPMN-Based Workflow (if bpmn_io supports it)**
- Define agent workflows as BPMN diagrams
- Use gateways for conditional branching
- Map BPMN tasks to agent invocations
- Pro: Visual workflow editor, industry standard. Con: May not integrate with ai_agents.

**Option D: Parallel Execution Patch (upstream contribution)**
- Modify `AiAgentEntityWrapper` to execute independent tool calls in parallel (using PHP Fibers or async)
- Contribute the patch upstream
- Pro: Solves the root cause. Con: Significant framework change, may not be accepted.

**Acceptance criteria:** One primary approach selected with rationale. Architecture document at `docs/research/ws2-architecture-decision.md`. Fallback approach identified.

### Phase 3: Implementation

**Step 4: Implement the selected approach**

Implementation details depend on the research/design outcome. At minimum, the following improvements can be made regardless of approach:

- Restructure orchestrator prompt to define explicit pipelines
- Add conditional SEO schema generation trigger
- Implement result-passing between SEO agent and page builder
- Add "skill" definitions that compose multiple agent operations

**Acceptance criteria:** At least one branching pattern works end-to-end (e.g., "page build -> automatic SEO schema generation"). Token cost of the branching pattern is measured and compared to the sequential baseline.

## Cross-References

- **WS1 (Efficiency):** WS1 must be complete first. The `return_directly` analysis in WS1 Step 3 directly informs which agents can run as independent branches. The `available_on_loop` mechanism discovered in WS1 Step 5 may provide a pattern for conditional execution.
- **WS3 (Markdown Config):** If agent prompts move to markdown (WS3), the orchestrator prompt restructuring in Step 3 Option A becomes easier to iterate on.
- **WS4 (Deploy):** WS4 needs to know the final agent architecture to build deployment recipes. If WS2 adds a custom module, WS4 must include it in the recipe.

## Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Framework provides no useful extension points | MEDIUM | HIGH | Fall back to Option A (prompt-only). Still achieves conditional logic via LLM intelligence. |
| BPMN integration is too complex for the timeline | HIGH | MEDIUM | BPMN is a nice-to-have. Options A and B are viable without it. |
| Parallel execution requires core framework changes | HIGH | HIGH | Don't pursue parallel execution in v1. Focus on smarter sequential routing (Option A/B). |
| Custom module creates maintenance burden | MEDIUM | MEDIUM | Keep the module minimal. Use events/subscribers, not framework patches. Target upstream contribution. |

## Success Criteria

1. At least one branching/conditional pattern implemented and working
2. Research documents complete with framework capability analysis
3. Architecture decision documented with rationale
4. No modifications to `web/modules/contrib/ai_agents/` core code (patches or custom module only)
5. Token cost of branching pattern measured and documented
