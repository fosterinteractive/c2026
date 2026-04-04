# Patch 3: canvas_ai — Deterministic Editing + Layout Scoping

**Target module:** `canvas_ai` (submodule of `canvas`)
**Issue:** TBD on drupal.org/project/canvas
**Depends on:** Patch 1 (layout_data token) for full layout scoping benefit. Deterministic editing works independently.

---

## Summary

This patch adds two capabilities to `canvas_ai`:

1. **Deterministic Edit Controller** — An endpoint at `/admin/api/canvas/direct-edit` that pattern-matches simple user edits ("change the heading to X", "make it blue") and applies them without invoking the LLM agent chain. 0 tokens, <100ms response.

2. **Layout Scoping Subscriber** — An event subscriber that replaces the full page layout in the system prompt with the active section's subtree before the LLM sees the prompt. Reduces token cost 60–80% per agent loop for section-scoped requests.

Both capabilities were prototyped in the `canvas_ai_scoping` custom module. This document describes the architectural corrections required to make the prototype contribution-ready.

---

## Files to Create

| File | Purpose |
|------|---------|
| `src/Service/ComponentSchemaLoaderInterface.php` | Contract for schema loading |
| `src/Service/ComponentSchemaLoader.php` | Loads SDC component YAML schemas; builds alias/enum maps |
| `src/Service/DirectEditMatcher.php` | Pattern-matches user messages to deterministic prop edits |
| `src/Service/ContextEnvelopeBuilder.php` | Builds focused context envelopes for selected components |
| `src/Controller/DirectEditController.php` | POST endpoint for deterministic edits |
| `src/EventSubscriber/LayoutScopingSubscriber.php` | Scopes layout in system prompt before LLM dispatch |
| `config/install/canvas_ai.direct_edit.settings.yml` | Default config (enabled: true, telemetry: false) |
| `config/schema/canvas_ai.direct_edit.schema.yml` | Schema for direct edit settings |
| `tests/src/Unit/DirectEditMatcherTest.php` | Unit tests for pattern matching logic |
| `tests/src/Kernel/DirectEditControllerTest.php` | Kernel tests for the endpoint |
| `tests/src/Kernel/LayoutScopingSubscriberTest.php` | Kernel tests for layout scoping |

## Files to Modify

| File | Change |
|------|--------|
| `canvas_ai.services.yml` | Register 5 new services (see Service Registration section) |
| `canvas_ai.routing.yml` | Add `canvas_ai.direct_edit` route |

---

## Architectural Corrections: Prototype → Contrib

### 1. ComponentSchemaLoader: Replace hardcoded `byte_theme` with dynamic theme discovery

**Prototype problem:**
```php
// canvas_ai_scoping/src/Service/ComponentSchemaLoader.php:65
private const THEME_NAME = 'byte_theme';
```

**Contrib solution:** Inject `\Drupal\Core\Extension\Theme\ActiveTheme` via `ThemeHandlerInterface` and discover the default theme at runtime.

```php
// Constructor injection
public function __construct(
  private readonly ThemeExtensionList $themeList,
  private readonly ThemeHandlerInterface $themeHandler,
  private readonly CacheBackendInterface $cache,
  private readonly LoggerInterface $logger,
) {}

// In resolveThemePath():
$defaultThemeName = $this->themeHandler->getDefault();
$theme = $this->themeList->get($defaultThemeName);
```

**SDC name derivation** changes from:
```php
$sdcName = 'sdc.' . self::THEME_NAME . '.' . $componentDir;
```
to:
```php
$sdcName = 'sdc.' . $defaultThemeName . '.' . $componentDir;
```

**Cache tags** must include `config:system.theme` so the schema maps rebuild when the site's default theme changes:
```php
$this->cache->set($cid, $data, CacheBackendInterface::CACHE_PERMANENT, [
  'config:system.theme',
  'canvas_ai',
]);
```

**Rationale:** `CanvasAiPageBuilderHelper` already uses `$this->themeHandler->getDefault()` for the same purpose (resolving which theme's components to use). This matches the existing module pattern.

---

### 2. ComponentSchemaLoader: Move semantic alias map to config or algorithmic derivation

**Prototype problem:** `generateAliases()` contains a 60-entry hardcoded `$semanticMap` with Byte-theme-specific prop aliases (e.g., `'heading_text' => ['heading', 'title', 'text']`). This couples `canvas_ai` to one theme.

**Contrib solution (two-tier approach):**

**Tier 1 — Algorithmic (always active):** Derive aliases from prop name structure only.
- The prop name itself is always an alias.
- Underscore-split words longer than 2 characters become aliases.
- Human-readable spaced version (`heading_text` → `heading text`) becomes an alias.

```php
private function generateAliasesAlgorithmic(string $propName): array {
  $aliases = [$propName];
  $words = explode('_', $propName);
  foreach ($words as $word) {
    if (mb_strlen($word) > 2 && $word !== $propName) {
      $aliases[] = $word;
    }
  }
  $spaced = str_replace('_', ' ', $propName);
  if ($spaced !== $propName) {
    $aliases[] = $spaced;
  }
  return array_values(array_unique($aliases));
}
```

**Tier 2 — Config override (optional):** Site administrators can define additional aliases in `canvas_ai.direct_edit.prop_aliases` config:
```yaml
# canvas_ai.direct_edit.prop_aliases.yml
aliases:
  'sdc.byte_theme.heading':
    'heading': 'heading_text'
    'title': 'heading_text'
```

This config is loaded and merged at map-build time. Sites using `byte_theme` can ship a config recipe that adds the domain-specific aliases without those being hardcoded in the module.

---

### 3. DirectEditController: Remove StateInterface dependency for telemetry toggle

**Prototype problem:**
```php
// DirectEditController.php:199
if ($this->state->get('canvas_ai_scoping.telemetry_enabled', FALSE)) {
```

Contrib modules should not use the `State` API for feature flags. State is for runtime operational data (last cron run, import counters), not configuration.

**Contrib solution:** Read the telemetry flag from config:
```php
$config = $this->configFactory->get('canvas_ai.direct_edit.settings');
if ($config->get('telemetry')) {
  // detailed logging
}
```

Replace `StateInterface` injection with `ConfigFactoryInterface` in the constructor. The config is defined in `canvas_ai.direct_edit.settings` (see Config Schema section).

---

### 4. LayoutScopingSubscriber: Replace str_replace with layout_data token

**Prototype problem:**
```php
// LayoutScopingSubscriber.php:129
$prompt = str_replace($originalLayoutJson, $scopedLayoutJson, $prompt);
```

This fails silently if the JSON appears multiple times in the prompt, or if encoding flags differ between what Canvas wrote and what the replacement produces.

**Contrib solution (with Patch 1 applied):**
```php
public function onPreSystemPrompt(BuildSystemPromptEvent $event): void {
  $tokens = $event->getTokens();

  // Use structured layout_data token from Patch 1 (canvas layout token).
  if (!isset($tokens['layout_data']) || !is_array($tokens['layout_data'])) {
    // Patch 1 not applied; log a one-time warning and skip scoping.
    $this->logger->warning('LayoutScopingSubscriber: layout_data token not available. '
      . 'Apply the canvas layout token patch for layout scoping support.');
    return;
  }

  $agentId = $event->getAgentId();
  if (!$this->shouldScope($agentId)) {
    return;
  }

  $activeUuid = $tokens['active_component_uuid'] ?? NULL;
  if (!$activeUuid || $activeUuid === 'None') {
    return;
  }

  $scopedLayout = $this->scopeToSection($tokens['layout_data'], $activeUuid);
  if ($scopedLayout !== NULL) {
    $event->setToken('layout_data', $scopedLayout);
    // Also update the serialized layout token so prompt builders see
    // the scoped version.
    $event->setToken('layout', json_encode($scopedLayout,
      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
  }
}
```

**Agent targeting:** The subscriber checks agent IDs against constants. These are Canvas's own agents; making this user-configurable adds UI complexity with no realistic use case.

```php
/** Agents that receive section-level scoping (reduces full page to active section). */
private const SECTION_SCOPED_AGENTS = ['canvas_page_builder_agent'];

/** Agents that receive component-envelope scoping (reduces to selected component subtree). */
private const ENVELOPE_AGENTS = ['canvas_component_agent'];
```

---

## Service Registration

New entries for `canvas_ai.services.yml`:

```yaml
canvas_ai.component_schema_loader:
  class: Drupal\canvas_ai\Service\ComponentSchemaLoader
  arguments:
    - '@extension.list.theme'
    - '@theme_handler'
    - '@cache.default'
    - '@logger.channel.canvas_ai'

canvas_ai.direct_edit_matcher:
  class: Drupal\canvas_ai\Service\DirectEditMatcher
  arguments:
    - '@canvas_ai.component_schema_loader'

canvas_ai.context_envelope_builder:
  class: Drupal\canvas_ai\Service\ContextEnvelopeBuilder

canvas_ai.layout_scoping_subscriber:
  class: Drupal\canvas_ai\EventSubscriber\LayoutScopingSubscriber
  arguments:
    - '@canvas_ai.tempstore'
    - '@canvas_ai.context_envelope_builder'
    - '@logger.channel.canvas_ai'
  tags:
    - { name: event_subscriber }
```

The `DirectEditController` uses `create()` for DI (matching `CanvasBuilder` pattern) — no explicit service registration needed.

---

## Route Definition

Add to `canvas_ai.routing.yml`:

```yaml
canvas_ai.direct_edit:
  path: '/admin/api/canvas/direct-edit'
  defaults:
    _controller: '\Drupal\canvas_ai\Controller\DirectEditController::edit'
  requirements:
    _permission: 'use Drupal Canvas AI'
  methods: [POST]
```

This mirrors `canvas_ai.canvas_builder`. Same permission — direct edit is a faster path to the same outcome, not an elevated capability.

---

## Config Schema

### `canvas_ai.direct_edit.schema.yml`

```yaml
canvas_ai.direct_edit.settings:
  type: config_object
  label: 'Canvas AI direct edit settings'
  mapping:
    enabled:
      type: boolean
      label: 'Enable deterministic direct edit endpoint'
    telemetry:
      type: boolean
      label: 'Enable detailed telemetry logging for direct edit timing'

canvas_ai.direct_edit.prop_aliases:
  type: config_object
  label: 'Canvas AI direct edit prop alias overrides'
  mapping:
    aliases:
      type: mapping
      label: 'Per-component prop alias overrides'
      mapping:
        '*':
          type: mapping
          label: 'Prop aliases for this SDC component'
          mapping:
            '*':
              type: string
              label: 'Canonical prop name'
```

### `canvas_ai.direct_edit.settings.yml` (config/install)

```yaml
enabled: true
telemetry: false
```

---

## DirectEditController: Key Differences from Prototype

The controller logic is identical to the prototype with these changes:

1. **Namespace:** `Drupal\canvas_ai\Controller` (was `Drupal\canvas_ai_scoping\Controller`).
2. **`StateInterface` removed:** `ConfigFactoryInterface` injected instead for telemetry flag.
3. **`DirectEditMatcher` namespace updated:** `Drupal\canvas_ai\Service\DirectEditMatcher`.
4. **Logger channel:** `logger.channel.canvas_ai` (was `logger.channel.canvas_ai_scoping`).
5. **All existing validation, CSRF checking, and response format:** Unchanged. The frontend already calls this endpoint pattern and handles 422 gracefully.

The request/response contract (JSON body with `message`, `component_uuid`, `component_name`; responses with `status`, `direct_edit: true`, `tokens_used: 0`) is already stable against the Canvas frontend.

---

## Migration Path

**Sites without `canvas_ai_scoping`:** Additive only. Direct edit endpoint exists and is callable. Frontend already handles 404/422 from this path gracefully.

**Sites with `canvas_ai_scoping` installed:** After applying this patch:
1. `drush pm:uninstall canvas_ai_scoping` — uninstall the custom module
2. `drush cr` — rebuild caches; new services register
3. Verify: direct edit still works (now served by `canvas_ai`)
4. Remove `web/modules/custom/canvas_ai_scoping/` directory

**Rollback:** Remove patch. `drush cr`. Direct edit path returns 404. Canvas frontend falls through to AI endpoint for all requests. No data loss. No config corruption.

---

## Size Estimate

This patch is approximately 800–1,000 lines of new PHP across 6 production files and 3 test files, plus ~40 lines of YAML (services, routing, config schema). It is large for a single issue but scoped to a single concern (deterministic editing + layout scoping) with clear boundaries. The two capabilities (editing and scoping) share the `ComponentSchemaLoader` dependency, which is why they are combined rather than split into separate issues.

If the maintainers prefer separate issues:
- **Issue A:** `ComponentSchemaLoaderInterface` + `ComponentSchemaLoader` + `DirectEditMatcher` + `DirectEditController` + route + tests
- **Issue B:** `ContextEnvelopeBuilder` + `LayoutScopingSubscriber` + tests (depends on Patch 1)
