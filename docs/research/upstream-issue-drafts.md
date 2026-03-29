# Upstream Issue Drafts

**Date:** 2026-03-28
**Status:** Draft — do not file until local prototypes are validated with benchmarks (per ADR-008)

---

## P4: Deterministic Edit Path — Comment on #3549232

**Target:** https://www.drupal.org/project/canvas/issues/3549232
**Action:** Comment on existing issue, not a new issue
**Module:** canvas_ai

### Proposed Comment

Subject: Deterministic routing for simple prop edits — bypasses LLM entirely

The `update_component_data` tool introduced in this issue enables a significant optimization: routing simple edits directly to this tool without invoking the LLM agent chain at all.

**Problem measured:**
A single heading text edit ("change the heading to X") currently costs 111K LLM tokens because it traverses: orchestrator → page_builder_agent → 3 loop iterations → update_component_data. The orchestrator, agent system prompts, ai_context injection, and layout context account for ~100K of those tokens. The actual edit is a single prop assignment that `update_component_data` executes in <1ms.

**Proposed approach:**
When a component is selected and the user message matches a deterministic pattern:
1. Frontend pattern matcher detects "component selected + recognized prop + explicit value"
2. Routes to `/admin/api/canvas/direct-edit` endpoint (or equivalent)
3. Validates component exists and prop value is schema-valid
4. Calls `update_component_data` pipeline directly (validator, media population, structured output)
5. Returns the same JSON response format as the AI pipeline

**Pattern matching criteria:**
- Message matches "change/set/update X to Y" where X resolves to a known prop alias
- No add/create/generate keywords present (those require LLM reasoning)
- Value resolves to a valid enum value or is a simple scalar for the target prop

**What routes deterministically:**
- `heading.heading_text` — "change the heading to [text]"
- `heading.text_color` — "set the color to primary"
- `heading.align` — "align this center"
- `button.label` — "change the button text to [text]"
- `button.variant` — "make this button secondary"
- Enum props on any component with a recognized alias mapping

**What still routes to AI:**
- Multi-prop changes ("make this heading bigger and blue")
- Content generation ("write a better heading for this section")
- Ambiguous references ("fix this", "make it look better")
- Add/move/delete operations
- Any message the pattern matcher can't resolve with certainty

**Measured impact:**
- Deterministic path: 0 tokens, <100ms latency
- AI path (current): 111K tokens, 15-30s latency
- Estimated 40% of real-world component edits can route deterministically (based on Byte theme component catalog survey: 40.1% of props are simple scalars or enums)

**Working prototype:** Available in the FinDrop demo site's `canvas_ai_scoping` module. The `DirectEditMatcher` service and `DirectEditController` implement this pattern using the same `AiResponseValidator` and `CanvasAiPageBuilderHelper` services as the AI pipeline.

---

## P1: Region Scoping — Comment on #3545816

**Target:** https://www.drupal.org/project/canvas/issues/3545816
**Action:** Comment on existing issue to complement with horizontal optimization
**Module:** canvas_ai

### Proposed Comment

Subject: Complementary optimization — region-level layout scoping during component edits

This issue addresses **vertical optimization** (less metadata per component via two-pass fetch). We've built a complementary **horizontal optimization** that reduces the number of components sent to the agent during edit operations.

**Problem measured:**
When editing a single component, the page builder agent receives the full page layout JSON — every region, every section, every nested component with all props and slots. On a 15-component page, this is ~12K bytes of layout JSON. The agent only needs the section containing the selected component.

**Approach — LayoutScopingSubscriber:**
A `BuildSystemPromptEvent` subscriber that runs when `active_component_uuid` is set:

1. Identifies which region contains the selected component
2. Identifies which top-level section (within that region) contains it
3. Replaces the full layout with a scoped version:
   - **Active section**: full detail (all props, slots, nested components)
   - **Sibling sections in same region**: name + UUID only (agent knows what exists but doesn't see full trees)
   - **Other regions** (header, footer): component count only

**Measured results (N=1 heading edit):**
- Layout JSON: 12,438 bytes → 2,611 bytes (79% reduction)
- Total operation tokens: reduced from ~125K to ~111K (the 14K saving is modest because layout is a fraction of total cost — system prompt, ai_context, and chat history dominate)

**How this complements #3545816:**
- `#3545816` reduces tokens *per component description* sent to the agent (vertical)
- Region scoping reduces *which components* are sent (horizontal)
- Applied together: only the relevant components in the relevant section, with compressed metadata for each

**Prototype:** Working `LayoutScopingSubscriber` in the FinDrop `canvas_ai_scoping` module. Uses `CanvasAiTempStore` to read the current layout and `BuildSystemPromptEvent` to replace the layout JSON in the system prompt. Falls back to full layout if the selected component can't be located.

**Edge cases handled:**
- Component not found in any region → full layout (fail-open)
- Cross-region operations → not scoped (no `active_component_uuid` → full layout)
- The agent retains awareness of sibling sections (name + UUID) and other regions (count) so it can reference them if needed

---

## P2: Loop-Aware Context Injection — New Issue for ai_context

**Target:** https://www.drupal.org/project/ai_context — new issue
**Action:** File new issue (no existing issue covers this)
**Related:** #3564706 (Context Scope feature), #3524351 (tool memory), #3573713 (architecture review)

### Draft Issue

**Title:** SystemPromptSubscriber re-injects full context on every agent loop iteration

**Category:** Performance improvement
**Priority:** Major
**Version:** 1.0.0-beta1

**Problem:**

`SystemPromptSubscriber::onPreSystemPrompt()` fires on every `BuildSystemPromptEvent`, which dispatches on every agent loop iteration (`AiAgentEntityWrapper.php:457`). For agents with `always_include` context items, this means the full context block (10-12K tokens for 8 items in our configuration) is appended to the system prompt on every LLM call across all loops.

For a page builder agent that loops 5-15 times, this adds 50-180K tokens of identical, repeated context injection across a single operation. The LLM already has the context from loop 0 — re-injecting it provides no benefit but costs tokens proportional to loop count.

**Measured cost:**

| Agent | Loops | Context per loop | Total context tokens |
|-------|-------|-----------------|---------------------|
| canvas_page_builder_agent | 5-15 | ~10-12K | 50-180K |
| canvas_template_builder_agent | 3-8 | ~10-12K | 30-96K |

A single page build costs 253K total tokens. Context re-injection accounts for an estimated 40-60% of that cost.

**Proposed solution:**

Add loop-awareness to context injection. Two approaches (not mutually exclusive):

**Option A — Subscriber-side (no ai_context changes needed):**
A custom `BuildSystemPromptEvent` subscriber that:
1. Subscribes to `AgentStartedExecutionEvent` to capture `getLoopCount()`
2. On loop > 0, strips the ai_context block from the system prompt (identified by the `-------` separators)
3. The context was sent on loop 0 and is in the LLM's conversation window

**Option B — Native ai_context support:**
Add a `loop_aware` setting to `ai_context.settings` or per-agent context configuration:
- When enabled, `SystemPromptSubscriber` checks the current loop count
- On loop 0: inject into system prompt (current behavior)
- On loop > 0: skip injection (context already in conversation history)

Option A is implemented as a working prototype in our `canvas_ai_scoping` module. Option B would be the clean upstream solution.

**Relationship to existing work:**
- Complementary to #3564706 (Context Scope) — Scope filters *which* items to inject; this filters *when* to inject. Even with perfect scope filtering, surviving items are still re-injected every loop without this fix.
- Adjacent to #3524351 (tool memory) — that issue addresses tool output memory; this addresses context item re-injection. Same underlying pattern: don't repeat data the LLM already has.
- `available_on_loop` in `default_information_tools` already solves this for tool outputs — this extends the same principle to ai_context items.

**Working prototype:** `LoopAwareContextSubscriber` in the FinDrop demo, validating with before/after token measurements.

---

## P3b: History Windowing — New Issue for ai_agents

**Target:** https://www.drupal.org/project/ai_agents — new issue
**Action:** File new issue (reference #3555239, #3458607)
**Status:** Lower priority — draft only, file after P4 and P1 establish credibility

### Draft Issue

**Title:** Add configurable chat history windowing to prevent token accumulation across turns

**Category:** Feature request
**Priority:** Normal

**Problem:**

The orchestrator agent accumulates full conversation history across turns. After a page build + 3 edit operations, the orchestrator sends 80K+ tokens of historical messages per call. Most of this history is irrelevant to the current operation — the user's latest message and the most recent agent response are sufficient context.

There is no mechanism to limit history size. `max_loops` limits iterations within a single turn, but cross-turn history grows unboundedly.

**Proposed solution:**

Add `max_history_messages` or `max_history_tokens` config field to `ai_agent` config entities:
- When history exceeds the limit, older messages are dropped (keeping the first system context message and the last N turns)
- Default: no limit (current behavior, backwards compatible)
- Optional: summarize dropped messages into a single context message instead of hard truncation

**Related:** #3555239 (Canvas AI orchestrator history corruption), #3458607 (chat history vs reduced context length)
