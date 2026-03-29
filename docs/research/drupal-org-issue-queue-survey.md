# Drupal.org Issue Queue Survey: Efficiency-Related Discussions

**Date:** 2026-03-28
**Author:** Document Specialist (research task for WS1 token efficiency work)
**Branch:** `feat/ws1-efficiency-optimization`
**Purpose:** Find existing upstream discussions that overlap with FinDrop's P1–P4 proposals before filing new issues

---

## Executive Summary

The drupal.org issue queues for `ai_agents`, `ai_context`, and `canvas` contain active work that directly intersects with all four of our upstream proposals. The most significant findings:

- **P1 (Region Scoping):** `#3545816` is the canonical upstream issue for two-pass component selection. It diagnoses the same 13K-token component context problem we measured, proposes the same two-step fetch pattern, and has an active MR. Filing a separate issue is unnecessary — we should contribute to this one.
- **P2 (Loop-Aware Context Injection):** No existing issue precisely targets loop-aware system prompt injection in `ai_context`. The `SystemPromptSubscriber` re-injection problem is undocumented in the queue. There is an `available_on_loop` memory issue (`#3524351`) that is adjacent but focuses on tool memory, not context items.
- **P3b (History Windowing):** `#3555239` documents the chat history corruption bug in Canvas AI and `#3458607` raises the broader history-vs-context-window tradeoff. Neither proposes windowing. Our proposal is net-new but informed by these discussions.
- **P4 (Lightweight Edit Path):** `#3549232` proposes the `update_component_data` tool — exactly the deterministic prop-update pathway P4 requires. Active MR exists. This is the highest-value existing issue to support.

**Recommendation:** Contribute patches or comments to `#3545816`, `#3549232`, and `#3555239` before filing new issues. P2 and P3b are genuinely net-new — file them after establishing contributor credibility via the three existing issues.

---

## Module 1: ai_agents

**Issue queue:** https://www.drupal.org/project/issues/ai_agents (126 open issues as of March 2026)

### Highly Relevant Issues

#### #3524351 — Add the possibility to add default information tools to tool result memory
- **URL:** https://www.drupal.org/project/ai_agents/issues/3524351
- **Status:** Active (has MR `!126`)
- **Filed:** May 2025
- **Category:** Feature request
- **Summary:** Currently, `default_information_tools` are injected into the system prompt. This issue proposes that when `available_on_loop` is set, instead of re-executing the tool and re-injecting into the system prompt, the tool result is added to chat history as a faked tool message. This keeps the data available across loops without system prompt re-injection.
- **Key quote:** "We already have `available_on_loop`, that says to inject into system message on one specific instance, but instead we could reutilize this to be used to inject into memory."
- **Relationship to our proposals:**
  - **P2 (Loop-Aware Context Injection):** Closely adjacent. This issue addresses tool memory; P2 addresses `ai_context` item injection via `SystemPromptSubscriber`. Different mechanism, same underlying problem: data re-injected into system prompt on every loop when it only needs to be sent once.
  - The `available_on_loop` mechanism this issue extends is the same mechanism documented in our ADR-002.
- **Action:** Read the MR diff. If our P2 work targets `ai_context`'s `SystemPromptSubscriber`, this issue shows the upstream community's thinking on the adjacent tool-memory problem. Cross-reference when filing P2.

#### #3523967 — Use the Chat History in the AiAgentEntityWrapper if wanted
- **URL:** https://www.drupal.org/project/ai_agents/issues/3523967
- **Status:** Active (has MR `!122`)
- **Filed:** May 2025
- **Category:** Feature request
- **Summary:** `AiAgentEntityWrapper` cannot currently use chat history alone (without a Task object). This issue makes it possible to run the agent with chat history only, without requiring a persistent Task entity.
- **Relationship to our proposals:**
  - **P3b (History Windowing):** Foundational. If the agent cannot properly consume chat history passed from outside, windowing is impossible to implement correctly. This issue must be resolved or stable before P3b is viable.
  - Also relevant to `#3555239` (Canvas AI orchestrator history corruption).
- **Action:** Review MR status. If merged, P3b can rely on this mechanism for passing windowed history.

#### #3515670 — Refine function call context based on value restrictions
- **URL:** https://www.drupal.org/project/ai_agents/issues/3515670
- **Status:** Active (has MR `!72`)
- **Filed:** March 2025
- **Category:** Feature request
- **Summary:** Tool `property_restrictions` (forced/allowed values) currently don't affect the function schema sent to the LLM. The LLM sees the unrestricted schema and can suggest invalid values that get silently overridden. This issue proposes modifying the context definitions to include `enum` for allowed values and `constant` for forced values — so the LLM's output is constrained by schema, not just post-processed.
- **Relationship to our proposals:**
  - **P1 (Region Scoping):** Tangentially relevant. When region scoping is active, agents that call layout tools should receive a scoped schema. This issue's pattern (modifying function context based on runtime constraints) is the right approach for that.
  - **General efficiency:** Reducing LLM retry loops caused by invalid tool outputs reduces token cost. Getting schemas right the first time is a prerequisite for reducing loop counts.
- **Action:** Note this as prior art for schema-driven constraint injection.

#### #3553458 — Agents failing to determine solvability forever stuck in "started" state
- **URL:** https://www.drupal.org/project/ai_agents/issues/3553458
- **Status:** Needs review (Major Bug, has MR)
- **Filed:** October 2025
- **Category:** Bug report
- **Summary:** When an agent hits `max_loops` during `determineSolvability()`, `AgentStartedExecutionEvent` fires (creating tracking state) but `AgentFinishedExecutionEvent` never fires. The fix moves the `$this->looped++` and `max_loops` check before event dispatch.
- **Relationship to our proposals:**
  - **P2 (Loop-Aware Context Injection):** Uses the same `getLoopCount()` counter we rely on in ADR-002. Confirms that `AgentStartedExecutionEvent` fires before `$this->looped++` (our off-by-one note). This bug fix must be applied or merged before our loop-counting logic is reliable in production.
  - **General:** Documents the `max_loops` mechanics that our config changes (reducing page_builder from 30→15 loops) depend on.
- **Action:** Monitor for merge. If still unmerged when we file P2, reference this issue.

#### #3556141 — [Meta] Move and improve AI Agents in AI Core roadmap
- **URL:** https://www.drupal.org/project/ai/issues/3556141
- **Status:** Active (Major, filed on `ai` project)
- **Filed:** November 2025
- **Category:** Plan
- **Summary:** The agent runner, agent config, and tool execution are being moved from `ai_agents` into `ai` core. Plugin-based agents are deprecated. The new architecture will be purely config-entity-based, with a stable Tool API.
- **Key architectural changes planned:**
  - `ai_agents` entity moves to AI Core
  - Tool API moves to beta and inclusion in AI Core
  - `ChatProcessor` and `ChatConsumer` pattern introduced
  - Agents will be usable as Tools (nested agent execution)
- **Relationship to our proposals:**
  - **All proposals:** This is a major restructuring of the layer our proposals target. Filing patches against current `ai_agents` structures may need porting if this lands before our PRs are merged. Must be tracked.
  - **P2:** The `SystemPromptSubscriber` in `ai_context` hooks into `BuildSystemPromptEvent`. If event architecture changes as part of this migration, P2's hook point changes.
  - **Timeline:** No firm completion date, but active sprint planning in early 2026.
- **Action:** Watch this issue closely. File our proposals against current stable `ai_agents` 1.x, not the in-progress restructure.

### Other Noteworthy Issues

| Issue | Title | Status | Relevance |
|-------|-------|--------|-----------|
| #3458607 | Handle chat history vs reduced context length with sensible defaults | Active (on `ai` project) | Raises the history-vs-context-window tradeoff; no resolution proposed. P3b territory. |
| #3547225 | Chatbot repeats itself even after 'clear history' if 'return direct' | Active | History state corruption; adjacent to P3b problems. |

---

## Module 2: ai_context (Context Control Center / CCC)

**Issue queue:** https://www.drupal.org/project/issues/ai_context (90 open issues as of March 2026)
**Note:** The module was branded "Context Control Center (CCC)" for the beta1 release. It reached `1.0.0-beta1` in approximately March 2026.

### Highly Relevant Issues

#### #3564706 — [META] Context Scope feature
- **URL:** https://www.drupal.org/project/ai_context/issues/3564706
- **Status:** Active (has branch `3564706-meta-add-context`)
- **Filed:** January 2026 target
- **Category:** Plan
- **Summary:** Introduces a "Scope" system for context items. Context items can be tagged with scope dimensions: use case (writing words, building canvas pages, creating components), site scope (cross-site, site, section, page), language, and freetagging topics. Agents subscribe to scope items, and the system injects only context items matching the agent's subscribed scopes.
- **Key quote:** "An agent creates textual content for blog posts — it doesn't care about creating landing pages or components, but does care about writing words. The agent can be configured to subscribe/link to 'writing words' context items."
- **Relationship to our proposals:**
  - **P2 (Loop-Aware Context Injection / Operation-Aware Context Scoping):** This is the upstream team's answer to the same problem P2 addresses. Where P2 focuses on *when* to inject (loop number), the Scope feature focuses on *what* to inject (operation type). These are complementary, not competing.
  - P2's loop-awareness optimization is distinct and additive — even with Scope filtering, the surviving context items would still be re-injected on every loop without P2.
  - This issue confirms the upstream team is actively working on context relevance filtering. Our P2 filing should reference this and frame loop-awareness as the temporal complement to the Scope system's content filtering.
- **Action:** Monitor implementation in MR `!65` / `!70`. Align P2 proposal language with the emerging Scope API.

#### #3568673 — Add context scope base code and use case context scope plugin
- **URL:** https://www.drupal.org/project/ai_context/issues/3568673
- **Status:** Active (has multiple MRs: `!65`, `!70`)
- **Filed:** January 2026
- **Category:** Feature request (child of `#3564706`)
- **Summary:** Implementation issue for the Scope foundation. Creates the pluggable factors system with an initial "use case" plugin. Target date was January 2026; actual status of MRs is unknown.
- **Relationship to our proposals:**
  - **P2:** Same as `#3564706`. This is the active implementation we should watch.
- **Action:** Check MR merge status before filing P2.

#### #3557719 — [Spike] Research AI Context categories
- **URL:** https://www.drupal.org/project/ai_context/issues/3557719
- **Status:** Active
- **Filed:** November 2025
- **Category:** Task (spike)
- **Collaborators:** @afoster (Aidan Foster, Canvas maintainer), @emma horrell, @kristen pol
- **Summary:** Researching standard context categories for non-technical users (marketers, content editors). Spawned from the Nov 2025 AI Context architecture meeting.
- **Relationship to our proposals:**
  - **P2:** Informs what "use cases" the Scope system will recognize. If "canvas page building" becomes a standard scope, P2's operation-awareness can use the same taxonomy.
- **Action:** Review outputs. If standard categories are proposed, align our P2 proposal with them.

#### #3573713 — Full architecture review of CCC in prep for 1.0
- **URL:** https://www.drupal.org/project/ai_context/issues/3573713
- **Status:** Active
- **Filed:** February 2026 (target: late Feb / early March 2026)
- **Category:** Task
- **Collaborators:** @kristen pol
- **Summary:** Full architecture review before alpha/beta. Multiple review documents attached covering domain model, service architecture, plugin architecture, access control, performance, UI, testability, and a remediation roadmap. The `performance_review.md` is directly relevant.
- **Relationship to our proposals:**
  - **P2:** The `performance_review.md` attachment may already identify the `SystemPromptSubscriber` re-injection issue. If it does, our P2 filing can reference this review as prior acknowledgment.
- **Action:** Request or read the `6-performance_review.md` attachment from comment #2.

#### [META] Smart context selection feature (referenced in issue list)
- **URL:** https://www.drupal.org/project/ai_context/issues (title: `[[META] Smart context selection feature]`)
- **Status:** Postponed (Major Feature request)
- **Filed:** approximately 2 months before survey date
- **Summary:** Meta issue for intelligent context selection — selecting the most relevant context items rather than injecting everything. Postponed, likely deferred post-beta1.
- **Relationship to our proposals:**
  - **P2:** This is the aspirational version of what P2 implements at the loop level. Our proposal is a narrower, implementable slice of this broader goal.
- **Action:** Reference as the strategic goal when framing P2.

### Context About the Module State

CCC reached `1.0.0-beta1` in March 2026 after a security review and significant refactoring (context selection logic refactor `#3556679`, N+1 pattern fixes, HTML helper extraction). The module is active and well-maintained by Salsa Digital (Kurt Foster team). Filing issues here will receive prompt attention given the sprint cadence visible in the queue.

---

## Module 3: canvas / canvas_ai

**Issue queue:** https://www.drupal.org/project/issues/canvas (1,037+ open issues as of March 2026)
**Note:** Canvas reached stable `1.0` in November 2025 and is now on `1.3.x`. Canvas AI is a submodule.

### Highly Relevant Issues

#### #3579796 — [Plan] Canvas AI Roadmap
- **URL:** https://www.drupal.org/project/canvas/issues/3579796
- **Status:** Active (Plan, filed March 2026)
- **Filed:** 2026-03-17
- **Assigned to:** rakhimandhania (QED42)
- **Summary:** The canonical planning document for Canvas AI development. Covers 8 priority levels from cross-cutting foundations (P0) through content templates (P8). Directly relevant sections:
  - **P0 (Cross-Cutting Foundations):** Includes `#3545816` (metadata/component context optimization) as foundational infrastructure.
  - **P1 (Stable Page Building):** Includes `#3549232` (updating page contents / deterministic edits) and `#3547209` (patterns as context).
  - **P2 (Page Generation):** Includes `#3546907` (two-step planning phase) and `#3533085` (incremental generation).
  - **P3 (Component Metadata):** Includes `#3545816` again — component selection optimization.
  - **P4 (AI Context Integration):** `#3571184` — Canvas AI integration with CCC. Still stub-only ("More details to be added").
  - **P5 (Chat Interface):** Includes `#3555239` (history corruption) as a bug fix.
  - **Suggested New Meta Issues** (filed at end of roadmap): The roadmap explicitly calls for a new meta issue on "Component metadata model and governance — standardised schema for component descriptions, AI-assisted metadata generation, site builder override controls, and context-overflow prevention." This is the upstream community's own description of the problem P1 solves.
- **Relationship to our proposals:**
  - **All four proposals:** This roadmap is the strategic home for Canvas AI contributions. Filing our proposals as followups to the appropriate priority items in this roadmap maximizes reception.
  - **P1:** Should be filed as a child of P0 (`#3545816`) and/or the suggested "Component metadata model" meta.
  - **P4:** Should be filed as a child of P1 (`#3549232`).
  - **P2:** Should be filed as a child of P4 (`#3571184`) — the Canvas AI + CCC integration issue.
- **Source:** https://www.drupal.org/project/canvas/issues/3579796

#### #3545816 — Simple approach to bringing advanced metadata into Canvas AI
- **URL:** https://www.drupal.org/project/canvas/issues/3545816
- **Status:** Active (two MRs: `!349` original, `!719` v2)
- **Filed:** September 2025
- **Category:** Feature request
- **Assigned to:** marcus_johansson
- **Summary:** The canonical issue for component selection optimization in Canvas AI. Documents the 13K-token component context problem directly:
  - Full component schema (label + description + props + slots) for all components is sent in one shot.
  - Mercury theme: ~13K tokens just for component context. Civic Theme (atomic design): "more or less impossible to use."
  - Proposes a two-pass approach: (1) function call returning only id/label/description for all components, (2) second function call taking a list of IDs, returning full metadata only for candidates.
  - Adds UI for site builders to add extended markdown metadata per component.
  - Changes builder agents to use the two-pass fetch pattern (initial tool in memory, expand on demand).
- **Relationship to our proposals:**
  - **P1 (Region Scoping):** Highly complementary. `#3545816` reduces the per-component context sent to the agent (vertical optimization: less per component). P1 reduces the number of components sent (horizontal optimization: only the active region). Together they multiply.
  - **P1 should reference this issue.** The roadmap (`#3579796`) lists `#3545816` as P0 foundational infrastructure and says two additional system prompt issues should be fixed alongside it.
  - This issue already has working MRs. Contributing a review or test report here builds credibility before filing P1.
- **Source:** https://www.drupal.org/project/canvas/issues/3545816

#### #3549232 — Canvas AI: Updating page contents with agents
- **URL:** https://www.drupal.org/project/canvas/issues/3549232
- **Status:** Active (has MR `!581`)
- **Filed:** September 2025
- **Category:** Feature request / Enhancement
- **Summary:** The current page builder agent can only ADD components. It cannot update existing component props or rearrange components. This issue proposes:
  - `update_component_data` tool: accepts UUID + prop values, validates, applies.
  - `move_component_in_page` tool: accepts placement, reference UUID, region, component UUID.
  - Stores `createExpectedPageLayout()` output in tempstore so it's available to both tools.
- **Relationship to our proposals:**
  - **P4 (Lightweight Edit Path / Deterministic Edits):** This is P4's upstream home. `update_component_data` is exactly the deterministic prop-update pathway P4 requires. Our local implementation proof should feed directly into this issue.
  - The "update existing component" use case is the primary target for P4's LLM bypass — "change this heading" should call `update_component_data` directly, not route through the full agent chain.
  - Has an active MR. Our P4 work can either extend this MR or file a followup that adds the frontend routing logic (detect edit intent → call `update_component_data` directly).
- **Source:** https://www.drupal.org/project/canvas/issues/3549232

#### #3555239 — Canvas AI: Orchestrator missing previous conversation context
- **URL:** https://www.drupal.org/project/canvas/issues/3555239
- **Status:** Active (Priority 0 and Priority 5 in roadmap `#3579796`, has MR `!687`)
- **Filed:** October 2025
- **Category:** Bug report
- **Summary:** Only the last two messages are sent as history to the orchestrator. Documents the corruption pattern:
  - Sub-agent intermediate status messages are included in history but user messages are excluded.
  - Proposes filtering sub-agent outputs from history, keeping only clean user/assistant pairs.
  - Includes PHP array showing the malformed history structure (sub-agent HTML blobs treated as history messages).
- **Relationship to our proposals:**
  - **P3b (History Windowing):** The history corruption bug documented here is a prerequisite problem for P3b. Any windowing mechanism must handle these malformed history entries. P3b should be filed as a follow-up to this fix (first fix the corruption, then add windowing).
  - The fix proposed in this issue (filter sub-agent outputs from history) is the "clean history" foundation P3b builds on.
- **Source:** https://www.drupal.org/project/canvas/issues/3555239

#### #3546907 — Implement Two-Step Agentic Flow with Planning Phase
- **URL:** https://www.drupal.org/project/canvas/issues/3546907
- **Status:** Active (listed under Priority 2 in roadmap `#3579796`)
- **Filed:** September 2025
- **Category:** Feature request
- **Summary:** Proposes a planning agent that analyzes requests before an execution agent acts. Planning phase: break down request, assess component dependencies, create execution roadmap, validate feasibility. Execution phase: follow the plan.
- **Relationship to our proposals:**
  - **P4 (Lightweight Edit Path):** Orthogonal but important. The two-step flow is for complex operations (full page builds). P4's fast path is for simple operations (single prop updates). These should not conflict — P4's detection logic runs before the two-step flow is invoked.
  - Explicitly notes: "Ensure planning overhead doesn't significantly impact performance."
- **Source:** https://www.drupal.org/project/canvas/issues/3546907

#### #3533085 — Followup: Incremental Component Generation
- **URL:** https://www.drupal.org/project/canvas/issues/3533085
- **Status:** Active (Priority 2 in roadmap)
- **Filed:** June 2025
- **Category:** Feature request
- **Summary:** Instead of generating all components in a single YAML response, the orchestrator splits requests into loops — one component per loop — so the user sees progressive output ("streaming-like" experience). Uses `setAiGeneratedComponentResponse` per component.
- **Relationship to our proposals:**
  - **P3b (History Windowing):** Incremental generation increases the number of loops, which increases history accumulation. P3b becomes more important as this feature is adopted.
  - **P2:** More loops means more opportunities for context re-injection. P2's loop-awareness savings scale with this feature.

#### #3549432 — Make it possible to disable component for Canvas AI selection
- **URL:** https://www.drupal.org/project/canvas/issues/3549432
- **Status:** Active (has MR `!154`)
- **Filed:** September 2025
- **Category:** Feature request
- **Summary:** Site builders can hide specific components from Canvas AI's component picker via a UI checkbox in `CanvasAiComponentDescriptionSettingsForm`. Reduces the component context the agent receives.
- **Relationship to our proposals:**
  - **P1 (Region Scoping):** Complementary. Component exclusion reduces the total context; region scoping reduces the per-call context. Both reduce `getAllComponentsKeyedBySource()` tokens.
  - **#3545816** depends on this being implemented to avoid disabled components appearing in the two-pass fetch.

#### #3547209 — Provide Canvas patterns as 'component best practices' context to AI
- **URL:** https://www.drupal.org/project/canvas/issues/3547209
- **Status:** Active (has MR `!92`)
- **Filed:** September 2025
- **Category:** Feature request
- **Summary:** Canvas patterns (predefined component arrangements) are exposed as best-practice context for the AI agent. E.g., if a pattern defines that a card container holds three cards, the AI applies this as a default.
- **Relationship to our proposals:**
  - **P1 / P2:** This adds context to the system prompt. If patterns are injected naively (on every loop, in full), it compounds the problems P1 and P2 solve. Patterns should use the two-pass fetch pattern from `#3545816` and the loop-aware injection from P2.

#### #3571184 — Canvas AI: Integration with context control center
- **URL:** https://www.drupal.org/project/canvas/issues/3571184
- **Status:** Active (stub — "More details to be added")
- **Filed:** January 2026 (listed as Priority 4 in roadmap)
- **Category:** Feature request
- **Summary:** Placeholder issue for integrating CCC (AI Context module) with Canvas AI. The roadmap notes: "A clear definition of what constitutes agent context — brand guidelines, accessibility rules, tone of voice — is required before integration can proceed."
- **Relationship to our proposals:**
  - **P2 (Loop-Aware Context Injection):** This is the issue where P2 should be filed or linked. Once CCC integrates with Canvas AI, the `SystemPromptSubscriber` re-injection problem becomes active for Canvas AI agents too.
  - Because this is a stub, there is an opportunity to shape the integration design from the start — including loop-awareness as a first-class requirement.

#### #3573571 — Use VariationCache for getAllComponentsKeyedBySource() cache context handling
- **URL:** https://www.drupal.org/project/canvas/issues/3573571
- **Status:** Active (Priority 0 in roadmap)
- **Filed:** February 2026
- **Category:** Task
- **Summary:** Fixes cache context handling for the component source lookup that feeds Canvas AI's component list. Correct caching reduces redundant computation.
- **Relationship to our proposals:**
  - **P1:** Prerequisite stability. Correct caching of `getAllComponentsKeyedBySource()` is a prerequisite for the two-pass fetch pattern (`#3545816`) to work reliably.

---

## Foster Interactive: Public Roadmap and Blog

**Aidan Foster** is the primary maintainer of Canvas at Foster Interactive. The following public sources document the Canvas AI direction.

### [Plan] Canvas AI Roadmap — drupal.org issue #3579796 (March 2026)

The roadmap itself (documented above) is the authoritative public statement of Canvas AI plans. Key efficiency-adjacent items:

- P0: Component metadata optimization (`#3545816`) — listed as foundational before all other work
- P0: "context-overflow prevention" called out explicitly in the suggested new meta issue
- P4: CCC integration (`#3571184`) — still a stub

### DrupalCon Vienna / Chicago talks

- **"AI page building in Drupal Canvas, Aidan Foster" (Evolve Digital Toronto, March 2026):** https://www.youtube.com/watch?v=OXQ3GzDT5OY — 26-minute talk covering Canvas AI. Not crawled; may contain roadmap discussions.
- **"Drupal Canvas page building with AI - DrupalCon Chicago 2026" (Dries Buytaert, March 2026):** https://www.youtube.com/watch?v=wFZ2FP9ibfQ — Short demo video (3:16). Confirms Canvas AI is a flagship DrupalCon Chicago story.

### Drupal's AI Roadmap for 2026 — drupal.org blog (February 2026)

- **URL:** https://www.drupal.org/blog/drupals-ai-roadmap-for-2026
- Canvas AI page generation is explicitly one of the **eight core priorities** for 2026.
- Quote: "Describe what you need and get a usable page, built from your actual design system components."
- 28 organizations, 23+ FTE contributors, QED42 (innovation) and 1xINTERNET (productization).
- No mention of token efficiency or cost as a design priority in the public summary. The efficiency work is happening within the issue queue, not at the announcement level.

### George Bonnici blog — "Drupal's AI-Native Page Building" (January 2026)

- **URL:** https://bonnici.co.nz/blog/drupal-ai-native-page-building-canvas-ai-context
- Published by a Drupal agency (Bonnici, NZ) using the Canvas + CCC + Canvas AI stack
- Provides the most complete public documentation of what the AI agent receives:
  - Context block assembled from CCC entities injected with every prompt
  - Component schemas (JSON Schema props from SDCs) sent to Canvas AI
  - Cost estimates: ~4K input tokens + ~3K output = ~$0.06/page at Sonnet 4.5
  - **Their estimate assumes a clean single-turn generation, not multi-loop editing.** The 4K input estimate is dramatically lower than our measured 22K/call average — likely because they are measuring page BUILD (fresh page, few context items) not component EDIT (full history, all context re-injected per loop).
- This gap between their 4K estimate and our 22K measurement is itself evidence of the loop re-injection problem — the cost compounds across loops in ways single-turn estimates miss.

---

## Cross-Cutting Findings

### What the community is discussing

1. **Component context optimization** — `#3545816` is the flagship. Two-pass fetch (labels-first, detail-on-demand) is the proposed pattern. Active MR.
2. **Deterministic prop updates** — `#3549232` proposes `update_component_data` tool. Exactly P4's mechanism.
3. **Chat history corruption** — `#3555239` documents the problem. Active MR.
4. **Context scoping by use case / scope dimensions** — `#3564706` and `#3568673`. Active implementation work.
5. **Moving agents into AI Core** — `#3556141`. Structural change that affects all proposals.

### What the community is NOT discussing

1. **Loop-aware system prompt injection in `ai_context`** — The `SystemPromptSubscriber` re-injection problem (P2's core target) is not documented in any existing issue. This is a net-new contribution opportunity.
2. **History windowing for bounded context growth** — `#3555239` fixes corruption; no issue proposes windowing as a design pattern. P3b is net-new.
3. **Token cost measurement as a first-class concern** — No issue treats token costs as a primary metric. The Bonnici blog's cost estimates are the closest public acknowledgment. Our measured data (111K tokens/edit, $0.73/edit) would be novel evidence in any upstream discussion.

### Contributor Landscape

| Issue | Key Contributors | Organization |
|-------|-----------------|--------------|
| `#3545816` | marcus_johansson | Unknown |
| `#3549232` | (no assignment) | — |
| `#3555239` | akhil babu | QED42 |
| `#3564706`, `#3568673` | kristen pol, afoster, emma horrell | Salsa Digital / Foster Interactive |
| `#3573713` | kristen pol | Salsa Digital |
| `#3579796` | rakhimandhania | QED42 |
| `#3524351` | (core ai_agents team) | 1xINTERNET |
| `#3556141` | (core ai team) | 1xINTERNET |

The two active organizations to engage are **QED42** (Canvas AI sprint team) and **Salsa Digital** (CCC team, Aidan Foster connection). 1xINTERNET owns `ai_agents` and `ai` core.

---

## Proposal-to-Issue Mapping

| Proposal | Existing upstream issue | Status | Recommended action |
|----------|------------------------|--------|-------------------|
| **P1** — Native region scoping in canvas_ai | `#3545816` (two-pass component fetch) | Active, MR exists | Contribute to this issue as a parallel optimization. Frame P1 as horizontal scoping (fewer components) to `#3545816`'s vertical optimization (less per component). File P1 as a child of `#3579796` P0. |
| **P2** — Loop-aware context injection in ai_context | `#3564706` / `#3568673` (Scope feature) | Active | No exact match exists. File P2 as a new issue on `ai_context`. Reference `#3564706` as the content-filtering complement; frame P2 as the temporal complement. Reference `#3524351` for adjacent tool-memory pattern. |
| **P3b** — Orchestrator history windowing | `#3555239` (history corruption fix) | Active, MR exists | File P3b as a follow-up to `#3555239`. First the corruption must be fixed; then windowing builds on clean history. |
| **P4** — Deterministic lightweight edit path | `#3549232` (update_component_data tool) | Active, MR exists | Contribute directly to `#3549232`. Our local proof-of-concept is evidence that this tool design works. P4's frontend routing (detect edit intent → call tool directly) may need a separate followup issue. |

---

## Recommended Filing Order (revised based on this survey)

1. **Contribute to `#3549232`** — Add a comment with our measured evidence that deterministic prop updates eliminate 100% of agent chain cost for simple edits. Link the `update_component_data` tool to the Canvas AI `AiWizard.tsx` edit detection path. This is the highest-impact contribution with the most community momentum.

2. **Contribute to `#3545816`** — Test the MR, report results on FinDrop with Civic Theme-scale component libraries. Provide data showing how our section-level scoping (P1) compounds with the two-pass fetch. Offer to file a P1-specific followup issue.

3. **File P2 as a new issue on `ai_context`** — "Loop-aware system prompt injection: prevent `SystemPromptSubscriber` from re-injecting unchanged context items on loops > 1." Reference `#3564706` and `#3524351`. Include our measurement: 21K tokens saved per edit (19% of current 111K baseline).

4. **File P3b as a followup to `#3555239`** — Once the history corruption is fixed, propose windowing as the next step for bounded context growth in multi-turn sessions.

5. **File P1 as a new issue on canvas** — "Native layout scoping for component operations: scope layout data to the containing section when `active_component_uuid` is present." Link `#3545816` as the vertical complement. Reference `#3579796` P0 and the "context-overflow prevention" meta issue called for in the roadmap.

---

## Sources

- https://www.drupal.org/project/issues/ai_agents — ai_agents issue queue
- https://www.drupal.org/project/issues/ai_context — ai_context issue queue
- https://www.drupal.org/project/issues/canvas — canvas issue queue
- https://www.drupal.org/project/canvas/issues/3579796 — Canvas AI Roadmap
- https://www.drupal.org/project/canvas/issues/3545816 — Component metadata optimization
- https://www.drupal.org/project/canvas/issues/3549232 — Updating page contents with agents
- https://www.drupal.org/project/canvas/issues/3555239 — Orchestrator missing conversation context
- https://www.drupal.org/project/canvas/issues/3546907 — Two-step agentic flow
- https://www.drupal.org/project/canvas/issues/3533085 — Incremental component generation
- https://www.drupal.org/project/canvas/issues/3549432 — Disable component for AI selection
- https://www.drupal.org/project/canvas/issues/3547209 — Canvas patterns as context
- https://www.drupal.org/project/canvas/issues/3571184 — Canvas AI + CCC integration
- https://www.drupal.org/project/ai_agents/issues/3524351 — Tool result memory
- https://www.drupal.org/project/ai_agents/issues/3523967 — Chat history in AiAgentEntityWrapper
- https://www.drupal.org/project/ai_agents/issues/3515670 — Refine function call context
- https://www.drupal.org/project/ai_agents/issues/3553458 — Max loops / solvability state bug
- https://www.drupal.org/project/ai/issues/3556141 — Move AI Agents into AI Core roadmap
- https://www.drupal.org/project/ai/issues/3458607 — Chat history vs context length
- https://www.drupal.org/project/ai_context/issues/3564706 — Context Scope META
- https://www.drupal.org/project/ai_context/issues/3568673 — Context scope base implementation
- https://www.drupal.org/project/ai_context/issues/3557719 — AI Context categories spike
- https://www.drupal.org/project/ai_context/issues/3573713 — CCC architecture review
- https://www.drupal.org/blog/drupals-ai-roadmap-for-2026 — Drupal AI 2026 Roadmap
- https://bonnici.co.nz/blog/drupal-ai-native-page-building-canvas-ai-context — Canvas + CCC + Canvas AI walkthrough (George Bonnici, January 2026)
- https://www.youtube.com/watch?v=OXQ3GzDT5OY — Aidan Foster, AI page building in Drupal Canvas (March 2026)
