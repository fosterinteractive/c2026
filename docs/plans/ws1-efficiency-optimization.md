# WS1: Agent Efficiency Optimization

**Revision: v2 — Revised based on proposal-critic feedback (2026-03-27)**

**Status:** Draft
**Created:** 2026-03-26
**Estimated Scope:** LARGE (12 agent configs, multiple context items, measurement infrastructure, 1 small PHP module)
**Dependencies:** None (this is the foundation workstream)
**Blocks:** WS2 (branching orchestration), WS4 (stable release + deploy)

---

## Changes from v1

1. **Removed `return_directly: 1` from Step 3** — the critic proved this would silently drop parallel tools. When any `return_directly` tool in a batch finishes, the orchestrator returns `JOB_SOLVABLE` and stops processing remaining tools (including the page builder). Replaced with a terse-prompt approach that reduces orchestrator interpretation overhead without breaking parallel execution.
2. **Added Phase 0: Measurement Baseline** — moved measurement BEFORE any optimization. `ai_observability` is enabled on the running site but NOT in the recipe. Phase 0 adds it to the recipe and captures baseline token counts.
3. **Added Step 4: SEO agent nesting mitigation** — the critic's top concern. The SEO agent has `canvas_page_builder_agent` as a tool, creating a 10x30=300 worst-case loop explosion. Mode A (schema generation) never needs the page builder. Added `tool_usage_limits` and prompt guardrails.
4. **Added Step 8: Token budget enforcement** — a lightweight PHP event subscriber that tracks cumulative tokens per request and halts execution when a budget is exceeded. This is the only PHP in the plan.
5. **Fixed Step 2 acceptance criteria** — competitor names also appear in Key Facts document (lines 199-202), not just the Sales Training Deck. Updated criteria to be honest about what Step 2 does and does not fix.
6. **Added Step 6: ai_context per-loop overhead** — `BuildSystemPromptEvent` fires every loop, injecting 10-12K tokens of context items each time for the builder agents. The `available_on_loop` principle should apply to context injection too.
7. **Updated Success Criterion #4** — was "no PHP code modified." Now acknowledges the token budget subscriber requires a small custom module.

---

## Problem Statement

The Canvas AI agent chain burns ~150-170K Anthropic tokens per page build. At current pricing this makes demos expensive and production deployment unsustainable. The root causes are structural: redundant context injection on every loop iteration, verbose system prompts with duplicative examples, an SEO-to-page-builder nesting path that can explode to 300 effective loops, and no token budget enforcement mechanism anywhere in the chain.

## Current State

### Token Cost Breakdown (per full page build)

| Source | Estimated Tokens | File(s) |
|--------|-----------------|---------|
| Orchestrator system prompt (24 examples) | ~4,500 | `custom_recipes/findrop/config/ai_agents.ai_agent.canvas_ai_orchestrator.yml` |
| Page builder system prompt + dynamic context | ~3,200 + layout JSON + component catalog per loop | `ai_agents.ai_agent.canvas_page_builder_agent.yml` |
| Template builder system prompt | ~2,000 | `ai_agents.ai_agent.canvas_template_builder_agent.yml` |
| Page builder `default_information_tools` (reloaded every loop) | ~2,000-5,000 per loop x 30 max loops | Page builder config, lines 281-291 |
| Template builder `default_information_tools` (reloaded every loop) | ~2,000-5,000 per loop x 10 max loops | Template builder config, lines 150-160 |
| Context items for template/page builders (8 items each) | ~10,000-12,000 per loop via BuildSystemPromptEvent | `custom_recipes/ai_context_setup/recipe.yml`, lines 14-47 |
| Sales Training Deck (always_include) | ~2,500 | `ai_context_data/sales-pitch-deck-travel-only.md` (247 lines) |
| SEO agent system prompt | ~3,000 | `ai_agents.ai_agent.drupal_canvas_seo_agent.yml` |
| SEO -> page builder nesting (worst case) | 10 x 30 = 300 loops | SEO agent config, line 193: page_builder as tool |
| Title/metadata agents (minimal prompts) | ~550 combined | Title agent: ~100 tokens post-context fix; Metadata: ~500 tokens |
| Orchestrator interpretation overhead (return_directly: 0) | ~500-1,000 per sub-agent response | All 6 sub-agent tool_settings in orchestrator config |

### Key Inefficiencies Identified

1. **`default_information_tools` reload every loop:** Both `canvas_page_builder_agent` and `canvas_template_builder_agent` define `current_layout` and `available_components` as default_information_tools. Per `AiAgentEntityWrapper.php:890-936`, these tools execute on EVERY loop iteration and their output is injected into the system prompt (not chat history, because neither builder uses `available_on_loop`). With page_builder at max_loops:30, this is catastrophic.

2. **ai_context items re-injected every loop:** `BuildSystemPromptEvent` fires on every loop iteration (`AiAgentEntityWrapper.php:455-458`), and `SystemPromptSubscriber::onPreSystemPrompt()` appends context items to the system prompt every time. For the page builder with 8 context items (~10-12K tokens), these are included in the system prompt of every LLM call across all loops. This compounds with `default_information_tools` to make each loop iteration carry 14-17K tokens of repeated context.

3. **Sales Training Deck in always_include:** The 2,500-token sales deck is in `always_include` for both builders. It contains competitor names that the Brand Guidelines explicitly prohibit in external content. This is both a token waste and a hallucination risk.

4. **SEO agent nesting is the single largest token multiplier:** `drupal_canvas_seo_agent` has `canvas_page_builder_agent` as a tool (config line 193). Worst case: 10 SEO loops x 30 page builder loops = 300 effective LLM calls. Mode A (schema generation) never needs the page builder -- it only needs `get_component_content` and `add_schema_org_json`. Only Mode B (internal linking) needs page builder access.

5. **24 worked examples in orchestrator prompt:** Examples 1-24 cover many overlapping patterns. Several could be consolidated without losing routing coverage.

6. **No token budget enforcement:** `max_loops` limits iterations but not token consumption per iteration. A single loop can consume vastly different token counts depending on context size and response length. There is no mechanism to halt execution when cumulative cost exceeds a budget.

## Proposed Approach

### Phase 0: Measurement Baseline

**Step 0: Install ai_observability and capture baseline**

`ai_observability` is enabled on the running DDEV site (done during the session) but is NOT in the findrop recipe. This step makes it persistent and captures pre-optimization measurements.

1. Add `ai_observability` to the findrop recipe's module install list
2. Export `ai_observability.settings.yml` with `log_input: true` and `log_output: true`
3. Apply recipe, verify token counts appear in Drupal logs
4. Build the "FinDrop Travel" product page 5 times (same prompt: "Create a product page for FinDrop Travel, a corporate travel management platform")
5. Record total tokens per build (input + output) from observability logs
6. Record per-agent token breakdowns (orchestrator, page_builder, template_builder, title, metadata, SEO)
7. Document baseline in `docs/plans/ws1-baseline-measurements.md`

**Acceptance criteria:** `ai_observability` is in the recipe and configured. 5 baseline builds recorded with per-agent token breakdowns. Baseline document exists with mean, min, max token counts.

### Phase 1: Quick Wins (YAML-only changes, no PHP)

**Step 1: Trim orchestrator examples**

Consolidate the 24 examples down to 10-12 by removing duplicative patterns:
- Merge Examples 2, 11, 14, 16 (all "page construction + empty title/description" variations) into 2 representative examples
- Merge Examples 12, 15, 17 (all "title/description already exist" variations) into 1 example
- Keep Examples 1, 3, 4, 5, 6, 7, 8, 10, 20, 22, 24 as they cover unique scenarios
- Remove Example 9 (generic "What can you do?" -- the agent can figure this out without an example)

**Acceptance criteria:** Orchestrator system prompt reduced from ~4,500 tokens to ~2,800-3,000 tokens. All unique routing scenarios still covered. Verify with a manual token count of the trimmed YAML.

**Step 2: Remove Sales Training Deck from always_include**

Remove `'FinDrop Travel -- Sales Training Deck'` from `always_include` for both `canvas_template_builder_agent` and `canvas_page_builder_agent` in `custom_recipes/ai_context_setup/recipe.yml`. Add it to `excluded_subcontext` for both agents (it is a sub-context of Brand Guidelines).

The deck is already in `excluded_subcontext` for the orchestrator (line 58), title agent (line 74), and metadata agent (line 87). This change makes the builders consistent.

**Acceptance criteria:** Sales deck no longer injected into builder agents. Saves ~2,500 tokens per agent invocation. Sales Training Deck competitor narratives (Rimp, Brix, SAQ Concur battle cards) removed from builder context. NOTE: Competitor names STILL persist in the Key Facts document (lines 199-202, "Competitive Comparison Facts" table), which is in `always_include` for both builders, title, metadata, and SEO agents. This is tracked as a separate cleanup item -- either (a) split the competitive comparison section into its own context item that can be independently excluded, or (b) create a filtered version of Key Facts without the comparison table. Verify by checking `ai_context.agents` config after recipe apply.

**Step 3: Make title/metadata agent responses cheaper to process (replaces v1 `return_directly` approach)**

v1 proposed `return_directly: 1` for title and metadata agents. This would break page builds: when any `return_directly` tool in a batch finishes, `AiAgentEntityWrapper.php:496-499` returns `JOB_SOLVABLE` immediately, killing all remaining tools in the batch (including the page builder). Since the orchestrator calls title + metadata + page builder in parallel (Examples 2, 11, 14, 16, 22), this would silently drop the page build.

Instead, reduce orchestrator interpretation overhead by making title/metadata responses terse enough that the orchestrator's processing pass is cheap:

- **Title agent prompt:** Add "Return only the title text. No explanation, no alternatives, no formatting."
- **Metadata agent prompt:** Add "Return only: Description: {value}. No explanation."
- **Orchestrator prompt:** Add to the existing title/metadata handling rules: "Title and metadata agent responses are final. Do not rewrite, summarize, or comment on their output. Proceed immediately to the next task."

This saves orchestrator output tokens (it no longer generates a paragraph interpreting each sub-agent's response) without breaking parallel execution.

**Acceptance criteria:** Orchestrator's interpretation of title/metadata responses is <50 tokens each (down from ~200-500). Parallel tool execution still works -- page builder, title, and metadata all execute when called together. Verify with a page build that triggers all three.

**Step 4: Mitigate SEO agent nesting**

The SEO agent (`drupal_canvas_seo_agent`) has `canvas_page_builder_agent` as a tool. This creates a worst-case 10x30=300 loop explosion. Analysis of the SEO agent's prompt shows:

- **Mode A (Schema.org generation):** Only needs `get_component_content` and `add_schema_org_json`. Never needs the page builder.
- **Mode B (Internal linking):** Needs `get_linkable_components` and then the page builder to insert links.
- **Mode C (SEO analysis):** Needs `get_component_content` only.

Mitigations (apply all three):

1. **Add `tool_usage_limits` for page builder within SEO agent:** Cap `canvas_page_builder_agent` invocations to 2 per SEO agent execution. This limits the worst case from 10x30=300 to 2x15=30 effective loops (combined with Step 5's max_loops reduction).

2. **Add prompt guardrail to SEO agent:** Add to the system prompt: "IMPORTANT: Only invoke canvas_page_builder_agent for Mode B (internal linking) operations. For Mode A (schema generation) and Mode C (SEO analysis), use get_component_content and add_schema_org_json directly. Never call the page builder for schema-only tasks."

3. **Reduce SEO agent max_loops:** 10 -> 5 (schema generation typically completes in 2-3 loops).

**Acceptance criteria:** SEO agent's worst-case page builder invocations capped at 2. Worst-case effective loops reduced from 300 to 30. Schema-only operations (Mode A) complete without invoking the page builder. Verify by running SEO schema generation and checking observability logs for page builder invocations.

**Step 5: Reduce max_loops**

- `canvas_page_builder_agent`: 30 -> 15 (still generous for complex pages)
- `canvas_template_builder_agent`: 10 -> 8
- `drupal_canvas_seo_agent`: 10 -> 5 (addressed in Step 4, listed here for completeness)

**Acceptance criteria:** max_loops values reduced in agent configs. Worst-case token burn cut roughly in half. Test by building a complex page (5+ sections with images) to verify pages still build successfully within reduced loop budgets.

### Phase 2: Context Optimization (requires testing)

**Step 6: Use `available_on_loop` for default_information_tools**

Modify both `canvas_page_builder_agent` and `canvas_template_builder_agent` `default_information_tools` YAML to add `available_on_loop: [1]` to both `current_layout` and `available_components`. Per the framework code (`AiAgentEntityWrapper.php:910-926`), this causes the tool output to be added to chat history on loop 1 only, instead of being re-injected into the system prompt every loop.

```yaml
default_information_tools: |-
  current_layout:
    label: 'Current layout'
    description: 'The current layout of the page is:'
    tool: 'canvas_ai:get_current_layout'
    parameters: {  }
    available_on_loop: [1]
  available_components:
    label: 'Available components'
    description: 'These are the Components available to use'
    tool: 'canvas_ai:get_component_context'
    parameters: {  }
    available_on_loop: [1]
```

**Risk:** The agent may lose awareness of layout changes it made in earlier loops. Mitigation: the `get_component_content` tool is still available for on-demand checks. Also, `get_current_layout` can be called explicitly by the agent if needed -- if testing shows the agent needs layout refresh, add `canvas_ai:get_current_layout` as an available tool in the agent's `tools` config.

**Detection strategy for regressions:** Do NOT rely only on "did the build complete." Review the actual page output for quality: correct section count, images placed correctly, component props populated. The agent will not error when missing layout context -- it will silently produce worse output. Compare against baseline builds from Phase 0.

**Acceptance criteria:** Layout JSON and component catalog loaded once (loop 1) instead of every loop. Estimated savings: 2,000-5,000 tokens x (N-1) loops. Verify via ai_observability comparing token counts before/after on a standard page build. Also verify page quality has not degraded by visual comparison to baseline builds.

**Step 7: Address ai_context per-loop injection overhead**

`BuildSystemPromptEvent` fires every loop iteration. The ai_context `SystemPromptSubscriber` appends context items to the system prompt on every fire. For the page builder with 8 context items (~10-12K tokens), this means 10-12K tokens of context are in EVERY LLM call's system prompt across all loops.

The `available_on_loop` pattern from Step 6 applies to `default_information_tools` but not to ai_context injection. Two options:

**Option A (preferred): Modify ai_context injection to be loop-aware.**
Create a small event subscriber in the `canvas_ai_prompts` module (or a new `canvas_ai_efficiency` module) that:
1. Subscribes to `BuildSystemPromptEvent` at a priority HIGHER than ai_context (runs first)
2. Checks the agent's current loop iteration (available via the agent wrapper)
3. On loop > 1, sets a flag or modifies the event to signal ai_context should skip injection
4. Alternatively: on loop > 1, the subscriber stores the context in chat history instead of the system prompt

**Option B (simpler): Accept the overhead, document for upstream contribution.**
File an issue with the ai_context module requesting loop-aware injection. Document the per-loop token cost in the measurement results. Defer to an upstream fix.

**Acceptance criteria:** If Option A: ai_context items injected into system prompt only on loop 1, moved to chat history on subsequent loops. Estimated savings: 10-12K tokens x (N-1) loops for builder agents. If Option B: Issue filed, overhead documented, accepted as known limitation.

### Phase 3: Token Budget Enforcement

**Step 8: Build a token budget enforcement subscriber**

Create a lightweight custom module (`canvas_ai_efficiency` or add to an existing custom module) with an event subscriber that:

1. Subscribes to `AgentResponseEvent` (fires after every LLM call, includes the provider response with token counts)
2. Tracks cumulative input + output tokens per HTTP request using a request-scoped service
3. Compares against a configurable budget (default: 200K tokens per request, configurable via settings)
4. When budget is exceeded: logs a warning, sets the agent's response to a "Budget exceeded" message, and returns. This prevents runaway token burn without crashing the request.
5. Optionally: subscribes to `AgentStartedExecutionEvent` to track per-agent breakdowns

The module should expose:
- A settings form for the token budget threshold
- A drush command to view the last N request token summaries
- Integration with ai_observability logging

**Acceptance criteria:** Token budget enforcement active. A test that exceeds the budget (e.g., setting budget to 1K tokens) triggers the halt mechanism. Budget threshold configurable. Token summaries logged per request.

### Phase 4: Measurement and Verification

**Step 9: Post-optimization measurement**

Using the same measurement protocol as Phase 0:
1. Apply all Phase 1-3 changes
2. Build the same "FinDrop Travel" product page 5 times with the same prompt
3. Record total tokens per build and per-agent breakdowns
4. Compare against Phase 0 baseline
5. Document results in `docs/plans/ws1-efficiency-results.md`

Include per-step attribution where possible:
- Phase 1 savings (prompt trimming, max_loops, SEO nesting)
- Phase 2 savings (available_on_loop, context optimization)
- Phase 3 contribution (budget enforcement -- measured as "how many tokens were prevented by the budget cap, if any")

**Acceptance criteria:** Before/after token measurements documented with per-agent breakdowns. Target: 40-50% reduction from baseline (150-170K down to 85-100K per page build). If target not met, identify remaining high-cost paths and document follow-up items.

## Cross-References

- **WS2 (Branching Orchestration):** Efficiency gains here reduce the cost of exploring branching patterns in WS2. The SEO nesting analysis (Step 4) directly informs WS2's assessment of which delegation patterns are problematic. WS2 research/design can proceed in parallel with WS1; only WS2 implementation needs WS1 done.
- **WS3 (Markdown Agent Config):** Prompt trimming in Steps 1 and 3 will be easier to maintain once prompts are in markdown files (WS3). Do the trimming now in YAML; WS3 will migrate the trimmed versions. The `canvas_ai_efficiency` module from Step 8 can coexist with WS3's prompt loader module.
- **WS4 (Stable Release + Deploy):** Token efficiency is a prerequisite for amazee.io deployment where LLM costs are metered. WS4 depends on WS1 achieving the target reduction. The `canvas_ai_efficiency` module must be included in WS4's deployment recipes.

## Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| `available_on_loop: [1]` breaks multi-loop builds | MEDIUM | HIGH | Test with complex page builds (5+ sections). Add `get_current_layout` as explicit tool if needed. Rollback is a single YAML change. Detect regressions via visual comparison, not just build completion. |
| Trimming orchestrator examples causes mis-routing | LOW | MEDIUM | Keep one example per unique routing pattern. Test all 6 tool routing paths. |
| SEO `tool_usage_limits` too restrictive for complex linking | LOW | MEDIUM | Cap is 2 page builder invocations -- sufficient for most linking scenarios. Monitor via observability. Increase if needed. |
| Reduced max_loops causes incomplete pages | MEDIUM | MEDIUM | Start with conservative reduction (30->15). Monitor via observability. Adjust up if needed. |
| Token budget enforcement halts legitimate long builds | LOW | MEDIUM | Default budget (200K) is above current baseline. Log warnings before hard halt. Make threshold configurable. |
| ai_context loop-aware injection breaks keyword selection | LOW | MEDIUM | If using Option A, verify context items are the same on loop 1 vs current behavior. Keyword selection happens on the prompt text, which is unchanged on loop 1. |

## Success Criteria

1. Token consumption per standard page build reduced by 40-50% (measured via ai_observability, with before/after data)
2. No regression in page build quality (complex page builds complete successfully with correct content)
3. Sales Training Deck competitor narratives removed from builder agent context (Key Facts competitor table tracked as separate cleanup)
4. SEO agent worst-case nesting reduced from 300 to 30 effective loops
5. Token budget enforcement active with configurable threshold
6. Measurement protocol documented with per-agent before/after data
7. Changes are YAML config + one small custom module (token budget subscriber) -- no modifications to contrib modules
