# Tiered Deterministic Edit Routing for Canvas AI

**Date:** 2026-03-29
**Status:** Proposal (post-critic review, revised)
**Branch:** `feat/ws1-efficiency-optimization`
**Context:** ADR-004 (Simple Operations Bypass LLM), ADR-008 (Show and Prove)

---

## Problem

Canvas AI uses a single execution path for all chat interactions: orchestrator agent (system prompt + 24 examples + ai_context) → sub-agent (system prompt + ai_context + layout + component catalog) → tool call. This costs 111K tokens for a heading text change and 253K tokens for a full page build.

The path is the same whether the user says "change this to blue" or "redesign this entire section to be more persuasive." The former is a lookup; the latter requires creative reasoning. Routing both through the same 111K-token pipeline is the core inefficiency.

## Proposal: Three-Tier Waterfall

Three tiers, tried in order. Each catches what it can resolve with certainty and passes unresolved requests to the next tier. All three share the same response format — the frontend doesn't know or care which tier handled the request.

```
User message + selected component
        │
        ▼
┌─────────────────────────┐
│  Tier 1: Pattern Match  │  0 tokens, <100ms
│  (PHP string matching)  │  "change heading to X" → {heading_text: X}
└───────┬─────────────────┘
        │ no match
        ▼
┌─────────────────────────┐
│  Tier 2: Compound Split │  0 tokens, <100ms
│  (split on "and"/",")   │  "change heading to X and make it blue" → {heading_text: X, text_color: primary}
└───────┬─────────────────┘
        │ no match
        ▼
┌─────────────────────────┐
│  Tier 3: Micro-Classify │  ~500 tokens, 1-2s
│  (tiny LLM call)        │  "make this bigger and centered" → {text_size: 5xl, align: center}
└───────┬─────────────────┘
        │ route: "ai"
        ▼
┌─────────────────────────┐
│  Tier 4: Full Agent     │  111K tokens, 15-30s
│  (existing pipeline)    │  "redesign this section to be more persuasive"
└─────────────────────────┘
```

Tiers 1-3 all terminate by calling the same Canvas validation pipeline (`AiResponseValidator` + `CanvasAiPageBuilderHelper` + `includeUpdateOperations`). The response JSON is identical to what Tier 4 produces.

---

## Tier 1: Pattern Matching (implemented)

**Status:** Working prototype in `canvas_ai_scoping` module.

Regex-based matching of user messages against known prop aliases. Handles single-prop edits where both the prop name and value are unambiguous.

**What it catches:**
- "change the heading to Welcome" → `{heading_text: "Welcome"}`
- "set the color to primary" → `{text_color: "primary"}`
- "align this center" → `{align: "center"}`
- "set the level to 3" → `{level: 3}`

**What it rejects:**
- Messages with add/create/generate keywords
- Unrecognized prop names or values
- Multi-prop edits ("change X and Y")
- Ambiguous values ("make this bigger")

**Current coverage:** 5 Byte theme components, ~30 prop aliases.

**Expansion path (Approach A):** Add aliases for all 23 Byte theme components. Requires a schema parsing service that reads component YAML at cache rebuild time, builds the alias/enum map, and handles per-component enum divergence (e.g., `text_size` uses `heading-responsive-*` on headings but `text-xs` through `text-3xl` on text components). This is new code, not just configuration.

**Cost:** 0 tokens. <100ms.

**Estimated coverage (assumption, not measured):** The Byte theme has ~120 total props across 23 components. ~108 (90%) are schema-deterministic (scalars + enums). However, prop-schema distribution does not equal user-edit distribution — users edit headings and colors far more often than `margin_block_end` or `symbol_position`. Without user-edit-behavior data, we estimate ~30-40% of actual edit operations are single-prop deterministic. Phase 4 measurement will provide real data.

---

## Tier 2: Compound Splitting (not yet implemented)

**Status:** Design only.

Handles multi-prop edits expressed as compound sentences. Splits the user message into fragments, runs each through the Tier 1 matcher, and combines results.

**Splitting strategy:**
1. Split on coordinating conjunctions: "and", "also", "plus", "then"
2. Split on commas followed by a verb: ", set", ", change", ", make"
3. Split on semicolons

**Examples:**
- "change the heading to Welcome and set the color to blue"
  → ["change the heading to Welcome", "set the color to blue"]
  → Tier 1 match each → `{heading_text: "Welcome", text_color: "primary"}`

- "set alignment to center, change the level to 3, and make it inverted"
  → ["set alignment to center", "change the level to 3", "make it inverted"]
  → Tier 1 match each → `{align: "center", level: 3, text_color: "inverted"}`

**Conflict resolution:**
- If two fragments set the same prop → reject, pass to Tier 3 (ambiguous intent)
- If any fragment fails Tier 1 matching → reject entire message, pass to Tier 3

**Why "all or nothing"?** Partial matching is dangerous. If the user says "change the heading to Welcome and add a card below," the heading change is deterministic but the card addition is not. Applying the heading change and then routing only the card addition to AI would create a confusing UX where the user sees one instant change and one delayed change. Better to route the whole message to the next tier.

**Narrow band acknowledgment:** This tier only catches messages where ALL fragments independently match in Tier 1. In practice, compound messages often mix deterministic and creative intent ("change the heading and make the description more engaging"). The practical window is messages like "change heading to X and set color to Y" — where the user explicitly names both props and values.

**Cost:** 0 tokens. <100ms.

**Estimated additional coverage (assumption):** ~3-8% of component edit operations. The narrow band limits this tier's reach. Its primary value is that it's nearly free to implement (depends on Tier 1) and catches compound deterministic edits that would otherwise cost 111K tokens.

---

## Tier 3: Micro-Classification (not yet implemented)

**Status:** Design only.

A minimal LLM call that resolves ambiguous edits by sending only the component schema and the user message — no page layout, no ai_context, no orchestrator examples.

**Why this works:** The full agent chain sends 111K tokens because it prepares the LLM for *any* possible operation. But if Tiers 1 and 2 have already filtered out the operations that don't need an LLM, what remains is: "I know this is an edit to a known component, I just can't resolve the exact prop/value mapping." That's a narrow classification task, not an open-ended agent task.

### Prompt Design

```
System: You are a component property resolver for the {component_name} component.
Available properties and their accepted values:
{schema from component YAML — props with types, enums, descriptions}

The user has selected this component and sent a chat message.
Your job: map the message to specific property changes.

Respond with ONLY valid JSON:
- If you can resolve: {"edits": [{"prop": "prop_name", "value": "value"}, ...]}
- If you cannot resolve (needs creative work, page context, or is not an edit): {"route": "ai"}

Do not explain. Do not add properties the user didn't ask about.
```

**Example prompts and responses:**

| User message | Component | Micro-classifier response | Tokens |
|---|---|---|---|
| "make this bigger and centered" | heading | `{"edits": [{"prop": "text_size", "value": "heading-responsive-5xl"}, {"prop": "align", "value": "center"}]}` | ~400 |
| "use a bolder style" | button | `{"edits": [{"prop": "variant", "value": "primary"}]}` | ~350 |
| "shrink the text a bit" | text | `{"edits": [{"prop": "text_size", "value": "text-sm"}]}` | ~300 |
| "rewrite this to be more engaging" | heading | `{"route": "ai"}` | ~250 |
| "add a testimonial section" | section | `{"route": "ai"}` | ~250 |

### Provider Configuration

The micro-classifier uses whatever LLM provider the site already has configured as the default `chat` provider in Drupal's AI module. No additional API keys or configuration needed.

```php
$defaults = $this->aiProviderPluginManager
  ->getDefaultProviderForOperationType('chat');
$provider = $this->aiProviderPluginManager
  ->createInstance($defaults['provider_id']);
```

This means:
- If the site uses Anthropic → micro-classifier uses Anthropic
- If the site uses OpenAI → micro-classifier uses OpenAI
- If the site uses a local model via Ollama → micro-classifier uses that
- If the site uses amazee.io's LLM proxy → works through the proxy

The classification prompt is ~200 tokens of system prompt + ~50 tokens of user message + the component schema (~100-400 tokens depending on component complexity). Total input: **300-650 tokens**. Output: **20-50 tokens**.

**Compared to the full agent chain:**

| | Micro-classifier | Full agent chain | Reduction |
|---|---|---|---|
| System prompt | ~200 tokens (classification instruction) | ~8-10K tokens (agent instructions) | 98% |
| Context | ~100-400 tokens (component schema only) | ~10-12K tokens (8 ai_context items) | 97% |
| Layout | 0 (not needed for prop edits) | ~2-3K tokens | 100% |
| Chat history | 0 (single-turn) | ~3-10K tokens | 100% |
| Tool definitions | 0 (JSON response, not tool calling) | ~3-4K tokens | 100% |
| **Total input** | **~300-650** | **~30K per call × 3 loops** | **99.3%** |

### Validation

The micro-classifier response goes through the same validation pipeline as Tiers 1 and 2:
1. Parse JSON response
2. Validate each prop exists on the component schema
3. Validate each value is valid for that prop's type/enum
4. If validation fails → fall through to Tier 4

This means an LLM hallucination (e.g., inventing a prop name) is caught and routed to the full agent chain rather than applied incorrectly.

**Cost:** ~500 tokens (~$0.003). 1-2 seconds.

**Estimated additional coverage:** ~15-20% of component edit operations.

---

## Tier 4: Full Agent Chain (existing, with reduced context)

Everything that Tiers 1-3 can't handle falls through to the existing Canvas AI agent chain. With the LoopAwareContextSubscriber and ContextScopingSubscriber already in place, this path is already cheaper than baseline:

- Orchestrator → sub-agent with scoped layout and loop-gated context
- Handles: page builds, content generation, add/move/delete operations, multi-component reasoning, creative edits

**Future optimization (independent of this proposal):** For operations that reach Tier 4, we can further reduce context by detecting the operation type. An "add a section" operation needs the component catalog but not the full ai_context items. A "rewrite this headline" needs brand voice context but not the component catalog. This is the direction of the upstream P2 proposal (loop-aware context) and the ai_context Scope feature (#3564706).

**Cost:** ~80-111K tokens (with existing optimizations). 15-30s.

**Estimated coverage:** ~5-10% of component edit operations (creative work, page builds).

---

## Combined Coverage Estimate

**Important caveat:** These are schema-derived assumptions, not measured user behavior. Phase 4 measurement will provide real data. The estimates below are based on the Byte theme prop analysis (108/120 props are schema-deterministic) adjusted downward to account for the gap between "what props exist" and "what edits users actually make."

| Tier | Coverage (est.) | Tokens | Latency | Cumulative |
|------|-----------------|--------|---------|------------|
| 1. Pattern match | 30-40% | 0 | <100ms | 30-40% |
| 2. Compound split | 3-8% | 0 | <100ms | 33-48% |
| 3. Micro-classify | 15-20% | ~500 | 1-2s | 48-68% |
| 4. Full agent | 32-52% | 80-111K | 15-30s | 100% |

**Weighted average cost per edit** (showing math, using midpoints):

- Tier 1: 35% × 0 tokens = 0
- Tier 2: 5.5% × 0 tokens = 0
- Tier 3: 17.5% × 500 tokens = 88
- Tier 4: 42% × 95K tokens = 39,900
- **Weighted total: ~40K tokens per edit**
- **Current: 111K tokens per edit**
- **Estimated reduction: ~64%**

If the actual user-edit distribution skews more toward simple prop changes (likely for content authors doing routine updates), the reduction could be higher. If it skews toward creative/structural operations, lower. We don't know until we measure.

---

## Implementation Plan

### Phase 1: Expand Tier 1 (schema parsing + alias expansion)
- Build a schema parsing service that reads component YAML files at cache rebuild
- Handle per-component enum divergence (heading `text_size` uses `heading-responsive-*`, text uses `text-xs` through `text-3xl`)
- Fix existing gap: `text_size` is in PROP_ALIASES but has no ENUM_VALUES mapping — "make the text larger" currently returns the raw string, not a valid enum value
- Build alias map for all 23 Byte theme components (~150 aliases)
- Cache the parsed schema via Drupal's cache API
- Fix the `"make"` keyword conflict (done — `"make"` removed from ADD_KEYWORDS, phrase-level detection added via ADD_PHRASES)
- Estimated: 3-5 days

### Phase 2: Build Tier 2 (small PHP addition)
- Add compound splitting logic to `DirectEditMatcher`
- Add conflict detection (same prop set twice → reject)
- Add "all or nothing" guard (any fragment fails → reject all)
- Estimated: 1-2 days

### Phase 3: Build Tier 3 (new service + controller logic)
- `MicroClassifier` service that builds the classification prompt from component schema
- Uses the site's configured default `chat` provider
- JSON response parsing + validation through existing pipeline
- Add to `DirectEditController` waterfall: Tier 1 → Tier 2 → Tier 3 → 422
- Estimated: 2-3 days

### Phase 4: Measurement and tuning
- Use [`drupal-intent-testing`](https://github.com/scottfalconer/drupal-intent-testing/) to build a regression suite with intent manifests for each tier's boundary cases
- Add structured logging to Tiers 1-3 (tier ID, match/reject reason, component, prop) for coverage analysis
- Run a representative edit session (20-30 operations across different component types) and measure actual tier distribution
- Tune Tier 1 aliases and Tier 3 prompt based on misclassification patterns
- Estimated: 2-3 days

**Total: 10-16 days** for all tiers (phases 1-4).

**Recommended sequencing:** Phase 1 → Phase 4 (measure) → decide on Phases 2-3. This validates assumptions before investing in additional tiers. If Phase 4 shows Tier 1 alone covers 40%+ of edits, Phases 2-3 are clearly worth building. If Tier 1 covers <20%, the effort may be better spent on reducing Tier 4's context size instead.

---

## Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Tier 2 splitting produces incorrect fragments | Medium | Medium | "All or nothing" — any failed fragment rejects the whole message |
| Tier 3 micro-classifier hallucinates a prop name | Low | Low | Schema validation catches it; falls through to Tier 4 |
| Tier 3 adds latency for messages that will end up in Tier 4 anyway | Medium | Low | Micro-classifier timeout at 3s; fast fail on "route: ai" responses |
| Users notice different latency between tiers (instant vs. 1-2s vs. 15-30s) | High | Low | This is actually a feature — faster is better. Optional: show "instant edit" indicator |
| Component schemas change between Canvas versions | Medium | Medium | Read schemas from YAML at cache rebuild, not hardcoded |
| Tier 3 prompt doesn't generalize across LLM providers | Low | Medium | Schema-based prompt is simple enough for any chat model; validate response JSON strictly |

---

## Architecture Decision

### Why a waterfall instead of a router?

A router (single classification step that picks the right tier) would require an LLM call for every request to decide the routing. The waterfall avoids this: Tiers 1 and 2 are free and instant. Only messages that escape both tiers pay the ~500 token cost of Tier 3. And Tier 3 itself is designed to quickly return `{"route": "ai"}` for anything it can't resolve, minimizing wasted tokens on creative requests.

### Why not just use Tier 3 for everything?

Tier 3 at ~500 tokens is cheap. But for a content author making 50 edits in a session:
- **Tier 1+2 only:** 0 tokens for ~55% of edits = 0 cost for 27 edits
- **Tier 3 for all:** 500 tokens × 50 edits = 25K tokens + latency
- **Waterfall:** 0 tokens for 27 edits + 500 × 10 edits (Tier 3) + 90K × 13 edits (Tier 4) = ~1.17M tokens

vs. current: 111K × 50 = 5.55M tokens. The waterfall saves ~79%.

Tiers 1 and 2 also provide **zero-latency** responses. For a content author in flow, the difference between instant and 1-2 seconds matters.

### Are the tiers exclusive?

No. They are explicitly designed to compose:
- Tier 2 depends on Tier 1 (it runs each fragment through the Tier 1 matcher)
- Tier 3 complements Tiers 1+2 (catches what they miss)
- Tiers 1-3 all produce the same response format consumed by Tier 4's frontend

You can deploy any combination:
- Tier 1 only (current state)
- Tiers 1+2 (free expansion)
- Tiers 1+3 (skip compound splitting)
- Tiers 1+2+3 (full waterfall, recommended)

Each tier is a separate service class with its own enable/disable toggle.

---

## Relationship to Existing Work

| Document | Relationship |
|---|---|
| ADR-004 (Simple Operations Bypass LLM) | This proposal implements ADR-004 across three tiers |
| ADR-006 (Selection-First Editing) | The 40% deterministic estimate from ADR-006 aligns with Tier 1 coverage |
| ADR-008 Track B (P4 prototype) | Tier 1 is the P4 prototype; Tiers 2-3 extend it |
| Upstream P4 (#3549232) | The upstream `update_component_data` tool is what all three tiers call |
| [drupal-intent-testing](https://github.com/scottfalconer/drupal-intent-testing/) | Testing framework for validating tier routing correctness |
| ai_context Scope (#3564706) | Complementary — Scope reduces Tier 4 cost; Tiers 1-3 reduce how often Tier 4 is reached |

---

## Open Questions

1. ~~Should Tier 3's micro-classifier response be cached?~~ **Premature optimization.** At ~$0.003 per call, the engineering cost of a cache system exceeds the token savings for any reasonable edit volume. Revisit only if Phase 4 measurement shows high-frequency repeated patterns.
2. Should Tier 2 support "then" as a sequence operator? "Change the heading to X, then move it up" — the first is deterministic but the second is a move operation. Current design rejects this ("all or nothing"). Should it?
3. What's the right timeout for Tier 3? Too short (1s) and complex schemas don't resolve; too long (5s) and users notice the delay before falling through to Tier 4.
4. Should the tier that handled a request be visible in the Canvas UI? E.g., a subtle "instant edit" badge. This could help content authors learn which phrasing gets instant results.
5. **Cross-turn reference resolution.** Tiers 1-3 are stateless. When a user says "change the heading to Welcome" (Tier 1 handles it) and then says "actually make it blue too" — the "it" refers to the heading from the previous turn. How should Tiers 1-3 handle anaphoric references? Options: (a) reject to Tier 4, (b) infer from `active_component_uuid` (the component is still selected), (c) Tier 3 gets minimal chat history (last 1-2 turns only).
6. **Undo/redo integration.** The full agent chain participates in Canvas's undo system. Do Tier 1-3 edits appear in the undo stack? The `DirectEditController` returns the same `operations` format, so the frontend should track them — but this needs verification.
7. **No-component-selected fallback.** The `DirectEditController` requires `component_uuid` and `component_name`. What happens when the user types "change the heading to Welcome" without selecting a component? ADR-006 assumes selection-first, but the frontend should handle this gracefully (e.g., skip Tiers 1-3, route directly to Tier 4 which can identify the component from context).
8. **Metrics collection.** Phase 4 needs structured data: (a) how many requests each tier handles, (b) Tier 1 rejections that Tier 3 resolved (missed alias opportunities), (c) Tier 3 rejections that Tier 4 resolved (micro-classifier limitations), (d) overall tier distribution per session. The `TokenBreakdownSubscriber` logs Tier 4 data; Tiers 1-3 need equivalent structured logging.
9. **Should Phase 4 (measurement) run before Phases 2-3?** Reordering to Phase 1 → Phase 4 → then decide on Phases 2-3 would validate coverage assumptions before investing in additional tiers. Trade-off: delays the full waterfall but reduces risk of building tiers that don't meaningfully expand coverage.
