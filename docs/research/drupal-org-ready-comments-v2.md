# Drupal.org Ready Comments — v2 (Post-Critic Revision)

**Revised:** 2026-03-29
**Filing order:** P2 (strongest) → P1 (complementary) → P4 (most architecturally disruptive)
**Changes from v1:** All numbers reconciled to ws1-measurement-results.md; N=1 qualified; tone softened; limitations disclosed; filing order reversed per critic recommendation.

---

## P2: Loop-Aware Context Injection — New Issue for ai_context

**Title:** SystemPromptSubscriber re-injects full context on every agent loop iteration

**Category:** Performance improvement

**Priority:** Major

---

**Problem:**

`SystemPromptSubscriber::onPreSystemPrompt()` fires on every `BuildSystemPromptEvent`, which dispatches on every agent loop iteration (`AiAgentEntityWrapper.php`). For agents with `always_include` context items, this means the full context block is re-appended to the system prompt on every LLM call across all loops.

The system prompt is rebuilt each loop, and the context block is re-injected each time — the same content, at the same position, providing no additional information to the LLM (which already has it from loop 0 in its conversation window). The cost scales with loop count.

The pattern is similar to cache-unaware code that re-fetches on every call despite the result being unchanged. `available_on_loop` in `default_information_tools` already solves the equivalent problem for tool outputs — the same principle should apply to ai_context items.

**Measured cost (N=1 heading edit on a demo site with 8 ai_context items):**

| Agent | Typical loops | Context per injection | Wasted tokens (loops 1+) |
|-------|---------------|----------------------|--------------------------|
| canvas_page_builder_agent | 3 (measured) | ~22K tokens (86K bytes) | ~44K |
| canvas_template_builder_agent | 3-8 (observed) | ~22K tokens | 44-154K |

On a heading edit operation (101K total tokens at baseline), stripping ai_context on loops 1+ reduces total cost to 48K tokens — a 52% reduction. This was the largest single optimization we measured across layout scoping, context filtering, and deterministic routing combined.

Context size is configuration-dependent — sites with fewer or smaller ai_context items will see proportionally smaller absolute savings, but the relative reduction from eliminating re-injection remains significant whenever context items are non-trivial.

**Note on measurement:** All measurements are N=1 on a single demo page (15 components, 8 ai_context items totaling ~86K bytes). We expect directional accuracy but recommend instrumented measurement across diverse operations before committing to an architectural change. The 52% figure is specific to this configuration.

**Proposed solution:**

Two approaches (not mutually exclusive):

**Option A — Custom subscriber (no ai_context module changes needed):**

Subscribe to `AgentStartedExecutionEvent` to capture `getLoopCount()`. On loop > 0, strip the ai_context block from the system prompt using the block separators. The context was sent on loop 0 and is in the LLM's conversation history.

This approach works today with the existing event API. Note: our prototype required a fix to the separator matching in the ai_context block parser (`strpos()` matching any 47+ dash run was changed to `preg_match_all()` with newline anchors to match only standalone separator lines). Without this fix, the subscriber cannot reliably locate the block boundaries.

**Option B — Native ai_context support (cleaner long-term):**

Add a `loop_aware` setting to per-agent context configuration. When enabled, `SystemPromptSubscriber` checks the current loop count and skips injection on loop > 0. This follows the same pattern as `available_on_loop` for tool outputs.

We have not observed output quality degradation in our testing (brand guidelines and writing tone remained consistent across edited content), but recommend the ai_context maintainers verify this for diverse agent configurations before enabling by default. The `loop_aware` flag (Option B) would let site builders control this per-agent, which provides a safe rollout path.

**Relationship to existing work:**

- Complementary to #3564706 (Context Scope feature) — Scope filters *which* items to inject; this filters *when* to inject them. Even with perfect scope filtering, surviving items are still re-injected every loop without this fix.
- Adjacent to #3524351 (tool memory re-injection) — that addresses tool output memory; this addresses context item re-injection. Same underlying pattern: don't repeat data the LLM already has.
- `available_on_loop` in `default_information_tools` is the closest precedent. Note that tool outputs and system prompt content are architecturally different (message array vs system prompt), but the principle — skip redundant injection when the LLM already has the content — applies to both.

**Prototype and test results:**

Working `LoopAwareContextSubscriber` in a custom module, validated against a demo site. Before/after measurements confirm 52% total token reduction on a single heading edit (N=1). The subscriber runs at priority -5, after ai_context's SystemPromptSubscriber (implicit priority 0 via Symfony default). Happy to contribute a patch implementing Option B if the approach looks right.

---

## P1: Region Scoping — Comment on #3545816

**Issue:** https://www.drupal.org/project/canvas/issues/3545816

---

This issue addresses vertical optimization (less metadata per component via two-pass fetch). We've built a complementary horizontal optimization that reduces which components the agent sees during edit operations.

**The problem, framed architecturally:**

When editing a single heading, the page builder agent receives the full page layout — every region, every section, every nested component with all props and slots. On a 15-component demo page, the full layout JSON is ~11.5K bytes (~2,900 tokens). The agent only needs the section containing the selected component.

**Approach — BuildSystemPromptEvent subscriber:**

A subscriber (priority -10, after ai_context at 0) that runs when `active_component_uuid` is set:

1. Identifies which region contains the selected component
2. Identifies which top-level section (within that region) contains it
3. Replaces the full layout with a scoped version:
   - Active section: full detail (all props, slots, nested components)
   - Sibling sections in same region: name + UUID only (agent knows what exists without full trees)
   - Other regions: component count only
   - Region index: lightweight map of all regions (~200 bytes) for cross-region awareness

**Known limitation:** The subscriber replaces layout JSON in the system prompt via string matching. If the serialization format between the tempstore and the prompt differs (whitespace, key ordering), the match fails and the subscriber falls through to the full layout — fail-open, never degrades the editing experience, but the optimization doesn't apply. A cleaner upstream approach would be a structured API on `BuildSystemPromptEvent` (e.g., `getLayoutData()`/`setLayoutData()`) rather than string surgery on the prompt.

**Measured results (N=1 heading edit, demo page with 15 components):**

Layout is approximately 10% of total per-loop cost — system prompt instructions and ai_context items dominate the other 90%. This means layout scoping yields a modest total reduction on its own but compounds with other optimizations:

| Layer | What it addresses | Measured savings |
|-------|-------------------|-----------------|
| Loop-aware context injection (separate issue) | ai_context re-injected every loop | 52% total |
| Region scoping (this) | Layout sent for irrelevant components | ~10% of per-loop cost |
| Deterministic bypass (separate issue) | Edits that don't need LLM | 100% for qualifying edits |

**Cross-region edit behavior:** Scoped layout preserves cross-region awareness via the region index but limits cross-region component detail. Operations requiring full cross-section context (e.g., "match the style of the hero section") would need the agent to request the full layout via existing tools, or would fall through to an unscoped prompt. This tradeoff is intentional — the common case (edit within a section) benefits from reduced noise.

**How this complements #3545816:**

- #3545816 reduces tokens per component description sent to the agent (vertical)
- Region scoping reduces which components are sent (horizontal)
- Applied together: only the relevant components in the relevant section, with compressed metadata for each

**Prototype:**

Working `LayoutScopingSubscriber` in a custom module. Uses `CanvasAiTempStore` to read the current layout and `BuildSystemPromptEvent` to replace layout JSON in the system prompt. Falls back to full layout if the selected component can't be located. 13 unit tests covering region index generation, section scoping, nested components, and edge cases.

We also prototyped a more aggressive "context envelope" mode for `canvas_component_agent` that sends only the selected component + neighbors + section metadata (~350 tokens vs ~3K for the full layout). Happy to share that work as well if there's interest.

---

## P4: Deterministic Edit Path — Comment on #3549232

**Issue:** https://www.drupal.org/project/canvas/issues/3549232

---

The `update_component_data` tool introduced in this issue enables a significant UX and performance optimization: routing simple edits directly to this tool without invoking the LLM agent chain at all.

**The user experience problem:**

A content author selects a heading and types "change the heading to Welcome." They wait for the agent chain to process what is functionally a key-value update. The orchestrator routes to page_builder_agent, which reads the layout, identifies the component, calls `update_component_data`, and confirms — 5 LLM calls totaling ~101K tokens (measured, N=1 heading edit on a 15-component demo page). The actual edit is a single prop assignment.

In our testing, this latency gap between intent and result was the most noticeable friction point in the editing flow.

**Proposed approach:**

When a component is selected and the user message matches a deterministic pattern, bypass the agent chain entirely:

1. Pattern matcher detects "component selected + recognized prop + explicit value"
2. Routes to a direct-edit endpoint
3. Validates component exists and prop value is schema-valid
4. Calls the same `AiResponseValidator` and `CanvasAiPageBuilderHelper` services as the AI path
5. Returns the same JSON response format

The pattern matcher is intentionally conservative — it only resolves edits where there is zero ambiguity:

- Message matches "change/set/update X to Y" where X resolves to a known prop alias from the SDC schema
- No add/create/generate keywords present (those require LLM reasoning)
- Value resolves to a valid enum value or is a simple scalar for the target prop
- Compound edits ("change heading to X and set color to blue") split on conservative boundaries and resolve each fragment independently
- Bare values ("blue") resolve via reverse enum index when unambiguous (only one prop accepts the value)
- Boolean toggles ("show the header") resolve against boolean prop metadata
- Relative adjustments ("bigger") navigate enum ordinals based on current prop values

**What still routes to AI — anything that requires reasoning:**

- Content generation ("write a better heading for this section")
- Ambiguous references ("fix this", "make it look better")
- Add/move/delete operations
- Cross-component references ("match the style of the hero")
- Any message the pattern matcher can't resolve with certainty

**Limitations we want to disclose:**

- **English only.** The pattern matcher uses English verbs (change/set/update) and English prop aliases. Non-English Drupal sites would route all edits to the AI chain, which handles multilingual natively. A contributed version could support localized verb/alias maps, but the prototype does not.
- **Theme-specific.** Our prototype loads prop schemas from Byte theme SDC YAML files. A contributed version would need to discover the active theme's SDC components dynamically rather than hardcoding a theme name.
- **Concrete class coupling.** The direct-edit endpoint depends on `AiResponseValidator` and `CanvasAiPageBuilderHelper` — concrete classes with no interface contract. If Canvas refactors these services, the endpoint breaks. This is arguably motivation for Canvas to extract a shared interface (e.g., `ComponentUpdatePipelineInterface`) that both the AI path and any deterministic shortcut can depend on.
- **False positive design.** The matcher is designed for zero false positives — when in doubt, it rejects to the AI chain (422 response). False negatives (missing a deterministic match) cost the standard AI path tokens but are safe. We have not encountered a false positive in testing, but the compound splitter has a known ambiguity with conjunctions in text values (e.g., "change the heading to Welcome and Goodbye" — is "and" text or a separator?). The matcher handles this by requiring the next fragment to begin with an edit verb.

**Measured impact (N=1 heading edit, demo page):**

- Deterministic path: 0 tokens, <7ms latency (median 3.2 microseconds for pattern matching alone, measured over 30 operations)
- AI path (baseline): ~101K tokens
- Component catalog survey of 23 Byte theme SDC components (125 total props): 40% are enum-constrained, 8.8% are boolean — 48.8% of props are addressable by the deterministic path without requiring LLM reasoning. 12 of 17 enum-bearing components have fully orthogonal enum values (no bare-value ambiguity).

**Working prototype:**

`DirectEditMatcher` + `DirectEditController` in a custom `canvas_ai_scoping` module. Uses the same `AiResponseValidator` and `CanvasAiPageBuilderHelper` services as the AI pipeline. 126 PHPUnit tests, 376 assertions across the module (matcher, controller, schema loader, layout scoping, context envelope). Playwright browser regression covering cold-start (empty tempstore) and compound multi-prop edits.

This complements agent chain optimizations by handling a category of edits that don't require agent reasoning — similar in principle to how Drupal's static page cache skips the full bootstrap for requests that don't need it.

Happy to contribute a patch if this direction aligns with Canvas's roadmap.
