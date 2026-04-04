# drupal.org Contrib Publishing Plan: ai_agents_canvas_direct_edit

> **For Claude:** This is a CONTRIB PUBLISHING plan, not a site-building plan. Use drupal-planner protocol adapted for d.o. merge request preparation.
> **Drupal Version:** 11 (core_version_requirement: ^10.3 || ^11)
> **Companion skills:** drupal-critic, drupal-coding-standards, zivtech-writing-style

**Feature:** Deterministic Canvas component property editing without LLM — resolves simple prop edits from SDC schemas in <7ms at 0 tokens.
**Risk Level:** Medium (contrib dependency chain on experimental modules; d.o. packaging standards; maintainer review expectations)
**Prior Art:** Filing strategy discussed in `docs/filing/p4a-experimental-collection-FINAL.md` and `docs/filing/p4a-tool-plugin-architecture.md`. Comment posted on canvas issue #3549232. Maintainers receptive.

---

## 1. d.o. Project Strategy

### Decision: Standalone project on drupal.org

**Recommendation:** Create a new standalone d.o. project `ai_agents_canvas_direct_edit`, NOT an MR against `canvas`, `ai_agents`, or `ai_agents_experimental_collection`.

**Rationale:**

| Option | Pros | Cons | Verdict |
|--------|------|------|---------|
| MR against `canvas` | Tightest integration | `canvas_ai` is a hidden submodule (`hidden: true` in `canvas_ai.info.yml:6`); Canvas maintainers would need to accept a new submodule that depends on `ai_agents` and `tool` — modules outside their dependency tree. Unreasonable coupling. | REJECT |
| MR against `ai_agents` | Same ecosystem | `ai_agents` depends on `drupal/ai` and `drupal/modeler_api` (`ai_agents/composer.json:10-12`); adding a `canvas` + `canvas_ai` dependency to `ai_agents` is backward — the general module should not depend on a specific page builder. | REJECT |
| Submodule in `ai_agents_experimental_collection` | Low bar — collection explicitly accepts experimental agents. `p4a-experimental-collection-FINAL.md` already drafted a filing for this. | Collection shipped `1.0.0-alpha1` on 2026-03-20. However, our module now uses `#[Tool]` attribute plugins (Tool API), not the `#[FunctionCall]` surface the collection was built on. This is architecturally forward — collections target legacy `AiFunctionCall`. Different maintainer expectations. | POSSIBLE BUT SUBOPTIMAL |
| **Standalone d.o. project** | Full control over release cadence. Clean composer require. Module already has its own namespace, services, config schema, permissions, install hooks, and 59 kernel tests. MCP submodule is a natural fit for a standalone project with submodules. No need to convince another project's maintainers to accept code into their tree. | Must create d.o. project, handle security advisory opt-in, manage releases independently. | **RECOMMENDED** |

**Key evidence for standalone:**
- The module defines its own `hook_schema()` (telemetry table) at `ai_agents_canvas_direct_edit.install:13-124` — this is infrastructure-level code, not a lightweight agent plugin.
- It has 7 custom services (`ai_agents_canvas_direct_edit.services.yml:1-43`) with interfaces, not just a single plugin class.
- It ships an MCP submodule (`modules/ai_agents_canvas_direct_edit_mcp/`) with its own routing, permissions, config schema, and services — submodule-in-a-submodule would be awkward.
- 59 kernel tests across 2 test classes — substantive enough for its own project.

### d.o. Project Metadata

| Field | Value |
|-------|-------|
| Project name | `ai_agents_canvas_direct_edit` |
| Project type | Module |
| Module package | `AI Tools` (matches `ai_agents_canvas_direct_edit.info.yml:4`) |
| Short description | Deterministic Canvas component property editing without LLM. Resolves simple prop edits from SDC schemas in <7ms at 0 tokens. |
| Maintenance status | Actively maintained |
| Development status | Under active development |
| Drupal core compatibility | ^10.3 \|\| ^11 |
| PHP compatibility | >=8.2 |
| License | GPL-2.0-or-later |
| Issue queue | Standard |

---

## 2. composer.json for d.o. Packaging

The module currently has no `composer.json`. One is required for d.o. packaging via Composer.

### Required composer.json (module root)

Structure follows the patterns observed in:
- `drupal/tool` (`web/modules/contrib/tool/composer.json:1-17`): minimal, PHP requirement only in `require`
- `drupal/ai_agents` (`web/modules/contrib/ai_agents/composer.json:1-20`): `drupal/core` + dependencies in `require`, dev dependencies in `require-dev`
- `drupal/canvas` (`web/modules/contrib/canvas/composer.json:1-33`): full dependency declarations, scripts for phpcs/phpstan

**Recommended structure:**

```
{
  "name": "drupal/ai_agents_canvas_direct_edit",
  "description": "Deterministic Canvas component property editing without LLM. Resolves simple prop edits from SDC schemas in <7ms at 0 tokens.",
  "type": "drupal-module",
  "license": "GPL-2.0-or-later",
  "homepage": "https://www.drupal.org/project/ai_agents_canvas_direct_edit",
  "support": {
    "issues": "https://drupal.org/project/issues/ai_agents_canvas_direct_edit",
    "source": "https://drupal.org/project/ai_agents_canvas_direct_edit"
  },
  "require": {
    "php": ">=8.2",
    "drupal/core": "^10.3 || ^11",
    "drupal/ai_agents": "^1.2",
    "drupal/tool": "^1.0@beta",
    "drupal/canvas": "^1.0@dev"
  },
  "suggest": {
    "drupal/ai": "Required for AI fallback when deterministic matching fails. Without it, unmatched edits return 503 instead of routing to LLM."
  },
  "extra": {
    "drupal": {
      "version": "1.0.x-dev",
      "datestamp": ""
    }
  }
}
```

**Design decisions:**

| Decision | Rationale |
|----------|-----------|
| `drupal/canvas` in `require` not `require-dev` | Module declares `canvas:canvas` and `canvas_ai:canvas_ai` as hard dependencies in `.info.yml:9-11`. Cannot function without Canvas. |
| `^1.0@dev` for canvas | Canvas has no stable release — only `1.x-dev`. The `@dev` stability flag is required for Composer to resolve it. |
| `^1.0@beta` for tool | Tool module is at beta. The `@beta` flag allows Composer to install it without `minimum-stability: dev` in the consuming project. |
| `drupal/ai` in `suggest` not `require` | The module uses `@?ai.provider` (nullable service injection) in `ai_agents_canvas_direct_edit.services.yml:22,29`. It degrades gracefully without `ai` — the availability checker returns `false`, complexity router returns empty defaults. But `ai_agents` already requires `drupal/ai`, so it's transitively available. Suggest clarifies the relationship. |
| No `drupal/canvas_ai` in require | `canvas_ai` is a hidden submodule of `canvas` (`canvas_ai.info.yml:6: hidden: true`). It is not a separate Composer package. Requiring `drupal/canvas` is sufficient. |

---

## 3. README.md Structure

The README should follow the [drupal.org README template](https://www.drupal.org/docs/develop/documenting-your-project/readme-template) conventions.

### Outline

```markdown
# AI Agents Canvas Direct Edit

## Introduction

Deterministic Canvas component property editing without LLM invocation.

When a Canvas component is selected and the user's message matches a
deterministic pattern ("change the heading to Welcome", "set the color to
blue"), the edit resolves directly from the SDC component schema — at zero
token cost and sub-7ms latency.

Edits the matcher cannot resolve with certainty fall through to the standard
AI agent path (HTTP 422 response from the controller, or a structured
"no_match" result from the Tool plugin).

## How It Works

[Diagram: User message -> DirectEditMatcher -> SDC schema lookup ->
Match? -> Yes: Apply via Canvas pipeline | No: Return to AI agent]

### Match Tiers

1. **Exact prop match** — "change the heading to Welcome" (confidence 1.0)
2. **Alias match** — "set the color to blue" resolves "blue" -> "primary"
   via configurable aliases (confidence 0.95)
3. **Bare value inference** — "blue" resolves via reverse enum index when
   unambiguous (confidence 0.90)
4. **Relative adjustment** — "bigger" navigates enum ordinals based on
   current prop value (confidence 0.85)
5. **Boolean toggle** — "show the header" / "hide the footer" (confidence 0.80)
6. **Reset/clear** — "reset the color" returns to default (confidence 0.80)
7. **Compound** — "change heading to X and set color to blue" splits and
   resolves independently

### What Routes to AI

- Content generation ("write a better heading")
- Ambiguous references ("fix this", "make it look better")
- Add/move/delete operations
- Cross-component references ("match the style of the hero")
- Any message the matcher cannot resolve with certainty

## Requirements

- Drupal 10.3+ or 11.x
- [Canvas](https://www.drupal.org/project/canvas) (1.x-dev)
- [AI Agents](https://www.drupal.org/project/ai_agents) (^1.2)
- [Tool](https://www.drupal.org/project/tool) (^1.0@beta)
- PHP 8.2+

## Installation

Install via Composer:

    composer require drupal/ai_agents_canvas_direct_edit

Enable the module:

    drush en ai_agents_canvas_direct_edit

### Optional: MCP Server submodule

For external MCP client integration (Claude Desktop, Cursor, etc.):

    drush en ai_agents_canvas_direct_edit_mcp

## Configuration

### Edit verbs and enum aliases

Configuration at `admin/config` (or via config export):

- `ai_agents_canvas_direct_edit.settings` — edit verb patterns, enum value
  aliases, telemetry settings, model routing

Edit verbs are configurable for non-English deployments. Enum value aliases
map natural language terms ("blue") to canonical values ("primary").

### Telemetry

Telemetry is enabled by default. Records are written to the
`canvas_direct_edit_telemetry` table and cleaned up via cron after 90 days
(configurable). Message text is NOT stored by default (PII safety).

Export endpoint: `GET /admin/reports/canvas-direct-edit/telemetry`
(requires "administer ai agents canvas direct edit" permission).

### MCP Server (submodule)

When the MCP submodule is enabled:

- Endpoint: `POST /api/mcp/canvas`
- JSON-RPC 2.0 protocol (MCP 2025-03-26)
- Configure CORS origins and session TTL in
  `ai_agents_canvas_direct_edit_mcp.settings`

## Tool API Plugins

The module provides 8 Tool API plugins, discoverable by AI agents and MCP
clients:

### Read operations

| Plugin ID | Description |
|-----------|-------------|
| `ai_agents_canvas_direct_edit:get_page_layout` | Current page layout tree from tempstore |
| `ai_agents_canvas_direct_edit:get_component_catalog` | All available Canvas components |
| `ai_agents_canvas_direct_edit:get_component_schema` | Full property schema for specific components |
| `ai_agents_canvas_direct_edit:get_component_props` | Current property values for page components |

### Write operations

| Plugin ID | Description |
|-----------|-------------|
| `ai_agents_canvas_direct_edit:match_direct_edit` | Deterministic prop matcher (the core tool) |
| `ai_agents_canvas_direct_edit:update_component_props` | Apply prop changes to a component |
| `ai_agents_canvas_direct_edit:add_component` | Add a component to a page region |
| `ai_agents_canvas_direct_edit:move_component` | Move a component to a new position |

## Permissions

| Permission | Description |
|------------|-------------|
| `use ai agents canvas direct edit` | Invoke the deterministic matching tool |
| `administer ai agents canvas direct edit` | Access telemetry export and settings |
| `access canvas mcp server` (submodule) | Access the MCP JSON-RPC endpoint |

## Measured Results

All measurements on a 15-component demo page:

- Deterministic path: 0 tokens, <7ms latency
- AI path baseline: ~101K tokens, 16.4s mean latency
- Component catalog (23 Byte theme components, 125 props): 48.8% of props
  deterministically addressable
- Hit rate: 60% on 20 mixed edits. All deterministic predictions correct.

## Maintainers

- [Your Name](https://www.drupal.org/u/your-username)

## AI Disclosure

AI tools assisted development. Architecture, test design, and code review
were human-directed.
```

---

## 4. Merge Request Description

### Title

`New module: AI Agents Canvas Direct Edit — deterministic property editing for Canvas components`

### Description Template

```markdown
## Summary

Standalone Drupal module providing deterministic Canvas component property
editing without LLM invocation. When a user's message matches a known edit
pattern, the change resolves directly from the SDC component schema at zero
token cost and sub-7ms latency. Unmatched edits fall through to the standard
AI agent path.

## Problem

Every Canvas component property edit currently flows through the full AI
agent chain — orchestrator -> page builder -> component agent -> LLM API
call. For trivial edits like "change the heading to Welcome" or "set the
color to blue", this costs ~101K tokens and 16.4s latency per edit. These
edits are objectively deterministic: the prop name and value can be resolved
from the SDC schema without any reasoning.

## Solution

A pattern-matching service (`DirectEditMatcher`) that resolves simple edits
against SDC component schemas. The matcher supports 7 resolution tiers:

1. Exact prop name match
2. Semantic alias resolution (configurable)
3. Bare value inference via reverse enum index
4. Relative ordinal navigation ("bigger"/"smaller")
5. Boolean toggles ("show"/"hide")
6. Reset/clear patterns
7. Compound edits (split and resolve independently)

Exposed as 8 Tool API plugins (compatible with `drupal/tool` ^1.0@beta)
and an optional HTTP bridge controller.

## Architecture

- **ComponentSchemaLoader** — discovers SDC YAML schemas from the active
  theme, builds alias/enum maps, caches with tag invalidation
- **DirectEditMatcher** — pure matching logic, config-driven verbs/aliases
- **MatchResult** — immutable value object with confidence scoring and
  complexity signal for downstream model routing
- **8 Tool API plugins** — read (page layout, catalog, schema, props) and
  write (match, update, add, move) operations
- **DirectEditController** — HTTP bridge at POST /admin/api/canvas/direct-edit
- **Telemetry system** — schema, collector, aggregator, export endpoint,
  cron cleanup
- **MCP submodule** — JSON-RPC 2.0 server exposing all tools to external
  MCP clients

## Dependencies

- `drupal/ai_agents` ^1.2
- `drupal/tool` ^1.0@beta
- `drupal/canvas` ^1.0@dev (includes canvas_ai submodule)

## Test Coverage

- 59 kernel tests across 2 test classes
- Tests cover: plugin discovery, single/compound/bare/boolean/relative/reset
  matching, miss handling, AI availability signaling, input validation,
  CSRF protection, controller response codes

## Related Issues

- canvas #3549232 — Updating page contents with agents (discussed there)
- tool #3575927 — Drush CLI for tools (future exposure layer)

## AI Disclosure

AI tools assisted development. Architecture, test design, and code review
were human-directed.
```

---

## 5. Release Strategy

### Version Numbering

| Release | Version | Rationale |
|---------|---------|-----------|
| Initial | `1.0.0-alpha1` | All dependencies are pre-stable (`canvas` 1.x-dev, `tool` 1.0@beta). Alpha signals "API may change". Matches `ai_agents_experimental_collection` alpha1 precedent. |
| Post-feedback | `1.0.0-alpha2` | Incorporate maintainer review feedback. |
| When deps stabilize | `1.0.0-beta1` | When `canvas` and `tool` reach beta/RC. |
| Stable | `1.0.0` | When Canvas has a stable release and tool API is stable. |

### Lifecycle Flag

The `.info.yml` already declares `experimental: true` (`ai_agents_canvas_direct_edit.info.yml:6`). This is correct for alpha — Drupal core surfaces an admin warning for experimental modules.

### Security Advisory Coverage

Do NOT opt into security advisory coverage for alpha releases. Opt in at beta1 when the API surface is stable enough to commit to backporting security fixes.

### Branch Strategy on d.o.

| Branch | Purpose |
|--------|---------|
| `1.0.x` | Development branch for all 1.x work |
| `1.0.0-alpha1` | Tag for first release |

---

## 6. Files to Include vs. Exclude

### INCLUDE in d.o. release

Every file currently in the module directory ships, with these additions:

| File | Status | Notes |
|------|--------|-------|
| `ai_agents_canvas_direct_edit.info.yml` | EXISTS | Ship as-is |
| `ai_agents_canvas_direct_edit.module` | EXISTS | Ship as-is (cron hook) |
| `ai_agents_canvas_direct_edit.install` | EXISTS | Ship as-is (schema + uninstall) |
| `ai_agents_canvas_direct_edit.services.yml` | EXISTS | Ship as-is |
| `ai_agents_canvas_direct_edit.routing.yml` | EXISTS | Ship as-is |
| `ai_agents_canvas_direct_edit.permissions.yml` | EXISTS | **NEEDS FIX: add `administer` permission** |
| `config/install/ai_agents_canvas_direct_edit.settings.yml` | EXISTS | Ship as-is |
| `config/schema/ai_agents_canvas_direct_edit.schema.yml` | EXISTS | Ship as-is |
| `config/optional/ai_agents.ai_agent.canvas_direct_edit.yml` | EXISTS | Ship as-is |
| `src/Plugin/tool/Tool/*.php` (8 files) | EXISTS | Ship all 8 Tool plugins |
| `src/Service/*.php` (7 files) | EXISTS | Ship all services + interfaces |
| `src/Controller/*.php` (2 files) | EXISTS | Ship both controllers |
| `src/Telemetry/*.php` (5 files) | EXISTS | Ship all telemetry classes |
| `modules/ai_agents_canvas_direct_edit_mcp/` (entire submodule) | EXISTS | Ship as-is |
| `tests/` (entire directory) | EXISTS | Ship all tests |
| **`composer.json`** | **CREATE** | See Section 2 |
| **`README.md`** | **CREATE** | See Section 3 |

### EXCLUDE from d.o. release (do not copy from c2026 repo)

These are FinDrop-specific or development artifacts:

| Pattern | Reason |
|---------|--------|
| `docs/` | Project-level documentation, not module documentation |
| `custom_recipes/` | FinDrop recipe infrastructure |
| `.ddev/` | Local dev environment |
| `patches/` | FinDrop-specific Canvas patches |
| `creating_patch_for_canvas/` | Patch tooling |
| `ai_context_data/` | FinDrop AI context items |
| `.omc/` | OMC orchestration state |
| Any file outside `web/modules/custom/ai_agents_canvas_direct_edit/` | Not part of this module |

### Files that need modification before publishing

These are actual code-level fixes, not just packaging:

#### 6.1 Missing `administer` permission definition

**Bug:** `ai_agents_canvas_direct_edit.routing.yml:14` references `administer ai agents canvas direct edit` but `ai_agents_canvas_direct_edit.permissions.yml` only defines `use ai agents canvas direct edit`.

**Fix:** Add to `ai_agents_canvas_direct_edit.permissions.yml`:

```yaml
administer ai agents canvas direct edit:
  title: 'Administer AI Agents Canvas Direct Edit'
  description: 'Access telemetry export, settings, and administrative functions.'
  restrict access: true
```

**Evidence:** `ai_agents_canvas_direct_edit.routing.yml:14` — the telemetry export route requires this permission. Without it, the route is inaccessible because Drupal treats undefined permissions as always-denied.

#### 6.2 MCP submodule info.yml lifecycle field

**Issue:** The MCP submodule uses `lifecycle: experimental` (`ai_agents_canvas_direct_edit_mcp.info.yml:7`) instead of `experimental: true`. The `lifecycle` key is a Drupal core convention for core modules. Contrib modules use `experimental: true`.

**Fix:** Change `lifecycle: experimental` to `experimental: true` in the MCP submodule's `.info.yml`.

#### 6.3 MCP submodule package field

**Issue:** MCP submodule declares `package: 'AI'` while parent module declares `package: 'AI Tools'`. These should be consistent for admin UI grouping.

**Fix:** Change to `package: 'AI Tools'` in the MCP submodule's `.info.yml`.

#### 6.4 Config model routing model IDs

**Issue:** `config/install/ai_agents_canvas_direct_edit.settings.yml:9-10` hardcodes specific model identifiers (`claude-haiku-4-5-20251001`, `claude-sonnet-4-6-20250514`). These are site-specific defaults from the FinDrop demo.

**Fix:** Set model routing `enabled: false` (already the case) and use generic placeholder model IDs or empty strings:

```yaml
model_routing:
  enabled: false
  models:
    simple: ''
    complex: ''
```

**Rationale:** Contrib modules should not ship with vendor-specific model IDs. Site builders configure their own models.

#### 6.5 Enum value aliases are Byte-theme-specific

**Issue:** The `enum_value_aliases` in `config/install/ai_agents_canvas_direct_edit.settings.yml:21-41` include aliases specific to the Byte theme's component design system. Some are universally applicable ("blue" -> "primary"), others are theme-specific ("framed" -> "bordered").

**Decision:** Ship a REDUCED set of universally applicable aliases. Remove theme-specific ones. Document that site builders should add their own.

**Universally safe aliases to keep:**

```yaml
enum_value_aliases:
  center: ['centered', 'middle']
  left: ['start']
  right: ['end']
  large: ['big']
  small: ['tiny']
  medium: ['mid']
  extra-large: ['xl', 'extra large']
  extra-small: ['xs', 'extra small']
  vertical: ['portrait']
  horizontal: ['landscape', 'side by side']
```

**Aliases to REMOVE (Byte-theme-specific):**

```yaml
# REMOVE - theme-specific color/style semantics
inverted: ['white', 'light']
primary: ['blue', 'brand']
secondary: ['grey', 'gray']
accent: ['highlight']
muted: ['subtle']
framed: ['bordered']
full: ['full width']
ribbon: ['thin', 'narrow']
before: ['prefix']
after: ['suffix']
```

---

## 7. Pre-Submission Checklist

### Code Quality

| Check | Status | Action |
|-------|--------|--------|
| `declare(strict_types=1)` in all PHP files | PASS | All files already have it |
| Drupal coding standards (PHPCS) | NEEDS CHECK | Run `phpcs --standard=Drupal,DrupalPractice` on module directory |
| PHPStan analysis | NEEDS CHECK | Run PHPStan level 6+ |
| No hardcoded secrets | PASS | No API keys, tokens, or credentials in code |
| All services use interfaces | PARTIAL | `ComponentSchemaLoaderInterface`, `AiProviderAvailabilityCheckerInterface`, `ComplexityModelRouterInterface`, `TelemetryCollectorInterface`, `TelemetryAggregatorInterface` exist. `DirectEditMatcher` does NOT have an interface — acceptable for now since it's a concrete final class. |
| Config schema defined | PASS | Both parent and MCP submodule have schema files |
| Permissions defined | NEEDS FIX | Missing `administer` permission (see 6.1) |
| All routes have access checks | PASS | All routes use `_permission` requirement |
| CSRF protection | PASS | Controller validates X-CSRF-Token against `canvas_ai.canvas_builder` token |
| Input validation | PASS | UUID format regex, component_name format regex, message length limit |

### d.o. Packaging

| Check | Status | Action |
|-------|--------|--------|
| `composer.json` present | NEEDS CREATE | See Section 2 |
| `README.md` present | NEEDS CREATE | See Section 3 |
| `.info.yml` has correct metadata | PASS | Package, description, core_version_requirement all correct |
| Config in `config/install/` | PASS | Settings file present |
| Config in `config/optional/` | PASS | Agent config entity present with correct dependency |
| Schema matches config | PASS | Schema covers all config keys |
| No site-specific data in config | NEEDS FIX | Model IDs and some aliases are site-specific (see 6.4, 6.5) |
| Tests pass | NEEDS VERIFY | Run full test suite in clean environment |

### Contrib Dependency Audit

| Dependency | d.o. Status | Version | Risk |
|------------|-------------|---------|------|
| `drupal/ai_agents` | Active, security-covered | ^1.2 (stable) | LOW |
| `drupal/tool` | Active | ^1.0@beta | MEDIUM — beta, API may change |
| `drupal/canvas` | Active, dev release only | 1.x-dev | HIGH — no stable release |
| `drupal/canvas_ai` | Hidden submodule of canvas | N/A | Coupled to canvas release |
| `drupal/ai` | Active, security-covered | Transitive via ai_agents | LOW |

**Risk mitigation:** The `experimental: true` flag and alpha release signal clearly communicates to adopters that dependencies are pre-stable.

---

## 8. Implementation Tasks

### Task 1: Fix Permission Gap

**Files to modify:**
- `ai_agents_canvas_direct_edit.permissions.yml`

**Change:** Add `administer ai agents canvas direct edit` permission definition.

**Evidence:** `ai_agents_canvas_direct_edit.routing.yml:14` references this permission. Without it, the telemetry export endpoint is inaccessible.

**Test:** Enable module, verify admin user can access `/admin/reports/canvas-direct-edit/telemetry`.

---

### Task 2: Fix MCP Submodule info.yml

**Files to modify:**
- `modules/ai_agents_canvas_direct_edit_mcp/ai_agents_canvas_direct_edit_mcp.info.yml`

**Changes:**
1. Replace `lifecycle: experimental` with `experimental: true`
2. Replace `package: 'AI'` with `package: 'AI Tools'`

**Evidence:** `ai_agents_canvas_direct_edit_mcp.info.yml:7` uses `lifecycle:` which is a core-only convention. `ai_agents_canvas_direct_edit_mcp.info.yml:4` has inconsistent package.

---

### Task 3: Neutralize Site-Specific Config

**Files to modify:**
- `config/install/ai_agents_canvas_direct_edit.settings.yml`

**Changes:**
1. Clear model routing model IDs (set to empty strings)
2. Remove Byte-theme-specific enum value aliases
3. Keep universally applicable aliases

**Evidence:** `config/install/ai_agents_canvas_direct_edit.settings.yml:9-10` has hardcoded Anthropic model IDs. Lines 22-41 have Byte-theme-specific color aliases.

---

### Task 4: Create composer.json

**Files to create:**
- `composer.json` (module root)

**Content:** See Section 2.

---

### Task 5: Create README.md

**Files to create:**
- `README.md` (module root)

**Content:** See Section 3. Apply zivtech-writing-style for d.o. publishing per project memory directive `feedback_module_descriptions.md`.

---

### Task 6: Run Coding Standards

**Action:** Run PHPCS with Drupal/DrupalPractice sniffs on the entire module directory.

**Expected issues:**
- Possible line length violations in long regex patterns (`DirectEditMatcher.php`)
- Possible doc comment format issues

**Fix:** Address all errors. Warnings are acceptable for alpha but should be noted.

---

### Task 7: Run PHPStan

**Action:** Run PHPStan level 6 on the module.

**Expected issues:**
- Nullable service injection (`@?ai.provider`) may need PHPStan baseline entries
- `CanvasAiTempStore`, `AiResponseValidator`, `CanvasAiPageBuilderHelper` are concrete dependencies without interface contracts — PHPStan won't flag these but they're architectural debt

---

### Task 8: Verify Tests in Clean Environment

**Action:** Run all 59 kernel tests in a clean DDEV environment.

**Command:**
```shell
ddev exec phpunit --group=ai_agents_canvas_direct_edit
```

**Expected:** All 59 tests pass.

---

### Task 9: Create d.o. Project and Push

**Sequence:**
1. Create project at `drupal.org/project/add`
2. Initialize `1.0.x` branch
3. Copy module files (excluding site-specific artifacts)
4. Push to d.o. git
5. Create `1.0.0-alpha1` tag
6. Create release node for `1.0.0-alpha1`

---

### Task 10: Post MR / Comment on #3549232

**Action:** Post on canvas issue #3549232 with a link to the new project and a summary of what it provides.

**Framing:** "We've published the deterministic edit module discussed in this issue as a standalone contrib project. It uses the Tool API surface and works alongside existing Canvas AI agents. Happy to discuss integration opportunities."

---

## 9. Review Checkpoint Plan

| Checkpoint | After Task | Focus |
|------------|-----------|-------|
| Permission audit | Task 1 | Verify all routes have matching permissions defined |
| Config neutrality | Task 3 | Verify no site-specific data in shipped config |
| Coding standards | Task 6 | PHPCS clean (errors only; warnings acceptable for alpha) |
| Static analysis | Task 7 | PHPStan level 6 clean |
| Integration test | Task 8 | All 59 tests pass in clean environment |
| Packaging review | Task 9 | composer.json resolves, module installs via `composer require` |

---

## 10. Risk Register

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Canvas maintainers reject standalone module approach | Low | Medium | Module is additive — it does not modify Canvas code. Prior comment on #3549232 was well-received. |
| `tool` module breaks API before stable | Medium | High | Pin to `^1.0@beta`. Tool plugin attribute API (`#[Tool]`) is already in use by multiple modules. |
| Canvas never reaches stable | Medium | Medium | Module works with `1.x-dev`. Alpha signaling manages adopter expectations. |
| Reviewers request AiFunctionCall instead of Tool API | Low | Medium | Tool API is the forward direction. `AiFunctionCall` is legacy. Explain rationale in MR. |
| PHPCS violations block MR | Medium | Low | Fix before pushing. Most patterns follow Drupal standards already. |

---

## Next Steps

1. **Execute Tasks 1-5** (code fixes + new files) — these are prerequisite for submission
2. **Execute Tasks 6-8** (quality checks) — verify readiness
3. **Execute Task 9** (create d.o. project + push)
4. **Execute Task 10** (community engagement)

**Execute with:** Manual implementation (code changes are small, focused edits)
**Review with:** `/drupal-critic` after Task 5, `/drupal-coding-standards` during Task 6
