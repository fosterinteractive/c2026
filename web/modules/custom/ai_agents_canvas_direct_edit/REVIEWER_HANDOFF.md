# Reviewer Handoff: AI Agents Canvas Direct Edit

This document helps reviewers understand the module quickly. You can also use it as a Claude Code context file — drop it into your project root as `CLAUDE.md` or reference it directly.

## What This Module Does

Deterministic Canvas component property editing without LLM. When a user says "change the heading to Welcome" or "set the color to blue", this module resolves the edit from SDC component schemas in <7ms at 0 tokens — no AI model invocation needed.

**Design principle:** Fail-open. The matcher only resolves edits where there is zero ambiguity. Anything uncertain returns 422, routing the request to the existing AI agent chain. Zero false positives by design.

## Architecture

```
┌─────────────────────────────────────────────────┐
│  User message: "set the color to blue"          │
└──────────────────┬──────────────────────────────┘
                   │
         ┌─────────▼──────────┐
         │  DirectEditMatcher  │  Pure matching — no side effects
         │  (6 match tiers)    │
         └─────────┬──────────┘
                   │
          matched? │ yes → MatchResult VO
                   │ no  → 422 (fail-open to AI chain)
                   │
    ┌──────────────▼───────────────┐
    │  DirectEditController        │  HTTP bridge
    │  POST /admin/api/canvas/     │  Validates, builds update ops,
    │       direct-edit            │  calls same Canvas AI services
    └──────────────────────────────┘
```

### Match Tiers (in priority order)

1. **Exact** — prop name match + valid value ("set text_color to primary")
2. **Alias** — semantic alias match ("set the color to blue" → text_color=primary)
3. **Enum** — bare value inference ("blue" → unambiguous → text_color=primary)
4. **Relative** — ordinal navigation ("bigger" → text_size steps up)
5. **Boolean** — toggle patterns ("show the header" → section_header=true)
6. **Reset** — reset/clear patterns ("reset the color" → text_color=default)
7. **Compound** — multiple tiers combined ("change heading to X and set color to blue")

### Key Services

| Service | Responsibility |
|---------|---------------|
| `ComponentSchemaLoader` | Discovers SDC YAML schemas from the active theme, builds prop alias + enum maps, caches with tag invalidation |
| `DirectEditMatcher` | Pure pattern matching — no Drupal dependencies beyond config |
| `DirectEditController` | HTTP bridge — CSRF, validation, Canvas AI service integration |
| `TelemetryCollector` | Records match/miss events to `canvas_direct_edit_telemetry` table |
| `TelemetryAggregator` | Aggregation queries for the export endpoint |
| `AiProviderAvailabilityChecker` | Checks if AI providers are configured (Canvas Lite 503) |
| `ComplexityModelRouter` | Returns model recommendations based on complexity signals |

### MCP Submodule (`ai_agents_canvas_direct_edit_mcp`)

Optional submodule exposing the same Tool API plugins via JSON-RPC 2.0 (MCP protocol). Endpoint: `POST /api/mcp/canvas`. Separate enable/disable.

### Tool API Plugins (8 total)

**Read operations:**
- `get_page_layout` — Returns component tree for a Canvas page
- `get_component_catalog` — Lists available SDC components
- `get_component_schema` — Full prop schema for specific components
- `get_component_props` — Current prop values for a component instance

**Write operations:**
- `match_direct_edit` — Deterministic matcher (this module's core)
- `update_component_props` — Applies prop changes via Canvas AI services
- `add_component` — Adds a component to a page region
- `move_component` — Repositions a component within/between regions

## Dependencies

- `ai_agents` (drupal.org) — AI agent framework
- `tool` (drupal.org, ^1.0@beta) — Tool API plugin system
- `canvas` (drupal.org) — Canvas page builder
- `canvas_ai` (drupal.org) — Canvas AI integration layer

## Running Tests

```bash
# All 59 kernel tests
ddev exec phpunit web/modules/custom/ai_agents_canvas_direct_edit/tests/ --no-coverage

# Just the matcher tests (fast, no mocks)
ddev exec phpunit web/modules/custom/ai_agents_canvas_direct_edit/tests/src/Kernel/Tool/MatchDirectEditTest.php

# Just the controller tests
ddev exec phpunit web/modules/custom/ai_agents_canvas_direct_edit/tests/src/Kernel/Controller/DirectEditControllerTest.php
```

Tests use `TestComponentSchemaLoader` — a test double that provides fixture data without requiring a real theme. No external services needed.

## Configuration

All config lives under `ai_agents_canvas_direct_edit.settings`:

- `edit_verbs` — Recognized verb patterns (extensible for i18n)
- `enum_value_aliases` — Maps natural language to canonical enum values
- `telemetry.*` — Enable/disable, retention, message storage (PII-safe by default)
- `model_routing.*` — Complexity-based model selection (opt-in)

## Relationship to Canvas AI

This module **extends** canvas_ai, it does not compete with it. It acts as a
pre-filter: deterministic edits resolve without touching the AI chain, reducing
load on the orchestrator and saving tokens. Anything the matcher can't resolve
falls through to the existing canvas_ai agent pipeline unchanged.

The module depends on canvas_ai services (`AiResponseValidator`,
`CanvasAiPageBuilderHelper`, `CanvasAiTempStore`) for validation and update
operations. It produces the same JSON response format so the Canvas frontend
needs zero changes.

## Why 7 Services (Not Fewer)

The services.yml header documents this in detail. In short:
- **3 core** (schema loader, matcher, logger) — irreducible
- **2 AI availability** (checker, router) — separate because the module works
  without `drupal/ai` installed; nullable injection needs its own wrapper
- **2 telemetry** (collector, aggregator) — write path and read path have
  different performance profiles and load timing

Telemetry is separate from AI Logging (`drupal/ai`) because they track different
data: this module records deterministic match attempts (tier, confidence, <7ms
latency), while AI Logging records LLM API calls (tokens, provider, model).
They complement each other.

## Key Design Decisions

1. **Schema-driven, not hardcoded.** Prop aliases and enum maps come from the active theme's `*.component.yml` files. When components update their schemas, the matcher auto-adapts.

2. **Config-driven aliases.** Enum value aliases (`blue→primary`, `centered→center`) are in config, not code. Theme developers can customize without patching.

3. **Conservative compound splitting.** Only splits on conjunctions followed by edit verbs to avoid splitting text values like "apples and oranges".

4. **MatchResult value object.** Carries confidence scores and complexity signals so downstream consumers can make informed routing decisions.

5. **Telemetry is opt-in.** Disabled by default. When enabled, messages are hashed (SHA-256) not stored, unless `store_messages` is explicitly enabled.

6. **Canvas Lite (503).** When AI providers aren't configured, no-match returns 503 instead of 422 — tells the frontend "deterministic edits work, but AI fallback is unavailable."

## Files Overview

```
ai_agents_canvas_direct_edit/
├── ai_agents_canvas_direct_edit.info.yml
├── ai_agents_canvas_direct_edit.install        # Schema + uninstall
├── ai_agents_canvas_direct_edit.module          # hook_cron (telemetry cleanup)
├── ai_agents_canvas_direct_edit.permissions.yml
├── ai_agents_canvas_direct_edit.routing.yml
├── ai_agents_canvas_direct_edit.services.yml
├── config/
│   ├── install/...settings.yml
│   ├── optional/ai_agents.ai_agent.canvas_direct_edit.yml
│   └── schema/...schema.yml
├── src/
│   ├── Controller/
│   │   ├── DirectEditController.php
│   │   └── TelemetryExportController.php
│   ├── Plugin/tool/Tool/                        # 8 Tool API plugins
│   ├── Service/
│   │   ├── ComponentSchemaLoader.php             # Schema discovery + caching
│   │   ├── DirectEditMatcher.php                 # Core matching engine
│   │   ├── MatchResult.php                       # Value object
│   │   ├── AiProviderAvailabilityChecker.php
│   │   └── ComplexityModelRouter.php
│   └── Telemetry/
│       ├── TelemetryEvent.php + Builder.php      # Immutable DTO + fluent builder
│       ├── TelemetryCollector.php
│       └── TelemetryAggregator.php
├── modules/
│   └── ai_agents_canvas_direct_edit_mcp/         # Optional MCP submodule
└── tests/src/Kernel/                             # 59 kernel tests
```

## For Claude Code Users

If you're reviewing this module with Claude Code, you can use this file as context:

```bash
# Clone and explore
git clone [repo-url]
cd ai_agents_canvas_direct_edit

# Point Claude at the module
# Add this file's content to your CLAUDE.md, or:
claude "Review this Drupal module for d.o. contrib readiness. Start by reading REVIEWER_HANDOFF.md"
```

Key review areas:
- **Coding standards**: `phpcs --standard=Drupal,DrupalPractice .`
- **Static analysis**: `phpstan analyse --level=6 .`
- **Test coverage**: 59 kernel tests, 2000+ assertions
- **Security**: CSRF validation, permission checks, input sanitization, no raw SQL
- **Config schema**: Full typed data schema for all config
