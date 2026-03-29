# Drupal.org Ready Comments

## P4: Deterministic Edit Path — Comment on #3549232

**Issue:** https://www.drupal.org/project/canvas/issues/3549232

---

The `update_component_data` tool introduced in this issue enables a significant UX and performance optimization: routing simple edits directly to this tool without invoking the LLM agent chain at all.

**The user experience problem:**

A content author selects a heading and types "change the heading to Welcome." They wait 15-30 seconds for the agent chain to process what is functionally a key-value update. The orchestrator routes to page_builder_agent, which reads the layout, identifies the component, calls `update_component_data`, and confirms — 5 LLM calls, 111K tokens. The actual edit is a single prop assignment that `update_component_data` executes in <1ms.

For a tool positioned as making page building faster, this latency gap between intent and result is the biggest UX friction point in the editing flow.

**Proposed approach:**

When a component is selected and the user message matches a deterministic pattern, bypass the agent chain entirely:

1. Pattern matcher detects "component selected + recognized prop + explicit value"
2. Routes to a direct-edit endpoint
3. Validates component exists and prop value is schema-valid
4. Calls the same validator + page builder helper pipeline as the AI path
5. Returns the same JSON response format

The pattern matcher is intentionally conservative — it only resolves edits where there is zero ambiguity:

- Message matches "change/set/update X to Y" where X resolves to a known prop alias from the SDC schema
- No add/create/generate keywords present (those require LLM reasoning)
- Value resolves to a valid enum value or is a simple scalar for the target prop
- Compound edits ("change heading to X and set color to blue") split on conservative boundaries and resolve each fragment independently

**What still routes to AI — anything that requires reasoning:**

- Content generation ("write a better heading for this section")
- Ambiguous references ("fix this", "make it look better")
- Add/move/delete operations
- Any message the pattern matcher can't resolve with certainty

**Measured impact:**

- Deterministic path: 0 tokens, <100ms latency
- AI path (current): 111K tokens, 15-30s latency
- Component catalog survey of 23 Byte theme components: 40.1% of props are simple scalars or enums — the addressable surface for deterministic routing

This is not an optimization of the agent chain — it's eliminating the chain entirely for operations that don't need it, analogous to how Drupal's page cache bypasses the full bootstrap for anonymous requests.

**Working prototype:**

`DirectEditMatcher` + `DirectEditController` in a custom `canvas_ai_scoping` module. Uses the same `AiResponseValidator` and `CanvasAiPageBuilderHelper` services as the AI pipeline. 41 PHPUnit tests, 107 assertions. Playwright browser regression covering cold-start (empty tempstore) and compound multi-prop edits.

Happy to contribute a patch if this direction aligns with Canvas's roadmap.

---

## P1: Region Scoping — Comment on #3545816

**Issue:** https://www.drupal.org/project/canvas/issues/3545816

---

This issue addresses vertical optimization (less metadata per component via two-pass fetch). We've built a complementary horizontal optimization that reduces which components the agent sees during edit operations.

**The problem, framed architecturally:**

When editing a single heading, the page builder agent receives the full page layout — every region, every section, every nested component with all props and slots. This is the equivalent of loading all entities when you need one. On a 15-component FinDrop demo page, the full layout JSON is 12,438 bytes. The agent only needs the section containing the selected component.

**Approach — BuildSystemPromptEvent subscriber:**

A subscriber (priority -10) that runs when `active_component_uuid` is set:

1. Identifies which region contains the selected component
2. Identifies which top-level section (within that region) contains it
3. Replaces the full layout with a scoped version:
   - Active section: full detail (all props, slots, nested components)
   - Sibling sections in same region: name + UUID only (agent knows what exists without full trees)
   - Other regions: component count only
   - Region index: lightweight map of all regions (~200 bytes) for cross-region awareness

**Measured results (heading edit):**

- Layout JSON: 12,438 bytes to 2,611 bytes (79% reduction)
- Total operation tokens: ~125K to ~111K (~11% total reduction)

Layout is ~10% of total operation cost — system prompt instructions and ai_context items dominate the other 90%. This is one layer of a multi-layer optimization:

| Layer | What it addresses | Measured savings |
|-------|-------------------|-----------------|
| Deterministic bypass (separate issue) | Edits that don't need LLM | 100% for qualifying edits |
| Loop-aware context injection | ai_context re-injected every loop | 52% total |
| Region scoping (this) | Layout sent for irrelevant components | 11% total |
| Combined | | 69% for non-deterministic edits |

**How this complements #3545816:**

- #3545816 reduces tokens per component description sent to the agent (vertical)
- Region scoping reduces which components are sent (horizontal)
- Applied together: only the relevant components in the relevant section, with compressed metadata for each

**Prototype:**

Working `LayoutScopingSubscriber` in a custom module. Uses `CanvasAiTempStore` to read the current layout and `BuildSystemPromptEvent` to replace layout JSON in the system prompt. Falls back to full layout if the selected component can't be located — fail-open, never degrades the editing experience. 12 unit tests covering region index generation, section scoping, nested components, and edge cases.

We also prototyped a more aggressive "context envelope" mode for `canvas_component_agent` that sends only the selected component + neighbors + section metadata (~350 tokens vs ~3K for the full layout). Happy to share that work as well if there's interest.

---

## P2: Loop-Aware Context Injection — New Issue for ai_context

**Title:** SystemPromptSubscriber re-injects full context on every agent loop iteration

**Category:** Performance improvement

**Priority:** Major

---

**Problem:**

`SystemPromptSubscriber::onPreSystemPrompt()` fires on every `BuildSystemPromptEvent`, which dispatches on every agent loop iteration (`AiAgentEntityWrapper.php`). For agents with `always_include` context items, this means the full context block is re-appended to the system prompt on every LLM call across all loops.

This is redundant work on a hot path. The LLM already has the context from loop 0 in its conversation window — re-injecting it on loops 1+ provides no benefit but costs tokens proportional to loop count.

The pattern is analogous to cache stampeding: the system does expensive redundant work because it doesn't track whether the result is already present. `available_on_loop` in `default_information_tools` already solves exactly this problem for tool outputs — the same principle should apply to ai_context items.

**Measured cost:**

| Agent | Typical loops | Context per loop | Wasted tokens (loops 1+) |
|-------|---------------|-----------------|--------------------------|
| canvas_page_builder_agent | 5-15 | ~10-12K | 40-168K |
| canvas_template_builder_agent | 3-8 | ~10-12K | 20-84K |

On a heading edit operation (101K total tokens without other optimizations), stripping ai_context on loops 1+ reduces total cost to 48K tokens — a 52% reduction from this single change. This is the largest single optimization we measured across layout scoping, context filtering, and deterministic routing combined.

**Proposed solution:**

Two approaches (not mutually exclusive):

**Option A — Custom subscriber (no ai_context module changes needed):**

Subscribe to `AgentStartedExecutionEvent` to capture `getLoopCount()`. On loop > 0, strip the ai_context block from the system prompt using the block separators. The context was sent on loop 0 and is in the LLM's conversation history.

This approach works today with the existing event API.

**Option B — Native ai_context support (cleaner long-term):**

Add a `loop_aware` setting to per-agent context configuration. When enabled, `SystemPromptSubscriber` checks the current loop count and skips injection on loop > 0. This follows the same pattern as `available_on_loop` for tool outputs.

Option A is implemented as a working prototype (`LoopAwareContextSubscriber`) with measured before/after token counts confirming the 52% reduction.

**Relationship to existing work:**

- Complementary to #3564706 (Context Scope feature) — Scope filters *which* items to inject; this filters *when* to inject them. Even with perfect scope filtering, surviving items are still re-injected every loop without this fix.
- Adjacent to #3524351 (tool memory re-injection) — that addresses tool output memory; this addresses context item re-injection. Same underlying pattern: don't repeat data the LLM already has.
- `available_on_loop` in `default_information_tools` is the direct precedent — this extends the same principle from tool outputs to context items.

**Prototype and test results:**

Working `LoopAwareContextSubscriber` in a custom module, validated against the FinDrop demo site. Before/after measurements confirm 52% total token reduction on a single heading edit. Happy to contribute a patch implementing Option B if the approach looks right.
