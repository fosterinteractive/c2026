# Baseline 3.0: Deterministic Pre-Cognition for Canvas AI Edits

**Date:** 2026-03-29 (revised after meta-critic review)
**Status:** Revised — executing
**Depends on:** Baseline 2.0 (Tiers 1+2 merged to main)
**Inspired by:** [chenglou/pretext](https://github.com/chenglou/pretext) — design inspiration (not structural equivalence)

---

## Core Thesis

The question is not "how many regex patterns can we add?" — it's **"how much of the schema/state space can we pre-resolve into lookup tables so that message resolution becomes arithmetic?"**

Pretext (Cheng Lou, 2026) demonstrated this principle for text layout: the browser's layout engine is expensive because it does a full computation when you only need one measurement. Pretext pre-computes font metrics once (`prepare()`), then layout is pure arithmetic (`layout()`). No DOM, no reflow, instant.

The design inspiration applies to AI edit routing — with an important caveat. Pretext operates on a closed mathematical domain (font metrics are deterministic given typeface + size + string). Our match phase operates on natural language, which is open-ended. The `prepare` side of the analogy is structurally sound (pre-compute schema structures once). The `match` side involves NL parsing, not pure arithmetic — it's lookup *after* parsing, which adds edge cases pretext doesn't face.

| Pretext | Canvas AI Deterministic |
|---|---|
| DOM reflow is expensive (~16ms) | LLM call is expensive (~15-30s, 111K tokens) |
| `prepare()` = measure font segments once | Schema preparation = parse SDC schemas, build alias/enum/constraint maps |
| `layout()` = pure arithmetic over cached widths | `match()` = lookup over cached prop maps, after NL parsing |
| Closed domain (font metrics) | Open domain (natural language) — fails safe to LLM on ambiguity |

---

## Current State (Baseline 2.0)

| Tier | Coverage | Cost | Latency | Status |
|------|----------|------|---------|--------|
| 1: Exact pattern match | ~35-40% | 0 tokens | <100ms | Shipped |
| 2: Compound splitting | ~5-8% | 0 tokens | <100ms | Shipped |
| 3: Micro-classifier | ~15-20% | ~500 tokens | 1-2s | Designed |
| 4: Full agent chain | ~32-45% | 31-111K tokens | 15-30s | Current default |

**Combined Tiers 1+2: ~40-48% deterministic.** The rest falls through to the LLM.

---

## Prop Inventory (Actual Byte Theme)

From the 23 Byte theme component schemas (120 total props):

| Prop Category | Count | % | Deterministically Resolvable? |
|---|---|---|---|
| Enum (string) | 62 | 51.7% | YES — if value maps to exactly one prop |
| Plain string | 27 | 22.5% | YES — if prop is named explicitly |
| Boolean | 11 | 9.2% | YES — show/hide/enable/disable verbs |
| Numeric enum (level) | 5 | 4.2% | YES — 1-6 range validation |
| Object ($ref image) | 9 | 7.5% | NO — requires media selection |
| Rich text (HTML) | 3 | 2.5% | NO — requires content generation |
| Integer (timestamp) | 1 | 0.8% | NO — requires date interpretation |
| String (URL) | 2 | 1.7% | YES — if explicitly provided |

**Key finding (revised after critic review):** Some Byte theme components have high prop-type orthogonality (heading, button, badge — color/size/alignment enums use distinct value sets). However, **several high-frequency components have intra-component enum collisions:**

- **Group**: `flex_gap`, `radius`, `padding` all accept `sm/md/lg/xl`. "Large" maps to 3 props.
- **Card-icon**: `border_radius` and `icon_size` both accept `small/medium/large`.
- **Section**: 4 spacing props share overlapping numeric string values.
- **Hero-side-by-side**: `image_radius` and `hero_flex_gap` collide on `large/extra-large`.

This means bare value resolution ("blue" → `text_color`) works unambiguously on heading/button/badge but **rejects to next tier on group/section/card-icon** due to ambiguity. Phase 1 coverage estimates must be adjusted per-component. All coverage percentages below are schema-derived estimates, not frequency-weighted — Phase 0 measurement provides real data.

---

## The Prepare/Match Architecture

### Prepare Phase (on page load, cached)

Run once when the editor opens. Expensive but amortized:

1. **Schema Maps** (already built by `ComponentSchemaLoader`):
   - Prop alias → canonical prop name, per component
   - Enum value → canonical value, per prop per component

2. **Constraint Graph** (new):
   - For each component: which prop types accept which value categories?
   - Value category = color, size, alignment, boolean, numeric, string
   - Pre-computed reverse index: given a bare value, which props on this component could accept it?

3. **Enum Ordinals** (new):
   - Ordered sequences for each enum prop: `text_size: [xs, sm, md, lg, xl, 2xl, 3xl]`
   - Enables relative navigation: "bigger" = next index, "smaller" = previous

4. **Boolean Semantics** (new):
   - Prop → polarity map: `disabled` is inverted (enable = false), `section_header` is normal (show = true)
   - Verb → boolean map: show/enable/turn on → true, hide/disable/turn off → false

5. **Component State Snapshot** (new):
   - Current prop values for each component in the selected section
   - Loaded from tempstore layout data (already available)
   - Enables relative adjustments and ambiguity breaking

### Match Phase (on every message, must be instant)

Pure lookup over cached structures. No LLM, no HTTP calls:

```
message arrives
  → try explicit pattern ("change X to Y")        [Tier 1, existing]
  → try compound split ("X and set Y")             [Tier 2, existing]
  → try bare value inference ("blue")              [NEW: constraint graph lookup]
  → try boolean toggle ("show the header")         [NEW: boolean verb match]
  → try relative adjustment ("bigger")             [NEW: ordinal navigation]
  → try multi-component batch ("all headings blue") [NEW: tree traversal + batch]
  → all failed → pass to Tier 3 or Tier 4
```

Each step is a lookup, not a computation. The `prepare` phase did the work.

---

## Techniques by Phase

### Phase 0: Measurement + Schema Loader Expansion (PREREQUISITE)

**Effort:** 3-4 days | **Coverage gain:** 0% (validation + infrastructure)

Before building new resolution techniques, two prerequisites:

**0a. Structured telemetry on existing Tiers 1+2 (2 days):**
- Add structured logging to `DirectEditController`: tier ID, match/reject reason, component type, prop, message hash (no PII), elapsed microseconds
- Telemetry gated behind `canvas_ai_scoping.telemetry_enabled` in State API (default off) — except `elapsed_us` which is always logged
- Run 30-50 representative edits, capture actual tier distribution
- Compare schema-derived estimates against real frequency data
- Decision gate: if bare value messages are <3% of actual messages, skip Phase 1 and build Tier 3 micro-classifier instead

**0b. ComponentSchemaLoader interface expansion (1-2 days):**
The existing `ComponentSchemaLoaderInterface` exposes only `getPropAliases()`, `getEnumValues()`, `getSupportedComponents()`. Phases 1-3 need:
- `getReversEnumIndex(string $componentName): array` — given a value, which props accept it? (Phase 1)
- `getBooleanProps(string $componentName): array` — which props are boolean + their polarity? (Phase 2)
- `getEnumOrdinals(string $componentName): array` — ordered sequences with direction metadata? (Phase 3)
- Per-component orthogonality report: which components have zero enum collisions vs which have ambiguity

These methods + cache entries are prerequisite infrastructure. The constraint graph, boolean map, and ordinal sequences are built during this phase and cached in `cache.default` alongside existing schema maps.

### Phase 1: Bare Value + Type Inference

**Effort:** 3-5 days | **Coverage gain:** +4-8% (revised down from 8-12% due to orthogonality collisions)

When the user says "blue" or "make it blue" and no prop is named, scan the component's reverse enum index:

- How many props accept "blue" (or its aliases)?
- If exactly one: resolve deterministically.
- If zero or multiple: reject to next tier.

**Works on orthogonal components** (heading, button, badge, hero, cta-section): "blue" → `text_color`, "center" → `align`, "large" → `text_size`. Zero ambiguity.

**Rejects on collision components** (group, section, card-icon, hero-side-by-side): "large" maps to 3 props on group → ambiguous → reject. This is correct behavior — the user must be more specific ("set the padding to large") for Tier 1 to resolve it.

Also handles:
- "make it blue" / "make this centered" (strip "make it/this" prefix — must not conflict with existing ADD_PHRASES patterns for "make a"/"make me")
- Bare values without any verb ("blue", "center", "primary")

**Implementation:** Add `resolveByTypeInference()` to `DirectEditMatcher`, called when `resolveEdit()` returns null. Uses `getReversEnumIndex()` from the expanded schema loader. Rejects on ambiguity (multiple props match).

### Phase 2: Boolean Toggle Patterns

**Effort:** 1-2 days | **Coverage gain:** +2-4%

11 boolean props across Byte theme. Users toggle these with natural verbs:

| Pattern | Resolution |
|---|---|
| show/enable/turn on/activate {alias} | true (or false for inverted props) |
| hide/disable/turn off/deactivate {alias} | false (or true for inverted props) |

Inverted props: `disabled` (enable = false), `overlap_navbar` (disable = true).

**Implementation:** Boolean verb patterns in `matchSingle()`. Schema detection via prop type check. Inverted semantics map as static config.

### Phase 3: Relative Adjustments (Ordinal Navigation)

**Effort:** 3-5 days | **Coverage gain:** +2-3%

"Bigger", "smaller", "lighter", "darker" — comparative adjectives that navigate enum ordinals.

Requires:
1. Read current prop value from tempstore (already accessible in controller)
2. Look up current position in the enum's ordinal sequence
3. Navigate one step in the indicated direction
4. Boundary check: at max → reject to next tier

**Adjective lexicon** (static map):
- bigger/larger → +1 on size ordinals
- smaller/tinier → -1 on size ordinals
- lighter → -1 on weight/color intensity ordinals
- darker → +1 on weight/color intensity ordinals
- bolder → +1 on weight ordinals

**Implementation:** Enum ordinal sequences defined in `ComponentSchemaLoader`. `resolveRelative()` method in `DirectEditMatcher`. Current value read from tempstore via controller.

### Phase 4: Measurement and Validation

**Effort:** 2-3 days | **Coverage gain:** 0% (validation)

Before investing in Phases 5+, measure the actual tier distribution:

1. Structured logging on all tiers: tier ID, match/reject reason, component type, prop, message hash
2. Run 30-50 representative edits, capture tier distribution
3. Compare measured distribution against schema-derived estimates
4. Decision gate: if deterministic ceiling < 65%, invest in Tier 3 micro-classifier instead of more deterministic techniques

### Phase 5: Multi-Component Batch Operations

**Effort:** 5-8 days | **Coverage gain:** +2-4% | **Conditional on Phase 4 data**

"Change all headings to blue" when a section is selected:

1. Resolve prop/value pair via Phases 1-3
2. Traverse component tree to find children of selected container
3. Filter to children with the target prop
4. Apply edit to each matching child (atomically — all or none)

The `updateComponents` response array at `DirectEditController.php:195` already supports multiple components.

### Phase 6: Speculative Resolution (Pretext-Inspired)

**Effort:** 3-5 days | **Coverage gain:** +1-3% | **Conditional on Phase 4 data**

Like pretext's `walkLineRanges()` speculatively testing multiple widths:

When a message arrives and a section (not a specific component) is selected, speculatively resolve against ALL components in the section. If exactly one component matches the message unambiguously, route to it — the user doesn't need to have clicked precisely.

This turns imprecise selection into precise resolution through constraint narrowing.

### Phase 7: Pre-Computed Constraint Graph Caching

**Effort:** 2-3 days | **Coverage gain:** 0% (performance)

Build the constraint graph on first editor load, cache in Drupal's cache backend (not tempstore — shared across sessions). Invalidate on theme/component schema changes.

This makes the `prepare` phase a one-time cost shared across all users, reducing cold-start latency for the first deterministic edit.

---

## Theoretical Ceiling

All percentages are **schema-derived estimates, not frequency-weighted**. Phase 0 measurement will validate or revise these.

| Category | % of Routine Edits (est.) | Deterministic? |
|---|---|---|
| Explicit prop + value (Tiers 1+2) | 35-48% | YES (shipped) |
| Implicit value / bare value (Phase 1) | 4-8% | YES (reduced: orthogonality collisions on group/section) |
| Boolean toggles (Phase 2) | 1-3% | YES (reduced: ~6 of 11 booleans are true toggles) |
| Relative adjustments (Phase 3) | 2-3% | YES |
| Multi-component batch (Phase 5) | 2-4% | YES |
| Speculative resolution (Phase 6) | 1-3% | YES |
| **Deterministic ceiling** | **~45-69%** | |
| Tier 3 micro-classifier (ambiguous middle) | 8-15% | ~500 tokens |
| **Combined (deterministic + micro)** | **~53-84%** | |
| Rich text / content generation | 3-5% | NO — LLM required |
| Image/media selection | 5-8% | NO — LLM/browser required |
| Structural operations (add/remove/move) | 5-8% | NO — LLM required |
| Creative/subjective edits | 3-5% | NO — LLM required |
| Cross-component reasoning | 2-3% | NO — LLM required |
| **Irreducible LLM floor** | **~18-25%** | |

**The hybrid architecture:** Deterministic (instant, free) → Micro-classifier (cheap, fast) → Full agent (expensive, slow). Three layers, each catching what the previous can't:

| Layer | Coverage | Cost | Latency |
|---|---|---|---|
| Deterministic (Phases 1-6) | ~45-69% | 0 tokens | <100ms |
| Micro-classifier (Tier 3) | ~8-15% | ~500 tokens | 1-2s |
| Full agent (Tier 4) | ~18-25% | 31-111K tokens | 15-30s |
| **Weighted average** | 100% | **~8-18K tokens** | **~4-10s** |

vs Baseline 2.0 weighted average: ~35K tokens, ~12s.

**Note on weighted average:** The lower bound (8K) assumes ~65% deterministic + agent calls averaging 31K (optimized path). The upper bound (18K) assumes ~50% deterministic + agent calls averaging 60K (complex generative operations that fall through tend to be the most expensive). Session type matters: pure editing sessions will be near the lower bound; mixed creation+editing sessions near the upper.

---

## Diminishing Returns

| Phase | Effort | Coverage Gain | Days per % point | Recommendation |
|---|---|---|---|---|
| 0: Measurement + schema expansion | 3-4d | 0% (prerequisite) | N/A | **Build first — validates everything** |
| 1: Type inference | 3-5d | +4-8% | ~0.6 | **Build — best ROI after measurement** |
| 2: Boolean toggles | 1-2d | +1-3% | ~0.7 | **Build — nearly free** |
| 3: Relative adjustment | 3-5d | +2-3% | ~1.5 | **Build — completes UX** |
| 5: Batch operations | 5-8d | +2-4% | ~2.0 | Conditional on Phase 0 data |
| 6: Speculative resolution | 3-5d | +1-3% | ~2.0 | Conditional on Phase 0 data |

Phase 7 (constraint caching) is collapsed into Phase 0 — `cache.default` is already shared across sessions.

**Inflection point: after Phase 3.** Phases 0-3 add 7-14% coverage at 10-16 days (~1.0 days/%). Phases 5-6 add 3-7% at 8-13 days (~2.0 days/%). The marginal cost doubles.

**Critical decision gate:** Phase 0 measurement determines whether Phase 1's bare value inference is worth building. If <3% of actual messages are bare values, skip Phase 1 and invest in Tier 3 micro-classifier (~500 tokens, catches a broader set of ambiguous messages).

---

## What Cannot Be Deterministic

These operations genuinely require LLM reasoning, regardless of pre-computation:

1. **Content generation** — "Write a compelling headline" requires creative language generation
2. **Rich text composition** — "Add a bulleted list of benefits" requires generating HTML
3. **Image selection** — "Use a professional photo matching our brand" requires media search
4. **Structural operations** — "Add a testimonial section" requires creating new components
5. **Subjective judgment** — "Make this look more professional" requires aesthetic reasoning
6. **Cross-component reasoning** — "Match the style of the section above" requires reading another component and applying its properties
7. **Ambiguity when schema is insufficient** — "Fix the spacing" on a section with 4 margin/padding props where "spacing" maps to all of them

The irreducible floor is 18-25% of routine editing operations. During initial page creation, it's 50-70% (structural/generative operations dominate).

---

## Open Questions

1. **Should Tier 3 (micro-classifier) be built before or after Phases 1-3?** It catches a broader set of ambiguous messages but costs ~500 tokens per call. Phases 1-3 are free but cover a narrower set.

2. **Is the speculative resolution (Phase 6) safe?** Resolving against a component the user didn't explicitly select could surprise them. Need UX input on whether "we resolved your edit on the heading because it was the only match" is acceptable.

3. **Should the constraint graph be shared across sessions?** Caching in Drupal's cache backend means all users benefit from one preparation, but it adds cache invalidation complexity when themes update.

4. **What's the right measurement framework?** The Phase 4 telemetry needs to capture enough data to validate estimates without logging user content. Message hashing + tier/prop/component metadata may be sufficient.
