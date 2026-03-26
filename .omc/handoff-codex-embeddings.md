# Handoff: Embedding Indexing Setup

## Context

FinDrop is a Drupal CMS 2.0 demo site (Drupal 11.3) at `/Users/AlexUA/claude/c2026`. It uses AI agents (Anthropic for chat, OpenAI for embeddings) with Milvus as the vector database.

The site is fully installed and running via DDEV. Everything works except **content indexing** — the search indexes need OpenAI embeddings (`text-embedding-3-small`) to populate the Milvus vector DB.

## What's Running

- **DDEV**: `c2026.ddev.site` (MariaDB 10.11, PHP 8.3, nginx)
- **Milvus 2.5**: etcd + MinIO + Milvus (port 19530) + Attu UI (port 8521)
- **Drupal**: Installed from recipes, all modules enabled, Canvas UI built
- **Anthropic key**: Set in `.ddev/.env`
- **OpenAI key**: **NOT SET** — this is the blocker

## What Needs to Happen

### 1. Set the OpenAI API Key

Edit `.ddev/.env` and set `OPENAI_API_KEY`:
```
OPENAI_API_KEY="sk-..."
```

Then restart DDEV to pick up the env var:
```bash
ddev restart
```

### 2. Verify the Key is Loaded

```bash
ddev drush php:eval "echo \Drupal::service('key.repository')->getKey('openai_api_key')->getKeyValue() ? 'KEY SET' : 'KEY MISSING';"
```

### 3. Index Content in Milvus

```bash
ddev drush sapi-i
```

This calls the OpenAI embeddings API (`text-embedding-3-small`) for every piece of content and stores vectors in Milvus. There are 3 search indexes:
- `canvas_page_search_index` — Canvas page content
- `content` — General content (nodes)
- `media_image_index_rag` — Media images (used by the page builder's RAG image search)

### 4. Verify Indexing

```bash
ddev drush sapi-s
```

All indexes should show items indexed.

## Token Cost Estimate

The demo site has limited content (installed from recipes). Embedding calls use `text-embedding-3-small` which costs $0.02/1M tokens. Expected cost for initial indexing: **< $0.10**.

## Architecture Notes

- AI provider config: `custom_recipes/findrop/config/ai_provider_openai.settings.yml`
- The `host` field is empty (uses default `https://api.openai.com/v1`)
- Prompt logging is enabled via `web/sites/default/settings.local.php`
- The AI module fires `PreGenerateResponseEvent` / `PostGenerateResponseEvent` Symfony events on every API call — these can be used for observability
- Milvus UI (Attu) is at `http://c2026.ddev.site:8521` for inspecting vectors

## What NOT to Do

- Don't modify files under `web/core/`, `web/modules/contrib/`, or `vendor/`
- Don't commit the `.ddev/.env` file (it's gitignored)
- Don't change the AI provider config in the recipe — the `host` field being empty is correct for direct API access
