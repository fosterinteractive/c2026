# AI Agents Canvas Direct Edit

Deterministic property editing for Canvas page builder components. When users
make simple changes like "set the color to blue" or "change the heading to
Welcome," this module resolves the edit directly from SDC component schemas --
no AI model call needed.

For edits that require reasoning (content generation, ambiguous references,
add/remove operations), the module returns a structured miss so the existing
AI agent chain handles them. Zero false positives by design.

## How It Works

The module reads your theme's Single Directory Component (SDC) YAML schemas to
build prop alias and enum value maps. When a user message matches a recognized
pattern, the matcher resolves the edit deterministically:

- **Exact and alias matches:** "set text_color to primary" or "set the color to
  blue"
- **Bare value inference:** Just "blue" resolves to the correct prop when
  unambiguous
- **Boolean toggles:** "show the header" or "hide the footer"
- **Relative adjustments:** "bigger" or "smaller" navigates enum ordinals
- **Reset patterns:** "reset the color" returns the prop to its default value
- **Compound edits:** "change the heading to Welcome and set the color to blue"

Everything the matcher can't resolve with certainty gets a 422 response, routing
the request back to the AI agent chain.

## Requirements

- Drupal 10.3+ or 11.x
- [AI Agents](https://www.drupal.org/project/ai_agents) module
- [Tool](https://www.drupal.org/project/tool) module (^1.0)
- [Canvas](https://www.drupal.org/project/canvas) page builder
- [Canvas AI](https://www.drupal.org/project/canvas_ai) integration

## Installation

Install via Composer:

```bash
composer require drupal/ai_agents_canvas_direct_edit
drush en ai_agents_canvas_direct_edit
```

## Configuration

All settings live under **Administration > Configuration > AI Agents Canvas
Direct Edit** (`ai_agents_canvas_direct_edit.settings`):

- **Edit verbs:** Recognized verb patterns. Extend these for non-English sites
  or domain-specific vocabulary.
- **Enum value aliases:** Maps natural language to canonical enum values. For
  example, "blue" maps to "primary." Theme developers can customize these
  without patching.
- **Telemetry:** Opt-in usage tracking. Disabled by default. When enabled,
  messages are hashed (SHA-256) for dedup analysis -- raw text is never stored
  unless explicitly configured.
- **Model routing:** Optional complexity-based model selection metadata for
  downstream consumers.

## Tool API Plugins

The module provides eight Tool API plugins, automatically discoverable by AI
agents and MCP clients:

### Read Operations

| Plugin | Description |
|--------|-------------|
| `get_page_layout` | Returns the component tree for a Canvas page |
| `get_component_catalog` | Lists available SDC components |
| `get_component_schema` | Full prop schema for specific components |
| `get_component_props` | Current prop values for a component instance |

### Write Operations

| Plugin | Description |
|--------|-------------|
| `match_direct_edit` | Deterministic matcher -- the core of this module |
| `update_component_props` | Applies prop changes via Canvas AI services |
| `add_component` | Adds a component to a page region |
| `move_component` | Repositions a component within or between regions |

## MCP Server (Optional Submodule)

The `ai_agents_canvas_direct_edit_mcp` submodule exposes the same Tool API
plugins via JSON-RPC 2.0 (MCP protocol) at `POST /api/mcp/canvas`. Enable it
separately if you need external MCP client access.

```bash
drush en ai_agents_canvas_direct_edit_mcp
```

## HTTP Bridge

For direct frontend integration, the module provides an HTTP endpoint at
`POST /admin/api/canvas/direct-edit`. This endpoint accepts the same request
format as the Canvas AI panel and returns compatible response structures.

## Design Decisions

**Schema-driven, not hardcoded.** Prop aliases and enum maps come from your
theme's `*.component.yml` files. When components update their schemas, the
matcher adapts automatically.

**Config-driven aliases.** Enum value aliases live in configuration, not code.
Site builders and theme developers can customize them without patching.

**Fail-open.** The matcher only resolves edits where there is zero ambiguity.
Anything uncertain returns 422 so the AI chain handles it. False negatives
(missing a match) are safe; false positives are not.

**Canvas Lite.** When AI providers aren't configured, the module still works for
deterministic edits. No-match returns 503 instead of 422, telling the frontend
that AI fallback is unavailable.

## Running Tests

```bash
# All kernel tests
phpunit web/modules/custom/ai_agents_canvas_direct_edit/tests/

# Matcher tests only
phpunit web/modules/custom/ai_agents_canvas_direct_edit/tests/src/Kernel/Tool/MatchDirectEditTest.php
```

## Maintainers

- Alex Urevick-Ackelsberg ([AlexUA](https://www.drupal.org/u/alexua)) -
  [Zivtech](https://www.zivtech.com)
