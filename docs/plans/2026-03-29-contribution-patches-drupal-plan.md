# Contribution-Ready Patches: Drupal Implementation Plan

> **For Claude:** Use drupal-planner protocol. Invoke drupal-critic at each checkpoint marked with review checkpoint.
> **Drupal Version:** 11.3 (Drupal CMS 2.0)
> **Companion skills:** drupal-critic, drupal-coding-standards, executing-plans

**Feature:** Extract the `canvas_ai_scoping` prototype into three contribution-ready patches against `ai_context`, `canvas_ai`, and `canvas` contrib modules.
**Risk Level:** High (modifying three contrib modules with interdependencies; upstream API surface changes; config schema additions)
**Existing Architecture:** Custom module `canvas_ai_scoping` contains all code. Target contrib modules have no knowledge of deterministic editing, loop-aware context, or layout scoping.

---

## Strategic Context

The `canvas_ai_scoping` custom module proved three optimizations:

1. **Deterministic editing** (P4): Pattern-match simple edits ("change heading to X") and apply them without invoking the LLM chain. 0 tokens, <100ms.
2. **Layout scoping** (P1): Replace full page layout in the system prompt with only the active section's subtree. ~60-80% token reduction per agent loop.
3. **Loop-aware context injection** (P2): Strip `ai_context` blocks on loop iterations >0 since the LLM already has them in conversation history. ~40% token reduction for multi-loop agents.

For upstream contribution, these must be extracted from the custom module into patches against the three modules that should own them. This plan specifies the architecture for each patch.

---

## Patch Dependency Chain

```
Patch 1: canvas (smallest, no deps)
  BuildSystemPromptEvent gains structured layout accessors
    |
Patch 2: ai_context (small, no deps on Patch 1)
  loop_aware config flag + SystemPromptSubscriber skip logic
    |
Patch 3: canvas_ai (largest, depends on Patch 1)
  DirectEditController, DirectEditMatcher, ComponentSchemaLoader, LayoutScopingSubscriber
```

Patches 1 and 2 are independent of each other. Patch 3 depends on Patch 1 (for the structured layout API). All three can be developed in parallel but must be applied in order: 1, 2, 3 (or 2, 1, 3).

---

## Patch 1: `canvas_ai` -- Structured Layout Token on BuildSystemPromptEvent

### Scope

**Problem:** `LayoutScopingSubscriber` currently uses `str_replace()` to swap layout JSON inside the system prompt string (`file:LayoutScopingSubscriber.php:129`). This is fragile -- if the JSON appears multiple times, is reformatted, or contains escaped characters, the replacement silently fails or corrupts the prompt.

**Solution:** The `BuildSystemPromptEvent` (owned by `ai_agents`, but the layout data is set by `canvas_ai`) should carry the layout as a structured array alongside the string prompt. Subscribers can modify the structured data; the final prompt builder serializes it.

**However:** `BuildSystemPromptEvent` is in `ai_agents`, not `canvas`. Patching `ai_agents` has a much larger blast radius and lower acceptance probability. Instead, this patch adds a **layout token** pattern: canvas_ai sets a well-known token key (`layout_data`) containing the parsed layout array, and the event's existing `setToken()`/`getTokens()` API carries it.

### What Changes

**Module:** `canvas_ai` (submodule of `canvas`)

**Files to modify:**

1. `modules/canvas_ai/src/Controller/CanvasBuilder.php` (~line 200-250, where tokens are set before dispatching `BuildSystemPromptEvent`)
   - **Change:** After setting `current_layout` as a string token, also set `layout_data` as a parsed array token via `$event->setToken('layout_data', $parsedLayout)`.
   - **Rationale:** The layout JSON is already parsed in `CanvasBuilder::render()` at `file:CanvasBuilder.php` when it calls `CanvasAiTempStore::setData()`. Passing the parsed version as a token eliminates redundant JSON parsing by every subscriber.

2. `modules/canvas_ai/src/EventSubscriber/CanvasAiSystemPromptSubscriber.php` (if it exists, or the equivalent that builds the system prompt)
   - **Change:** When constructing the system prompt, serialize `layout_data` into the prompt string at the designated position, and provide a replacement marker `{{ layout_json }}` so subscribers can also do string-level replacement as a fallback.

### Minimal Viable Change

The absolute smallest patch that delivers value:

- **Add one line in `CanvasBuilder::render()`:** After the layout is decoded, set it as a token:
  ```
  $event->setToken('layout_data', $parsedLayout);
  ```
- This is a non-breaking, additive change. No existing behavior changes. Subscribers that want structured access can use `$event->getTokens()['layout_data']`. Subscribers that don't know about it ignore it.

### Why This Design

| Decision | Rationale |
|----------|-----------|
| Token-based, not new event methods | `BuildSystemPromptEvent` is in `ai_agents` (different module, different maintainer). Adding methods requires patching `ai_agents`. Using the existing token bag is zero-API-change. |
| Parsed array, not accessor methods | Keeps the event class unchanged. The token is just data. |
| Both string and structured available | Backward compatible. Existing subscribers that do string manipulation still work. New subscribers can use the structured version. |

### Config Schema

No new config. No schema changes.

### Tests

- **Kernel test:** Verify that when `CanvasBuilder::render()` fires `BuildSystemPromptEvent`, the `layout_data` token is a valid array with `regions` key.
- **Kernel test:** Verify that the token contains the same data as the JSON string in the prompt (round-trip equivalence).

### Migration Path

None. Additive change only. No existing behavior modified.

---

## Patch 2: `ai_context` -- Loop-Aware Context Injection

### Scope

**Problem:** `ai_context`'s `SystemPromptSubscriber` (`file:ai_context/src/EventSubscriber/SystemPromptSubscriber.php:87`) appends 10-12K tokens of context on every `BuildSystemPromptEvent` dispatch. For agents that loop 5-15+ times (like `canvas_page_builder_agent`), this means 50K-180K tokens of identical context re-injected across loops. The LLM already has the context from loop 0 in its conversation history.

**Solution:** Add a `loop_aware` boolean to the per-agent config in `ai_context.agents`. When `loop_aware: true`, `SystemPromptSubscriber` skips context injection on loop count > 0.

### What Changes

**Module:** `ai_context`

**Files to modify:**

1. **`config/schema/ai_context.schema.yml`** (line 166-196, the `ai_context.agents` schema)
   - **Change:** Add `loop_aware` boolean to the per-agent mapping:
     ```
     loop_aware:
       type: boolean
       label: 'Skip context injection on agent loop iterations > 0'
     ```
   - **Location:** Inside the sequence mapping at line 166, alongside `always_include`, `excluded_subcontext`, and `scope_subscriptions`.

2. **`src/EventSubscriber/SystemPromptSubscriber.php`** (line 87, `onPreSystemPrompt()`)
   - **Change:** Before calling `$this->selector->select()`, check if this agent has `loop_aware: true` in config AND the current loop count > 0. If both, return early (skip injection).
   - **Loop count source:** The subscriber already listens to `AgentStartedExecutionEvent` at priority 100 (`file:SystemPromptSubscriber.php:59`). It captures `$event->getAgentRunnerId()`. It needs to also capture `$event->getLoopCount()` in `onAgentStarted()` and store it in `$this->loopCounts[$agentId]`.
   - **Config access:** Load `ai_context.agents` config, find the agent entry, check `loop_aware` flag.

3. **`src/Form/AiContextAgentForm.php`** (line 583-676, submit handler)
   - **Change:** Add a `loop_aware` checkbox to the per-agent settings form. Default: FALSE.
   - **Location:** After the scope subscriptions section (line 300-377), add a simple checkbox.
   - **Submit:** Persist `loop_aware` alongside `always_include`, `excluded_subcontext`, `scope_subscriptions`.

**Files to create:**

4. **`tests/src/Kernel/LoopAwareContextTest.php`**
   - Test that with `loop_aware: true`, context is injected on loop 0 but skipped on loop > 0.
   - Test that with `loop_aware: false` (default), context is injected on every loop.

### Design Decisions

| Decision | Rationale |
|----------|-----------|
| Per-agent config flag (not global) | Only multi-loop agents benefit. Single-loop agents (orchestrator, chatbot) should always get context. Per-agent gives admins control. |
| Boolean flag, not numeric threshold | Simplest possible API. A threshold ("skip after loop N") adds complexity for no proven benefit. If needed later, boolean can be replaced with integer without breaking existing `true`/`false` values (true = 1, false = 0). |
| Modify `SystemPromptSubscriber` directly, not a separate subscriber | The current prototype uses a separate `LoopAwareContextSubscriber` that strips the context block after injection. This is fragile: it depends on parsing the separator pattern (`AiContextPromptParser`), which breaks if `ai_context` changes its formatting. The correct fix is for `SystemPromptSubscriber` to not inject in the first place. |
| No `AiContextPromptParser` needed | By skipping injection rather than stripping it post-hoc, we eliminate the separator parsing dependency entirely. The parser in the prototype (`file:canvas_ai_scoping/src/AiContextPromptParser.php`) is a workaround for not owning the injection code. |

### Why NOT the Prototype Approach

The prototype (`LoopAwareContextSubscriber` + `AiContextPromptParser`) has two architectural problems:

1. **Separator coupling:** `AiContextPromptParser::SEPARATOR` (`-----------------------------------------------`, 47 dashes) is a format detail of `SystemPromptSubscriber`. If `ai_context` changes the separator (adds a header, uses XML tags, changes dash count), the parser silently breaks.

2. **Inject-then-strip waste:** The current approach lets `SystemPromptSubscriber` inject 10K tokens, then immediately strips them. The correct pattern is don't-inject, which is only possible inside `SystemPromptSubscriber` itself.

### Config Classification

| Config Item | Type | Exportable? | Why Here |
|-------------|------|-------------|----------|
| `ai_context.agents.*.loop_aware` | Simple config (boolean on existing config object) | Yes (part of `ai_context.agents` config export) | Per-agent behavioral flag, same lifecycle as other agent settings |

### Permissions

No new permissions. The `loop_aware` toggle is exposed in the existing agent configuration form, which requires `administer ai_context settings` (or whatever permission gates `AiContextAgentForm`).

### Cache Strategy

No new cacheable items. The `loop_aware` flag is read from config during event handling. Config is cached by Drupal's config system. When config changes (admin saves form), config cache invalidates automatically.

### Migration Path

- **New installs:** `loop_aware` defaults to `FALSE`. No behavior change.
- **Existing installs with `ai_context.agents` config:** The new `loop_aware` key is absent. Code must treat missing key as `FALSE`: `$agentConfig['loop_aware'] ?? FALSE`.
- **No `hook_update_N` needed:** Missing key is handled by the `?? FALSE` default.
- **Rollback:** Removing the patch leaves `loop_aware` keys in config. They are ignored by the original code (config schema is additive; extra keys don't cause errors in Drupal's config system for `type: mapping` with wildcard keys).

---

## Patch 3: `canvas_ai` -- Deterministic Editing + Layout Scoping

### Scope

This is the largest patch. It adds two capabilities to `canvas_ai`:

**A. Deterministic Edit Controller** -- A new endpoint that pattern-matches simple edits and applies them without the LLM chain.

**B. Layout Scoping Subscriber** -- An event subscriber that scopes the layout in the system prompt to the active section, reducing token usage for AI requests that do reach the LLM.

### Architecture: Deterministic Editing

#### Service: `ComponentSchemaLoader`

**Purpose:** Loads SDC component YAML schemas from the active theme and builds alias/enum maps consumed by `DirectEditMatcher`.

**Current problem:** The prototype hardcodes `byte_theme` (`file:ComponentSchemaLoader.php:60`, `private const THEME_NAME = 'byte_theme'`).

**Solution for upstream:** Discover the theme dynamically using `ThemeHandlerInterface::getDefault()`, which is already used by `CanvasAiPageBuilderHelper` for the same purpose (`file:CanvasAiPageBuilderHelper.php:1314`, `$active_theme = $this->themeHandler->getDefault()`).

| Decision | Rationale |
|----------|-----------|
| Use `ThemeHandlerInterface::getDefault()` | Matches existing pattern in `CanvasAiPageBuilderHelper` (same module). Returns the default frontend theme, which is where SDC components live. |
| NOT configurable theme name | Canvas pages use the default theme's components. There is no use case for loading schemas from a non-default theme. If one emerges, a config option can be added later. |
| Cache tag includes theme name | If the default theme changes (rare), the cache must rebuild. Tag: `['config:system.theme', 'canvas_ai_scoping']`. The `config:system.theme` tag invalidates when `system.theme.default` changes. |

**SDC name derivation:** The prototype builds SDC names as `'sdc.' . self::THEME_NAME . '.' . $componentDir` (`file:ComponentSchemaLoader.php:352`). With dynamic theme discovery, this becomes `'sdc.' . $defaultTheme . '.' . $componentDir`. This correctly produces SDC names like `sdc.byte_theme.heading` for Byte theme, `sdc.olivero.card` for Olivero, etc.

**Semantic alias map:** The prototype's `generateAliases()` (`file:ComponentSchemaLoader.php:474-567`) contains a large hardcoded `$semanticMap` with Byte-theme-specific prop aliases. For upstream:

| Decision | Rationale |
|----------|-----------|
| Move semantic aliases to a config entity or settings YAML | The alias map is theme-specific knowledge. Hardcoding Byte theme aliases in `canvas_ai` couples the module to one theme. |
| Alternative: Derive aliases algorithmically from prop names only | Simpler. `heading_text` produces `['heading_text', 'heading', 'text']` via underscore splitting. Loses domain aliases like `heading_text -> title` but works for any theme. |
| **Recommended: Algorithmic + optional override** | Default: algorithmic alias generation (underscore split + common patterns). Override: `canvas_ai.direct_edit.settings` config with a `prop_aliases` mapping for theme-specific additions. |

**Enum value aliases:** The same pattern applies to `getNaturalAliasesForEnumValue()`, which maps canonical enum values to natural language alternatives (e.g., "inverted" → ["white", "light"]). The prototype originally had a 50-entry hardcoded map with Byte-theme-specific values. This has been moved to `canvas_ai_scoping.settings` config under `enum_value_aliases`, with an algorithmic fallback that derives aliases from hyphenated values (e.g., "extra-large" → "extra large", "heading-responsive-4xl" → "4xl"). Theme developers can add theme-specific aliases via config without modifying module code.

| Decision | Rationale |
|----------|-----------|
| Config-driven enum value aliases | Same rationale as prop aliases: "primary" → "blue" is a Byte design token, not a universal mapping. Config makes this theme-portable. |
| Algorithmic fallback for hyphenated values | Covers the common case (enum values with hyphens) without requiring manual configuration. |
| Ship sensible defaults in `config/install` | New installs get a set of common aliases (color names, alignment terms, size abbreviations) that work across themes. Theme developers extend or override via config. |

#### Service: `DirectEditMatcher`

**Purpose:** Pattern-matches user messages against deterministic edit patterns and returns the prop name + value.

**Current problem:** The matcher's regex patterns are English-only (`file:DirectEditMatcher.php:176-181`, patterns like `change|set|update|modify|make`).

**Solution for upstream:**

| Decision | Rationale |
|----------|-----------|
| Ship English patterns as default | Canvas AI's system prompts and agent instructions are English. The frontend UI is English. The matcher targets the same language the user interacts with Canvas in. |
| Document i18n as future work | True multilingual support requires pattern sets per language. This is out of scope for the initial contribution. The architecture supports it: patterns are constants that could become config. |
| Reject gracefully for non-English | Non-English messages won't match any pattern and fall through to the AI chain (422 response). No incorrect behavior -- just no optimization. |

**No changes needed for upstream beyond namespace:** The `DirectEditMatcher` class is pure logic with no Drupal service dependencies beyond `ComponentSchemaLoaderInterface`. Move it from `Drupal\canvas_ai_scoping\Service` to `Drupal\canvas_ai\Service`.

#### Controller: `DirectEditController`

**Purpose:** HTTP endpoint at `/admin/api/canvas/direct-edit` that the Canvas frontend already calls before falling through to the AI endpoint.

**Current problem:** The controller depends on three concrete `canvas_ai` classes (`file:DirectEditController.php:7-9`):
- `AiResponseValidator` (no interface)
- `CanvasAiPageBuilderHelper` (no interface)
- `CanvasAiTempStore` (no interface)

**Solution for upstream:**

| Decision | Rationale |
|----------|-----------|
| Keep concrete dependencies | These are all `canvas_ai` services living in the same module as the controller. Interface extraction for internal services is overengineering when there is exactly one implementation and no foreseeable alternate implementations. The controller and these services ship, test, and version together. |
| Inject via `create()` using service IDs | Match existing `CanvasBuilder::create()` pattern (`file:CanvasBuilder.php:51-61`) which also injects `canvas_ai.page_builder_helper` and `canvas_ai.tempstore` as concrete types. |
| Remove `StateInterface` dependency | The prototype uses `State` for telemetry toggle (`file:DirectEditController.php:199`). For upstream, use a simple config setting or remove telemetry entirely. Contrib modules should not use State API for feature flags. |

**Response format:** The controller produces responses that match what `directEdit.ts` (`file:directEdit.ts:13-16`) and `AiWizard.tsx` (`file:AiWizard.tsx:751`) expect. The interface is already stable and tested against the frontend.

**Route definition:**
```
canvas_ai.direct_edit:
  path: '/admin/api/canvas/direct-edit'
  defaults:
    _controller: '\Drupal\canvas_ai\Controller\DirectEditController::edit'
  requirements:
    _permission: 'use Drupal Canvas AI'
  methods: [POST]
```

This mirrors the existing `canvas_ai.canvas_builder` route pattern (`file:canvas_ai.routing.yml:1-6`).

### Architecture: Layout Scoping

#### Subscriber: `LayoutScopingSubscriber`

**Purpose:** Scopes the layout in the system prompt to the active component's section, replacing the full page layout with a focused subtree.

**Current problem:** Uses `str_replace()` on JSON in the prompt string (`file:LayoutScopingSubscriber.php:129-132`). Fragile -- fails silently if JSON is reformatted or appears multiple times.

**Solution for upstream (with Patch 1):**

| Decision | Rationale |
|----------|-----------|
| Use `layout_data` token from Patch 1 | Read structured layout from `$event->getTokens()['layout_data']`. Modify the array. Write back via `$event->setToken('layout_data', $scopedLayout)`. No string surgery. |
| Fallback to `str_replace` if token missing | For backward compatibility if Patch 1 is not applied. Log a deprecation warning. |
| Keep `ContextEnvelopeBuilder` as separate service | Single responsibility: the subscriber decides WHEN to scope; the builder decides HOW to build the envelope. Keeps the subscriber thin and the envelope logic testable. |

**Agent targeting:** The prototype hardcodes agent IDs (`file:LayoutScopingSubscriber.php:33-41`):
```php
private const SECTION_SCOPED_AGENTS = ['canvas_page_builder_agent'];
private const ENVELOPE_AGENTS = ['canvas_component_agent'];
```

For upstream:

| Decision | Rationale |
|----------|-----------|
| Keep hardcoded agent IDs initially | These are Canvas's own agents. The module knows its own agent IDs. Making this configurable adds UI complexity for zero user benefit (users don't add custom Canvas agents). |
| Document the constants | Add docblock explaining which agents get which scoping level and why. |

### Files for Patch 3

**Files to create in `canvas_ai`:**

| File | Purpose |
|------|---------|
| `src/Service/ComponentSchemaLoaderInterface.php` | Interface for component schema loading |
| `src/Service/ComponentSchemaLoader.php` | Dynamic theme discovery, alias/enum map building |
| `src/Service/DirectEditMatcher.php` | Pattern matching for deterministic edits |
| `src/Service/ContextEnvelopeBuilder.php` | Builds focused context envelopes for selected components |
| `src/Controller/DirectEditController.php` | HTTP endpoint for deterministic edits |
| `src/EventSubscriber/LayoutScopingSubscriber.php` | Scopes layout in system prompt |
| `config/schema/canvas_ai.direct_edit.schema.yml` | Schema for direct edit settings (optional) |
| `tests/src/Unit/DirectEditMatcherTest.php` | Unit tests for pattern matching |
| `tests/src/Kernel/DirectEditControllerTest.php` | Kernel tests for the endpoint |
| `tests/src/Kernel/LayoutScopingSubscriberTest.php` | Kernel tests for scoping |

**Files to modify in `canvas_ai`:**

| File | Change |
|------|--------|
| `canvas_ai.services.yml` | Register 5 new services |
| `canvas_ai.routing.yml` | Add `canvas_ai.direct_edit` route |

### Service Registration

New entries for `canvas_ai.services.yml`:

| Service ID | Class | Dependencies |
|-----------|-------|--------------|
| `canvas_ai.component_schema_loader` | `ComponentSchemaLoader` | `extension.list.theme`, `theme_handler`, `cache.default`, `logger.channel.canvas_ai` |
| `canvas_ai.direct_edit_matcher` | `DirectEditMatcher` | `canvas_ai.component_schema_loader` |
| `canvas_ai.context_envelope_builder` | `ContextEnvelopeBuilder` | (none) |
| `canvas_ai.layout_scoping_subscriber` | `LayoutScopingSubscriber` | `canvas_ai.tempstore`, `canvas_ai.context_envelope_builder`, `logger.channel.canvas_ai` (tagged: `event_subscriber`) |
| (controller uses `create()`) | `DirectEditController` | `canvas_ai.direct_edit_matcher`, `canvas_ai.response_validator`, `canvas_ai.page_builder_helper`, `canvas_ai.tempstore`, `csrf_token`, `logger.channel.canvas_ai` |

### Config Schema (Patch 3)

| Config Item | Type | Schema | Exportable? | Why Here |
|-------------|------|--------|-------------|----------|
| `canvas_ai.direct_edit.settings` | Simple config | `enabled: boolean` (default true), `telemetry: boolean` (default false) | Yes | Module-level on/off switch. Replaces `State` API usage in prototype. |

If the optional prop alias override is included:

| Config Item | Type | Schema | Exportable? | Why Here |
|-------------|------|--------|-------------|----------|
| `canvas_ai.direct_edit.prop_aliases` | Simple config | `aliases: mapping` keyed by SDC component name, value is mapping of alias->prop_name | Yes | Theme-specific alias overrides. Not required for basic operation. |

### Permission Model

No new permissions. All endpoints use the existing `use Drupal Canvas AI` permission, matching the existing `canvas_ai.canvas_builder` route (`file:canvas_ai.routing.yml:5`).

| Role | Permission | Rationale |
|------|-----------|-----------|
| Canvas editor | `use Drupal Canvas AI` | Same permission as the AI endpoint. Direct edit is a faster path to the same outcome. No elevated privilege. |

### Cache Strategy

| Cacheable Item | Tags | Contexts | Max-Age | Invalidation Trigger | Rationale |
|----------------|------|----------|---------|---------------------|-----------|
| Component schema maps (alias, enum, boolean, ordinal) | `['config:system.theme', 'canvas_ai']` | None (same for all users) | PERMANENT | Theme change, `drush cr` | Schema maps are derived from theme YAML files. They change only when the theme changes or components are updated. `config:system.theme` tag handles theme switches. |
| Layout scoping (per-request) | Not cached | N/A | 0 | N/A | Layout scoping is per-request computation on the system prompt. No persistent cache needed. |
| Direct edit responses | Not cached | N/A | 0 | N/A | Each edit is unique (user message + component state). No caching benefit. |

### Migration Path for Existing Sites

**Sites without `canvas_ai_scoping`:** No migration. New features are additive. Direct edit endpoint exists but does nothing unless the frontend calls it (it already does -- Canvas frontend already calls `/admin/api/canvas/direct-edit` and handles 404/422 gracefully).

**Sites with `canvas_ai_scoping` custom module:** After applying patches:
1. `drush pm:uninstall canvas_ai_scoping` -- uninstall the custom module
2. `drush cr` -- rebuild caches to pick up new services
3. Verify: direct edit still works (now served by `canvas_ai` instead of custom module)
4. Remove `web/modules/custom/canvas_ai_scoping/` directory

**Rollback:** Remove the three patches. `drush cr`. Direct edit endpoint returns 404. Frontend falls through to AI for all edits. No data loss. No config corruption.

---

## Critical Design Issues and Resolutions

### Issue 1: ComponentSchemaLoader Hardcodes `byte_theme`

**File:** `canvas_ai_scoping/src/Service/ComponentSchemaLoader.php:60`

**Resolution:** Replace `private const THEME_NAME = 'byte_theme'` with runtime discovery:
- Inject `ThemeHandlerInterface` (service: `theme_handler`)
- Call `$this->themeHandler->getDefault()` in `resolveThemePath()`
- Include `config:system.theme` in cache tags so maps rebuild on theme switch

**Cache invalidation proof:** When `system.theme` config changes (admin switches default theme), all cache items tagged with `config:system.theme` are invalidated. The schema maps will be rebuilt with the new theme's components on next access.

### Issue 2: DirectEditController Coupling to Concrete Classes

**File:** `canvas_ai_scoping/src/Controller/DirectEditController.php:7-9`

**Resolution:** Keep concrete dependencies. Justification:
- `AiResponseValidator`, `CanvasAiPageBuilderHelper`, and `CanvasAiTempStore` are internal `canvas_ai` services
- The controller will live in the same module (`canvas_ai`)
- The existing `CanvasBuilder` controller (`file:CanvasBuilder.php:38-46`) already depends on the same concrete classes
- Interface extraction adds maintenance burden with no testability or extensibility benefit
- If interfaces are later needed, they can be added without breaking the controller

### Issue 3: LayoutScopingSubscriber Uses `str_replace` on JSON

**File:** `canvas_ai_scoping/src/EventSubscriber/LayoutScopingSubscriber.php:129`

**Resolution:** Two-pronged approach:
1. **Primary (with Patch 1):** Use the `layout_data` token for structured modification. Read array, scope it, write back.
2. **Fallback (without Patch 1):** Keep `str_replace` as a fallback with a logged warning. This handles the case where Patch 3 is applied but Patch 1 is not (e.g., different review/merge timelines).

The fallback should normalize both the original and replacement JSON to minimize formatting mismatches:
- Decode the layout string from the prompt
- Re-encode with consistent flags (`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`)
- Compare and replace

### Issue 4: LoopAwareContextSubscriber Depends on AiContextPromptParser

**File:** `canvas_ai_scoping/src/EventSubscriber/LoopAwareContextSubscriber.php:99`

**Resolution:** This subscriber does NOT move to `canvas_ai`. The loop-aware behavior belongs in `ai_context` (Patch 2) as a modification to `SystemPromptSubscriber`. The prototype's approach of inject-then-strip is replaced by don't-inject-at-all, which is architecturally correct and eliminates the parser dependency entirely.

### Issue 5: No Config Schema for New Settings

**Resolution:** Covered above. Patch 2 adds `loop_aware` to `ai_context.agents` schema. Patch 3 optionally adds `canvas_ai.direct_edit.settings` schema.

### Issue 6: English-Only Pattern Matching

**Resolution:** Documented as a known limitation. Ship English as default. The pattern constants in `DirectEditMatcher` could later be externalized to config, but the initial contribution should not over-engineer for a use case that doesn't exist yet (Canvas AI agents are English-only).

---

## Implementation Tasks

### Task 1: Patch 1 -- Structured Layout Token in `canvas`

**Review checkpoint:** Verify token propagation, backward compatibility

**Files to modify:**
- `web/modules/contrib/canvas/modules/canvas_ai/src/Controller/CanvasBuilder.php`

**Structure:**
- In `render()`, after the layout is decoded and stored in tempstore, set `$event->setToken('layout_data', $parsedLayout)` on the `BuildSystemPromptEvent`.
- This is a 1-3 line change.

**Tests:**
- Kernel test: dispatch `BuildSystemPromptEvent` via `CanvasBuilder::render()`, assert `layout_data` token is an array with expected structure.

**Risk:** Low. Additive only.

---

### Task 2: Patch 2 -- Loop-Aware Context in `ai_context`

**Review checkpoint:** Config schema correctness, form integration, skip logic timing

**Files to modify:**
- `web/modules/contrib/ai_context/config/schema/ai_context.schema.yml` -- add `loop_aware` boolean
- `web/modules/contrib/ai_context/src/EventSubscriber/SystemPromptSubscriber.php` -- add loop tracking + skip logic
- `web/modules/contrib/ai_context/src/Form/AiContextAgentForm.php` -- add checkbox

**Structure:**
- `SystemPromptSubscriber::onAgentStarted()`: capture `$event->getLoopCount()` into `$this->loopCounts[$agentId]`
- `SystemPromptSubscriber::onPreSystemPrompt()`: before `$this->selector->select()`, check:
  1. Load `ai_context.agents` config
  2. Find agent entry matching `$agentId`
  3. If `$agentConfig['loop_aware'] ?? FALSE` is TRUE and `$this->loopCounts[$agentId] ?? 0` > 0, return early
- `AiContextAgentForm`: add `loop_aware` checkbox after scope subscriptions, persist in submit handler

**Tests:**
- Kernel test: create agent config with `loop_aware: true`, dispatch `AgentStartedExecutionEvent` with loop=0 then `BuildSystemPromptEvent` -- context injected
- Kernel test: same agent, dispatch with loop=1 then `BuildSystemPromptEvent` -- context NOT injected
- Kernel test: agent with `loop_aware: false`, loop=5 -- context still injected

**Risk:** Medium. Modifying the core injection path of `ai_context`. Must not break non-loop-aware agents.

---

### Task 3: Patch 3a -- ComponentSchemaLoader + DirectEditMatcher in `canvas_ai`

**Review checkpoint:** Theme discovery correctness, cache invalidation, alias generation without hardcoded theme data

**Files to create:**
- `modules/canvas_ai/src/Service/ComponentSchemaLoaderInterface.php`
- `modules/canvas_ai/src/Service/ComponentSchemaLoader.php`
- `modules/canvas_ai/src/Service/DirectEditMatcher.php`

**Files to modify:**
- `modules/canvas_ai/canvas_ai.services.yml` -- register services

**Structure:**
- `ComponentSchemaLoader`:
  - Replace `private const THEME_NAME = 'byte_theme'` with `$this->themeHandler->getDefault()`
  - Replace hardcoded `$semanticMap` with algorithmic generation + optional config override
  - Cache tags: `['config:system.theme', 'canvas_ai']`
  - Add `ThemeHandlerInterface` dependency
- `DirectEditMatcher`: direct port from prototype with namespace change (`canvas_ai_scoping` -> `canvas_ai`)

**Tests:**
- Unit test: `DirectEditMatcher::match()` with 20+ patterns covering Tier 1, Tier 2, bare value, boolean toggle, relative adjustment, compound edits, rejection cases
- Kernel test: `ComponentSchemaLoader` discovers components from the installed default theme

---

### Task 4: Patch 3b -- DirectEditController + Route in `canvas_ai`

**Review checkpoint:** CSRF validation, response format compatibility with frontend, access control

**Files to create:**
- `modules/canvas_ai/src/Controller/DirectEditController.php`

**Files to modify:**
- `modules/canvas_ai/canvas_ai.routing.yml` -- add route

**Structure:**
- Port from prototype with these changes:
  - Namespace: `Drupal\canvas_ai\Controller`
  - Service IDs: `canvas_ai.direct_edit_matcher` (not `canvas_ai_scoping.direct_edit_matcher`)
  - Remove `StateInterface` dependency; replace with simple config check for telemetry
  - Logger channel: `canvas_ai` (not `canvas_ai_scoping`)

**Tests:**
- Kernel test: POST to `/admin/api/canvas/direct-edit` with valid payload, verify 200 response with `direct_edit: true`
- Kernel test: POST with non-matching message, verify 422 response
- Kernel test: POST without CSRF token, verify 403

---

### Task 5: Patch 3c -- LayoutScopingSubscriber in `canvas_ai`

**Review checkpoint:** Token-based vs string-based scoping, agent targeting, event priority ordering

**Files to create:**
- `modules/canvas_ai/src/Service/ContextEnvelopeBuilder.php`
- `modules/canvas_ai/src/EventSubscriber/LayoutScopingSubscriber.php`

**Files to modify:**
- `modules/canvas_ai/canvas_ai.services.yml` -- register subscriber + service

**Structure:**
- `LayoutScopingSubscriber`:
  - Priority: -10 (after `ai_context` at 0, before any downstream subscribers)
  - Reads `layout_data` token if available (Patch 1), falls back to `str_replace`
  - Section scoping for `canvas_page_builder_agent`
  - Envelope scoping for `canvas_component_agent`
- `ContextEnvelopeBuilder`: direct port from prototype

**Tests:**
- Kernel test: dispatch `BuildSystemPromptEvent` with full layout, verify scoped layout in token
- Kernel test: component in main region selected, verify other regions summarized
- Kernel test: no component selected, verify no scoping applied

---

### Task 6: Config Schema + Integration Tests

**Review checkpoint:** Schema validation passes, config export/import round-trips correctly

**Files to create/modify:**
- `modules/canvas_ai/config/schema/canvas_ai.schema.yml` -- add direct_edit settings schema (if settings config is included)
- `modules/canvas_ai/config/install/canvas_ai.direct_edit.settings.yml` -- default settings

**Tests:**
- Config schema validation: `drush config:validate` passes with new schema
- Integration test: apply all 3 patches, run a direct edit end-to-end through the Canvas editor

---

## Review Checkpoint Plan

| Checkpoint | After Task | drupal-critic Focus |
|------------|-----------|---------------------|
| 1 | Task 1 (Patch 1) | Backward compatibility of token addition, no side effects on existing subscribers |
| 2 | Task 2 (Patch 2) | Config schema correctness, loop count edge cases (0, 1, reset between requests), form UX |
| 3 | Task 3 (Patch 3a) | Theme discovery correctness, cache tag completeness, no hardcoded theme names |
| 4 | Task 4 (Patch 3b) | CSRF validation matches existing endpoint, response format matches frontend expectations, no XSS in JSON response |
| 5 | Task 5 (Patch 3c) | Event subscriber priority ordering, fallback behavior when Patch 1 absent, no prompt corruption |
| 6 | Task 6 (Integration) | All three patches applied together, full E2E flow, config export/import |

---

## Failure Modes

| Failure Mode | Impact | Prevention |
|-------------|--------|------------|
| Theme name changes between cache build and cache read | Stale schema maps, wrong SDC names | `config:system.theme` cache tag invalidates on theme change |
| `layout_data` token modified by subscriber running before scoping subscriber | Scoping operates on pre-modified layout | Document priority ordering; scoping runs at -10 |
| `SystemPromptSubscriber` refactored in future `ai_context` release | Patch 2 conflicts | Patch is minimal (add one property, one check). Small conflict surface. |
| Canvas frontend changes `directEdit.ts` response handling | 200 responses ignored by frontend | Pin Canvas version in composer.json; monitor upstream changes |
| Agent ID constants change in future Canvas release | Scoping stops targeting correct agents | Constants are documented; test coverage catches regressions |
| Persistent PHP runtime (FrankenPHP) leaks loop state across requests | Wrong loop count, context incorrectly skipped | Reset `$this->loopCounts` on loop=0 (prototype already does this at `file:LoopAwareContextSubscriber.php:73`) |

---

## Next Steps

**Execute with:** `/drupal-critic` -- review each patch architecture before implementation
**Implement with:** Each patch as a separate git branch, generating `git diff` patch files
**Test with:** `phpunit` for kernel/unit tests, Playwright for E2E validation
**Contribute:** File issues on drupal.org for each patch, attach patch files, reference benchmark data

---

### Contract Appendix (for spec-kitty-bridge WP translation)

### Architecture Overview

Three patches against three Drupal contrib modules:
1. `canvas` (1-3 line change): Add structured layout data as a token on `BuildSystemPromptEvent`
2. `ai_context` (50-80 lines): Add `loop_aware` boolean to per-agent config, skip context injection on loop > 0
3. `canvas_ai` (800-1000 lines): Add `DirectEditController`, `DirectEditMatcher`, `ComponentSchemaLoader`, `LayoutScopingSubscriber`, `ContextEnvelopeBuilder`

Key decisions: dynamic theme discovery via `ThemeHandlerInterface::getDefault()`, concrete dependencies for internal services (no interface extraction), token-based layout modification (not string surgery), skip-injection (not inject-then-strip).

### Implementation Tasks

#### Task 1: Structured Layout Token
Estimated Effort: low
Depends on: none
#### Test Strategy for Task 1
Kernel test verifying token presence and structure on `BuildSystemPromptEvent`.
#### Acceptance Criteria for Task 1
- `BuildSystemPromptEvent` tokens include `layout_data` key
- Value is a parsed array with `regions` key
- Existing subscribers unaffected (no behavioral change)

#### Task 2: Loop-Aware Context
Estimated Effort: medium
Depends on: none
#### Test Strategy for Task 2
Kernel tests for loop=0 injection, loop>0 skip, default false behavior.
#### Acceptance Criteria for Task 2
- Config schema validates
- `loop_aware: true` skips injection on loop > 0
- `loop_aware: false` (default) injects on every loop
- Admin form checkbox works
- Missing `loop_aware` key in existing config treated as false

#### Task 3: ComponentSchemaLoader + DirectEditMatcher
Estimated Effort: high
Depends on: none
#### Test Strategy for Task 3
Unit tests for 20+ matcher patterns. Kernel test for theme discovery.
#### Acceptance Criteria for Task 3
- Schema maps built from default theme (not hardcoded)
- Cache invalidates on theme change
- All Tier 1, Tier 2, Phase 1-3 patterns match correctly
- Non-matching messages return null (no false positives)

#### Task 4: DirectEditController
Estimated Effort: medium
Depends on: [3]
#### Test Strategy for Task 4
Kernel tests for 200/400/403/422 responses.
#### Acceptance Criteria for Task 4
- CSRF validation matches existing Canvas endpoint pattern
- Response format matches `directEdit.ts` expectations
- 422 on non-matching messages (frontend falls through to AI)
- No State API usage (config-based telemetry or none)

#### Task 5: LayoutScopingSubscriber
Estimated Effort: medium
Depends on: [1]
#### Test Strategy for Task 5
Kernel tests for section scoping, envelope building, fallback behavior.
#### Acceptance Criteria for Task 5
- Active section fully included, siblings summarized, other regions counted
- Works with layout_data token (Patch 1) or falls back to str_replace
- Event priority documented and correct (-10)

#### Task 6: Config Schema + Integration
Estimated Effort: low
Depends on: [1, 2, 3, 4, 5]
#### Test Strategy for Task 6
Config schema validation, E2E integration test.
#### Acceptance Criteria for Task 6
- `drush config:validate` passes
- Config export/import round-trips correctly
- All three patches apply cleanly against contrib HEAD

### Failure Modes
- Theme switch without cache clear: stale schema maps (mitigated by `config:system.theme` tag)
- `ai_context` upstream refactor: Patch 2 merge conflict (mitigated by minimal change surface)
- Canvas frontend API change: direct edit responses ignored (mitigated by version pinning)
- Persistent PHP runtime state leak: wrong loop count (mitigated by reset on loop=0)
- Missing Patch 1 when Patch 3 applied: str_replace fallback (logged warning, functional but fragile)
