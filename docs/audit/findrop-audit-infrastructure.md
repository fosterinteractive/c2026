# FinDrop AI Demo Site — Drupal Audit Infrastructure Plan

**Created:** 2026-03-26
**Status:** DRAFT — awaiting user confirmation
**Scope:** DDEV service composition, AI provider proxy routing, Drush inspection, Playwright testing harness
**Estimated complexity:** MEDIUM (6 tasks, ~12 files to create/modify)

---

## Context

The FinDrop demo site is a Drupal CMS 2.0 / Drupal 11.3 project with:
- **12 AI agents** (orchestrator + 11 sub-agents) running on Anthropic claude-sonnet-4-6 (chat) and OpenAI text-embedding-3-small (embeddings)
- **Canvas page builder** with a React-based AI chatbot panel (deep-chat component)
- **Milvus vector DB** already in DDEV via docker-compose.milvus.yaml (etcd + MinIO + Milvus + Attu)
- **Key module** loading API keys from environment variables (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`) in `.ddev/.env`

The goal is to add a LiteLLM proxy layer to intercept, log, and cache all AI API calls for auditing purposes without modifying any Drupal contrib module code.

---

## Work Objectives

1. Add LiteLLM as a DDEV service that proxies all AI API calls
2. Route Drupal AI providers through the proxy via settings.local.php
3. Provide a complete Drush inspection toolkit for AI state audit
4. Enable Playwright-based testing of the Canvas AI chatbot
5. Document risks and mitigations

---

## Guardrails

**Must Have:**
- LiteLLM proxy is transparent — Drupal code is unmodified
- All AI calls (chat + embeddings) are logged with full request/response payloads
- Configuration is reversible — toggle proxy vs direct with a single file change
- API keys stay in `.ddev/.env` (already gitignored) and flow to LiteLLM, not duplicated
- Milvus stack continues working alongside LiteLLM

**Must NOT Have:**
- No patches to contrib AI provider modules
- No secrets committed to git
- No changes to the findrop recipe configs
- No Redis or external cache dependencies

---

## Task 1: LiteLLM DDEV Service Composition

### Files to create

#### `.ddev/docker-compose.litellm.yaml`

```yaml
services:
  litellm:
    container_name: ddev-${DDEV_SITENAME}-litellm
    image: ghcr.io/berriai/litellm:main-latest
    expose:
      - "4000"
    ports:
      - "4000:4000"
    environment:
      - OPENAI_API_KEY
      - ANTHROPIC_API_KEY
      # Bind to all interfaces inside the container
      - LITELLM_MASTER_KEY=sk-litellm-dev-key
    volumes:
      - ./litellm/litellm_config.yaml:/app/config.yaml
      - ./litellm/logs:/app/logs
    command: ["--config", "/app/config.yaml", "--detailed_debug", "--port", "4000"]
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:4000/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 30s
    labels:
      com.ddev.site-name: ${DDEV_SITENAME}
      com.ddev.approot: ${DDEV_APPROOT}
```

**Key design decisions:**
- Uses `expose` (container-to-container) so the web container reaches it at `http://litellm:4000`
- Also maps port 4000 to host for direct inspection via `http://localhost:4000`
- Reads `OPENAI_API_KEY` and `ANTHROPIC_API_KEY` from the DDEV environment (which reads `.ddev/.env`)
- Mounts a local config file and logs directory
- No port conflicts with Milvus (19530), Attu (8521/3000), etcd (2379), or MinIO (9000/9001)

#### `.ddev/litellm/litellm_config.yaml`

```yaml
model_list:
  # Anthropic models — chat operations
  - model_name: claude-sonnet-4-6
    litellm_params:
      model: anthropic/claude-sonnet-4-6
      api_key: os.environ/ANTHROPIC_API_KEY
  - model_name: claude-sonnet-4-20250514
    litellm_params:
      model: anthropic/claude-sonnet-4-20250514
      api_key: os.environ/ANTHROPIC_API_KEY

  # OpenAI models — embeddings
  - model_name: text-embedding-3-small
    litellm_params:
      model: openai/text-embedding-3-small
      api_key: os.environ/OPENAI_API_KEY
  - model_name: text-embedding-3-large
    litellm_params:
      model: openai/text-embedding-3-large
      api_key: os.environ/OPENAI_API_KEY

  # OpenAI chat models (fallback if user switches default provider)
  - model_name: gpt-4o
    litellm_params:
      model: openai/gpt-4o
      api_key: os.environ/OPENAI_API_KEY
  - model_name: gpt-4o-mini
    litellm_params:
      model: openai/gpt-4o-mini
      api_key: os.environ/OPENAI_API_KEY
  - model_name: gpt-3.5-turbo
    litellm_params:
      model: openai/gpt-3.5-turbo
      api_key: os.environ/OPENAI_API_KEY

litellm_settings:
  # Logging — full request/response payloads
  set_verbose: true
  json_logs: true
  log_responses: true
  turn_off_message_logging: false

  # In-memory caching (no Redis needed)
  cache: true
  cache_params:
    type: local
    # Cache embeddings aggressively (same content = same embedding)
    supported_call_types:
      - embedding
    ttl: 3600

general_settings:
  master_key: sk-litellm-dev-key
  # Enable the /spend and /model/info endpoints
  enable_admin_ui: true
```

#### `.ddev/litellm/logs/` (empty directory, gitignored via existing `.ddev/**/volumes/` pattern)

Add to `.gitignore`:
```
.ddev/litellm/logs/
```

### Acceptance criteria
- [ ] `ddev start` brings up LiteLLM alongside Milvus without errors
- [ ] `ddev exec curl http://litellm:4000/health` returns healthy from the web container
- [ ] `curl http://localhost:4000/health` returns healthy from the host
- [ ] LiteLLM logs appear in `.ddev/litellm/logs/` or via `ddev logs -s litellm`

---

## Task 2: Drupal AI Provider Configuration via settings.local.php

### Key findings from source code analysis

Both AI provider modules support the `host` config field identically:

```php
// In both OpenAiProvider.php and AnthropicProvider.php:
protected function loadClient(): void {
    if (!empty($this->getConfig()->get('host'))) {
        $this->setEndpoint($this->getConfig()->get('host'));
    }
    parent::loadClient();
}
```

- **OpenAI provider:** `host` is in the config schema but NOT exposed in the settings form UI. Default empty string means "use `https://api.openai.com/v1`".
- **Anthropic provider:** `host` is NOT in the config schema at all, but the code reads it from config. Default `$endpoint = 'https://api.anthropic.com/v1'`.
- **Important:** The `OpenAiHelper` class (used for form validation only) constructs URLs differently: `'https://' . ($host ?: 'api.openai.com/v1')`. This means the `host` field value for the helper is just the domain+path without scheme. But the `loadClient()` / `setEndpoint()` path takes a full URL. For proxy routing, we use `setEndpoint()` path via config override, so the full URL is correct.

### File to create: `web/sites/default/settings.local.php`

Already gitignored by `/web/sites/*/*settings*.php`.

```php
<?php

/**
 * @file
 * LiteLLM proxy routing for AI provider audit.
 *
 * Toggle: Set LITELLM_PROXY_ENABLED=1 in .ddev/.env to route through proxy.
 * When disabled, providers use their default endpoints.
 */

declare(strict_types=1);

// Only override when proxy is explicitly enabled.
if (getenv('LITELLM_PROXY_ENABLED')) {
  $litellm_base = 'http://litellm:4000';

  // Route OpenAI calls through LiteLLM.
  // LiteLLM's OpenAI-compatible endpoint is at /v1.
  $config['ai_provider_openai.settings']['host'] = $litellm_base . '/v1';

  // Route Anthropic calls through LiteLLM.
  // LiteLLM's Anthropic-compatible endpoint is at /v1.
  $config['ai_provider_anthropic.settings']['host'] = $litellm_base . '/v1';
}

// Keys remain loaded from environment variables via Key module.
// LiteLLM reads the same env vars directly from .ddev/.env.
// Drupal still needs the keys for its own validation/setup.
```

### Update `.ddev/.env`

Add the proxy toggle and a LiteLLM master key:

```env
# OpenAI API key is required for embeddings and AI search.
OPENAI_API_KEY=""
ANTHROPIC_API_KEY=""

# Set to 1 to route AI calls through LiteLLM proxy for auditing.
LITELLM_PROXY_ENABLED=1
```

### How settings.local.php gets included

After `ddev demo-setup` runs `drush si`, DDEV's auto-generated `settings.ddev.php` is included by `settings.php`. We need to ensure `settings.local.php` is also included. The standard Drupal `settings.php` has a commented-out block for this at the bottom. DDEV's `settings.ddev.php` does NOT include `settings.local.php` automatically.

**Option A (recommended):** Create a DDEV post-start hook or add to `demo-setup` to uncomment the `settings.local.php` include in `settings.php`. Or simpler:

**Option B:** Add the config overrides directly into a new file at `.ddev/web/settings.ddev.local.php` and source it from `settings.ddev.php`. But this is fragile.

**Option C (simplest, recommended):** Add a hook in `.ddev/config.yaml` or handle it in the `demo-setup` command by appending an include to settings.php after site install:

```bash
# In demo-setup, after drush si:
cat >> web/sites/default/settings.php << 'SETTINGS'

// Include local overrides if present.
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
SETTINGS
```

### Acceptance criteria
- [ ] With `LITELLM_PROXY_ENABLED=1`, `ddev drush config:get ai_provider_openai.settings host` returns `http://litellm:4000/v1`
- [ ] With `LITELLM_PROXY_ENABLED` unset/empty, `ddev drush config:get ai_provider_openai.settings host` returns empty string
- [ ] AI chat calls appear in LiteLLM logs when proxy is enabled
- [ ] Embedding calls for `drush sapi-i` appear in LiteLLM logs when proxy is enabled

**Note on config:get vs runtime behavior:** The `$config` override in settings.local.php overrides at runtime but `drush config:get` reads from the database. To verify the override is active, use:
```bash
ddev drush php:eval "echo \Drupal::config('ai_provider_openai.settings')->get('host');"
```

---

## Task 3: Drush Inspection Commands

### AI agent and context inspection

```bash
# List all AI agents
ddev drush config:list | grep ai_agents.ai_agent

# Read a specific agent config (e.g., the orchestrator)
ddev drush config:get ai_agents.ai_agent.canvas_ai_orchestrator

# List all agent IDs
ddev drush config:list | grep ai_agents.ai_agent | sed 's/ai_agents.ai_agent.//'

# Read AI default provider settings (which provider handles chat vs embeddings)
ddev drush config:get ai.settings

# Read AI context settings
ddev drush config:get ai_context.settings

# Read AI context agent mappings
ddev drush config:get ai_context.agents

# List all AI context items
ddev drush entity:list ai_context_item
# Or if entity:list is unavailable:
ddev drush php:eval "foreach (\Drupal::entityTypeManager()->getStorage('ai_context_item')->loadMultiple() as \$e) echo \$e->label() . ' (' . \$e->id() . ')' . PHP_EOL;"
```

### AI provider inspection

```bash
# Read provider configs
ddev drush config:get ai_provider_openai.settings
ddev drush config:get ai_provider_anthropic.settings
ddev drush config:get ai_provider_amazeeio.settings

# Check runtime-effective host (includes settings.local.php overrides)
ddev drush php:eval "echo \Drupal::config('ai_provider_openai.settings')->get('host');"
ddev drush php:eval "echo \Drupal::config('ai_provider_anthropic.settings')->get('host');"

# List Key module keys
ddev drush config:list | grep key.key
ddev drush config:get key.key.openai_api_key
ddev drush config:get key.key.antropic_api_key

# Verify keys are loading from env vars
ddev drush php:eval "echo \Drupal::service('key.repository')->getKey('openai_api_key')->getKeyValue() ? 'KEY SET' : 'KEY MISSING';"
ddev drush php:eval "echo \Drupal::service('key.repository')->getKey('antropic_api_key')->getKeyValue() ? 'KEY SET' : 'KEY MISSING';"
```

### Search API / Milvus inspection

```bash
# List search API indexes
ddev drush sapi-l

# Check index status
ddev drush sapi-s

# Trigger reindex (CAUTION: calls OpenAI embeddings API)
ddev drush sapi-i

# Read search API server configs
ddev drush config:get search_api.server.canvas_page_search
ddev drush config:get search_api.server.media_image_search
ddev drush config:get search_api.server.database

# Read search API index configs
ddev drush config:get search_api.index.canvas_page_search_index
ddev drush config:get search_api.index.content
ddev drush config:get search_api.index.media_image_index_rag
```

### Module and Canvas inspection

```bash
# List all AI-related modules
ddev drush pm:list --filter=ai --status=enabled

# List all enabled modules with 'canvas' in name
ddev drush pm:list --filter=canvas --status=enabled

# Export specific config for offline review
ddev drush config:export --destination=/tmp/config-export 2>/dev/null
# Or export just AI configs:
ddev drush php:eval "
foreach (\Drupal::service('config.storage')->listAll('ai') as \$name) {
  \$data = \Drupal::config(\$name)->getRawData();
  file_put_contents('/tmp/' . \$name . '.yml', \Symfony\Component\Yaml\Yaml::dump(\$data, 10));
  echo \$name . PHP_EOL;
}"

# Check Canvas AI settings
ddev drush config:get canvas_ai.settings
```

### Custom drush commands (if available)

```bash
# Check if AI modules provide custom drush commands
ddev drush list | grep -i ai
ddev drush list | grep -i canvas
ddev drush list | grep -i context
```

### Acceptance criteria
- [ ] All listed commands execute without error on a running FinDrop instance
- [ ] Agent configs, provider settings, search API status, and key status are all inspectable
- [ ] Runtime config overrides from settings.local.php are verifiable via `php:eval`

---

## Task 4: Canvas UI Interaction Points (Playwright Testing)

### URL paths

| Path | Purpose |
|------|---------|
| `/canvas` | Canvas UI boot (empty, no entity) |
| `/canvas/editor/canvas_page/{id}` | Canvas page editor (where AI chatbot lives) |
| `/canvas/code-editor/component/{name}` | Code component editor |
| `/admin/content/pages/add` | Create new Canvas page |

### API endpoints to intercept

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `POST /admin/api/canvas/token` | POST | Get CSRF token for AI calls |
| `POST /admin/api/canvas/ai` | POST | Main AI agent execution (sends prompt, receives structured response) |
| `GET /admin/api/canvas/ai-progress?request_id={id}` | GET | Poll agent execution progress |
| `PATCH /canvas/api/v0/layout/{entity_type}/{entity}` | PATCH | Apply layout changes from AI |
| `PATCH /canvas/api/v0/content/auto-save/canvas_page/{id}` | PATCH | Auto-save content changes |

### DOM selectors for Playwright

```typescript
// AI Panel toggle button
page.getByRole('button', { name: 'Open AI Panel' })

// AI Panel container
page.getByTestId('canvas-ai-panel')

// Deep Chat input (inside AI panel)
page.getByRole('textbox', { name: 'Build me a' })  // placeholder text

// Submit button (inside deep-chat component)
page.getByTestId('canvas-ai-panel').locator('.input-button.inside-right')

// File upload area (drag and drop)
page.locator('[data-testid="canvas-ai-panel"] deep-chat #drag-and-drop')

// Page title input
page.getByRole('textbox', { name: 'Title*' })

// Meta description input
page.getByRole('textbox', { name: 'Meta description' })

// Publish button
page.getByTestId('canvas-publish-review')
```

### Network interception pattern for Playwright

```typescript
// Intercept all AI API calls
await page.route('**/admin/api/canvas/ai', async (route) => {
  const request = route.request();
  console.log('AI Request:', {
    method: request.method(),
    postData: request.postData(),
    headers: request.headers(),
  });

  // Forward to actual endpoint
  const response = await route.fetch();
  const body = await response.json();
  console.log('AI Response:', body);

  await route.fulfill({ response });
});

// Intercept progress polling
await page.route('**/admin/api/canvas/ai-progress**', async (route) => {
  const response = await route.fetch();
  const body = await response.json();
  console.log('Progress:', body);
  await route.fulfill({ response });
});
```

### How Canvas triggers AI agent calls

1. User types in the deep-chat input and submits
2. AiWizard.tsx calls `POST /admin/api/canvas/token` to get a CSRF token (once on mount)
3. AiWizard.tsx sends `POST /admin/api/canvas/ai` with:
   - `X-CSRF-Token` header
   - JSON body containing: `messages[]`, `entity_type`, `entity_id`, `current_layout`, `selected_component`, `page_title`, `page_description`, `request_id`, etc.
4. Simultaneously polls `GET /admin/api/canvas/ai-progress?request_id={id}` for real-time agent status
5. The CanvasBuilder controller creates the `canvas_ai_orchestrator` agent, which delegates to sub-agents
6. Sub-agents call the AI provider (Anthropic for chat, OpenAI for embeddings) via the Drupal AI module
7. Response includes layout patches, component data, and status messages
8. AiWizard applies layout changes via the Canvas API

### Acceptance criteria
- [ ] Playwright can navigate to Canvas editor, open the AI panel, and submit a query
- [ ] Network requests to `/admin/api/canvas/ai` are captured with full payloads
- [ ] When LiteLLM proxy is enabled, AI calls appear in LiteLLM logs during Playwright test execution

---

## Task 5: Risk Analysis

### LiteLLM downtime
- **Impact:** All AI agent calls fail. Canvas AI chatbot shows "No default provider found" or connection errors.
- **Mitigation:** Toggle `LITELLM_PROXY_ENABLED=0` in `.ddev/.env` and `ddev restart` to bypass. The `settings.local.php` toggle makes this a 30-second recovery.
- **Error handling:** The CanvasBuilder controller catches exceptions from `determineSolvability()` and returns `{'status': false, 'message': error}` as JSON. The UI shows the error message.

### Milvus indexing and API calls
- **Fact:** `drush sapi-i` calls the OpenAI embeddings API (`text-embedding-3-small`) for every piece of content. This is NOT a local operation.
- **Impact:** If LiteLLM is down or the OpenAI key is invalid, indexing fails.
- **Mitigation:** LiteLLM caches embedding calls (configured with `type: local`, TTL 3600s). Repeated indexing of the same content hits the cache.
- **Cost:** The search API configs show 3 indexes: `canvas_page_search_index`, `content`, `media_image_index_rag`. Initial indexing could generate significant API costs depending on content volume.

### Memory constraints
- **Current stack:** MariaDB 10.11 (~200MB) + PHP-FPM (~300MB) + nginx + etcd (~50MB) + MinIO (~200MB) + Milvus (~1.5GB) + Attu (~100MB)
- **Adding LiteLLM:** ~300-500MB additional
- **Total estimated:** ~2.5-3.5GB for the full Docker stack
- **Assessment:** Tight but workable on a 16GB laptop. On 8GB, could hit swap. Milvus is the heaviest consumer.
- **Mitigation:** If memory is tight, add `mem_limit: 512m` to the LiteLLM service definition. Can also disable Attu UI when not needed.

### The `host: ''` field for OpenAI
- **Answer confirmed from source code:** Empty string means "use default endpoint." The code checks `if (!empty($this->getConfig()->get('host')))` — empty string is falsy in PHP, so it falls through to the default `https://api.openai.com/v1` endpoint.

### Key management
- **Pattern:** Drupal's Key module reads from env vars (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`). LiteLLM also reads the same env vars. In proxy mode, the actual API calls go through LiteLLM (which uses the real keys), but Drupal still loads the keys for its own validation checks.
- **Risk:** If Drupal sends keys to LiteLLM in request headers, and LiteLLM also has its own keys configured, there could be key conflicts.
- **Analysis:** The AI provider modules send the API key as an `Authorization: Bearer` header (OpenAI) or `X-API-Key` header (Anthropic). LiteLLM in proxy mode uses its own configured keys to forward to the real APIs. The LiteLLM master key (`LITELLM_MASTER_KEY`) is only for LiteLLM's admin API, not for proxied requests.
- **Mitigation:** LiteLLM's model config uses `api_key: os.environ/ANTHROPIC_API_KEY` which reads the key directly. The key sent by Drupal in the request header is used for LiteLLM authentication (optional). This works correctly because LiteLLM is configured to not require auth for proxied requests by default.

### Anthropic API version header
- **The Anthropic provider config has `version: '20240229'`**. LiteLLM must forward this header. LiteLLM handles this natively for Anthropic models.

---

## Task 6: Implementation Checklist

### File creation order

1. `.ddev/litellm/litellm_config.yaml` — LiteLLM model + logging config
2. `.ddev/docker-compose.litellm.yaml` — DDEV service definition
3. `web/sites/default/settings.local.php` — Proxy routing overrides
4. Update `.ddev/.env` — Add `LITELLM_PROXY_ENABLED=1`
5. Update `.gitignore` — Add `.ddev/litellm/logs/`
6. Update `.ddev/commands/host/demo-setup` — Add settings.local.php include injection

### Verification sequence

```bash
# 1. Start DDEV with LiteLLM
ddev start

# 2. Verify LiteLLM is healthy
ddev exec curl -s http://litellm:4000/health | python3 -m json.tool

# 3. Verify proxy routing is active
ddev drush php:eval "echo \Drupal::config('ai_provider_openai.settings')->get('host');"
# Expected: http://litellm:4000/v1

ddev drush php:eval "echo \Drupal::config('ai_provider_anthropic.settings')->get('host');"
# Expected: http://litellm:4000/v1

# 4. Verify keys are loaded
ddev drush php:eval "echo \Drupal::service('key.repository')->getKey('openai_api_key')->getKeyValue() ? 'OK' : 'MISSING';"

# 5. Test an actual AI call (generate a title for a canvas page)
# Navigate to Canvas editor and use AI panel, then check:
ddev logs -s litellm

# 6. Test embedding indexing through proxy
ddev drush sapi-i
ddev logs -s litellm | grep embedding
```

### Acceptance criteria (overall)
- [ ] Full DDEV stack starts cleanly with `ddev start` (Milvus + LiteLLM + MariaDB + PHP)
- [ ] AI chat calls route through LiteLLM and are logged
- [ ] Embedding calls route through LiteLLM and are cached
- [ ] Proxy can be disabled by setting `LITELLM_PROXY_ENABLED=0` and restarting
- [ ] All Drush inspection commands work
- [ ] Playwright can interact with Canvas AI panel and capture network traffic
- [ ] No contrib module code was modified

---

## Architecture Decision Record

**Decision:** Use LiteLLM as an in-container HTTP proxy, configured via Drupal's `$config` override mechanism in `settings.local.php`, toggled by environment variable.

**Drivers:**
1. Both AI provider modules support `host` config override (confirmed from source code)
2. LiteLLM provides OpenAI-compatible proxy that handles both OpenAI and Anthropic protocols
3. Environment variable toggle provides instant reversibility

**Alternatives considered:**
- **Drupal config override via drush config:set** — Persists in database, harder to toggle, survives config export (contamination risk). Rejected.
- **Patching provider modules** — Violates "no contrib patches" guardrail. Rejected.
- **Mitmproxy/Charles at the network level** — Requires HTTPS cert injection into the PHP container, complex Docker networking. Overkill for this use case. Rejected.
- **Using `hook_ai_pre_execute` event** — Would require custom module code, only captures Drupal-side data, doesn't provide caching/cost tracking. Rejected.

**Consequences:**
- LiteLLM adds ~300-500MB memory to the Docker stack
- First request after `ddev start` may be slow (LiteLLM cold start ~10-15s)
- LiteLLM logs grow unbounded; periodic cleanup needed for long sessions
- The `OpenAiHelper` class (used for form validation) constructs URLs differently from `loadClient()` — form validation may bypass the proxy. This is acceptable since we care about runtime calls, not admin form validation.

**Follow-ups:**
- Consider LiteLLM's `/spend` endpoint for cost tracking per agent
- Explore LiteLLM callbacks for structured log export (JSON lines)
- Test whether LiteLLM correctly handles the Anthropic `anthropic-version` header passthrough
