# WS1: Agent Efficiency Optimization

**Status:** Draft
**Created:** 2026-03-26
**Estimated Scope:** LARGE (12 agent configs, multiple context items, measurement infrastructure)
**Dependencies:** None (this is the foundation workstream)
**Blocks:** WS2 (branching orchestration), WS4 (stable release + deploy)

---

## Problem Statement

The Canvas AI agent chain burns ~150-170K Anthropic tokens per page build. At current pricing this makes demos expensive and production deployment unsustainable. The root causes are structural: redundant context injection on every loop iteration, verbose system prompts with duplicative examples, narrating sub-agents that waste tokens explaining their reasoning, and a `return_directly: 0` setting on all sub-agents that forces every response through the orchestrator for interpretation that adds no value.

## Current State

### Token Cost Breakdown (per full page build)

| Source | Estimated Tokens | File(s) |
|--------|-----------------|---------|
| Orchestrator system prompt (24 examples) | ~4,500 | `custom_recipes/findrop/config/ai_agents.ai_agent.canvas_ai_orchestrator.yml` |
| Page builder system prompt + dynamic context | ~3,200 + layout JSON + component catalog per loop | `ai_agents.ai_agent.canvas_page_builder_agent.yml` |
| Template builder system prompt | ~2,000 | `ai_agents.ai_agent.canvas_template_builder_agent.yml` |
| Page builder `default_information_tools` (reloaded every loop) | ~2,000-5,000 per loop x 30 max loops | Page builder config, lines 281-291 |
| Template builder `default_information_tools` (reloaded every loop) | ~2,000-5,000 per loop x 10 max loops | Template builder config, lines 150-160 |
| Context items for template/page builders (8 items each) | ~10,000-12,000 | `custom_recipes/ai_context_setup/recipe.yml`, lines 14-47 |
| Sales Training Deck (always_include) | ~2,500 | `ai_context_data/sales-pitch-deck-travel-only.md` (247 lines) |
| SEO agent system prompt | ~3,000 | `ai_agents.ai_agent.drupal_canvas_seo_agent.yml` |
| Title/metadata agents (minimal prompts) | ~550 combined | Title agent: 3-line prompt; Metadata: ~500 tokens |
| Orchestrator interpretation overhead (return_directly: 0) | ~500-1,000 per sub-agent response | All 6 sub-agent tool_settings in orchestrator config |

### Key Inefficiencies Identified

1. **`default_information_tools` reload every loop:** Both `canvas_page_builder_agent` and `canvas_template_builder_agent` define `current_layout` and `available_components` as default_information_tools. Per `AiAgentEntityWrapper.php:890-936`, these tools execute on EVERY loop iteration and their output is injected into either the system prompt (default) or chat history (`available_on_loop`). Neither builder uses `available_on_loop`, so the full layout JSON and component catalog are re-injected into the system prompt every single loop. With page_builder at max_loops:30, this is catastrophic.

2. **Sales Training Deck in always_include:** The 2,500-token sales deck (`sales-pitch-deck-travel-only.md`) is in `always_include` for both `canvas_template_builder_agent` and `canvas_page_builder_agent`. It contains competitor names (Rimp, Brix, SAQ Concur, Navex, Dill/Bivvy) that the Brand Guidelines explicitly prohibit in external content. This is both a token waste and a hallucination risk.

3. **All sub-agents return through orchestrator:** Every sub-agent has `return_directly: 0` in the orchestrator's `tool_settings`. Per `AiAgentEntityWrapper.php:1010-1013`, this means the orchestrator must process and "interpret" every sub-agent response. For title, metadata, and simple page builder operations, this interpretation adds no value but costs tokens.

4. **24 worked examples in orchestrator prompt:** Examples 1-24 cover many overlapping patterns (e.g., Examples 2, 11, 12, 14, 15, 16 all demonstrate the same title/description generation trigger logic). Several could be consolidated.

5. **Narrating sub-agents:** The SEO agent prompt (Mode A, step 5) instructs it to "Write a natural explanation" before outputting JSON-LD. The metadata agent generates "Generated description: {{ value }}" format responses. These narrative wrappers waste tokens when the orchestrator just needs the result.

6. **Unbounded retry in page builder:** The error handling section says "Retry the tool call immediately" with a 3-retry cap (line 217), but the system prompt also says "Continue until all succeed" which conflicts with the cap. Combined with max_loops:30, this creates runaway token burn on persistent errors.

## Proposed Approach

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

**Acceptance criteria:** Sales deck no longer injected into builder agents. Saves ~2,500 tokens per agent invocation. Competitor names no longer in builder context. Verify by checking `ai_context.agents` config after recipe apply.

**Step 3: Enable `return_directly: 1` for leaf sub-agents**

Change `return_directly` to `1` for these sub-agents in the orchestrator's `tool_settings`:
- `canvas_title_generation_agent` -- produces a title string, no interpretation needed
- `canvas_metadata_generation_agent` -- produces a description string, no interpretation needed

Do NOT change `return_directly` for:
- `canvas_page_builder_agent` -- orchestrator needs to check for questions/errors
- `canvas_template_builder_agent` -- same reason
- `canvas_component_agent` -- orchestrator may need to handle context switches
- `drupal_canvas_seo_agent` -- orchestrator prompt Rule #8 says to pass SEO response through

**Acceptance criteria:** Title and metadata agent responses bypass orchestrator loop. Saves 1-2 orchestrator loops per page build (~1,000-2,000 tokens). Verify that title/metadata still get saved correctly by testing a page build with empty title.

**Step 4: Reduce max_loops**

- `canvas_page_builder_agent`: 30 -> 15 (still generous for complex pages)
- `canvas_template_builder_agent`: 10 -> 8
- `drupal_canvas_seo_agent`: 10 -> 6

**Acceptance criteria:** max_loops values reduced in agent configs. Worst-case token burn cut roughly in half. Test with the driesnote demo script to verify pages still build successfully.

### Phase 2: Context Optimization (requires testing)

**Step 5: Use `available_on_loop` for default_information_tools**

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

**Risk:** The agent may lose awareness of layout changes it made in earlier loops. Mitigation: the `get_component_content` tool is still available for on-demand checks. Also, `get_current_layout` can be called explicitly by the agent via its tools if needed (it is not currently in the agent's tool list -- if testing shows the agent needs layout refresh, add `canvas_ai:get_current_layout` as an available tool).

**Acceptance criteria:** Layout JSON and component catalog loaded once (loop 1) instead of every loop. Estimated savings: 2,000-5,000 tokens x (N-1) loops. Verify by enabling `ai_observability` logging and comparing token counts before/after on a standard page build.

**Step 6: Tighten narration in sub-agent prompts**

- **Title agent:** Add concise instructions: "Generate a concise, SEO-friendly page title (50-60 characters). Do not explain your reasoning. Save immediately using the appropriate tool, then return only the title text."
- **Metadata agent:** Remove the emoji and verbose flow description. Replace with: "Generate a meta description (max 160 characters, SEO-friendly) based on page content. Save using ai_agent_add_metadata tool. Return only: 'Description: {value}'"
- **SEO agent Mode A step 5:** Change "Write a natural explanation" to "Return only the JSON-LD in a fenced code block. Do not narrate your decisions."

**Acceptance criteria:** Sub-agent responses are shorter. Title agent response < 100 tokens (was unbounded). Metadata agent response < 50 tokens (was ~200). SEO schema response drops narration overhead.

### Phase 3: Measurement

**Step 7: Establish token budget baseline and monitoring**

Use the `ai_observability` module (already enabled per the recipe) with `log_input: true` and `log_output: true` to capture token counts per agent invocation.

Create a measurement protocol:
1. Build the "FinDrop Travel" product page (standard driesnote demo) 3 times before optimization
2. Record total tokens per build (input + output) from observability logs
3. Apply Phase 1 changes, rebuild 3 times, record
4. Apply Phase 2 changes, rebuild 3 times, record
5. Document results in `docs/plans/ws1-efficiency-results.md`

**Acceptance criteria:** Before/after token measurements documented. Target: 40-50% reduction from baseline (150-170K down to 85-100K per page build).

## Cross-References

- **WS2 (Branching Orchestration):** Efficiency gains here reduce the cost of exploring branching patterns in WS2. The `return_directly` analysis directly informs WS2's design for which agents can run independently.
- **WS3 (Markdown Agent Config):** Prompt trimming in Steps 1 and 6 will be easier to maintain once prompts are in markdown files (WS3). Do the trimming now in YAML; WS3 will migrate the trimmed versions.
- **WS4 (Stable Release + Deploy):** Token efficiency is a prerequisite for amazee.io deployment where LLM costs are metered. WS4 depends on WS1 achieving the target reduction.

## Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| `available_on_loop: [1]` breaks multi-loop builds | MEDIUM | HIGH | Test thoroughly with driesnote demo. Add `get_current_layout` as an explicit tool if needed. Rollback is a single YAML change. |
| Trimming orchestrator examples causes mis-routing | LOW | MEDIUM | Keep one example per unique routing pattern. Test all 6 tool routing paths. |
| `return_directly: 1` on title/metadata breaks flow | LOW | LOW | These agents are simple write-and-return. The orchestrator prompt already handles parallel calls. |
| Reduced max_loops causes incomplete pages | MEDIUM | MEDIUM | Start with conservative reduction (30->15). Monitor via observability. Adjust up if needed. |

## Success Criteria

1. Token consumption per standard page build reduced by 40-50% (measured via ai_observability)
2. No regression in page build quality (driesnote demo completes successfully)
3. Competitor names no longer injected into builder agent context
4. All changes are YAML config only -- no PHP code modified
5. Measurement protocol documented with before/after data
