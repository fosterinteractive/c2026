# WS1: Agent Efficiency Optimization -- Proposal Critique

**Reviewer:** proposal-critic (opus)
**Date:** 2026-03-26
**Review Mode:** ADVERSARIAL (escalated after 1 CRITICAL + 3 MAJOR findings)
**Plan Reviewed:** `docs/plans/ws1-efficiency-optimization.md`

---

# Verdict: REVISE

## Summary

The plan correctly identifies the top token sinks (default_information_tools reloading, verbose prompts, return_directly overhead) and proposes reasonable YAML-only mitigations. However, it contains one change that would silently break page builds (return_directly on parallel tools), ignores the single largest token multiplier the reviewer explicitly flagged (SEO agent nesting at 10x30=300 loops), and relies on a measurement tool that is neither installed nor configured. The plan needs targeted fixes, not a rewrite.

**Pre-commitment Predictions vs Actual:**
1. PREDICTED: `available_on_loop` will break multi-loop builds. ACTUAL: The plan acknowledges this risk and proposes a reasonable mitigation (add explicit tool). Partially addressed.
2. PREDICTED: Plan ignores nested agent calls (SEO -> page builder). ACTUAL: Confirmed. The reviewer explicitly flagged "the nested multiplication (10 x 30) is still the bigger concern" and "whether the SEO agent needs to call the page builder at all." The plan does not address this at all.
3. PREDICTED: Token savings estimates will be hand-wavy. ACTUAL: Confirmed. No model of compounding across loops. Individual step estimates are plausible but not summed or validated against the 40-50% target.
4. PREDICTED: No token budget enforcement. ACTUAL: Confirmed. The reviewer said "An event subscriber that tracks cumulative token usage per request and throws a RuntimeException when the budget is exceeded would take a day to build." The plan has no such mechanism.
5. PREDICTED: Measurement comes last. ACTUAL: Confirmed. Phase 3 (measurement) comes after Phases 1-2. Baseline should be captured first.

---

## Findings

### Critical Findings

**1. `return_directly: 1` on title/metadata agents will silently drop parallel tools, including the page builder**

The plan's Step 3 proposes setting `return_directly: 1` for `canvas_title_generation_agent` and `canvas_metadata_generation_agent`. The stated rationale is that `"Title and metadata agent responses bypass orchestrator loop. Saves 1-2 orchestrator loops per page build."`

This will break page builds. Here is why:

The orchestrator's Examples 2, 11, 14, 16, and 22 all show the orchestrator calling page builder + title + metadata agents "in parallel" (same LLM response). Per `AiAgentEntityWrapper.php:476-505`, all tools from a single LLM response are collected into `$this->contextTools` and executed sequentially in a `foreach` loop. At line 496-499:

```php
if ($this->toolShouldReturnDirectly($tool)) {
    $this->chatHistory[] = new ChatMessage('tool', $output);
    $this->question = $output;
    return PluginInterfacesAiAgentInterface::JOB_SOLVABLE;
}
```

When ANY tool in the batch has `return_directly: true`, the orchestrator immediately returns `JOB_SOLVABLE` and stops processing ALL remaining tools. If the title agent executes before the page builder (which depends on iteration order of `$this->contextTools`), the page builder and metadata agent would never execute. The page would get a title but no content.

The research document at Section 2 confirms: `return_directly` causes the output to be "immediately returned as the agent's answer without being fed back to the LLM for further processing." It does not say "only for that specific tool" -- it terminates the entire agent loop.

- Confidence: HIGH
- Why this matters: Silent data loss. Pages would appear to build successfully (title generated) but content would be missing. This would be extremely difficult to debug because the orchestrator returns `JOB_SOLVABLE`.
- Fix: Do NOT set `return_directly: 1` for any sub-agent that is called in parallel with other tools. The only safe candidates for `return_directly` would be agents that are always called alone, and the orchestrator's prompt explicitly calls title/metadata in parallel with page construction tools. If you want to reduce orchestrator interpretation overhead, instead make the title/metadata agent prompts more terse so the orchestrator's interpretation pass is cheaper (fewer output tokens to process).

---

### Major Findings

**2. The plan completely ignores the SEO agent nesting problem -- the single largest token multiplier**

The reviewer's feedback explicitly stated: "The nested multiplication (10 x 30) is still the bigger concern. I'd seriously consider whether the SEO agent needs to call the page builder at all."

The audit report flags this at the top of its Recursion Risks table: `drupal_canvas_seo_agent -> page_builder` has worst case `10 x 30 = 300 effective loops`. The SEO agent config at `ai_agents.ai_agent.drupal_canvas_seo_agent.yml` confirms it has `canvas_page_builder_agent` as a tool (line 193: `'ai_agents::ai_agent::canvas_page_builder_agent': true`).

The plan's Step 4 reduces `drupal_canvas_seo_agent` max_loops from 10 to 6, which reduces worst case from 300 to 180. But it does not address the fundamental question: does the SEO agent need to invoke the page builder at all for schema.org generation (Mode A)? Looking at the SEO agent's prompt, Mode A (Schema) only needs `get_component_content` and `add_schema_org_json` -- it never needs the page builder. Mode B (Internal Linking) does need it. A simple mitigation would be to conditionally remove the page builder tool from the SEO agent's available tools when the task is schema generation, or at minimum, set `tool_usage_limits` to restrict page builder invocations.

The plan reduces max_loops on the page builder from 30 to 15, which brings the SEO nesting worst case to 6x15=90. Better, but still an unaddressed architectural problem worth more than the ~1,500 tokens saved by trimming orchestrator examples.

- Confidence: HIGH
- Why this matters: The SEO -> page builder nesting is the single most expensive path in the entire chain. A single internal linking operation could burn 90 LLM calls even after the plan's max_loops reductions. This dwarfs the savings from all other steps combined.
- Fix: Add a step that addresses SEO agent nesting directly. Options: (a) Remove `canvas_page_builder_agent` from SEO agent's tools for schema-only flows. (b) Add `tool_usage_limits` capping page builder invocations within the SEO agent to 1. (c) At minimum, reduce SEO agent's page builder's inherited max_loops via `overrideFunctions()` in a custom event subscriber. The reviewer's question "whether the SEO agent needs to call the page builder at all" deserves an explicit answer in the plan.

**3. `ai_observability` is not installed and its measurement plan is unverifiable**

The plan's Step 7 says: `"Use the ai_observability module (already enabled per the recipe) with log_input: true and log_output: true to capture token counts per agent invocation."`

This claim is false. Searching the findrop recipe (`custom_recipes/findrop/`) for `ai_observability` returns zero matches. The module exists as a submodule at `web/modules/contrib/ai/modules/ai_observability/` but is not listed in the recipe's install list. Its default config (`ai_observability.settings.yml`) ships with `log_input: false` and `log_output: false`.

The entire Phase 3 measurement protocol depends on this module being installed and configured. Without measurement, there is no way to validate whether the 40-50% reduction target was achieved, making Success Criterion #1 unverifiable.

- Confidence: HIGH
- Why this matters: The plan's primary success metric (`"40-50% reduction measured via ai_observability"`) cannot be evaluated. The measurement infrastructure does not exist.
- Fix: Add a Step 0 to Phase 3 (or better, move measurement to Phase 0 before any changes): enable `ai_observability` module, set `log_input: true` and `log_output: true`, and verify token counts appear in logs. Alternatively, use the `AgentResponseEvent` (which fires after every LLM call and includes the provider response with token counts) to build a lightweight logger. The reviewer's suggestion of "an event subscriber that tracks cumulative token usage per request" is the right approach and should be Phase 0, not Phase 3.

**4. The plan's acceptance criteria for Step 2 are factually wrong -- competitor names remain in builder context after the fix**

Step 2 states: `"Competitor names no longer in builder context"` as an acceptance criterion after removing the Sales Training Deck from `always_include`.

This is incorrect. Competitor names (Rimp, Brix, Dill/Bivvy) also appear in `FinDrop Key Facts & Value Propositions.md` at lines 199-202, in a "Competitive Comparison Facts" table. This document is in `always_include` for BOTH builders (lines 38-39 of `custom_recipes/ai_context_setup/recipe.yml`: `'FinDrop Key Facts & Value Propositions'`) AND the title and metadata agents (lines 69, 82).

Removing the Sales Training Deck eliminates the detailed competitive narratives (~2,500 tokens of Rimp/Brix/SAQ Concur battle cards), but the Key Facts document still injects a comparison table with competitor names directly into the builder and SEO agent context. The Brand Guidelines document at `ai_context_data/FinDrop Brand Guidelines.md:65` explicitly says: `"NEVER mention competitors by name (e.g., Romp, SAQ Concur, Brix) in public-facing content without explicit legal approval."`

- Confidence: HIGH
- Why this matters: The acceptance criterion is unachievable with the proposed change alone. Competitor name leakage persists through a different document.
- Fix: Either (a) create a filtered version of Key Facts that omits the Competitive Comparison Facts section, or (b) add the competitive comparison section as a separate context item that can be independently excluded, or (c) revise the acceptance criterion to say "Sales Training Deck competitor narratives removed; Key Facts comparison table remains (tracked for separate cleanup)." Option (b) is cleanest.

---

### Minor Findings

**5.** The plan references audit claims about title/metadata agents having "ZERO context items" -- this has already been fixed in commit `6c05886` ("fix: Address critical AI agent audit findings"). The current `recipe.yml` (lines 65-88) shows title agent gets `FinDrop Brand Guidelines` + `FinDrop Key Facts & Value Propositions`, and metadata agent gets those plus `Writing Tone & Voice`. The plan should reference current state, not the pre-fix audit findings.

**6.** Token savings estimates are not summed or validated against the 40-50% target. The plan provides per-step estimates (Step 1: ~1,500 tokens, Step 2: ~2,500, Step 3: ~1,000-2,000, Step 5: 2,000-5,000 x (N-1) loops) but never adds them up to show they reach 60-85K savings (40-50% of 150-170K). Step 5 (`available_on_loop`) is the only change with substantial savings potential, and its estimate depends heavily on the actual number of loops in a typical build -- unknown because measurement comes last.

**7.** The plan claims `"no PHP code modified"` (Success Criterion #4) but the reviewer's token-tracking event subscriber suggestion requires PHP. If the plan adds measurement infrastructure (as it should), this criterion needs revision.

**8.** Step 6 narration tightening for the metadata agent proposes removing the emoji, but it is a section marker, not decoration. Removing it saves approximately 1 token.

---

## What's Missing

- **No token budget enforcement mechanism.** The reviewer explicitly suggested "an event subscriber that tracks cumulative token usage per request and throws a RuntimeException when the budget is exceeded would take a day to build. Don't let the perfect upstream solution stop you from implementing a working site-level one." The plan has no equivalent. `max_loops` is a loop ceiling, not a token ceiling -- a single loop can consume vastly different token counts depending on context size and response length.

- **No analysis of ai_context per-loop overhead.** The `BuildSystemPromptEvent` fires on every loop iteration (`AiAgentEntityWrapper.php:455-458`), and `SystemPromptSubscriber::onPreSystemPrompt()` appends context items to the system prompt every time. For the page builder with 8 context items (~10-12K tokens), these are included in the system prompt of every LLM call across all loops. The plan addresses `default_information_tools` re-injection but does not model the total per-call system prompt size (base prompt + context items + default_information_tools output).

- **No phasing strategy for the measurement baseline.** The plan says "Build 3 times before optimization, record, then apply Phase 1, rebuild 3 times..." but does not specify whether these builds use the same prompt, same page state, or how variance is controlled. Three samples is statistically insufficient for meaningful before/after comparison given the stochastic nature of LLM outputs.

- **No rollback detection plan.** Step 5 (`available_on_loop`) has the highest risk of breaking builds. The plan says rollback is "a single YAML change" but does not address how you detect that builds are broken. The agent will not error -- it will silently produce worse output because it lacks layout context on loops 2+. Detection requires human review of page quality, not just "did the build complete."

- **No consideration of whether `ai_context` items should use `available_on_loop` too.** If `default_information_tools` can be restricted to loop 1, the same principle applies to context items. The `BuildSystemPromptEvent` subscriber could be modified to only inject context on the first loop, moving it to chat history instead. This would save 10-12K tokens x (N-1) loops for the builder agents -- larger than any individual step in the plan.

---

## Ambiguity Risks

- `"Saves 1-2 orchestrator loops per page build (~1,000-2,000 tokens)"` (Step 3) -- Interpretation A: The orchestrator needs 1-2 fewer loop iterations because it does not need to "interpret" the title/metadata response. Interpretation B: The orchestrator's LLM call that would have processed these responses is eliminated entirely. Neither interpretation is correct given the `return_directly` bug, but even conceptually the plan does not clarify the mechanism.
  - Risk if wrong interpretation chosen: Incorrect token savings estimates propagate to the 40-50% target.

- `"Test with the driesnote demo script"` (Steps 4, 5) -- What is the driesnote demo script? It is not referenced anywhere else in the codebase or docs. Is it a manual procedure, an automated script, or a reference to a specific page build scenario? An executor would not know what to run.
  - Risk if wrong interpretation chosen: Testing is skipped or uses wrong scenarios, missing regressions.

---

## Multi-Perspective Notes

- **Executor:** Steps 1-2 and 4 are clear and executable. Step 3 would produce a subtle, hard-to-detect bug. Step 5 requires careful testing but has a clear rollback path. Step 6 is straightforward. Step 7 cannot be executed as written because `ai_observability` is not installed.

- **Stakeholder:** The 40-50% target is reasonable but unverifiable without measurement infrastructure. The plan addresses real problems but skips the highest-impact one (SEO nesting). If I am paying for tokens, the SEO -> page builder chain is where I am losing money, not in orchestrator example verbosity.

- **Skeptic:** The plan optimizes the wrong things. Steps 1 and 6 (prompt trimming) save ~3,000-4,000 tokens total -- roughly 2% of the 150-170K budget. Step 5 (`available_on_loop`) is the only high-leverage change, and it carries the highest risk. The plan does not model total token flow to show where the 150-170K actually goes, making it impossible to verify the optimization targets the right places. The reviewer's two concrete suggestions (token budget enforcement, SEO agent decoupling) are both absent.

---

## Verdict Justification

**REVISE.** The plan has one change that would break page builds (Step 3 `return_directly`), ignores the reviewer's two highest-priority suggestions (token budget enforcement and SEO agent nesting), and builds its measurement strategy on infrastructure that does not exist. The remaining steps (1, 2, 4, 5, 6) are sound and can proceed with minor corrections.

Review mode was ADVERSARIAL due to the critical `return_directly` finding plus three MAJOR findings. No Realist Check recalibrations were applied -- all surviving findings have concrete codebase evidence and realistic worst-case outcomes that are not mitigated by other factors.

To reach ACCEPT-WITH-RESERVATIONS:
1. Remove or fundamentally redesign Step 3 (`return_directly`). It cannot work as specified.
2. Add a step addressing SEO -> page builder nesting (the reviewer's top concern).
3. Move measurement to Phase 0 and either install `ai_observability` or build a lightweight token tracker.
4. Fix the acceptance criteria for Step 2 (competitor names persist in Key Facts).
5. Address the reviewer's token budget enforcement suggestion, even if as a future item with a concrete ticket.

Verdict challenge: "Should this be REJECT instead of REVISE?" No. The core approach (YAML-only optimizations targeting known inefficiencies) is sound. Steps 1, 2, 4, 5, and 6 are individually correct and valuable. The problems are fixable without rethinking the strategy.

---

## Open Questions (unscored)

- What is the actual token count of a `get_current_layout` call and a `get_component_context` call? The plan estimates 2,000-5,000 tokens but this range is wide. A single measurement before optimization would calibrate all estimates.
- Does `AiContextSelector::select()` cache results between loop iterations? If it does, the per-loop context injection is just string concatenation overhead, not repeated entity loading. If not, there may be database query overhead on every loop too.
- The `field_agent_triage` in the ai_agents module default config already uses `available_on_loop`. Has anyone verified that this pattern works correctly in production? This would de-risk Step 5.
- Would switching to a smaller/cheaper model for title and metadata generation (e.g., Haiku instead of the inherited Opus/Sonnet) provide better cost reduction than prompt trimming? The plan does not consider model routing as an optimization lever.

---

**Key files referenced in this review:**
- `/Users/AlexUA/claude/c2026/docs/plans/ws1-efficiency-optimization.md` (the plan under review)
- `/Users/AlexUA/claude/c2026/web/modules/contrib/ai_agents/src/PluginBase/AiAgentEntityWrapper.php` (lines 476-505, 890-936: `return_directly` and `getDefaultInformationTools` logic)
- `/Users/AlexUA/claude/c2026/custom_recipes/ai_context_setup/recipe.yml` (lines 14-112: context item mapping per agent)
- `/Users/AlexUA/claude/c2026/custom_recipes/findrop/config/ai_agents.ai_agent.drupal_canvas_seo_agent.yml` (line 193: page builder as sub-agent tool)
- `/Users/AlexUA/claude/c2026/custom_recipes/findrop/config/ai_agents.ai_agent.canvas_ai_orchestrator.yml` (tool_settings showing `return_directly: 0` for all sub-agents)
- `/Users/AlexUA/claude/c2026/ai_context_data/FinDrop Key Facts & Value Propositions.md` (lines 193-202: competitor names in Key Facts)
- `/Users/AlexUA/claude/c2026/ai_context_data/sales-pitch-deck-travel-only.md` (competitor battle cards)
- `/Users/AlexUA/claude/c2026/web/modules/contrib/ai_context/src/EventSubscriber/SystemPromptSubscriber.php` (lines 57-144: per-loop context injection)