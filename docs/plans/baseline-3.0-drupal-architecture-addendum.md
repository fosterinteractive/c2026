# Baseline 3.0: Drupal Architecture Addendum

**Date:** 2026-03-29
**Status:** Addendum to baseline-3.0-deterministic-precognition.md
**Drupal Version:** 11.3 (Drupal CMS 2.0)
**Module:** `canvas_ai_scoping`

> **For Claude:** Use drupal-planner protocol. Invoke drupal-critic at each checkpoint marked with review checkpoint.
> **Companion skills:** drupal-critic, test-driven-development, drupal-coding-standards

**Purpose:** This document provides Drupal-specific architectural decisions for the 7 phases described in the parent plan. It covers service design, cache strategy, tempstore access, logging, testing, and incremental migration — the decisions that must be correct before the first line of PHP is written.

**Risk Level:** Medium — extends an existing working system (Tiers 1+2) with new service collaborators. Primary risks are: breaking existing deterministic matching, cache invalidation correctness, and tempstore coupling.

---

## 1. Service Design

### 1.1 Current Service Graph

The existing module has this dependency structure:

```
DirectEditController
  -> DirectEditMatcher
       -> ComponentSchemaLoaderInterface (ComponentSchemaLoader impl)
            -> ThemeExtensionList
            -> CacheBackendInterface (cache.default)
            -> LoggerInterface
  -> AiResponseValidator (contrib: canvas_ai)
  -> CanvasAiPageBuilderHelper (contrib: canvas_ai)
  -> CanvasAiTempStore (contrib: canvas_ai)
  -> CsrfTokenGenerator (core)
  -> LoggerInterface
```

**Key architectural constraint:** `DirectEditMatcher` is currently a pure function of `(message, componentName)`. It has zero side effects, zero I/O beyond the schema loader's cached data, and zero awareness of page state. This purity is what makes it unit-testable with mocked `ComponentSchemaLoaderInterface`. Every new service must preserve this property or explicitly document why it breaks.

### 1.2 New Services Required

#### Service 1: `ConstraintGraphBuilder`

**Purpose:** Builds the pre-computed reverse index from bare values to candidate props per component — the "constraint graph" from the parent plan's Prepare phase.

**Responsibility (one sentence):** Given a component name, produces a map of `{normalized_value => [prop_name, ...]}` by inverting all enum value maps for that component, enabling bare-value resolution when exactly one prop matches.

**Why Custom:** No contrib module solves this. It is a derived data structure computed from the existing `ComponentSchemaLoaderInterface` output. It is a pure transformation — no new I/O, no new dependencies beyond the schema loader.

**Interface:** `ConstraintGraphBuilderInterface`

```
getValueCandidates(string $componentName): array<string, list<string>>
  — Returns {normalized_value => [prop_name_1, prop_name_2, ...]}
  — When the list has exactly one entry, the value is unambiguous for that component.

getBooleanProps(string $componentName): array<string, array{polarity: string, aliases: list<string>}>
  — Returns {prop_name => {polarity: 'normal'|'inverted', aliases: [...]}}
  — 'normal': show/enable => true. 'inverted' (e.g., 'disabled'): enable => false.

getEnumOrdinals(string $componentName): array<string, list<string>>
  — Returns {prop_name => [value_0, value_1, ..., value_N]} in schema-defined order.
  — Used by ordinal navigation ("bigger" = next index).
```

**Constructor dependencies:**
- `ComponentSchemaLoaderInterface` — the existing schema loader (provides raw prop/enum data)
- `CacheBackendInterface` — for caching the derived constraint graph (see Section 2)
- `LoggerInterface` — the existing `logger.channel.canvas_ai_scoping`

**Why not extend ComponentSchemaLoader:** The schema loader's responsibility is "parse YAML, build alias/enum maps." The constraint graph is a derived structure consumed differently (reverse lookups, ordinal sequences, boolean semantics). Mixing these concerns would violate SRP and make the schema loader harder to test. The constraint graph builder *composes with* the schema loader as a collaborator, not a subclass.

**Composition with existing services:** The builder reads from `ComponentSchemaLoaderInterface` (the same interface already mocked in tests). It does NOT access YAML files, the theme extension list, or tempstore. It is a pure derivative service.

#### Service 2: `RelativeValueResolver`

**Purpose:** Resolves comparative adjectives ("bigger", "darker") to concrete enum values by combining the constraint graph's ordinal sequences with the current prop value from page state.

**Responsibility (one sentence):** Given a component name, a direction keyword, and the current prop values, navigates the enum ordinal to the next/previous step and returns the resolved prop+value pair, or null if at boundary or ambiguous.

**Why Custom:** This is application-specific logic with no contrib equivalent. The adjective lexicon and ordinal navigation are specific to the Byte theme's enum structure.

**Interface:** `RelativeValueResolverInterface`

```
resolve(string $message, string $componentName, array $currentPropValues): ?array{prop: string, value: mixed}
  — Parses the message for comparative adjectives.
  — Looks up which prop the adjective category maps to (size, color intensity, weight).
  — Reads the current value from $currentPropValues.
  — Navigates the ordinal and returns the next/previous value.
  — Returns null if: no adjective match, ambiguous prop, at boundary, or current value unknown.
```

**Constructor dependencies:**
- `ConstraintGraphBuilderInterface` — for ordinal sequences
- `LoggerInterface`

**Critical design decision: current values as parameter, not injected tempstore.** This service receives current prop values as a method parameter, NOT by injecting `CanvasAiTempStore`. Rationale in Section 3.

#### Service 3: `BooleanToggleResolver`

**Purpose:** Resolves boolean toggle commands ("show the header", "disable the button") to concrete prop+value pairs.

**Responsibility (one sentence):** Given a message and component name, matches boolean verb patterns against the component's boolean prop map (from ConstraintGraphBuilder), respecting polarity inversion for props like `disabled` and `overlap_navbar`.

**Why Custom:** Boolean toggle semantics (verb polarity, prop inversion) are application-specific. No contrib module.

**Interface:** `BooleanToggleResolverInterface`

```
resolve(string $message, string $componentName): ?array{prop: string, value: bool}
  — Matches "show/enable/turn on" => true, "hide/disable/turn off" => false.
  — For inverted-polarity props: flips the boolean.
  — Returns null if no boolean prop matches the message.
```

**Constructor dependencies:**
- `ConstraintGraphBuilderInterface` — for boolean prop map
- `LoggerInterface`

### 1.3 Service Registration (services.yml additions)

The following services are added. Note that all three new services depend on `ConstraintGraphBuilderInterface`, not on each other — they are siblings, not a chain.

```
canvas_ai_scoping.constraint_graph_builder:
  class: ...\Service\ConstraintGraphBuilder
  arguments:
    - '@canvas_ai_scoping.component_schema_loader'
    - '@cache.default'
    - '@logger.channel.canvas_ai_scoping'

canvas_ai_scoping.boolean_toggle_resolver:
  class: ...\Service\BooleanToggleResolver
  arguments:
    - '@canvas_ai_scoping.constraint_graph_builder'
    - '@logger.channel.canvas_ai_scoping'

canvas_ai_scoping.relative_value_resolver:
  class: ...\Service\RelativeValueResolver
  arguments:
    - '@canvas_ai_scoping.constraint_graph_builder'
    - '@logger.channel.canvas_ai_scoping'
```

### 1.4 How DirectEditMatcher Composes With New Services

**Option A (recommended): Matcher delegates to resolvers.** `DirectEditMatcher` gains constructor dependencies on `BooleanToggleResolverInterface` and (for Phase 3) `RelativeValueResolverInterface`. The `match()` method's fallback chain becomes:

```
match(message, componentName, ?currentPropValues = null):
  1. Try existing explicit pattern match (Tier 1)       [matchSingle]
  2. Try compound split (Tier 2)                          [splitCompoundMessage]
  3. Try bare value inference (Phase 1)                   [resolveByTypeInference — new, uses ConstraintGraphBuilder directly]
  4. Try boolean toggle (Phase 2)                         [BooleanToggleResolver::resolve]
  5. Try relative adjustment (Phase 3)                    [RelativeValueResolver::resolve — needs currentPropValues]
  6. All failed -> return null
```

**Why not Option B (separate orchestrator):** A new "MatchOrchestrator" service that calls the matcher and then the new resolvers would add a layer of indirection with no benefit. The matcher already IS the orchestrator — its `match()` method already implements a fallback chain. Adding steps to that chain is simpler and preserves the single entry point that the controller depends on.

**Interface change to DirectEditMatcher:** The `match()` method signature gains an optional third parameter:

```
public function match(string $message, string $componentName, ?array $currentPropValues = null): ?array
```

The `?array $currentPropValues` is null for Phases 1-2 (no state needed) and populated by the controller for Phase 3+. This is backward compatible — existing callers pass two arguments and get identical behavior.

### 1.5 Service Dependency Diagram (After All Phases)

```
DirectEditController
  -> DirectEditMatcher
       -> ComponentSchemaLoaderInterface
       -> ConstraintGraphBuilderInterface    [Phase 1]
       -> BooleanToggleResolverInterface     [Phase 2]
       -> RelativeValueResolverInterface     [Phase 3]
  -> AiResponseValidator (contrib)
  -> CanvasAiPageBuilderHelper (contrib)
  -> CanvasAiTempStore (contrib)             [reads current values for Phase 3]
  -> CsrfTokenGenerator (core)
  -> LoggerInterface
```

**Tempstore is only accessed by the controller**, never by the matcher or resolvers. See Section 3.

---

## 2. Cache Strategy

### 2.1 What Is Being Cached

The constraint graph (Phase 1), boolean prop map (Phase 2), and ordinal sequences (Phase 3) are all derived from the same source: the component YAML schemas parsed by `ComponentSchemaLoader`. They change only when:

1. The byte_theme is updated (composer update / patch)
2. A component YAML file is added, removed, or modified
3. The cache is explicitly cleared (`drush cr`)

### 2.2 Cache Bin Decision

**Use `cache.default`, NOT a custom cache bin.**

Rationale:
- The existing `ComponentSchemaLoader` already uses `cache.default` (file: `ComponentSchemaLoader.php:137-148`). The constraint graph is derived from the same data and has the same invalidation lifecycle. Using the same bin keeps invalidation atomic — a single `drush cr` or tag invalidation clears both the source maps and the derived graph.
- A custom bin (`cache.canvas_ai_scoping`) would require a `*.services.yml` bin definition, a `container.modules` tag, and offers zero benefit since the data volume is small (120 props across 23 components produces a graph under 50KB serialized).
- The `cache.default` bin is backed by the database in default Drupal and by Redis/Memcache in production — both sufficient for this data size.

### 2.3 Cache IDs

Following the existing convention from `ComponentSchemaLoader`:

| Cache ID | Contents | Phase |
|---|---|---|
| `canvas_ai_scoping:prop_aliases` | Prop alias maps (EXISTING) | — |
| `canvas_ai_scoping:enum_values` | Enum value maps (EXISTING) | — |
| `canvas_ai_scoping:constraint_graph` | Reverse value-to-prop index | Phase 1 |
| `canvas_ai_scoping:boolean_props` | Boolean prop map with polarity | Phase 2 |
| `canvas_ai_scoping:enum_ordinals` | Ordered enum sequences | Phase 3 |

### 2.4 Cache Tags

**Use the existing `canvas_ai_scoping` tag** for all new cache entries.

Rationale: The existing `ComponentSchemaLoader` already tags both its entries with `canvas_ai_scoping` (file: `ComponentSchemaLoader.php:140,147`). Since the constraint graph is derived from those entries, it must be invalidated whenever they are. Using the same tag ensures atomic invalidation.

```
$this->cache->set(
  'canvas_ai_scoping:constraint_graph',
  $graph,
  CacheBackendInterface::CACHE_PERMANENT,
  ['canvas_ai_scoping'],
);
```

### 2.5 Cache Invalidation Triggers

| Trigger | Mechanism | What Happens |
|---|---|---|
| `drush cr` | Full cache rebuild | All `cache.default` entries cleared, including all `canvas_ai_scoping:*` entries. On next request, `ensureLoaded()` rebuilds from YAML. |
| Theme update (composer) | Deployment script runs `drush cr` | Same as above. |
| Explicit tag invalidation | `Cache::invalidateTags(['canvas_ai_scoping'])` | Invalidates all 5 cache entries atomically. Used if a hook detects theme registry changes. |

**No automatic rebuild on schema change** is needed beyond `drush cr`. The component YAML files are part of the theme's codebase and change only during deployments, which always include cache clearing. There is no runtime mechanism for editing component schemas in Drupal.

### 2.6 Cache Rebuild Strategy

The `ConstraintGraphBuilder::ensureLoaded()` method follows the identical pattern to `ComponentSchemaLoader::ensureLoaded()` (file: `ComponentSchemaLoader.php:121-149`):

1. Check in-memory property (`$this->constraintGraph !== null` -> return)
2. Check cache backend (`$this->cache->get(CID)`)
3. If cache miss: call `ComponentSchemaLoaderInterface` to get raw data, build derived structures, write to cache

This means the constraint graph is built lazily on first access after cache clear, NOT eagerly. The cold-start penalty is negligible because it is pure in-memory computation over the already-loaded schema maps.

### 2.7 Phase 7 (Shared Constraint Graph Caching) — Assessment

The parent plan proposes caching the constraint graph in a shared cache backend (not tempstore) so all users benefit. **This is already the architecture described above.** `cache.default` is shared across all sessions — it is not per-user. The `PrivateTempStore` (which IS per-user) is only used for component state. There is no additional work needed for Phase 7 beyond what Phases 1-3 already implement.

**Recommendation:** Merge Phase 7 into Phases 1-3. It is not a separate effort; it is the natural consequence of using `cache.default` with the `canvas_ai_scoping` tag.

---

## 3. Tempstore Access Pattern

### 3.1 The Problem

Phase 3 (relative adjustments like "bigger") needs the current prop value for the selected component. The current prop values live in `CanvasAiTempStore` under `COMPONENTS_IN_PAGE_WITH_PROP_VALUES_KEY`. The data is a JSON string: `{"uuid": {"prop": "value", ...}, ...}`.

Two options:
- **A: Controller reads tempstore, passes values to matcher** (recommended)
- **B: Matcher injects CanvasAiTempStore and reads directly**

### 3.2 Decision: Option A — Controller Passes Values

**The controller reads tempstore and passes current prop values as a parameter to `DirectEditMatcher::match()`.**

Rationale with evidence:

1. **Preserves matcher purity.** `DirectEditMatcher` is currently a pure function of `(message, componentName)` with no I/O beyond the cached schema loader. The test file (`DirectEditMatcherTest.php:119-138`) mocks only `ComponentSchemaLoaderInterface` — no tempstore, no request, no session. If the matcher injects `CanvasAiTempStore`, every test must mock a `PrivateTempStoreFactory` → `PrivateTempStore` chain, which requires Drupal's service container (pushing tests from Unit to Kernel).

2. **Follows the existing pattern.** The controller already accesses `CanvasAiTempStore` (file: `DirectEditController.php:9,38,134`). It already knows the `component_uuid`. Reading current values is one additional `getData()` call + JSON decode + array lookup — trivial code in the controller.

3. **Avoids coupling service to session.** `CanvasAiTempStore` wraps `PrivateTempStore`, which is scoped to the current user's session. Injecting it into the matcher ties a reusable algorithm to a session-scoped service. If the matcher is ever called from a non-HTTP context (drush command for testing, queue worker for batch operations), the tempstore will be empty or wrong.

### 3.3 Implementation in Controller

The controller adds this logic before calling `match()`:

```
// Read current prop values for the selected component (Phase 3: relative adjustments).
$currentPropValues = null;
$componentsJson = $this->canvasAiTempStore->getData(
  CanvasAiTempStore::COMPONENTS_IN_PAGE_WITH_PROP_VALUES_KEY
);
if (is_string($componentsJson)) {
  $allComponents = Json::decode($componentsJson);
  if (is_array($allComponents) && isset($allComponents[$componentUuid])) {
    $currentPropValues = $allComponents[$componentUuid];
  }
}

$match = $this->matcher->match($message, $componentName, $currentPropValues);
```

**Note:** The controller already has `$componentUuid` validated (file: `DirectEditController.php:103-105`). The tempstore data may be null if the page was never loaded in the editor — this is handled gracefully because `$currentPropValues` defaults to null and `RelativeValueResolver::resolve()` returns null when current values are missing.

### 3.4 Data Flow for Phase 3

```
1. User loads page in Canvas editor
   -> CanvasBuilder::render() populates tempstore with component prop values
      (file: CanvasBuilder.php:244-246)

2. User selects a heading component, types "bigger"
   -> Frontend POSTs to /admin/api/canvas/direct-edit

3. DirectEditController::edit()
   -> Reads tempstore for component UUID -> gets {text_size: "heading-responsive-4xl", ...}
   -> Calls matcher->match("bigger", "sdc.byte_theme.heading", {text_size: "heading-responsive-4xl"})

4. DirectEditMatcher::match()
   -> Tier 1: no explicit pattern match
   -> Tier 2: no compound split
   -> Phase 1: "bigger" not in any enum value set
   -> Phase 2: "bigger" not a boolean verb
   -> Phase 3: RelativeValueResolver::resolve("bigger", component, currentValues)
      -> "bigger" maps to +1 on size ordinals
      -> Current text_size = "heading-responsive-4xl" (index 4 in ordinal sequence)
      -> Next = "heading-responsive-3xl" (index 5) [sizes go 8xl->xl, so "bigger" = toward 8xl = index 3]
      -> Returns {prop: "text_size", value: "heading-responsive-3xl"}

5. Controller applies the edit via the standard Canvas pipeline
```

**Open design question for Phase 3 implementation:** The ordinal direction for heading sizes requires care. The enum order in YAML is `[default, heading-responsive-8xl, ..., heading-responsive-xl]` — 8xl is the largest. "Bigger" should move toward 8xl (lower index), not higher index. The ordinal sequence builder must record whether the enum is ascending or descending for the "size" category. This is a schema-interpretation concern, not a Drupal architecture concern, but it must be specified before implementation.

---

## 4. Logging Strategy

### 4.1 The Problem

Phase 4 requires structured telemetry: tier ID, match/reject reason, component type, prop resolved, message hash. This data is needed for:
- Measuring actual tier distribution across representative edits
- Validating the coverage estimates from the parent plan
- Decision gate: invest in Phases 5-6 or pivot to Tier 3 micro-classifier

### 4.2 Decision: Extend the Existing Logger Channel Pattern

**Use the existing `logger.channel.canvas_ai_scoping` with structured log messages, NOT a custom database table.**

Rationale with evidence:

1. **Follows existing module convention.** `TokenBreakdownSubscriber` (file: `TokenBreakdownSubscriber.php:95-111`) already logs structured telemetry through the module's logger channel using named placeholders (`@agent`, `@loop`, `@total_bytes`). This data is consumed by reading Drupal's `watchdog` table or the syslog. The same approach works for tier distribution.

2. **A custom database table is over-engineering for measurement.** Phase 4 explicitly states "run 30-50 representative edits, capture tier distribution." This is a one-time measurement pass, not a permanent analytics system. The `watchdog` table with structured log messages is sufficient. If persistent analytics are needed later, that is a separate feature.

3. **Avoids schema changes.** A custom table requires `hook_schema()`, `hook_update_N()`, and a service for CRUD. The measurement data is ephemeral — it informs a decision gate and is then discarded.

### 4.3 Log Format

A single structured log entry per `match()` call:

```
DirectEditTier @tier | component=@component | prop=@prop | reason=@reason | message_hash=@hash | elapsed_us=@elapsed
```

| Placeholder | Value | Purpose |
|---|---|---|
| `@tier` | `tier1_explicit`, `tier2_compound`, `phase1_bare_value`, `phase2_boolean`, `phase3_relative`, `reject` | Which tier resolved the edit |
| `@component` | SDC component name | Component type distribution |
| `@prop` | Resolved prop name or `none` | Which props are most commonly edited |
| `@reason` | `matched`, `ambiguous`, `no_match`, `boundary`, `unknown_value` | Why this tier was selected or rejected |
| `@hash` | SHA-256 of the message (first 12 chars) | Deduplication without logging user content |
| `@elapsed` | Microseconds for the full match() call | Performance validation (<100ms target) |

### 4.4 Implementation Location

The logging is added inside `DirectEditMatcher::match()` itself, not in a separate subscriber. Rationale:
- The matcher knows which tier resolved the edit (it runs the fallback chain)
- The controller does not know which internal step succeeded
- A subscriber pattern is wrong because `match()` is not an event — it is a synchronous method call

The matcher gains a `LoggerInterface` constructor dependency (it currently has none). This is a minimal change:

```
// services.yml change:
canvas_ai_scoping.direct_edit_matcher:
  class: ...\Service\DirectEditMatcher
  arguments:
    - '@canvas_ai_scoping.component_schema_loader'
    - '@canvas_ai_scoping.constraint_graph_builder'
    - '@canvas_ai_scoping.boolean_toggle_resolver'
    - '@canvas_ai_scoping.relative_value_resolver'
    - '@logger.channel.canvas_ai_scoping'
```

### 4.5 Measurement Toggle

Telemetry logging should be gated behind a state flag to avoid log noise in normal operation:

```
canvas_ai_scoping.telemetry_enabled (State API — NOT config)
```

**Why State API, not config:** This is runtime toggle state, not site configuration. It should not be exported to code, not version-controlled, and not deployed across environments. The existing module already uses State API for the strip-during-edits list (file: `ContextEditScopeManager.php:36-37`). Same pattern.

Enable: `drush state:set canvas_ai_scoping.telemetry_enabled 1`
Disable: `drush state:set canvas_ai_scoping.telemetry_enabled 0`

When disabled (default), no telemetry log entries are written. When enabled, every `match()` call logs one entry.

---

## 5. Testing Strategy

### 5.1 Current Test Inventory

| Test Class | Type | What It Tests | Dependencies Mocked |
|---|---|---|---|
| `DirectEditMatcherTest` | UnitTestCase | Pattern matching, compound splitting, rejection | `ComponentSchemaLoaderInterface` |
| `DirectEditControllerTest` | UnitTestCase | Controller flow, tempstore seeding, response format | All 6 collaborators |
| `AiContextPromptParserTest` | UnitTestCase | Prompt parsing utility | None (static methods) |
| `ContextEnvelopeBuilderTest` | UnitTestCase | Layout envelope building | None |
| `LayoutScopingSubscriberTest` | UnitTestCase | Event subscriber logic | Tempstore, logger |

**Pattern:** All existing tests are `UnitTestCase` with mocked interfaces. No Kernel or Browser tests exist.

### 5.2 Does This Pattern Scale?

**Yes, for Phases 1-3. No, for Phases 5-6.**

**Phases 1-3 (bare value, boolean, relative):** These are pure algorithmic resolvers with interface-based dependencies. They scale perfectly with the existing unit test pattern:

- `ConstraintGraphBuilderTest` (UnitTestCase): Mock `ComponentSchemaLoaderInterface`, verify the reverse index, boolean map, and ordinal sequences are built correctly.
- `BooleanToggleResolverTest` (UnitTestCase): Mock `ConstraintGraphBuilderInterface`, verify verb matching and polarity inversion.
- `RelativeValueResolverTest` (UnitTestCase): Mock `ConstraintGraphBuilderInterface`, pass current values as parameter, verify ordinal navigation and boundary handling.
- `DirectEditMatcherTest` (UnitTestCase): Add test cases to the existing data providers for Phase 1-3 patterns. Mock the new resolver interfaces alongside the existing schema loader mock.

**Phase 4 (measurement):** Logging tests are straightforward in UnitTestCase — mock the logger and assert `->info()` was called with expected placeholders.

**Phase 5 (multi-component batch):** Requires traversing the component tree from tempstore layout data. If the matcher stays pure (receives the tree as a parameter), UnitTestCase works. If it needs to resolve the tree from tempstore, a KernelTestBase test with `PrivateTempStoreFactory` from the container may be needed.

**Phase 6 (speculative resolution):** Same consideration as Phase 5 — depends on whether the component tree is passed in or fetched.

### 5.3 Test Plan Per Phase

#### Phase 1: Bare Value + Type Inference

**Test class:** Extend `DirectEditMatcherTest` + new `ConstraintGraphBuilderTest`

`ConstraintGraphBuilderTest` (UnitTestCase):
- `testValueCandidatesWithSingleMatch()` — "blue" maps to exactly [text_color] on heading
- `testValueCandidatesWithAmbiguousMatch()` — a value that maps to 2+ props returns both
- `testValueCandidatesWithNoMatch()` — "rainbow" maps to []
- `testCacheHitSkipsBuild()` — verify cache backend is read before building
- `testCacheMissTriggersRebuild()` — verify build + cache write on miss

`DirectEditMatcherTest` additions (data provider entries):
- `"blue"` on heading component -> resolves to `{prop: text_color, value: primary}`
- `"center"` on heading component -> resolves to `{prop: align, value: center}`
- `"make it blue"` with prefix stripping -> resolves to `{prop: text_color, value: primary}`
- `"blue"` on a component where two props accept "blue" -> returns null (ambiguous)
- `"blue"` on unknown component -> returns null

#### Phase 2: Boolean Toggle Patterns

**Test class:** New `BooleanToggleResolverTest` + extend `DirectEditMatcherTest`

`BooleanToggleResolverTest` (UnitTestCase):
- `testShowHeader()` — "show the header" on section -> `{prop: section_header, value: true}`
- `testHideHeader()` — "hide the header" on section -> `{prop: section_header, value: false}`
- `testEnableDisabledProp()` — "enable the button" on button (disabled prop) -> `{prop: disabled, value: false}` (inverted)
- `testDisableDisabledProp()` — "disable the button" on button -> `{prop: disabled, value: true}` (inverted)
- `testNoBooleanPropMatch()` — "show the color" on heading -> null (color is not boolean)
- `testTurnOnVerb()` — "turn on the footer" -> `{prop: section_footer, value: true}`
- `testDeactivateVerb()` — "deactivate overlap" on hero -> `{prop: overlap_navbar, value: false}`

#### Phase 3: Relative Adjustments

**Test class:** New `RelativeValueResolverTest` + extend `DirectEditMatcherTest`

`RelativeValueResolverTest` (UnitTestCase):
- `testBiggerFromMidpoint()` — current text_size=4xl, "bigger" -> next larger size
- `testSmallerFromMidpoint()` — current text_size=4xl, "smaller" -> next smaller size
- `testBiggerAtMaxBoundary()` — current text_size=8xl, "bigger" -> null (at max)
- `testSmallerAtMinBoundary()` — current text_size=xl, "smaller" -> null (at min, excluding default)
- `testNoCurrentValue()` — currentPropValues is null -> null
- `testUnknownCurrentValue()` — current value not in ordinal -> null
- `testDarkerLighter()` — verify color intensity ordinal navigation

`DirectEditMatcherTest` additions (data provider entries):
- `"bigger"` with currentPropValues -> resolves to next size
- `"bigger"` without currentPropValues (null) -> returns null
- `"smaller"` at boundary -> returns null

#### Phase 4: Measurement

**Test class:** Extend `DirectEditMatcherTest`

- `testTelemetryLoggedWhenEnabled()` — mock logger, mock state with telemetry=1, verify `->info()` called with tier/component/hash placeholders
- `testNoTelemetryWhenDisabled()` — mock state with telemetry=0, verify logger `->info()` NOT called for telemetry (but regular logs still work)

### 5.4 Test Infrastructure Consideration

The matcher test file (`DirectEditMatcherTest.php`) currently constructs the matcher with one mock. After Phase 3, it constructs with five dependencies. This is manageable but should use a `setUp()` helper that builds all mocks with sensible defaults, so individual test methods only override the mock behavior they care about. The existing `setUp()` pattern (file: `DirectEditMatcherTest.php:119-138`) already does this for the schema loader — extend it for the new interfaces.

---

## 6. Migration Path: Incremental Landing Without Breaking Tiers 1+2

### 6.1 Constraint: Backward Compatibility of `match()` Signature

The `match()` method is called from one place: `DirectEditController::edit()` (file: `DirectEditController.php:142`):

```php
$match = $this->matcher->match($message, $componentName);
```

Adding `?array $currentPropValues = null` as a third parameter with a default of `null` is backward compatible. The controller continues to work without change until Phase 3 is landed.

### 6.2 Interface Contract: `DirectEditMatcherInterface`

**The matcher does not currently have an interface.** The controller injects the concrete class `DirectEditMatcher` (file: `DirectEditController.php:10,35`). The controller test also uses the concrete class (file: `DirectEditControllerTest.php:31`).

**Recommendation: Do NOT extract an interface now.** The matcher is the only implementation and is internal to this module. Extracting an interface for a single concrete class adds ceremony without value. If a second implementation is needed later (e.g., a "learning matcher" that uses ML), extract the interface then.

However, the new resolver services SHOULD have interfaces because:
1. They are injected into the matcher, which is unit-tested with mocks
2. Mocking a concrete class requires the class to be non-final or use a mock framework that supports final classes
3. All three resolvers are `final class` per Drupal coding standards (like the existing services)

### 6.3 Landing Sequence

Each phase lands as a separate PR. Each PR:
- Adds the new service(s) and interface(s)
- Adds the service to `services.yml`
- Adds the dependency to `DirectEditMatcher`'s constructor
- Adds test cases to the existing test file + new test files
- Does NOT modify `DirectEditController` (except Phase 3 which adds tempstore read)

| PR | Phase | Services Added | Matcher Changes | Controller Changes | Risk |
|---|---|---|---|---|---|
| PR 1 | Phase 1: Bare value | `ConstraintGraphBuilder` + interface | Add `resolveByTypeInference()` fallback after `matchSingle()` returns null | None | Low: new fallback, existing paths unchanged |
| PR 2 | Phase 2: Boolean | `BooleanToggleResolver` + interface | Add boolean check in fallback chain after Phase 1 | None | Low: new fallback, existing paths unchanged |
| PR 3 | Phase 3: Relative | `RelativeValueResolver` + interface | Add relative check in fallback chain after Phase 2, add `$currentPropValues` param | Add tempstore read before `match()` call | Medium: controller change + new param |
| PR 4 | Phase 4: Telemetry | None (logging in matcher) | Add `LoggerInterface` dependency, add telemetry logging | None | Low: logging only, gated behind state flag |
| PR 5+ | Phases 5-7 | Conditional on Phase 4 data | TBD | TBD | TBD |

### 6.4 Rollback Strategy Per PR

Each PR is independently revertable:
- **PR 1 revert:** Remove `ConstraintGraphBuilder` from `services.yml` and matcher constructor. Phase 1 fallback path removed. Tiers 1+2 continue to work.
- **PR 2 revert:** Remove `BooleanToggleResolver` from `services.yml` and matcher constructor. Phase 2 fallback removed.
- **PR 3 revert:** Remove tempstore read from controller, remove `RelativeValueResolver`, revert `match()` signature to two params. Phase 3 fallback removed.
- **PR 4 revert:** Remove `LoggerInterface` from matcher constructor, remove telemetry code. Logging stops.

**No database migrations.** No schema changes. No config entity changes. All state is in cache (auto-rebuilds) and State API (manually set via drush). Rollback is purely code revert + `drush cr`.

### 6.5 Feature Flag Consideration

For Phases 1-3, feature flags are unnecessary. Each new tier either resolves a message or returns null. If it returns null, the existing behavior (fall through to Tier 3/4) applies. There is no behavioral change to existing matches — only new matches for previously-unresolvable messages.

The only flag needed is the Phase 4 telemetry toggle (`canvas_ai_scoping.telemetry_enabled`), which is already State API (Section 4.5).

---

## 7. Open Architectural Questions

### 7.1 Should ConstraintGraphBuilder Pre-compute at Module Install?

Currently: all data is lazy-loaded (cache miss triggers build). This is consistent with `ComponentSchemaLoader`.

Alternative: `hook_install()` or `hook_modules_installed()` pre-warms the cache.

**Recommendation: No.** The cold-start cost is negligible (pure in-memory computation over 23 YAML files). Pre-computing at install adds code for an optimization that saves <50ms on the first request after cache clear.

### 7.2 Should the Adjective Lexicon Be Config or Code?

The parent plan defines a static map: `bigger/larger -> +1 on size`, `darker -> +1 on color intensity`. This could be:
- **A: Constants in code** (like `ADD_KEYWORDS` in `DirectEditMatcher.php:48-51`)
- **B: Simple config** in `config/install/canvas_ai_scoping.adjective_lexicon.yml`

**Recommendation: A (constants in code).** The lexicon is small (under 20 entries), tightly coupled to the matching algorithm, and unlikely to be changed by site builders. Config adds schema definition, config entity overhead, and cache layer for no practical benefit. If the lexicon grows beyond ~50 entries or needs per-site customization, promote to config then.

### 7.3 Phase 5 Batch: Tree Traversal Service?

Phase 5 ("change all headings to blue") requires traversing the component tree to find children of the selected container. Should this be:
- **A: A method on `DirectEditMatcher`** — the matcher already knows the component context
- **B: A new `ComponentTreeTraverser` service** — separates tree traversal from matching

**Recommendation: B, but defer the decision until Phase 4 data confirms Phase 5 is worth building.** The tree traversal concern is different from pattern matching. The traverser would need the layout data (from tempstore via controller), making it stateful in a way the matcher is not. Keep the design space open.

### 7.4 Inverted Boolean Polarity: Static Map vs Schema Detection

The parent plan mentions inverted props (`disabled`, `overlap_navbar`). The polarity could be:
- **A: Static map** in `ConstraintGraphBuilder` — hardcoded list of inverted prop names
- **B: Heuristic** — prop names containing "disabled", "overlap", "hide" are inverted

**Recommendation: A (static map).** There are only 2-3 inverted props in the Byte theme. A heuristic is fragile ("disabled" is inverted but "hidden" might not exist). A static map of `['disabled' => 'inverted', 'overlap_navbar' => 'inverted']` is clear, testable, and maintainable. If new inverted props appear in future theme versions, add them to the map.

---

## Review Checkpoints

| Checkpoint | After | drupal-critic Focus |
|---|---|---|
| 1 | PR 1 (Phase 1 bare value) | Interface design, cache tag correctness, fallback chain does not alter existing Tier 1/2 behavior, constraint graph correctness for all 23 Byte components |
| 2 | PR 2 (Phase 2 boolean) | Boolean polarity inversion correctness, verb pattern coverage, no false positives on non-boolean props |
| 3 | PR 3 (Phase 3 relative) | Tempstore access pattern (controller only, not matcher), ordinal direction correctness, boundary handling, backward-compatible signature change |
| 4 | PR 4 (Phase 4 telemetry) | No PII in logs (message hash only), state flag toggle works, telemetry log format parseable for analysis |

---

## Appendix: Existing Code References

All architectural decisions in this document reference specific files and lines:

| Decision | Evidence |
|---|---|
| Use `cache.default` bin | `ComponentSchemaLoader.php:127-148` — existing service uses same bin |
| Use `canvas_ai_scoping` cache tag | `ComponentSchemaLoader.php:140,147` — existing tag |
| Controller reads tempstore, not matcher | `DirectEditController.php:9,38,134` — controller already injects tempstore; `DirectEditMatcherTest.php:119-138` — matcher tests mock only the schema loader interface |
| State API for telemetry toggle | `ContextEditScopeManager.php:36-37` — existing module uses State API for runtime flags |
| Logger channel pattern for telemetry | `TokenBreakdownSubscriber.php:95-111` — existing structured logging through same channel |
| `match()` signature backward compatibility | `DirectEditController.php:142` — single call site with two arguments |
| No matcher interface needed | `DirectEditController.php:10,35` — injects concrete class; `DirectEditControllerTest.php:31` — tests use concrete class |
| Boolean props from schema | `badge.component.yml:38`, `button.component.yml:68,72,78`, `section.component.yml:130,137`, `hero-billboard.component.yml:74`, `accordion.component.yml:22`, `card.component.yml:82`, `card-pricing.component.yml:72`, `footer.component.yml:8` — 11 boolean props across 8 components |
| Enum ordinals from schema | `heading.component.yml:29-48` — text_size enum with ordered values |
| Tempstore data format | `CanvasAiTempStore.php:22` — `COMPONENTS_IN_PAGE_WITH_PROP_VALUES_KEY` stores JSON string; `CanvasAiPageBuilderHelper.php:1537-1542` — `getComponentContents()` decodes it |
| CanvasBuilder populates tempstore | `CanvasBuilder.php:244-246` — sets layout data on render |
