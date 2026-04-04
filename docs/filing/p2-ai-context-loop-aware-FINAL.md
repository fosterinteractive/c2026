# P2: Loop-Aware Context Injection — Ready to Post

**Target:** New issue on [ai_context](https://www.drupal.org/project/ai_context) project
**Title:** SystemPromptSubscriber re-injects full context on every agent loop iteration
**Category:** Feature request (Performance improvement)
**Priority:** Major

---

## Issue body (copy below this line)

---

### Problem

`SystemPromptSubscriber::onPreSystemPrompt()` fires on every `BuildSystemPromptEvent`, which dispatches on every agent loop iteration. For agents with `always_include` context items, the full context block is re-appended to the system prompt on every LLM call across all loops.

The system prompt is rebuilt each loop, and the context block is re-injected each time — the same content, at the same position, providing no additional information to the LLM (which already has it from loop 0 in its conversation window). The cost scales with loop count.

The pattern is similar to cache-unaware code that re-fetches on every call despite the result being unchanged. `available_on_loop` in `default_information_tools` already solves the equivalent problem for tool outputs — the same principle should apply to ai_context items.

### Measured cost

All measurements are N=1 on a single demo page (15 components, 8 ai_context items totaling ~86K bytes). We expect directional accuracy but recommend instrumented measurement across diverse operations before committing to an architectural change.

| Agent | Typical loops | Context per injection | Wasted tokens (loops 1+) |
|-------|---------------|----------------------|--------------------------|
| canvas_page_builder_agent | 3 (measured) | ~22K tokens (86K bytes) | ~44K |
| canvas_template_builder_agent | 3-8 (observed) | ~22K tokens | 44-154K |

On a heading edit operation (101K total tokens at baseline, 16.4s latency), stripping ai_context on loops 1+ reduces total cost to 48K tokens — a 52% reduction. This was the largest single optimization we measured across layout scoping, context filtering, and deterministic routing combined.

Context size is configuration-dependent — sites with fewer or smaller ai_context items will see proportionally smaller absolute savings, but the relative reduction from eliminating re-injection remains significant whenever context items are non-trivial.

### Proposed solution

Add a `loop_aware` setting to per-agent context configuration in `ai_context.agents`. When enabled, `SystemPromptSubscriber` checks the current loop count and skips injection on loop > 0. This follows the same pattern as `available_on_loop` for tool outputs.

**Implementation sketch:**

1. Add `loop_aware` boolean to `ai_context.schema.yml` per-agent mapping (alongside `always_include`, `excluded_subcontext`).
2. In `SystemPromptSubscriber::onAgentStarted()`, capture `$event->getLoopCount()` per agent ID.
3. In `SystemPromptSubscriber::onPreSystemPrompt()`, check `loop_aware` config + loop count > 0 → skip injection.
4. Default: `FALSE` (no behavior change for existing sites). Missing key treated as `FALSE` — no update hook needed.

Per-agent granularity is intentional: single-loop agents (orchestrators, chatbots) should always get context. Only multi-loop agents (page_builder, template_builder) benefit from skipping.

We have not observed output quality degradation in our testing (brand guidelines and writing tone remained consistent across edited content), but recommend verifying this for diverse agent configurations before enabling by default. The per-agent flag provides a safe rollout path.

### Relationship to existing work

- Complementary to #3564706 (Context Scope feature) — Scope filters *which* items to inject; this filters *when* to inject them. Even with perfect scope filtering, surviving items are still re-injected every loop without this fix.
- Adjacent to #3524351 (tool memory re-injection) — that addresses tool output memory; this addresses context item re-injection. Same underlying pattern: don't repeat data the LLM already has.
- `available_on_loop` in `default_information_tools` is the closest precedent.

### Prototype

Working `LoopAwareContextSubscriber` in a custom module. Before/after measurements confirm 52% total token reduction on a single heading edit (N=1). The subscriber runs at priority -5, after ai_context's SystemPromptSubscriber (implicit priority 0 via Symfony default).

Happy to contribute a patch if this direction looks right.

---

## Filing notes (do not post)

- This targets ai_context module, NOT canvas or canvas_ai
- Option A (custom subscriber) is mentioned only if maintainers ask "can we do this without changing ai_context?" — don't lead with it
- The 52% figure is specific to our 8-item ai_context config; disclose this proactively
- If asked about our prototype codebase: "custom module on a Canvas demo site, happy to share"
- If asked about AI tooling in development: "AI tools assisted with development and measurement; architecture and testing were human-directed"
