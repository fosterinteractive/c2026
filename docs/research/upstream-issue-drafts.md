# Upstream Issue Drafts

**Date:** 2026-03-29 (updated)
**Status:** P4 and P1 ready to file. P2 ready to file. P3b deferred.

---

## Filing Order (per ADR-008)

1. **P4** — Comment on #3549232 (deterministic edit bypass). Strongest evidence, zero-token path.
2. **P1** — Comment on #3545816 (region scoping). Complements existing discussion.
3. **P2** — New issue on ai_context (loop-aware injection). References #3564706, #3524351.
4. **P3b** — New issue on ai_agents (history windowing). Deferred until P4/P1 establish credibility.

---

## P4: Deterministic Edit Path — Comment on #3549232

**Target:** https://www.drupal.org/project/canvas/issues/3549232
**Action:** Comment on existing issue
**Module:** canvas_ai
**Status: READY TO FILE**

### Proposed Comment

Subject: Deterministic routing for simple prop edits — bypasses LLM entirely

The `update_component_data` tool introduced in this issue enables a significant optimization: routing simple edits directly to this tool without invoking the LLM agent chain at all.

**Problem measured:**
A single heading text edit ("change the heading to X") costs 111K LLM tokens because it traverses: orchestrator -> page_builder_agent -> 3 loop iterations -> update_component_data. The orchestrator, agent system prompts, ai_context injection, and layout context account for ~100K of those tokens. The actual edit is a single prop assignment that `update_component_data` executes in <1ms.

**Proposed approach:**
When a component is selected and the user message matches a deterministic pattern:
1. Frontend pattern matcher detects "component selected + recognized prop + explicit value"
2. Routes to a direct-edit endpoint (or equivalent)
3. Validates component exists and prop value is schema-valid
4. Calls the same validator + page builder helper pipeline as the AI path
5. Returns the same JSON response format

**Pattern matching criteria:**
- Message matches "change/set/update X to Y" where X resolves to a known prop alias
- No add/create/generate keywords present (those require LLM reasoning)
- Value resolves to a valid enum value or is a simple scalar for the target prop
- Compound edits ("change heading to X and set color to blue") split on conservative boundaries and resolve each fragment independently

**What routes deterministically:**
- Heading text, color, alignment, level
- Button label, variant, size
- Any component prop with a recognized alias mapping from the SDC schema
- Compound edits where all fragments resolve (Tier 2)

**What still routes to AI:**
- Content generation ("write a better heading")
- Ambiguous references ("fix this", "make it look better")
- Add/move/delete operations
- Any message the pattern matcher can't resolve with certainty

**Measured impact:**
- Deterministic path: 0 tokens, <100ms latency
- AI path (current): 111K tokens, 15-30s latency
- Component catalog survey: 40.1% of Byte theme props are simple scalars or enums — the addressable surface for deterministic routing

**Working prototype:** `DirectEditMatcher` + `DirectEditController` in the FinDrop demo's `canvas_ai_scoping` module. Uses the same `AiResponseValidator` and `CanvasAiPageBuilderHelper` services as the AI pipeline. 41 PHPUnit tests, 107 assertions. Playwright browser regression covering cold-start and compound edits.

---

## P1: Region Scoping — Comment on #3545816

**Target:** https://www.drupal.org/project/canvas/issues/3545816
**Action:** Comment on existing issue to complement with horizontal optimization
**Module:** canvas_ai
**Status: READY TO FILE**

### Proposed Comment

Subject: Complementary optimization — region-level layout scoping during component edits

This issue addresses vertical optimization (less metadata per component via two-pass fetch). We've built a complementary horizontal optimization that reduces which components the agent sees during edit operations.

**Problem measured:**
When editing a single component, the page builder agent receives the full page layout JSON. On a 15-component FinDrop page, this is 12,438 bytes of layout JSON. The agent only needs the section containing the selected component.

**Approach — LayoutScopingSubscriber:**
A `BuildSystemPromptEvent` subscriber (priority -10) that runs when `active_component_uuid` is set:

1. Identifies which region contains the selected component
2. Identifies which top-level section (within that region) contains it
3. Replaces the full layout with a scoped version:
   - Active section: full detail (all props, slots, nested components)
   - Sibling sections in same region: name + UUID only
   - Other regions: component count only
   - Region index: lightweight map of all regions for cross-region awareness

**Measured results (heading edit, N=1):**
- Layout JSON: 12,438 -> 2,611 bytes (79% reduction)
- Total operation tokens: ~125K -> ~111K (~11% reduction)
- Layout is ~10% of total cost; system prompt and ai_context dominate the rest

**How this complements #3545816:**
- #3545816 reduces tokens per component description (vertical)
- Region scoping reduces which components are sent (horizontal)
- Applied together: only the relevant components with compressed metadata

**Prototype:** Working `LayoutScopingSubscriber` in the FinDrop `canvas_ai_scoping` module. Uses `CanvasAiTempStore` to read the current layout and `BuildSystemPromptEvent` to replace layout JSON. Falls back to full layout if the selected component can't be located. 12 unit tests covering region index generation, section scoping, and nested components.

---

## P2: Loop-Aware Context Injection — New Issue for ai_context

**Target:** https://www.drupal.org/project/ai_context — new issue
**Action:** File new issue
**Related:** #3564706 (Context Scope), #3524351 (tool memory), #3573713 (architecture review)
**Status: READY TO FILE**

### Draft Issue

**Title:** SystemPromptSubscriber re-injects full context on every agent loop iteration

**Category:** Performance improvement
**Priority:** Major

**Problem:**

`SystemPromptSubscriber::onPreSystemPrompt()` fires on every `BuildSystemPromptEvent`, which dispatches on every agent loop iteration. For agents with `always_include` context items, this means the full context block (10-12K tokens for 8 items in our configuration) is re-appended to the system prompt on every LLM call across all loops.

For a page builder agent that loops 5-15 times, this adds 50-180K tokens of identical, repeated context. The LLM already has the context from loop 0 — re-injecting it provides no benefit.

**Measured cost:**

| Agent | Loops | Context per loop | Wasted tokens |
|-------|-------|-----------------|---------------|
| canvas_page_builder_agent | 5-15 | ~10-12K | 40-168K |
| canvas_template_builder_agent | 3-8 | ~10-12K | 20-84K |

On a heading edit (101K total tokens without other optimizations), stripping ai_context on loops 1+ reduces cost to 48K tokens — a 52% reduction from this single change.

**Proposed solution:**

Add loop-awareness to context injection. Two approaches:

**Option A — Custom subscriber (no ai_context changes):**
Subscribe to `AgentStartedExecutionEvent` to capture `getLoopCount()`. On loop > 0, strip the ai_context block from the system prompt. The context was sent on loop 0 and is in the LLM's conversation window.

**Option B — Native ai_context support:**
Add a `loop_aware` setting to per-agent context configuration. When enabled, `SystemPromptSubscriber` checks the current loop count and skips injection on loop > 0.

Option A is implemented as a working prototype (`LoopAwareContextSubscriber`). Option B is the clean upstream path.

**Relationship to existing work:**
- Complementary to #3564706 (Context Scope) — Scope filters which items to inject; this filters when. Even with perfect scope filtering, surviving items are still re-injected every loop.
- Adjacent to #3524351 (tool memory) — that addresses tool output memory; this addresses context item re-injection. Same pattern: don't repeat data the LLM already has.
- `available_on_loop` in `default_information_tools` already solves this for tool outputs — this extends the same principle to ai_context items.

---

## P3b: History Windowing — New Issue for ai_agents

**Target:** https://www.drupal.org/project/ai_agents — new issue
**Action:** File new issue (reference #3555239, #3458607)
**Status: DEFERRED — file after P4 and P1 establish credibility**

### Draft Issue

**Title:** Add configurable chat history windowing to prevent token accumulation across turns

**Category:** Feature request
**Priority:** Normal

**Problem:**

The orchestrator agent accumulates full conversation history across turns. After a page build + 3 edit operations, the orchestrator sends 80K+ tokens of historical messages per call. Most of this history is irrelevant to the current operation.

There is no mechanism to limit history size. `max_loops` limits iterations within a single turn, but cross-turn history grows unboundedly.

**Proposed solution:**

Add `max_history_messages` or `max_history_tokens` config field to `ai_agent` config entities:
- When history exceeds the limit, older messages are dropped (keeping the first system context message and the last N turns)
- Default: no limit (current behavior, backwards compatible)

**Related:** #3555239 (Canvas AI orchestrator history corruption), #3458607 (chat history vs reduced context length)
