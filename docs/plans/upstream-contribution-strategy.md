# Upstream Contribution Strategy: Efficient AI Operations for Drupal

**Date:** 2026-03-27
**Status:** Revised (post-critic v2 — meta-critic round with proposal/drupal/perf critics)
**Branch:** `feat/ws1-efficiency-optimization`
**ADRs:** `docs/adrs/ADR-001` through `ADR-009`

---

## Executive Summary

The Drupal AI module ecosystem (ai_agents, ai_context, canvas_ai) has structural inefficiencies that make AI-assisted content editing unsustainably expensive. A simple heading change costs 111K LLM tokens because the system sends the full page layout, all context items, and full conversation history on every API call — and makes 5 calls for what is functionally a key-value update.

This strategy proposes 4 upstream contributions across 3 modules, organized into 3 architectural layers that compose into a coherent "efficient operations" system.

**Estimated savings by scenario** (from 111K current baseline for edits):

| Scenario | Proposals applied | Estimated result | Reduction |
|----------|------------------|-----------------|-----------|
| Simple deterministic edit (e.g., change heading text) | P4 (LLM bypass) | 0 tokens | 100% |
| Complex single-component edit (e.g., restyle section) | P1 + P2 (data reduction) | ~65-75K | ~35-45% |
| Complex edit in multi-turn session (5th edit) | P1 + P2 + P3 (all layers) | ~45-55K | ~50-60% |

P4's impact depends on what percentage of real-world edits qualify as "simple" — this is unknown and must be measured before claiming aggregate savings. For edits that go through the agent chain, P1 + P2 deliver ~35-45% reduction.

All proposals are framed as **performance and architecture improvements** — the same principles (scoped data loading, loop-aware injection, deterministic fast paths) that Drupal core applies to rendering and caching.

---

## Evidence Base

### Changes Already Applied to Recipe Configs

These optimizations are already in the recipe YAML. All measurements below were taken WITH these changes in place. They are **not** future work — they represent the current state.

| Change | Original | Current | File |
|--------|----------|---------|------|
| page_builder max_loops | 30 | 15 | `ai_agents.ai_agent.canvas_page_builder_agent.yml:280` |
| template_builder max_loops | 10 | 8 | `ai_agents.ai_agent.canvas_template_builder_agent.yml:149` |
| SEO agent max_loops | 10 | 5 | `ai_agents.ai_agent.drupal_canvas_seo_agent.yml:230` |
| page_builder `available_on_loop: [1]` | not set | set on `available_components` | page_builder config line 292 |
| template_builder `available_on_loop: [1]` | not set | set on BOTH tools | template_builder config lines 156, 163 |
| Orchestrator examples | 24 | 13 | orchestrator config |
| LayoutScopingSubscriber | n/a | active (section-level) | `canvas_ai_scoping` module |

### Measured Token Costs (FinDrop, March 2026)

All measurements taken with the above changes already applied. The "baseline" is the pre-layout-scoping state but WITH config changes.

| Scenario | Tokens | API Calls | Notes |
|----------|--------|-----------|-------|
| Full page build (pre-all-changes) | 253,593 | 10 | True original baseline |
| Page build (config tweaks applied) | 259,649 | 12 | Config changes alone don't help |
| Heading edit (region scoping) | 125,607 | 5 | 13% layout reduction |
| Heading edit (section scoping) | 111,004 | 5 | 79% layout reduction — CURRENT STATE |
| Heading edit (section + context strip) | 108,839 | 5 | Context strip didn't fire (bug) |

**Current baseline for remaining work: 111K tokens per heading edit, 259K per page build.**

### Per-Call Cost Breakdown (page_builder_agent, ~22K/call measured average)

| Component | Tokens/Call (estimated) | Reducible? | Proposal |
|-----------|------------------------|-----------|----------|
| System prompt (agent instructions) | 8-10K | Partially | — |
| ai_context items (7 always_include) | 6-8K | Yes | P2 |
| Tool definitions (6 tools) | 3-4K | No | Framework-controlled |
| Layout JSON (already scoped) | ~2.8K | Done | P1 (already applied locally) |
| Chat history (accumulates) | 3-10K | Yes | P3 |

**Reconciliation note:** Component midpoints sum to ~28.8K, but the measured average is ~22.2K (111K / 5 calls). The ~6.6K discrepancy likely reflects overlap (ai_context is appended to the system prompt, so their tokens partially overlap in the total). These component estimates need instrumented verification — a debug subscriber that logs `strlen()` of each segment as assembled in `BuildSystemPromptEvent`. This is Week 1 measurement work.

### Cost Translation

At current Anthropic Claude Sonnet pricing (~$3/MTok input, ~$15/MTok output, assuming 70/30 split):
- Heading edit (111K tokens): **~$0.73/edit**
- Page build (253K tokens): **~$1.67/build**
- 20-edit session at current cost: **~$14.60**
- Production site (1000 edits/day): **~$730/day, ~$22K/month** in LLM costs for editing alone

### What We Proved Does NOT Work

1. **Config-only changes** (prompt trim, loop caps): 259K vs 253K — negligible
2. **`available_on_loop`**: Skips tool re-execution on loops > 1, but loop-1 output persists in chat history. Net effect on total per-call tokens needs re-measurement — the tool output is not duplicated, but it remains in history. Savings are from avoiding tool re-execution overhead, not from reducing transmitted data.
3. **`return_directly: 1`**: Breaks title/metadata generation (orchestrator can't trigger follow-up tools)
4. **Workflow A collapsing**: `active_component_uuid` is present for both edits AND add-relative-to-selection — unsafe to infer edit intent

---

## The 3-Layer Architecture

These proposals are not 4 independent patches. They compose into 3 architectural layers:

```
Request arrives
    │
    ├── [Layer 3: Call Elimination — P4]
    │   Is this a deterministic edit? ──YES──► Direct prop update (0 tokens)
    │
    NO
    │
    ▼
    ├── [Layer 1: Data Reduction — P1 + P2]
    │   ├── Scope layout to active region (P1)
    │   └── Load only operation-relevant context items (P2)
    │
    ├── [Layer 2: History Management — P3]
    │   └── Window orchestrator cross-turn history
    │
    ▼
    Agent system processes with reduced data + bounded history
```

Each layer is independent and additively beneficial.

**Note on `available_on_loop`:** This mechanism (already applied to builder agents' `default_information_tools`) skips tool re-execution on loops > 1. The tool output from loop 1 remains in chat history and is sent on every subsequent call. The savings come from avoiding redundant tool calls (the tool doesn't re-fetch layout/components), NOT from reducing data transmitted to the LLM. The loop-1 output persists in history. This is a different mechanism from P2's loop-aware context injection, which prevents context items from being re-appended to the system prompt.

---

## Contribution Sequencing

### Filing Order (strategic + dependency-driven)

**Note on P3a:** The original strategy proposed adding `getLoopIteration()` to `BuildSystemPromptEvent`. Post-critic code review revealed that `AgentStartedExecutionEvent` already exposes `getLoopCount()` (line 81-83) and fires BEFORE `BuildSystemPromptEvent` (line 449 vs 457 in `AiAgentEntityWrapper`). The ai_context `SystemPromptSubscriber` already subscribes to `AgentStartedExecutionEvent`. **P3a is eliminated.** P2 implements loop-awareness using the existing event API — no upstream framework change needed.

**Off-by-one note:** `getLoopCount()` returns 0 on the first loop (it fires before `$this->looped++`). P2's subscriber must treat loop 0 as "first iteration — inject context."

**1. P4 — Lightweight Edit Path** (file first)
- **Why first:** Most aligned with Drupal community values. Argues *against* using LLMs where unnecessary. Easiest to explain: "Why are we using a language model for string replacement?"
- **Community reception:** Highest. Maps to the principle that deterministic tooling beats probabilistic approaches.
- **Dependencies:** None.

**2. P1 — Native Region Scoping** (file second)
- **Why second:** Already proven via custom module. 79% layout reduction measured. Full proposal already written for Foster Interactive. Lowest technical risk.
- **Community reception:** High. Data loading optimization — familiar pattern.
- **Dependencies:** None.

**3. P2 — Loop-Aware Context Injection** (file third)
- **Why third:** No upstream dependency — uses existing `AgentStartedExecutionEvent::getLoopCount()`. By now P4 and P1 have built contributor credibility.
- **Community reception:** Conditional. Sound principle, extends existing agent-aware selection pattern.
- **Dependencies:** None (P3a eliminated — loop count already available).

**4. P3b — Orchestrator History Windowing** (file last)
- **Why last:** Highest risk. `allRequiredToolsRan()` breaks with naive windowing. Needs careful scoping to orchestrator-level cross-turn history only.
- **Community reception:** Mixed. Windowing is controversial; may be deferred to a future major version.
- **Dependencies:** None.

### Implementation Order (for local development)

1. P1 (region scoping) — already proven, extend custom module
2. P2 (context scoping) — fix ContextScopingSubscriber, use existing `AgentStartedExecutionEvent::getLoopCount()`
3. P4 (lightweight edit path) — frontend + backend, most design work
4. P3b (history windowing) — defer until upstream discussion matures

---

## Proposal Specifications

### P1: Native Region Scoping (canvas_ai)

**drupal.org Issue Title:** "Reduce layout data sent to AI agents during component editing"

**Description:**
When a user edits a single component, the system serializes the full page layout (all components, all regions, all props) and sends it to the LLM. On a 30-component page, this sends 8-12KB of layout data when only the target component's 200-400 bytes are relevant.

Proposed: When `active_component_uuid` identifies a specific component, serialize only that component's containing section. Include a lightweight region index (section names + node paths) so agents can reason about the full page structure without full data.

**Patch Scope:**

| File | Change | LOC |
|------|--------|-----|
| `ui/src/components/aiExtension/AiWizard.tsx` | Scope `transformLayout()` + filter `textPropsMapString` | ~60 |
| `canvas_ai/src/Controller/CanvasBuilder.php:167-169` | Accept `scope` param, store scoped layout | ~40 |
| `canvas_ai/src/CanvasAiTempStore.php` | Region index get/set methods | ~20 |
| `canvas_ai/src/Plugin/AiFunctionCall/GetCurrentLayout.php` | Return scoped layout when scope is active in tempstore (currently returns full unscoped layout) | ~20 |
| `canvas_ai/src/Plugin/AiFunctionCall/SetAIGeneratedTemplateData.php` | Region-aware validation | ~15 |
| `canvas_ai/src/Plugin/AiFunctionCall/MoveComponentInPage.php` | Cross-region boundary detection | ~15 |

**Test Plan:**
- Scoped requests serialize only target section (unit)
- Unscoped requests send full layout — backwards compatible (unit)
- Region index is accurate across different page configurations (kernel)
- Cross-region moves work with region index only (kernel)
- Template builder always gets full layout (kernel)
- Multiple loop iterations maintain scope consistency (integration)

**Objection Handling:**

| Likely Objection | Response | Evidence |
|-----------------|----------|----------|
| "How was 79% measured? On one page?" | Measured on 30-component page. Must add benchmarks across varying layouts with worst-case analysis. | ADR-005 measurement data |
| "What if agent needs cross-region context?" | Region index provides full page map. Agent can call `get_current_layout` tool for on-demand full access. | Escape hatch design |
| "This is AI-specific optimization" | It's a data payload reduction for page editing operations. Same principle as entity view modes — don't load what you don't need. | Drupal core precedent |

**Acceptance Criteria:**
- Layout JSON for single-component edits reduced by ≥70%
- Full layout mode unchanged (backwards compatible)
- Cross-region operations tested and working
- Region index available for agent reasoning

---

### P2: Loop-Aware Context Injection (ai_context + ai_agents)

**drupal.org Issue Title:** "Extend context selection with loop-aware injection to avoid redundant re-injection"

**Description:**
The ai_context module already has agent-aware context selection — `AiContextSelector::select()` accepts an `$agentId` parameter and loads per-agent `always_include` / `excluded_subcontext` configuration. This agent-level scoping works well.

However, `BuildSystemPromptEvent` fires on every loop iteration within an agent's execution, and `SystemPromptSubscriber` re-appends all selected context items every time. For agents with 7 `always_include` items (~6-8K tokens), this injects 6-8K tokens of identical content on every loop — content the LLM already has from the first iteration.

Proposed: **Extend** the existing agent-aware selection with **loop-aware injection**. No upstream framework change is needed — `AgentStartedExecutionEvent` already exposes `getLoopCount()` (line 81-83) and the `SystemPromptSubscriber` already subscribes to it (line 59). The subscriber caches the loop count from `AgentStartedExecutionEvent` and checks it during `onPreSystemPrompt()`, skipping re-injection when loop > 0 (note: `getLoopCount()` returns 0 on the first iteration because it fires before `$this->looped++`).

This follows the existing pattern — `AiContextSelector` already filters by agent; this adds filtering by loop iteration as a second dimension using an event the subscriber already listens to.

**Patch Scope:**

| File | Change | LOC |
|------|--------|-----|
| `ai_context/src/EventSubscriber/SystemPromptSubscriber.php` | Cache loop count from `AgentStartedExecutionEvent::getLoopCount()` in `onAgentStarted()`, check in `onPreSystemPrompt()` — skip injection when loop > 0 | ~20 |

**No upstream dependency.** Uses existing `AgentStartedExecutionEvent` API.

**Test Plan:**
- Context items injected on loop 0 (first iteration, identical to current behavior) (unit)
- Context items NOT re-injected on loop 1+ (unit)
- Backwards compatible: without the subscriber change, current inject-every-loop behavior unchanged (kernel)
- Items using keyword-based selection (which may change between loops based on new messages) can opt out of loop-aware skipping (unit)

**Objection Handling:**

| Likely Objection | Response | Evidence |
|-----------------|----------|----------|
| "The existing agent-aware selection already handles this" | Agent-aware selection filters WHICH items load. This addresses WHETHER to re-inject on subsequent loops. Orthogonal dimensions — same items, fewer re-injections. | `AiContextSelector::select($task, $agentId)` already works per-agent |
| "What if context items change between loops?" | Default is inject-every-loop (backwards compatible). Loop-aware skipping is opt-in. Keyword-matched items that depend on new messages can declare themselves as "re-inject always." | Backwards compatibility |
| "This only changes one module's subscriber" | Correct — the change is entirely within ai_context. It uses an existing ai_agents event API (`AgentStartedExecutionEvent::getLoopCount()`) that the subscriber already listens to. | No cross-module patch needed |
| "Sending identical data isn't a problem — the model has it in context" | It IS in context, which means re-injecting it adds duplicate content to the system prompt. The LLM processes all system prompt tokens on every call regardless of duplication. For the page_builder agent (7 items, ~7K tokens, 3-4 loops per edit): ~7K × 3 skipped loops = ~21K wasted tokens per edit operation. | ai_observability logs (needs instrumented verification) |

**Acceptance Criteria:**
- Context injection is loop-aware (configurable, default: every loop for backwards compatibility)
- Per-call context cost reduced by 6-8K tokens on loops 2+
- No regression in context availability on loop 1
- Keyword-matched items can opt out of loop-aware skipping

---

### P3: Orchestrator History Windowing (ai_agents)

**drupal.org Issue Title:** "Add configurable conversation history limit for multi-turn agent sessions"

**Description:**
In multi-turn conversations (e.g., build page → edit heading → add footer → change color), the orchestrator accumulates the FULL conversation history. After 5 turns, the orchestrator sends 80K+ of historical messages per API call — messages from operations that completed turns ago.

Proposed: Add a configurable `max_history_turns` to the **provider-level settings** (not the agent config entity), allowing sites to cap how many previous turns are included. This is environment-specific tuning (depends on model, context window), not site configuration.

**Critical constraint:** History windowing must ONLY apply to the **orchestrator's cross-turn history**. Within a single `determineSolvability()` recursion chain (a single operation's loop), history must remain intact. The `allRequiredToolsRan()` method at `AiAgentEntityWrapper.php:1022-1050` scans the full history to verify tool usage — windowing within an operation would cause false negatives and infinite loops.

**Patch Scope:**

| File | Change | LOC |
|------|--------|-----|
| `ai_agents/config/schema/ai_agents.schema.yml` | Add `max_history_turns` to provider settings | ~5 |
| `ai_agents/src/PluginBase/AiAgentEntityWrapper.php:524` | Window history before ChatInput construction | ~30 |
| `ai_agents/src/PluginBase/AiAgentEntityWrapper.php:1022` | Exclude windowed messages from `allRequiredToolsRan()` scope | ~15 |

**Objection Handling:**

| Likely Objection | Response | Evidence |
|-----------------|----------|----------|
| "This is a vendor cost concern, not architecture" | It's a resource concern — sending 80K+ of stale messages per call is redundant computation analogous to uncapped log buffers. | 80K measured after 5 turns |
| "Token limits belong in the provider config, not agent config" | Agreed — proposed as provider-level setting, not agent entity field. Environment-specific, not exportable. | Config design principle |
| "Windowing breaks tool verification" | Only window cross-turn history. Within a single operation, history is intact. `allRequiredToolsRan()` only needs current-operation history. | Architectural analysis |
| "Loop-aware context should be in the same issue" | P2 (loop-aware context injection) is a separate concern — it modifies ai_context, not ai_agents. Different modules, different maintainers. | Scope management |

---

### P4: Lightweight Edit Path (canvas_ai)

**drupal.org Issue Title:** "Add direct prop update path for deterministic component edits"

**Description:**
When a user selects a specific component and provides an explicit value change ("Change heading to X"), the system routes through the full agent chain: orchestrator → page_builder_agent → 3-5 LLM loops → tool call. This costs 111K tokens and takes 10-30 seconds for what is functionally a single `update_component_data` call.

Proposed: Add a frontend detection layer that identifies deterministic edits (single component + recognized prop + explicit value) and routes them directly to the update endpoint. Complex edits (ambiguous references, multi-component, style reasoning) continue through the agent chain.

The classification is **pattern-based with conservative scope**: the detector fires only when the user's input matches a narrow set of explicit edit patterns (e.g., "change/set/update [prop] to [value]") AND a component is selected. Any input containing add/insert/create/new keywords falls through to the AI path. The prop name is resolved against the component schema's display labels. This is a constrained pattern matcher, not a general NLU system — it handles the ~60% of edits that are unambiguous, and everything else goes to the AI.

**Edit/add disambiguation:** The detector explicitly checks for add-intent keywords ("add", "insert", "create", "new", "below", "above", "after", "before") and falls through to the AI path if any are present. A selected component + "add a testimonial below this" will NOT be classified as a deterministic edit.

**Patch Scope:**

| File | Change | LOC |
|------|--------|-----|
| `canvas/ui/src/components/aiExtension/AiWizard.tsx` | Pattern-based edit detection, prop name resolution against schema labels, routing logic, add-intent keyword check | ~200-300 |
| `canvas_ai/src/Controller/CanvasBuilder.php` | New `renderDirect()` method with CSRF validation + `'use Drupal Canvas AI'` permission check (matching existing `render()` security) | ~80 |
| `canvas_ai/canvas_ai.routing.yml` | New `/canvas-ai/direct-edit` route with `_permission: 'use Drupal Canvas AI'` | ~10 |
| `canvas_ai/src/Plugin/AiFunctionCall/UpdateComponentData.php` | Direct invocation support (extract validation logic for reuse) | ~30 |

**Test Plan:**
- Exact prop match + literal value → direct path (unit)
- Ambiguous reference → agent path (unit)
- Multi-component edit → agent path (unit)
- Unknown prop name → agent path (unit)
- Direct edit produces correct result (kernel)
- Brand voice NOT applied on direct path — documented limitation (docs)
- Performance comparison: direct vs agent (benchmark)

**Objection Handling:**

| Likely Objection | Response | Evidence |
|-----------------|----------|----------|
| "How do you define 'simple'?" | Pattern-based: user input matches "change/set/update [prop] to [value]" + component selected + prop resolves against schema. Conservative scope — add-intent keywords ("add", "insert", "create", "new") always fall through to AI. | Component metadata API + keyword exclusion |
| "What about prop name resolution?" | Component metadata provides display labels → prop IDs mapping. The frontend already has this data for rendering the component form. Ambiguous prop names (no match or multiple matches) fall through to AI. | `GetMetadataOfComponents.php:92` |
| "This bypasses brand voice enforcement" | Documented limitation. Direct edits are explicit user intent — the user typed exactly what they want. UI indicator shows "direct edit" vs "AI-assisted." | User intent argument |
| "Scope creep — users will want more patterns" | Strict scope: only patterns with 100% deterministic mapping. Conservative boundary. Complex edits fall through to AI. | ADR-004 |

---

## Phase 2 Vision: Selection-First Editing (ADR-006/007)

P1-P4 fix the current AI path. ADR-006 and ADR-007 describe a longer-term paradigm shift: **making the AI the escalation path, not the default path for all editing.** User selection narrows context. Templates, presets, and content tokens expand the deterministic surface area. The AI handles creative and ambiguous operations only.

**These are internal vision documents, not upstream proposals.** ADR-006/007 prescribe a UX philosophy that is Foster Interactive's decision to make. We do not reference them in drupal.org issues. P1-P4 are presented as standalone improvements; ADR-006/007 inform our local prototyping and our conversations with Canvas maintainers.

**How P1-P4 are stepping stones toward the vision:**
- P4 (deterministic bypass) is the first concrete implementation of ADR-006's selection-first principle
- P1 (region scoping) is the first step toward ADR-006's context envelopes (section-level → component-level)
- P2 (loop-aware context) reduces the cost of the AI path, making the AI-vs-deterministic boundary less costly to cross
- P3 (history windowing) bounds session-level growth for multi-turn creative operations

**Projected impact (estimated, sensitivity varies with edit-type distribution):**

| Edit-type split (direct/simple/complex) | Session reduction |
|-----------------------------------------|-------------------|
| 60/25/15 (optimistic) | ~87-90% |
| 40/30/30 (moderate) | ~70-77% |
| 20/30/50 (pessimistic) | ~53-63% |

The edit-type split is unknown and must be measured via usage telemetry before citing specific aggregate numbers. See ADR-008 for the local validation plan.

---

## Competing Alternatives Analysis

### Option A: Do Nothing (keep custom module workarounds)

**What it looks like:** Maintain `canvas_ai_scoping` locally. Accept 111K tokens per edit. Work around framework limitations.

**Pros:** No upstream coordination effort. No risk of rejection. Ship immediately.

**Cons:** Fragile string replacement on system prompts. Breaks silently when upstream modules change format. No benefit to community. Custom code per deployment. Layout scoping alone only saves 12%.

**Verdict:** Unsustainable. The custom module is a proof of concept, not a solution.

### Option B: Upstream Everything (all 4 proposals)

**What it looks like:** File all 4 issues on drupal.org. Provide patches with tests. Engage in review cycles.

**Pros:** Maximum community benefit. Cleanest architecture. No local workarounds needed long-term.

**Cons:** 24+ week timeline for all proposals to land. Review bandwidth from maintainers is limited. Risk of rejection on P3b (history windowing) and P4 (lightweight edit). High coordination overhead.

**Verdict:** Correct long-term strategy, but needs sequencing and patience.

### Option C: Upstream Critical + Extend Locally (RECOMMENDED)

**What it looks like:** File all 3 issues (P4, P1, P2 — P3a eliminated, P3b deferred). Provide patches for P4 and P1 first (strongest positioning, lowest risk). Maintain and extend `canvas_ai_scoping` locally while upstream discussion matures. File P2 after building credibility.

**Pros:** Immediate local improvements. Upstream credibility built incrementally. Lower coordination risk. Community benefits from the easiest wins first.

**Cons:** Dual maintenance (local module + upstream patches) for 3-6 months. Local module may diverge from upstream direction.

**Verdict:** Best risk/reward. Ship locally now, contribute incrementally.

---

## Pre-Mortem: What Could Cause These Contributions to Fail?

### 1. Maintainer bandwidth (Probability: HIGH)
The ai_agents and ai_context modules are maintained by the Drupal AI initiative contributors who are shipping features fast. Performance patches compete for attention with new capabilities.
**Mitigation:** Make patches self-contained with tests. Offer to maintain. Start with the easiest wins to build trust.

### 2. Architectural disagreement on P2 (Probability: MEDIUM)
Maintainers may prefer operation scope on the context entity rather than in the subscriber, or may want a completely different approach.
**Mitigation:** File as RFC first. Present our approach as one option. Be prepared to implement their preferred approach.

### 3. Canvas maintainer divergence on P4 (Probability: MEDIUM)
Foster Interactive may have their own roadmap for lightweight edits that conflicts with our proposal.
**Mitigation:** We already have a relationship with Foster Interactive. Discuss before filing. The region scoping proposal is already written for them.

### 4. Community skepticism about AI module contributions (Probability: LOW-MEDIUM)
Drupal core committers (notably catch) are skeptical of LLM-related contributions. These proposals target contrib AI modules, not core, which reduces friction — but high-profile AI contributors can still attract scrutiny.
**Mitigation:** Be honest about the AI context. Lead with architecture and measurable data (tokens as payload metrics). Keep patches narrowly scoped with tests. Build credibility through small wins (P4, P1) before larger proposals. Don't try to hide that these are AI module improvements — the maintainers know their own modules.

### 5. The framework changes direction (Probability: LOW)
The ai_agents module is in active development. A major refactor could make our patches obsolete.
**Mitigation:** Keep patches minimal and focused. The principles (loop-aware events, scoped data loading) apply regardless of framework internals.

---

## Backcasting: Working Backward from "All 4 Merged"

**End state:** P1, P2, P4 merged upstream. P3 in discussion or deferred. `canvas_ai_scoping` module retired. Edit operations cost <40K tokens (AI path) or 0 tokens (deterministic path).

**Week 24:** P4 (lightweight edit path) merged after 2 review cycles.
- Required: P1 merged, giving us credibility. Pattern-based detection tested across component types.

**Week 18:** P3 (history windowing) merged or deferred to next major.
- Required: `allRequiredToolsRan()` scoping fix landed. Provider-level config accepted as the right home.

**Week 12:** P2 (context scoping) merged.
- Required: Uses existing `AgentStartedExecutionEvent::getLoopCount()` — no upstream dependency. Subscriber approach validated by maintainers.

**Week 8:** P1 (region scoping) merged.
- Required: Foster Interactive buy-in (already have relationship). Benchmarks across multiple page configurations (5, 15, 30 components). Tests passing in CI.

**Week 4:** P4 + P1 filed on drupal.org with patches. Local `canvas_ai_scoping` module extended with ContextScopingSubscriber fix and loop-aware injection.

**Week 1:** Benchmark methodology established. Repeated measurements (5x) with mean + range. Component schema surveyed for P4 edit coverage. ADRs finalized.

---

## Evidence Strategy

### Standard Benchmark Protocol

Every issue must include reproducible benchmarks:

1. **Environment:** Drupal version, PHP version, AI provider, model
2. **Test scenario:** Specific page (component count, layout complexity), specific prompt
3. **Metrics captured:** Total tokens (input + output), API call count, wall clock time
4. **Repetitions:** ≥3 runs, report mean + range
5. **Methodology:** ai_observability enabled, token counts from provider responses

### Per-Issue Evidence Requirements

| Proposal | Must Show | Comparison |
|----------|----------|------------|
| P1 (Region scoping) | Layout bytes before/after across 3+ page configs | Full page vs. scoped section |
| P2 (Context scoping) | Context tokens per loop before/after | Every-loop vs. loop-1-only |
| P3 (History windowing) | Orchestrator history size vs. turn count | Unbounded vs. windowed |
| P4 (Lightweight edit) | Token count + latency for simple edit: agent vs. direct | 111K tokens / 10-30s vs. 0 tokens / <1s |

### Presentation in drupal.org Issues

- Lead with the **before number** (e.g., "111,004 tokens for a heading text change")
- Show the **per-component breakdown** (system prompt, context, layout, history)
- Include a **table with multiple page sizes** (5, 15, 30 components)
- Reference **analogous Drupal core patterns** that solve the same class of problem
- Attach the benchmark script or drush command for reproducibility

---

## Community Framing Guidelines

These are AI modules. Their maintainers think about tokens constantly. Pretending otherwise would be disingenuous and damage credibility. Instead: **lead with the architectural principle, use token counts as concrete evidence.**

### Framing Approach

- **Be honest about tokens.** Token counts are a concrete, measurable proxy for "unnecessary data being sent to an external API." Use them the same way you'd use response times or memory usage in a core performance issue.
- **Connect to Drupal architectural patterns.** The principles ARE the same as entity view modes (scoped data loading), lazy builders (defer work until needed), and cache tags (consumer-declares-relevance). Draw the analogy, but don't pretend these aren't AI-specific implementations of those patterns.
- **Frame the problem as architecture, not cost.** "The system re-sends 6-8K of identical context on every loop iteration" is an architecture problem. "This costs $X per API call" is a business problem. Lead with architecture; let readers draw their own cost conclusions.
- **One concern per issue, narrow scope.** This is standard Drupal contribution practice, not an AI-specific tactic.

### DO

- Lead with measurable data (token counts, API call counts, payload sizes)
- Reference analogous Drupal core patterns where the architectural principle genuinely applies
- Include reproducible benchmarks with methodology
- Keep scope narrow — one concern per issue
- File follow-ups proactively
- Use drupal.org terminology (patch, RTBC, follow-up, MR)
- Acknowledge this is about AI module efficiency — the modules' purpose is AI

### DO NOT

- Disguise AI concerns as non-AI concerns (maintainers will see through it)
- Frame as cost savings (architecture concern, not business concern)
- File all issues simultaneously (drip-feed, build credibility)
- Combine loop-aware events and history windowing in one issue
- Propose heuristic/ML-based classifiers for the simple edit detector
- Overstate the analogy to core caching — these are related principles, not identical problems

### The Key Sentence

> "We profiled a Canvas site's AI agent chain and found three structural inefficiencies: the system re-sends 6-8K of identical context items on every loop iteration, sends the full 30-component layout when editing a single heading, and routes deterministic string replacements through multi-step agent chains. These follow the same anti-patterns that entity view modes, lazy builders, and the render cache address in core — loading more data than the operation requires."

---

## Local Implementation: What to Build Now

While upstream proposals are in review, extend `canvas_ai_scoping`:

### Immediate (this week)

1. **Fix ContextScopingSubscriber** — debug the separator format mismatch. Enable `log_input: true`, capture the actual system prompt format, fix string matching.
2. **Investigate Sales Training Deck injection path** — the Deck is NOT in `always_include` for builders (it was already excluded for orchestrator, title, metadata agents). It likely arrives as a `subcontext_type: required` child of Brand Guidelines (parent entity `6f634162`), which IS in `always_include`. Fix: add `'FinDrop Travel — Sales Training Deck'` to `excluded_subcontext` for both `canvas_template_builder_agent` and `canvas_page_builder_agent` in the recipe. Verify with ai_context debug logging that it no longer appears in builder prompts.
3. **Commit all working code** — clean up `\Drupal::logger()` debug calls.

### Short-term (weeks 2-4)

4. **Loop-aware context injection** — add a custom subscriber that checks loop iteration (read from agent wrapper if accessible) and skips ai_context re-injection on loop > 1.
5. **Measurement suite** — drush command that runs standard test scenarios and captures token metrics.
6. **Multi-page benchmarks** — measure across 5, 15, 30 component pages for issue evidence.

### Medium-term (weeks 4-8)

7. **Simple edit detector prototype** — frontend TypeScript, conservative classification, fall-through to agent path.
8. **Direct edit endpoint** — backend route that invokes `update_component_data` without the agent system.

---

## Timeline

| Phase | Weeks | Activities | Deliverables |
|-------|-------|-----------|-------------|
| **Foundation** | 1-2 | Fix local module, run 5x repeated measurements, instrument per-component breakdown, survey component schemas for P4 | Working local module, benchmark suite with mean+range, measurement data |
| **Show & Prove** | 3-6 | Build P4 prototype, context envelope prototype (ADR-006), multi-page benchmarks | Local demos of deterministic editing + reduced context |
| **First Issues** | 7-8 | File P4 + P1 on drupal.org with patches, tests, and benchmark evidence | 2 drupal.org issues with MRs |
| **Build Credibility** | 9-16 | Engage in review, iterate on feedback, file P2 | 3 issues total, P1 approaching RTBC |
| **Advanced** | 17-24 | P3 filed, P4 refined, upstream patches landing | Contributions merged, local module retired |

**Milestones:**
- Optimistic: P1 merged by week 12, P2 in review
- Realistic: P1 merged by week 16, P2 filed, P4 in discussion
- Pessimistic: P1 in review at week 20, others in discussion

---

## Success Criteria

1. Edit operations < 40K tokens (down from 111K) with local optimizations
2. ≥2 upstream patches merged within 6 months
3. `canvas_ai_scoping` module retired (replaced by upstream features)
4. Benchmark suite established and reproducible
5. Relationship with Canvas maintainers (Foster Interactive) strengthened
6. Honest, evidence-based framing accepted by module maintainers

---

## Cross-References

- **ADRs:** `docs/adrs/ADR-001` through `ADR-009` (001-005: upstream proposals, 006-007: internal vision, 008-009: execution discipline)
- **Existing proposal:** `docs/proposals/canvas-ai-region-scoping.md` (Foster Interactive)
- **WS1 plan:** `docs/plans/ws1-efficiency-optimization.md`
- **Measurement data:** `docs/plans/ws1-baseline-measurement.md`
- **Static audit:** `docs/audit/canvas-agent-static-audit.md`
- **Remaining levers:** `.omc/plans/token-reduction-remaining-levers.md`
- **Handoff:** `docs/handoff/handoff-upstream-strategy.md`

## Architectural References (from code analysis)

- `AiAgentEntityWrapper.php:424` — `determineSolvability()` entry (loop engine)
- `AiAgentEntityWrapper.php:449` — `AgentStartedExecutionEvent` dispatch (fires BEFORE looped++)
- `AiAgentEntityWrapper.php:450` — `$this->looped++` (loop count increments here)
- `AiAgentEntityWrapper.php:455-458` — `BuildSystemPromptEvent` dispatch (every loop, AFTER looped++)
- `AiAgentEntityWrapper.php:524` — ChatInput construction (windowing insertion point)
- `AiAgentEntityWrapper.php:890-936` — `getDefaultInformationTools()` with `available_on_loop`
- `AiAgentEntityWrapper.php:1022-1050` — `allRequiredToolsRan()` (breaks with naive windowing)
- `AgentStartedExecutionEvent.php:81-83` — `getLoopCount()` already exists (P3a unnecessary)
- `SystemPromptSubscriber.php:59` — already subscribes to `AgentStartedExecutionEvent`
- `SystemPromptSubscriber.php:87-144` — Context injection into system prompt
- `AiContextSelector.php:82` — Context selection logic (already agent-aware via `$agentId` param)
- `AiContextRenderer.php:157` — Context item format (fragile string matching target)
- `CanvasBuilder.php:69-314` — Request entry point, tempstore setup
- `GetCurrentLayout.php:70-71` — Layout retrieval from tempstore (needs scoping for P1)
- `GetCurrentLayout.php:70-71` — Layout retrieval from tempstore
