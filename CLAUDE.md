# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**FinDrop** is a Drupal CMS 2.0 (Drupal 11.3) demo site showcasing AI-powered content creation with the Canvas page builder. It uses AI agents (Anthropic/OpenAI) to generate pages, SEO metadata, and analytics insights, with Milvus as the vector database for AI search.

The site is a **demo/reference implementation** — not a production application. Content and configuration are stored as Drupal recipes and exported back into the repo after changes.

## Development Environment

DDEV-based local development. Requires Node.js >= 20.19 on the host (for building Canvas UI assets).

### Setup

```shell
cp .env.template .ddev/.env
# Set OPENAI_API_KEY and/or ANTHROPIC_API_KEY in .ddev/.env
ddev demo-setup
```

`ddev demo-setup` does: `ddev delete -O` → `ddev start` → `composer install` → `drush si` with the findrop recipe → enables `ai_context` → applies `ai_context_setup` recipe → builds Canvas UI (`npm install` + `npm run build` in `web/modules/contrib/canvas/`) → indexes content in Milvus via `drush sapi-i`.

### Common Commands

```shell
ddev drush uli                  # Get a one-time login URL
ddev drush cr                   # Clear caches
ddev drush sapi-i               # Re-index content in vector DB
ddev backup                     # Snapshot DB + files to .backups/
```

### Exporting Content Back to Recipes

After making changes in Drupal, export content so it persists in the repo:

```shell
ddev export-all-content         # All entities (canvas pages, nodes, media, menu links, terms)
ddev export-canvas-pages        # Canvas pages only
ddev export-media               # Media + file entities only
ddev export-ai-context          # AI Context items and usage records
```

Exports land in `custom_recipes/findrop/content/` (main content) or `custom_recipes/ai_context_setup/content/` (AI context items).

## Architecture

### Recipe-Based Site Installation

The site installs from Drupal recipes rather than a traditional install profile:

- **`custom_recipes/findrop/`** — Main site recipe. Contains all config, content entities (canvas pages, nodes, media, files, menu links, taxonomy terms), and the full module install list. Used by `drush si` during setup.
- **`custom_recipes/ai_context_items/`** — AI Context item entities (brand guidelines, content structure docs, etc.)
- **`custom_recipes/ai_context_setup/`** — Enables `ai_context`, runs the items recipe, and maps context items to specific AI agents via the `aiContextAgentsUpdate` config action.

### Custom Modules

- **`web/modules/custom/ai_google_analytics/`** — Integrates Google Analytics with Canvas AI. Provides an `AiFunctionCall` plugin (`GoogleAnalytics`) so AI agents can query GA data, plus a settings form and review controller. Depends on `ai` and `ai_agents`.
- **`web/modules/custom/canvas_ai_seo/`** — Generates Schema.org JSON-LD for Canvas pages. Includes `AiFunctionCall` plugins (`AddSchemaOrgJson`, `GetLinkableComponents`), a `ConfigAction` (`AiContextAgentsUpdate`), a layout response event subscriber, and Canvas hooks. Depends on `canvas_ai` and `metatag`.

### AI Agent Configuration

AI agents are configured in `custom_recipes/findrop/config/ai_agents.ai_agent.*.yml`. Key agents:
- `canvas_ai_orchestrator` — Top-level orchestrator for Canvas page building
- `canvas_page_builder_agent` / `canvas_template_builder_agent` — Page and template construction
- `canvas_component_agent` — Component-level editing
- `canvas_metadata_generation_agent` / `canvas_title_generation_agent` — Metadata and titles
- `drupal_canvas_seo_agent` — SEO and Schema.org generation
- `analytics_monitoring_agent` — GA data analysis
- `drupal_cms_assistant` — General Drupal CMS assistant

### AI Context System

The `ai_context` module provides agent-specific context (brand guidelines, content structure rules, value propositions). Context items live in `ai_context_data/` as markdown files and are imported as content entities. The `ai_context_setup` recipe maps which context items each agent receives via `always_include` / `excluded_subcontext` lists.

### Canvas & Patches

Canvas is a contributed module installed as a dev release (`1.x-dev`). Multiple patches are applied via `cweagans/composer-patches`:
- A combined patch addressing 9 Canvas issues (UI, AI panel, component schema)
- A content/performance patch for AiPanel and AiWizard components
- A fix for long JSON-LD blocking page publishing

The `creating_patch_for_canvas/` directory contains tooling to regenerate the combined Canvas patch from source files. Canvas UI assets must be built on the host (`npm install` + `npm run build` in the canvas module directory).

### Infrastructure

- **Database**: MariaDB 10.11
- **PHP**: 8.3
- **Webserver**: nginx-fpm
- **Vector DB**: Milvus 2.5 (with etcd + MinIO), exposed on port 19530
- **Milvus UI**: Attu, accessible at port 8521
- **Theme**: `byte_theme` (frontend), `gin` (admin)

### Key Contrib Modules

`ai`, `ai_agents`, `ai_context`, `ai_search`, `ai_chatbot`, `ai_automators`, `canvas`, `canvas_ai`, `byte` (theme system), `metatag`, `pathauto`, `webform`, `search_api`

## Working with This Codebase

- **Don't edit files under `web/core/`, `web/modules/contrib/`, or `vendor/`** — these are managed by Composer. Change patches or composer.json instead.
- **Content changes must be exported** — the database is ephemeral. Run the appropriate `ddev export-*` command after changing content in the UI.
- **Canvas patches are the primary mechanism** for Canvas module changes. See `creating_patch_for_canvas/README.md` for the patch regeneration workflow.
- **AI agent behavior** is configured via YAML in the recipe config, not in PHP code. Agent context mapping is in `custom_recipes/ai_context_setup/recipe.yml`.
- **Test scenarios** for the demo are documented in `ai_context_data/test_scenarios/` with a phased checklist.
