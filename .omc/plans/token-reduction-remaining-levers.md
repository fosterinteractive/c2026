# Plan: Token Reduction — Remaining Levers (REVISED per meta-critic)

**Goal:** Reduce component-edit token cost from 111K to ~78-88K (20-30% reduction)
**Baseline:** 111,004 tokens for a heading edit (5 API calls, section-scoping active)
**Branch:** `feat/ws1-efficiency-optimization`
**Critic verdict:** REVISE — `return_directly` unsafe, Workflow A collapse unsafe, savings recalculated

## Problem Statement

Section-level layout scoping reduced layout data by 79% but only saved ~12% of total tokens because layout is a fraction of per-call cost. The dominant costs are system prompt (~8-10K), ai_context items (~6-8K), tool definitions (~3-4K), and accumulated chat history (~3-10K per call). We need to reduce these other components.

## Per-call cost breakdown (page_builder_agent, ~30K per call)

| Component | Size | Reducible? | How |
|-----------|------|-----------|-----|
| System prompt (agent instructions) | ~8-10K | Yes | Strip irrelevant workflows for edit ops |
| ai_context items (8 always_include'd) | ~6-8K | Yes | Strip non-essential items for edit ops |
| Tool definitions (6 tools) | ~3-4K | No | Framework-controlled |
| Layout JSON (scoped) | ~2.8K | Done | Already scoped |
| Chat history (tool calls/responses) | ~3-10K | Partially | Can't easily reduce within agent loop |
| Orchestrator overhead | ~10K | Yes | return_directly bypasses post-processing |

## Tier 1: Config-only changes (no code, immediate)

### 1a. `return_directly: 1` on page_builder and template_builder

**File:** `custom_recipes/findrop/config/ai_agents.ai_agent.canvas_ai_orchestrator.yml`

Change `return_directly: 0` to `return_directly: 1` for:
- `canvas_page_builder_agent` (line 279)
- `canvas_template_builder_agent` (line 285)

**Rationale:** After the sub-agent completes, the orchestrator currently receives the full response and writes a summary. With `return_directly: 1`, the sub-agent's response goes straight to the user, skipping one orchestrator round-trip.

**Estimated savings:** ~10K tokens (one orchestrator call with system prompt + full conversation history)

**Risk:** Low. The orchestrator's post-processing for page_builder responses is just a summary rewrite. The page_builder already writes user-facing confirmation text. However, Rule #5 (proactive title/description generation) triggers AFTER page building — need to verify that `return_directly` doesn't skip the title/metadata agent calls. If the orchestrator never sees the page_builder response, it can't trigger title generation.

**Mitigation:** Test with an empty-title page to confirm title generation still fires. If it doesn't, only enable `return_directly` for edits (which don't trigger title generation anyway). But this is a per-agent-config toggle, not per-operation. May need to leave it at 0 and accept the overhead.

### 1b. Remove Sales Training Deck from builder agents

**File:** `custom_recipes/ai_context_setup/recipe.yml`

Remove `'FinDrop Travel — Sales Training Deck'` from `always_include` for:
- `canvas_template_builder_agent` (line 23)
- `canvas_page_builder_agent` (line 40)

**Rationale:** The Sales Training Deck contains competitive positioning and sales messaging. It's not needed for component editing and arguably not needed for page building either (the content comes from the user's prompt, not the deck).

**Estimated savings:** ~2,500 tokens × every builder invocation

**Risk:** Low. For page builds, the user provides the content. For edits, the deck is irrelevant. Competitor names in Key Facts remain available.

## Tier 2: Event subscriber enhancements (custom module code)

### 2a. Strip non-essential ai_context items during edit operations

**File:** `web/modules/custom/canvas_ai_scoping/src/EventSubscriber/LayoutScopingSubscriber.php`

When `active_component_uuid` is set (edit mode), identify and remove non-essential ai_context blocks from the system prompt.

**Items to strip during edits:**
- `Visuals & Imagery` — not needed for text/prop edits
- `Content Structure: Product Pages` — structural guidance for building, not editing
- `General Page Building Guidelines` — same
- `FinDrop Key Facts & Value Propositions` — not needed for prop changes
- `Abbreviations, Spelling, Dates & Formatting` — keep only if editing text content

**Items to keep during edits:**
- `FinDrop Brand Guidelines` — always relevant
- `Writing Tone & Voice` — relevant for text edits

**Approach:** ai_context items are injected by `SystemPromptSubscriber` with a configurable prefix. They appear in the system prompt as blocks with identifiable titles. Our subscriber (running at priority -10, after ai_context at 0) can find and strip specific blocks via string matching.

**Need to verify:** What markers/separators does ai_context use between items? Check `SystemPromptSubscriber::onBuildSystemPrompt()` to understand the format.

**Estimated savings:** ~4-6K tokens per call × 3 loops = ~12-18K

**Risk:** Medium. We're doing string surgery on the system prompt. If ai_context changes its format, our stripping breaks silently (items leak back in). Need robust marker detection.

### 2b. Strip irrelevant workflow sections from page_builder prompt during edits

**File:** `web/modules/custom/canvas_ai_scoping/src/EventSubscriber/LayoutScopingSubscriber.php`

The page_builder system prompt contains 3 workflows:
- **Workflow A: Adding New Components** (~3-4K) — sections A1-A5
- **Workflow B: Modifying Existing Components** (~2K) — sections B1-B4
- **Workflow C: Moving Components** (~1K) — sections C1-C2

For edit operations, only Workflow B is needed. Strip A and C.

**Detection logic:** If the orchestrator's task prompt (passed to the sub-agent) references the `active_component_uuid` AND contains edit-intent words ("change", "update", "modify", "edit", "set"), classify as edit. Otherwise, keep full prompt.

**Approach:** The task prompt is added to `$this->chatHistory` as the first user message, not in the system prompt. We'd need to check the chat messages for intent classification. This is fragile. Alternative: use the presence of `active_component_uuid` as a proxy — if a component is selected, it's likely an edit, not an add.

**Estimated savings:** ~4-5K tokens per call × 3 loops = ~12-15K

**Risk:** Medium-high. If the user selects a component and says "add 3 cards below this", stripping Workflow A would break it. The subscriber can't reliably distinguish "edit this component" from "add something relative to this component" without understanding the user's intent.

**Alternative safer approach:** Instead of stripping workflows entirely, collapse them to one-line summaries: "For adding new components, see Workflow A (use set_component_structure tool)". This preserves the agent's awareness of capabilities while dramatically reducing tokens.

## Tier 3: Upstream proposals (document, don't implement)

### 3a. Orchestrator chat history windowing

**Problem:** The orchestrator accumulates the FULL conversation history. After a page build + 3 edits, the orchestrator sends ~80K+ of historical messages per call.

**Proposal:** Add `max_history_messages` or `max_history_tokens` config field to ai_agent entities. When history exceeds the limit, older messages are summarized or dropped, keeping only the last N turns.

**Where to propose:** ai_agents module issue queue on drupal.org + Foster Interactive conversation.

### 3b. Operation-type-aware context loading

**Problem:** ai_context loads the same items regardless of whether the operation is a build, edit, or SEO task.

**Proposal:** Add an `operation_scope` tag to context items (e.g., "build", "edit", "all") and filter based on the current operation type detected by the agent runner.

**Where to propose:** ai_context module issue queue.

### 3c. Lightweight edit path (no LLM)

**Problem:** Simple prop edits (change text, update color) don't need an LLM. The user specified exactly what to change and which component.

**Proposal:** Canvas UI detects "simple edit" patterns and routes them to a direct `update_component_data` call, bypassing the agent system entirely. Complex edits (ambiguous references, multi-component changes) still go through the AI.

**Where to propose:** Canvas module issue / Foster Interactive conversation.

## Execution Order

1. **1b** — Remove Sales Training Deck (config, safe, immediate)
2. **1a** — `return_directly` (config, test with title generation first)
3. **2a** — ai_context stripping (need to investigate format first)
4. **2b** — Workflow collapsing (safer alternative to stripping)
5. **3a-3c** — Write upstream proposals

## Verification

After each change:
1. Clear watchdog logs
2. Select a component on canvas_page/13
3. Send "Change this heading to [new text]"
4. Capture token metrics from logs
5. Compare against 111K baseline (B2 measurement)

After all Tier 1+2 changes:
1. Run the full page build test (driesnote prompt) to verify builds still work
2. Verify title/metadata generation still fires on new pages
3. Run an edit test and measure total tokens
4. Run an add-component test to verify we didn't break adding

## Expected Outcome

| Change | Est. Savings | Cumulative Total | Status |
|--------|-------------|-----------------|--------|
| Baseline (B2) | — | 111K | Measured (N=1) |
| Sales Training Deck removal | -2.5K (via excluded_subcontext) | ~108K | Needs investigation — arrives via parent subcontext, not always_include |
| ~~return_directly (if safe)~~ | ~~-10K~~ | ~~N/A~~ | **EXCLUDED** — breaks title/metadata generation (confirmed) |
| ai_context stripping for edits (P2) | -21K (corrected: 3 loops × 7K) | ~87K | Pending — ContextScopingSubscriber bug fix needed |
| Workflow collapsing for edits | -9-12K | ~75-78K | Medium risk — may break add-relative-to-selection |
| **Revised Target** | | **~75-80K** | |

## Open Questions

1. Does `return_directly: 1` prevent the orchestrator from triggering title/metadata generation after page builds?
2. What format does ai_context use when injecting items into the system prompt? Need to read `SystemPromptSubscriber` to know what markers to match.
3. Is there a way to detect "edit" vs "add" operations from within the BuildSystemPromptEvent subscriber? The task prompt is in chatHistory, not in the event.
4. Should we strip workflows entirely or collapse them to summaries? Stripping is more aggressive but risks breaking add/move operations when a component is selected.
