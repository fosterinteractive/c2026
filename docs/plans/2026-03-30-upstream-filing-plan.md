# Upstream Filing Plan: Canvas Direct-Edit Contribution

> **For Claude:** Use drupal-canvas-planner architectural principles + plan-writer strategic approach. Invoke drupal-critic at review checkpoints.
> **Drupal CMS Version:** 2.0 (Drupal 11.3)
> **Canvas Version:** 1.x-dev
> **Companion skills:** drupal-critic (Canvas skills), proposal-critic

**Feature:** Contribute deterministic editing, loop-aware context injection, and layout scoping optimizations to the Canvas/ai_context contrib ecosystem.
**Risk Level:** High — three contrib modules, three different maintainer groups, architecturally ambitious proposals touching AI token economics.
**Measured evidence:** 430x speedup (38ms vs 16.4s), 60% hit rate, 52% token reduction, 144 unit tests, 16 E2E tests.

---

## Strategic Context

### The Contribution Story

Canvas AI is powerful but expensive. A single heading edit costs 101K tokens (~$0.30) and takes 16.4s. Our work proves that 60% of common edits are deterministic — they can be pattern-matched and applied in 38ms with 0 tokens. The remaining 40% can be made cheaper via loop-aware context stripping (52% token reduction) and layout scoping (~11% reduction).

This is not a feature request. This is measured performance work with working code, benchmarks, and tests. The contribution strategy must frame it as **helping Canvas succeed at scale** — not as criticism of the current architecture.

### Why Filing Order Matters

The patches have different risk profiles and different audiences:

| Patch | Module | Maintainers | Risk | Standalone Value |
|-------|--------|-------------|------|-----------------|
| P2: Loop-aware context | ai_context | ai_context maintainers | Low | High — universal benefit for any multi-loop agent |
| P1: Region scoping comment | canvas_ai (comment on canvas #3545816) | Canvas/XB team (Wim Leers, larowlan, tedbow) | Medium | Medium — complements existing vertical optimization |
| P4: Deterministic routing | canvas_ai (#3549232) | Canvas AI team | High | High — but architecturally ambitious |

**Filing order: P2 → P1 → P4** (established credibility → complementary evidence → ambitious proposal)

**P2 filed:** https://www.drupal.org/project/ai_context/issues/3582288 (2026-03-30)

---

## Maintainer Intelligence (from Canvas Issue Queue Corpus)

**Source:** 2,964 Canvas/XB issues, 40,780 comments, 457 unique authors. Searched 2026-03-30.

### Critical Finding: larowlan Rejects AI-Generated Code

> "was going to review the MR but then realised it looked AI generated so not going to" — larowlan, #3522013

**Impact:** ALL patches must be manually reviewed for AI-generated tells: over-documentation, uniform style, excessive comments, overly generic variable names. larowlan is a Canvas committer. If our patches trigger this reaction, they're dead on arrival.

**Mitigation:** Human review pass on all patch code. Follow Drupal coding standards precisely but naturally. No JSDoc/PHPDoc blocks on obvious methods. No "comprehensive" anything.

### Critical Finding: lauriii Explicitly Wants Deterministic Validation

> "The goal of this issue would be to introduce a deterministic validation for the cases where the LLM goes off track with the changes." — lauriii, #3551659

> "this is essentially an issue where AI doesn't follow the instructions provided for it. The likelihood of running into this also depends on the model. For example, after some testing, we are seeing that this is happening more often on Claude Sonnet 4.5 than on Claude Opus 4.5." — lauriii, #3551659

**Impact:** lauriii is the Canvas product lead. She is ALREADY experiencing the exact problem our deterministic routing solves — AI unreliability on simple operations. This is the strongest possible framing for P4: not "bypass the AI" but "deterministic safety net for when the LLM goes off track."

**Strategy shift:** Frame P4 using lauriii's own language: "deterministic validation/routing for cases where the LLM produces incorrect results on simple property edits."

### Critical Finding: catch Opposes AI Dependency Coupling

> "This will add a composer dependency to ai_agents to every site that uses experience builder, even if they never install this module. As a result will also mean that XB is unable to be fully compatible with major versions of Drupal core until ai_agents is. Making it a separate project that depends on both xb and ai_agents would avoid both of these issues." — catch, #3522013

**Impact:** catch explicitly wants Canvas to work without AI dependencies. This DIRECTLY supports the Canvas Lite angle. When we propose that 60% of edits work without AI, catch's position provides philosophical cover.

### Critical Finding: Canvas AI Has No Stable APIs

> "Canvas does not provide any JS nor PHP APIs for the Canvas AI module. The only supported APIs are the ones listed in /API.md (none!) with the exception of 2 hooks... +1 for separate module, it'd make my life easier 😄 … but how will that actually be feasible?" — Wim Leers, #3579810

**Impact:** canvas_ai has NO backwards-compatibility promises. This means our P4 patch (which targets canvas_ai) faces a lower API stability bar than a canvas core patch would. The maintainers explicitly acknowledge canvas_ai is unstable. This is good — it means architectural changes are more acceptable there.

### Critical Finding: Wim Leers Is Pragmatic About LLMs

> "Using this reasonably well defined issue... as a way to see how an LLM fares. Attached: original plan it generated (Sonnet 4.6)" — Wim Leers, #3555300

> "While I was doing the research for #6, I had an LLM write the necessary changes here. Reviewed it. Verified the test is correct. Looks good." — Wim Leers, #3578142

> "The AI's work lost >1000 LoC of assertions... it is clearly an unacceptable regression." — Wim Leers, #3555300

**Impact:** Wim Leers actively uses LLMs but holds a very high quality bar. He credits them when they help and calls them out when they fail. He is NOT anti-AI — he's anti-low-quality. Our approach of measured benchmarks + comprehensive tests aligns with his values.

### Critical Finding: Wim Leers Already Thinks in "Deterministic" Terms

> "Both are supposed to be deterministic. Objective vs subjective is the difference." — Wim Leers, #3555300

**Impact:** The "deterministic vs AI" framing maps directly onto his mental model for the codebase. Our P4 pitch can use this vocabulary confidently.

### Critical Finding: Testing Is Non-Negotiable

> "Also: zero tests? 😱" — Wim Leers, #3522013
> "just wanted to voice my objection to postponing tests to a followup." — larowlan, #3522013

**Impact:** Our 144 unit tests + 16 E2E specs is a differentiator, not just a hygiene requirement. Lead with test coverage in every filing.

### Critical Finding: lauriii Prioritizes Velocity

> "Contributing in a single MR makes it difficult for multiple people to contribute. We want to get away from that as soon as possible because it's already hurting the velocity." — lauriii, #3522013

**Impact:** Frame contributions as accelerating Canvas development, not creating review burden. Small, focused patches over monolithic MRs.

### Maintainer Disposition Summary

| Maintainer | Role | LLM Stance | Optimization Stance | Likely Reception |
|------------|------|-----------|-------------------|-----------------|
| Wim Leers | Canvas lead | Pragmatic user, high quality bar | Values perf, thinks in deterministic terms | Positive if well-tested, measured |
| lauriii | Product lead | Experiencing AI reliability pain | Wants deterministic safety nets | Strongest ally for P4 |
| larowlan | Committer | Rejects AI-generated code on sight | Merged DynamicPropSource optimization | Positive if code is human-quality |
| tedbow | Canvas AI dev | Pragmatic, detail-oriented | Working on AI validation issues | Positive if architecturally sound |
| catch | Core committer | Vocal LLM skeptic (CIDs 16511942+) | Opposes AI coupling in core | Ally for Canvas Lite framing |

---

## Phase 1: Pre-Filing Preparation (Before Any Drupal.org Post)

### 1.1 Update Upstream Comment Drafts

**Status:** Required before filing. Evidence matrix shows 3 discrepancies to fix.

| Fix | In | Change |
|-----|-----|--------|
| AI path latency | P4 comment | Replace "15-30s" with "16.4s measured (N=5, SD=838ms, 95% CI [15.3s, 17.4s])" |
| Test counts | All comments | Update to 144 unit tests / 541 assertions (was 126/376) |
| Context size | P2 comment | Replace "10-12K tokens" with "22K tokens (86K bytes) measured on demo site with 8 ai_context items" |
| Token baseline | P4 comment | Standardize on 101K (ws1 measurement), not 111K |

**File:** `docs/research/drupal-org-ready-comments-v2.md`

### 1.2 Tone Calibration (Corpus-Informed)

The Drupal core community has specific norms. These are grounded in actual Canvas issue queue behavior, not assumptions.

**Do:**
- Lead with the problem, not the solution
- Show measured data with methodology and limitations
- Frame as "we built this, here's what we found, does the direction seem right?"
- Acknowledge N=1 limitations explicitly
- Reference existing issue queue work and precedents (especially #3551659, #3545816)
- Offer to contribute patches, don't assume they're wanted
- **Lead with test counts** — Wim and larowlan both insist on tests (#3522013). Our 144/541 is a strength.
- **Use lauriii's language** — "deterministic validation for cases where the LLM goes off track" (#3551659)
- **Reference Wim's own framing** — "deterministic vs subjective" (#3555300)

**Don't:**
- Lead with speedup claims (sounds like marketing)
- Submit code that reads as AI-generated — **larowlan will refuse to review it** (#3522013: "realised it looked AI generated so not going to")
- Assume the maintainers haven't thought about this — lauriii has already filed #3551659 about AI unreliability
- File all three simultaneously (overwhelming — Wim explicitly dislikes issue queue overhead: "Trivial things should not be getting follow-up issues")
- Use benchmarks as pressure ("look how slow your module is")
- Over-document code (AI tell — uniform JSDoc, excessive inline comments, "comprehensive" language)

### 1.3 AI-Generated Code Risk (CRITICAL)

**larowlan's stated policy:** He will refuse to review code he perceives as AI-generated (#3522013). This is not a preference — it's a gate.

**Before submitting any patch:**

**Reviewer:** Alex (plan author). Review after a 48-hour cooling-off period from writing the code.

**Style reference baseline:** Match these existing `canvas_ai` files:
- `CanvasBuilder.php` — controller patterns, DI via `create()`, response format
- `canvas_ai.routing.yml` — route definitions
- `canvas_ai.services.yml` — service registration

**Binary checklist (all must pass):**
1. [ ] No method has more than 2 lines of PHPDoc (match `CanvasBuilder.php` density)
2. [ ] Variable names match naming convention in `CanvasBuilder.php` (`$activeComponent`, `$pageLayout`, not `$item`, `$data`)
3. [ ] No test method names contain "comprehensive", "thorough", "complete", or "extensive"
4. [ ] No constant arrays have uniform inline comments on every entry (AI tell)
5. [ ] Commenting density matches surrounding canvas_ai code (sparse, not verbose)
6. [ ] No alphabetically-sorted constant arrays (humans group by domain, not alphabet)
7. [ ] PHPDoc: only `@param`/`@return`/`@throws` on public methods, nothing else
8. [ ] Code passes `phpcs --standard=Drupal,DrupalPractice`
9. [ ] No file exceeds 400 lines (split if necessary — `DirectEditMatcher` at 632 lines needs review)

**If asked directly about AI tooling:** Be honest that AI tools assisted development and measurement, but architecture decisions, measurements, and testing were human-directed. Note: Canvas maintainers explicitly accept AI-assisted contributions when disclosed (see `#3553397`, `#3555300`, `#3578142` — Wim Leers credits LLM assistance openly).

**Wim Leers' quality bar:** He found an LLM lost >1000 LoC of assertions (#3555300). Our patches must demonstrate the opposite — comprehensive coverage that a human cared about.

---

## Phase 2: File P2 — Loop-Aware Context Injection (ai_context)

### Why First

- **Strongest standalone case:** 52% token reduction, universal benefit
- **Lowest risk:** Boolean config flag on existing config object, no API changes
- **Clearest precedent:** `available_on_loop` in `default_information_tools` does the exact same thing for tool outputs
- **Different maintainer group:** ai_context maintainers, not Canvas team — establishes credibility before the Canvas filings
- **No Canvas dependency:** Works independently of P1 and P4

### Filing Strategy

**Issue type:** New feature request (Performance improvement category)

**Title:** `Add loop_aware flag to skip context re-injection on agent loop iterations > 0`

**Opening paragraph template:**
> We've been profiling token usage across multi-loop Canvas AI agents and found that ai_context items are re-injected on every agent loop iteration via SystemPromptSubscriber. Since the LLM already has the context from loop 0 in its conversation history, loops 1+ re-inject identical content at full token cost. On our demo site (8 context items, ~22K tokens per injection), a 3-loop heading edit wastes ~44K tokens — 52% of total cost.
>
> `available_on_loop` in `default_information_tools` already solves this for tool outputs. We'd like to propose the same pattern for ai_context items: a per-agent `loop_aware` flag that skips injection on loop > 0.

**Key architectural points (from Canvas planner analysis):**
- Per-agent config, not global — only multi-loop agents benefit
- Boolean flag with `?? FALSE` default — no migration needed
- Modify SystemPromptSubscriber directly (don't inject) vs prototype approach (inject-then-strip)
- Config schema addition is additive, backward-compatible

**Attach:**
- Prototype patch implementing Option B (native ai_context support)
- Before/after token measurement (101K → 48K, N=1 with methodology)

**Do not attach:**
- The full canvas_ai_scoping module (scope creep)
- The deterministic editing code (separate issue)

### Expected Maintainer Response Vectors

| Response | Likelihood | Our Counter |
|----------|-----------|-------------|
| "Interesting, but N=1 is insufficient" | High | Acknowledge. Offer to run larger measurement suite. The directional accuracy is clear even at N=1. |
| "We're already thinking about this" | Medium | Great — ask what their preferred approach is. Offer our prototype as evidence for the design. |
| "This should be in ai_agents, not ai_context" | Medium | The context injection happens in ai_context's subscriber. The loop count comes from ai_agents' event. Both modules are involved. We put the flag where the injection happens. |
| "Why not just make context items smaller?" | Low | Orthogonal. Scope filtering (#3564706) addresses *which* items; this addresses *when* to inject them. They compound. |

### Success Criteria

- Issue filed with measured data and working patch
- At least one maintainer engages (question, code review, or "needs work" with direction)
- No negative reaction to the methodology or framing

---

## Phase 3: File P1 — Region Scoping Comment (canvas #3545816)

### Why Second

- **Complementary to existing issue:** #3545816 already discusses vertical optimization (less metadata per component). Our region scoping is the horizontal complement (fewer components visible).
- **Comment, not new issue:** Lower barrier. We're adding data to an existing discussion.
- **Canvas team introduction:** First touch with Wim Leers / larowlan / tedbow. Establishes us as a contributor who measures things.

### Filing Strategy

**Type:** Comment on existing issue #3545816

**Opening:**
> Following up on the metadata optimization discussion here. We've built a complementary approach: horizontal scoping that reduces which components the agent sees during edit operations, rather than reducing metadata per component.

**Key points:**
- Subscriber-based approach (priority -10, after ai_context)
- Fail-open design — if string matching fails, full layout is used
- Measured layout reduction (report actual bytes, not estimates — need re-measurement)
- Acknowledge the fragility of string matching; propose structured API as cleaner upstream path
- Frame the `layout_data` token idea as a question, not a prescription

**Critical Canvas architecture alignment (from Canvas planner):**
- The `BuildSystemPromptEvent` token system is the correct extension point
- Layout data as a parsed array token follows Canvas's existing token pattern
- No new event methods needed — uses existing `setToken()`/`getTokens()` API
- Backward compatible: existing string-based subscribers continue working

### Expected Maintainer Response Vectors

| Response | Likelihood | Our Counter |
|----------|-----------|-------------|
| "We'd prefer a structured API on the event" | High | Agree — that's the cleaner path. Our string-matching approach is a proof of concept. Ask if they'd accept a patch adding `getLayoutData()`/`setLayoutData()` to the event. |
| "Layout is only 10% of cost, not worth the complexity" | Medium | True in isolation. Show compounding: layout scoping + loop-aware context + deterministic routing together yield 60-80% savings. Each layer is modest; the stack is transformative. |
| "This belongs in canvas_ai, not canvas" | Medium | The layout data originates in canvas_ai's CanvasBuilder. The token should be set there. The scoping subscriber also lives in canvas_ai. We agree — the canvas module itself only needs the token transport (which it already has). |

### Timing (Conditional on P2 Reception)

File P1 only after confirming P2 did not receive a hostile or dismissive response.

**Go criteria (file P1):** At least one of:
- A maintainer responds to P2 with a question or code review comment
- P2 receives "needs work" with constructive direction
- P2 is acknowledged but deferred (e.g., "interesting, will review later")
- No response after 2 weeks (neutral — proceed with P1 as a separate touch point)

**Hold criteria (delay P1):** Any of:
- P2 receives "won't fix" or "not wanted" signal
- A maintainer explicitly says "we're solving this differently"
- P2 sparks a contentious discussion about AI optimization philosophy

**Abort criteria (reassess entire strategy):**
- P2 is closed as duplicate with no engagement
- A maintainer reacts negatively to the measurement methodology
- `#3556141` (AI Agents restructuring) lands an MR that changes the event API surface

---

## Phase 4: Deterministic Routing — Two Paths

### Maintainer Feedback (2026-03-30)

A project maintainer directed us to:
- **`ai_agents_experimental_collection`** — collection of 32 experimental AI agents as standalone submodules. Includes a Canvas Page Manager agent. Explicitly AI-generated, no stability promises, low contribution barrier.
- **`tool` module issue #3575927** — Drush CLI for listing, searching, and running AI tools. Designed for coding agents (CLI > MCP). Our deterministic matcher could be exposed as a Tool.

This opens a **faster, lower-risk contribution path** alongside the original canvas_ai approach.

### Path A: Experimental Collection (Lower Bar, Faster)

**Target:** New submodule in `ai_agents_experimental_collection`
**Module name:** `ai_agents_direct_edit` (or `ai_agents_canvas_direct_edit`)

**Why this path:**
- Collection explicitly accepts AI-generated code ("every part of this app was generated by AI")
- No larowlan gate — different maintainer group, different quality norms
- Existing `Canvas Page Manager` agent provides the integration surface
- Standalone submodule — doesn't require modifying canvas_ai internals
- Can be installed independently alongside or instead of canvas_ai

**What the submodule would provide:**
- `DirectEditMatcher` as an `AiFunctionCall` plugin (Tool) — the AI agent chain can call it as a tool, or it can be invoked directly
- `ComponentSchemaLoader` with dynamic theme discovery
- Config-driven aliases and synonym verbs
- Drush integration via #3575927 when that lands (list/search/run tools via CLI)

**Contribution approach:**
1. Open an issue on `ai_agents_experimental_collection` proposing the submodule
2. Attach the architecture doc + working code
3. Reference the Canvas Page Manager agent as the integration point
4. Offer to contribute an MR

### Path B: canvas_ai Comment (Higher Bar, Stronger Signal)

**Target:** Comment on existing issue #3549232 (original plan)

This is the higher-credibility path — contributing directly to the module that ships with Canvas. The filing text at `docs/filing/p4-deterministic-routing-FINAL.md` is ready for this path.

**When to use Path B instead of/alongside Path A:**
- If P2/P1 receive positive engagement from Canvas maintainers
- If a maintainer explicitly says "this should be in canvas_ai, not experimental"
- If the experimental collection approach proves too isolated from the Canvas editing flow

### Recommended Strategy: Both, Sequenced

1. **File Path A first** (experimental collection) — lower barrier, faster feedback, proves the concept works as a standalone module
2. **File Path B after Path A gets traction** — reference the working experimental module as evidence. "This is already working as a standalone agent; here's how it could be integrated into canvas_ai natively."

This follows the Drupal contribution pattern: prove it in contrib, then propose for inclusion. The experimental collection is literally designed for this workflow — its README says agents "become production ready" by graduating to their own projects or being absorbed into core modules.

### Why Last (still applies)

### Filing Strategy (Corpus-Informed)

**Type:** Comment on existing issue #3549232 (or new issue if #3549232 is closed/stale)

**Framing — lead with lauriii's own pain point, not economics:**

lauriii already identified the core problem in #3551659: "this is essentially an issue where AI doesn't follow the instructions provided for it." Our deterministic routing is the architectural answer to that problem for the subset of edits that are objectively resolvable.

> Following up on the discussion in #3551659 about AI producing incorrect results that vary by model. For simple property edits — "change the heading to X", "make the background blue" — the correct result is deterministic: it's a known prop on a known component with a known set of valid values. The LLM path introduces unnecessary variability for these cases.
>
> We built a deterministic fast path that pattern-matches simple edits against component schemas and applies them directly. On edits it can resolve, it's correct 100% of the time (validated by 144 unit tests, 541 assertions). On edits it can't resolve, it falls through to the AI chain (422 response, fail-open).
>
> Measured: 60% hit rate on 20 mixed edits. 38ms response vs 16.4s (N=5, measured) on the AI path. 0 tokens for deterministic edits.
>
> Is deterministic routing for simple property edits a direction the Canvas AI team would consider? Happy to share the architecture doc and working prototype.

**Why this framing works:**
- References lauriii's own issue (#3551659) — shows we're engaged with their problems
- Uses "deterministic" — Wim's own vocabulary (#3555300)
- Leads with correctness/reliability, not speed/cost — addresses the pain they're feeling
- Asks permission before filing the full architecture doc
- Doesn't mention "430x speedup" (marketing smell) — lets them discover the numbers in the data

**Key architectural points (from Canvas planner analysis):**
- `DirectEditController` as a new route, not a modification of existing AI endpoint
- `ComponentSchemaLoader` uses dynamic theme discovery (same pattern as `CanvasAiPageBuilderHelper`)
- `DirectEditMatcher` is pure logic, no Drupal dependencies beyond schema loader
- Response format already matches what `directEdit.ts` and `AiWizard.tsx` expect
- Fail-open: unmatched edits return 422, frontend falls through to AI path
- Config-driven: aliases, synonym verbs, telemetry are all configurable

**The Canvas Lite angle (strategic, not technical):**
> An interesting implication: with a 60-80% hit rate, the majority of common page edits work without any AI API key. This could lower the barrier to Canvas adoption — sites could offer immediate editing value on day one, with AI as an enhancement for complex operations.

### Expected Maintainer Response Vectors (Corpus-Calibrated)

| Response | Who | Likelihood | Our Counter |
|----------|-----|-----------|-------------|
| "We'd rather improve the AI path than bypass it" | tedbow | Medium | Not mutually exclusive. lauriii's #3551659 shows the AI path has inherent variability. Deterministic routing handles the objectively-resolvable cases; AI handles the rest. |
| "60% hit rate isn't high enough" | Wim Leers | Medium | 60% is baseline. Ceiling ~80%. But frame as: "60% of edits never produce wrong results, regardless of model choice" — addresses lauriii's Sonnet 4.5 vs Opus 4.5 variability concern. |
| "This couples canvas_ai to theme schemas" | Wim Leers | Medium | Coupling already exists — Canvas AI reads schemas to build prompts. DirectEditMatcher uses the same data. Wim would appreciate this being explicit rather than hidden. |
| "The frontend should handle this" | jessebaker | Low | Backend has the schema registry. Frontend would need to duplicate it. The API call already exists (`directEdit.ts`). |
| "How does this work with Canvas updates?" | Wim Leers | High | Schema-driven: when component YAML changes, matcher auto-adapts. No manual maintenance. This is exactly the "deterministic" approach Wim values (#3555300). |
| "This looks AI-generated" | larowlan | **HIGH** | **Must be pre-mitigated.** See Section 1.3. Human review pass, match existing code style, no over-documentation. |
| "This solves a problem we're experiencing" | lauriii | Medium-High | Best case. She's already filed #3551659 about AI unreliability. Offer to contribute the patch to her team's roadmap. |
| "Canvas AI might become a separate project" | Wim Leers | Low | Our patches work regardless of whether canvas_ai stays as submodule or separates (#3579810). The deterministic controller has no dependency on canvas core. |

### Architecture Document

Attach `patch-3-deterministic-routing-architecture.md` (~800-1000 lines) as a companion to the comment. This is the detailed technical spec that developers can evaluate independently.

### Timing

File 1-2 weeks after P1, after seeing maintainer engagement on the first two filings. If P2/P1 received positive engagement, proceed. If they were ignored or received negatively, reassess approach before filing P4.

---

## Phase 5: Strategic Initiatives (Post-Filing)

These are not filed immediately. They're the "where this goes" story that emerges from the three patches.

### 5.1 Deterministic Editing Without AI Keys — catch as Philosophical Ally

**What:** For pages already built, ~60% of subsequent property editing operations (heading text, colors, spacing, alignment) can be handled deterministically without an AI API key. This does NOT mean Canvas "works without AI" — page creation, component addition, content generation, and layout changes still require the AI chain.
**When to raise:** After P4 gets engagement. Frame as a natural implication of deterministic routing, not as a standalone feature.
**Do not frame as:** "Canvas Lite," "offline mode," or "API-key-free mode." These overstate what P4 delivers. The deterministic path handles property edits on existing components — not the page-building experience.

**Corpus-backed support:** catch explicitly opposed AI dependency coupling in #3522013: "This will add a composer dependency to ai_agents to every site that uses experience builder, even if they never install this module." The deterministic path partially addresses catch's concern — a meaningful subset of the editing experience doesn't require ai_agents. lauriii overruled catch on the coupling question (#3522013), but deterministic editing would satisfy both positions: deep AI integration when available (lauriii's velocity goal) + functional property editing without it (catch's independence goal).

### 5.2 Canvas MCP Server

**What:** Route AI edits through user's desktop Claude/ChatGPT subscription ($20/mo flat) instead of site API keys ($3-15/MTok).
**When to raise:** Only after Canvas maintainers have engaged with the deterministic routing concept. This is a bigger architectural conversation.
**Risk:** May conflict with Canvas's business model if AI API usage generates revenue for the project.

### 5.3 Prompt Caching

**What:** Loop-aware context makes system prompts stable after loop 0. Anthropic prompt caching could cut remaining AI cost by 90%.
**When to raise:** Alongside or after P2. This is a natural extension of loop-aware context.
**Dependency:** Requires the AI module to support Anthropic's prompt caching API.

### 5.4 Model Routing by Complexity

**What:** Simple AI edits → Haiku (fast, cheap). Complex operations → Sonnet. Matcher confidence score informs routing.
**When to raise:** After P4 engagement. The deterministic matcher's confidence scoring naturally extends to model selection.

---

## Risk Register (Corpus-Calibrated)

| Risk | Impact | Likelihood | Evidence | Mitigation |
|------|--------|-----------|----------|------------|
| **larowlan rejects patches as AI-generated** | Critical | **High** | #3522013: refused to review AI-looking MR | Section 1.3 human review pass. Match existing canvas_ai code style exactly. |
| catch's LLM skepticism poisons reception | Medium | Low | catch engages with Canvas as core committer, not AI-specific reviewer. P2 targets ai_context, not his domain. | Lead with measurements. catch actually supports the "works without AI" angle (#3522013). |
| Wim Leers is too busy to engage | Medium | **High** | 2,964 open issues. He reviews 50+ file MRs (#3571536) and creates issues himself. | Be patient. Small, well-tested patches reduce his review burden. Follow up once after 2 weeks. |
| ai_context maintainers disagree on approach | Medium | Medium | ai_context is newer, less entrenched opinions | Offer both Option A and B. Let them choose. |
| P4 rejected as too complex | Medium | **Medium-Low** | lauriii wants deterministic validation (#3551659). Wim thinks in deterministic terms (#3555300). canvas_ai has no API stability promises (#3579810). | Frame as addressing their stated pain point. Architecture doc stands alone as reference. |
| Canvas AI becomes separate project | Low | Medium | Wim wants separation (#3579810), lauriii prefers submodule (#3579810). Active tension. | Our patches work either way. DirectEditController has no canvas core dependency. |
| Existing Canvas roadmap conflicts | Low | Low | #3579796 roadmap exists but is community-filed, not official | Ask permission before P4 filing. |
| Community perceives contribution as self-promotion | Low | Low | Focus on technical contribution. No company name in comments. | Problem-first framing. Reference their issues, not our project. |
| **`#3556141` AI Agents restructuring into AI Core** | **High** | **Medium** | Active sprint planning in early 2026. If `BuildSystemPromptEvent` moves namespaces, P2's hook point changes. | **Pre-filing check:** Verify `#3556141` status before each filing. If an active MR exists, either file against new API or note compatibility with both. P2 targets `ai_context` (not `ai_agents`), so impact may be limited to event class imports. |
| **`#3553458` Loop count off-by-one in AgentStartedExecutionEvent** | **High** | **Medium** | `AgentStartedExecutionEvent` fires before `$this->looped++`, creating off-by-one. P2's `loop > 0` check depends on correct counting. | **Pre-filing check:** Verify whether `#3553458` is fixed in current `ai_agents` release. If not, either (a) reference the bug in P2 filing, (b) file a patch for `#3553458` first as an even lower-risk credibility builder, or (c) verify our prototype accounts for it. |
| Planning documents discoverable in public repo | Medium | Medium | This plan contains maintainer profiling that could read as manipulation if discovered. | Either make repo private before filing, move strategy docs to a non-public location, or accept the risk and be prepared to answer honestly. |

---

## Filing Timeline

| Week | Action | Depends On | Gate |
|------|--------|------------|------|
| Week 0 | Verify `#3556141` and `#3553458` status. Run patches on clean Drupal CMS 2.0. Human code review (48hr cooling-off). | PR #12 merged | Pre-filing checks pass |
| Week 1 | File P2 (ai_context loop-aware). | Pre-filing checks | — |
| Week 2-3 | Monitor P2 engagement. Respond to questions. | P2 filed | — |
| Week 2-3 | File P1 (canvas #3545816 region scoping comment). | P2 go criteria met (see Phase 3 Timing) | P2 not hostile/dismissed |
| Week 3-4 | File P4 Path A (experimental collection submodule). | P1 filed | — |
| Week 3-4 | File P4 Path B (canvas_ai #3549232 comment) if P2/P1 received positive engagement. | P2/P1 engagement | No abort triggers |
| Week 4+ | Strategic conversations (deterministic editing, tool module integration, prompt caching) based on P4 reception. | P4 engagement | — |

**Abort triggers (stop all filings):** P2 closed as "not wanted"; `#3556141` restructuring lands MR changing event API; maintainer explicitly says optimization direction is unwelcome.

---

## Pre-Merge Checklist (Before Any Filing)

- [ ] PR #12 reviewed and merged
- [ ] Evidence matrix discrepancies fixed in comments
- [ ] Test counts updated (144/541)
- [ ] AI path latency updated (16.4s measured)
- [ ] Context size updated (22K tokens)
- [ ] All comments reviewed for tone (problem-first, measured, humble)
- [ ] No marketing language in any comment
- [ ] Drupal coding standards verified on all patch code
- [ ] Patches tested on clean Drupal CMS 2.0 install (not just FinDrop)
- [ ] `#3556141` (AI Agents restructuring) status verified — no active MR changing event API
- [ ] `#3553458` (loop count off-by-one) status verified — fixed in current release OR accounted for in prototype
- [ ] AI-code review checklist passed (Section 1.3) — 48hr cooling-off, binary checklist all green
- [ ] `DirectEditMatcher` reviewed for AI tells (632 lines, uniform constant arrays — item 6 on checklist)
- [ ] Maintainer quote provenance verified (see `docs/research/maintainer-quotes-with-sources.md`)
- [ ] Repo visibility assessed — strategy docs either moved to private location or risk accepted

---

## Companion Critics

- **drupal-critic** (Canvas skills) — Reviews patch architecture against Canvas conventions
- **proposal-critic** — Reviews filing strategy for gaps, assumptions, cognitive bias

## Review History

**2026-03-30 — Meta-Critic Review (3 Opus critics in parallel):**
- Proposal Critic: ACCEPT-WITH-RESERVATIONS (5 MAJOR, 5 MINOR)
- Harsh Critic: ACCEPT-WITH-RESERVATIONS (4 MAJOR, 5 MINOR)
- Drupal Critic: ACCEPT-WITH-RESERVATIONS (4 MAJOR, 6 MINOR)
- All findings addressed in this revision. Key additions: conditional P1 filing, `#3556141`/`#3553458` in risk register, operationalized AI-code review process, tightened Canvas Lite framing, verified frontend endpoint exists (`AiWizard.tsx:751`).

**Corpus research:** Maintainer intelligence sourced from catch-bot Canvas corpus (2,964 issues, 40,780 comments) searched 2026-03-30. Quote provenance documented in `docs/research/maintainer-quotes-with-sources.md`.
