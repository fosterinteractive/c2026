# Canvas AI SEO

AI-generated Schema.org JSON-LD structured data for Canvas pages. When an AI
agent builds or updates a Canvas page, this module gives it the tools to
generate and store valid JSON-LD, which is then injected into the page `<head>`
at render time.

The module also provides a `GetLinkableComponents` tool so agents can identify
rich-text props eligible for internal linking.

## Requirements

- Drupal 10.x or 11.x
- [Canvas AI](https://www.drupal.org/project/canvas_ai) (`canvas:canvas_ai`)
- [Metatag](https://www.drupal.org/project/metatag) (`metatag:metatag`)
- [AI](https://www.drupal.org/project/ai) module (for `AiFunctionCall` plugin
  discovery)
- [AI Agents](https://www.drupal.org/project/ai_agents) module

## Installation

Install via Composer:

```bash
composer require drupal/canvas_ai_seo
drush en canvas_ai_seo
```

After enabling the module, run a database update to add the `schema_jsonld` base
field to existing Canvas page entities:

```bash
drush updb
```

## Configuration

### Wiring agents via recipe

The recommended way to map AI context items to agents is through the
`aiContextAgentsUpdate` config action provided by this module. Add it to your
setup recipe under `config.actions`:

```yaml
# my_recipe/recipe.yml
install:
  - canvas_ai_seo
config:
  actions:
    ai_context.agents:
      aiContextAgentsUpdate:
        agents:
          - id: drupal_canvas_seo_agent
            context_items: {}
            always_include:
              - 'My Brand Guidelines'
            excluded_subcontext: []
            scope_subscriptions: {}
```

The action accepts human-readable AI context item labels instead of numeric
entity IDs. It resolves each label to its entity ID at recipe apply time, so
recipes remain readable regardless of the IDs on a given installation.

Apply the recipe with:

```bash
drush recipe path/to/my_recipe
```

### How JSON-LD gets generated

1. An AI agent calls the `ai_agent_add_schema_org_json` function tool with a
   valid JSON-LD string as `schema_org_data`.
2. The module validates the JSON and stores it in the `schema_jsonld` base field
   on the Canvas page entity.
3. At render time, `hook_metatags_attachments_alter` reads the stored value,
   round-trips it through `json_decode` / `json_encode` to strip any injected
   `</script>` sequences, and attaches it as an `application/ld+json` script tag
   in the page `<head>`.

No manual configuration is required beyond enabling the module and pointing an
agent at the function tools.

## How It Works

### `AddSchemaOrgJson` (AiFunctionCall plugin)

Plugin ID: `ai_agent:add_schema_org_json`
Function name: `ai_agent_add_schema_org_json`
Group: `modification_tools`

Receives a Schema.org JSON-LD string from the agent, validates it, and returns
structured output that Canvas AI writes to the `schema_jsonld` field on the
page entity. Invalid JSON is rejected with a descriptive error message so the
agent can correct and retry.

### `GetLinkableComponents` (AiFunctionCall plugin)

Plugin ID: `canvas_ai_seo:get_linkable_components`
Function name: `get_linkable_components`
Group: `information_tools`

Reads the current Canvas page layout from the AI tempstore, walks the component
tree in the content region, and returns a YAML tree of components that have at
least one rich-text prop (`contentMediaType: text/html` in the component's JSON
schema). Ancestor components include only `uuid` and `name`; linkable components
also include their prop content. Non-linkable props are labelled `(non linkable
prop)` so the agent does not add links to them.

### `AiContextAgentsUpdate` (ConfigAction plugin)

Plugin ID: `aiContextAgentsUpdate`

A Drupal config action that accepts `ai_context.agents` configuration with
human-readable context item labels and resolves them to entity IDs before
saving. This keeps recipe YAML readable without requiring site-specific numeric
IDs. Throws `ConfigActionException` if a label cannot be matched to an existing
`ai_context_item` entity.

### `LayoutResponseSubscriber` (event subscriber)

Subscribes to `kernel.response`. On Canvas layout API responses
(`/canvas/api/v0/layout/canvas_page/*`), it injects an empty
`schema_jsonld[0][value]` key into `entity_form_fields` when the key is absent.
This prevents the Canvas UI from inheriting a stale JSON-LD value from a
previously loaded page.

### `schema_jsonld` base field

Added to the `canvas_page` entity type via `hook_entity_base_field_info` when
both this module and Metatag are enabled. The field is translatable, revisionable,
and marked internal (not shown in public field listings). It is exposed as a
textarea in the entity form for manual review or override.

## Maintainers

- Alex Urevick-Ackelsberg ([AlexUA](https://www.drupal.org/u/alexua)) -
  [Zivtech](https://www.zivtech.com)
