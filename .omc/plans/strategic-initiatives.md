# Strategic Initiatives: Canvas Direct-Edit System
## Technical Implementation Plan

**Module:** `web/modules/custom/ai_agents_canvas_direct_edit/`
**Branch:** `feat/strategic-initiatives` (from `feat/show-and-prove-session-2`)
**Date:** 2026-03-31
**ai module version:** 1.3.0-rc2
**Drupal:** 11.3, PHP 8.3

---

## Initiative 1: Canvas Lite (API-Key-Free Mode)

### Problem
Canvas currently requires AI API keys to function at all. 60-70% of edits are simple prop changes that the DirectEditMatcher resolves deterministically in ~38ms at zero token cost. Sites without API keys should still be able to use these deterministic edits.

### Architecture

**New/Modified Services:**
- `AiProviderAvailabilityChecker` -- new service that checks whether any AI provider is configured and usable for `chat` operation type. Wraps `AiProviderPluginManager::getDefaultProviderForOperationType('chat')` and `::isUsable()`.
- `DirectEditController::edit()` -- modify the 422 (no_match) response path to check AI availability. If no AI provider configured, return a 503 with a structured message instead of 422.
- `MatchDirectEdit` Tool plugin -- add `ai_available` boolean to the `no_match` response payload so the agent (or MCP client) knows whether AI fallback is possible.

**New Config:**
None required. The availability check is dynamic (reads from `ai.settings` default providers at runtime).

**Events:**
None. This is pure service-layer routing.

**Controllers:**
- `DirectEditController::edit()` -- modified response on no_match path.

### Implementation Tasks

| # | Task | Depends On | Complexity | Acceptance Criteria |
|---|------|-----------|------------|-------------------|
| 1.1 | Create `AiProviderAvailabilityChecker` service | -- | Low | Service returns `bool` for `isAiAvailable()`. Registered in `*.services.yml`. Injected with `ai.provider` (AiProviderPluginManager). |
| 1.2 | Modify `DirectEditController` no_match path | 1.1 | Low | When match fails AND no AI provider configured: returns 503 JSON with `{status: false, reason: "ai_unavailable", message: "This edit requires AI..."}`. When AI IS available: returns 422 as before. |
| 1.3 | Add `ai_available` to `MatchDirectEdit` Tool response | 1.1 | Low | The `no_match` result JSON includes `"ai_available": true/false`. |
| 1.4 | Add kernel tests for API-key-free mode | 1.2, 1.3 | Medium | Tests: (a) simple edit works with zero providers, (b) complex edit returns 503 with no provider, (c) complex edit returns 422 with provider configured, (d) Tool plugin includes ai_available field. |

### Config Schema

No new config YAML. The check is dynamic against existing `ai.settings` config.

### Test Strategy

| Type | Scenarios |
|------|-----------|
| Kernel | Mock `AiProviderPluginManager` to return null default provider. Verify controller returns 503 for no_match. |
| Kernel | Mock `AiProviderPluginManager` with a configured provider. Verify controller returns 422 for no_match. |
| Kernel | Verify deterministic matches work identically regardless of AI provider availability. |
| Kernel | Verify `MatchDirectEdit` Tool plugin includes `ai_available` in no_match response. |

### Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| `AiProviderPluginManager::getDefaultProviderForOperationType()` returns stale data | Low | Low | No caching layer on the checker -- reads config on each call. Config changes invalidate automatically. |
| Canvas AI frontend JS expects 422 and breaks on 503 | Medium | Medium | 503 only fires when no AI is configured, which means the AI path was never reachable anyway. Frontend should handle non-422 gracefully. Document the new response code. |

---

## Initiative 2: Canvas MCP Server

### Problem
AI edits cost $3-15/MTok via server-side API keys. Users with Claude Desktop Pro or ChatGPT Plus have unlimited tokens. An MCP server lets desktop AI tools invoke Canvas editing operations using the user's subscription.

### Architecture

**New Module (submodule):**
`ai_agents_canvas_direct_edit_mcp/` -- a submodule to avoid adding MCP dependencies to the core direct-edit module.

**New Services:**
- `McpToolBridge` -- service that adapts `ToolManager` plugin definitions into MCP tool schemas. Iterates `ToolManager::getDefinitions()`, filters for tools prefixed with `ai_agents_canvas_direct_edit:`, and converts `InputDefinition` objects to JSON Schema format.
- `McpRequestHandler` -- processes incoming MCP JSON-RPC requests. Routes `tools/list` to McpToolBridge, routes `tools/call` to ToolManager plugin execution.

**New Controller:**
- `McpServerController` -- Drupal route endpoint at `/api/mcp/canvas` implementing MCP Streamable HTTP transport (2025-03-26 spec). Handles:
  - `POST` with `application/json` -- JSON-RPC messages (initialize, tools/list, tools/call)
  - Session management via `Mcp-Session-Id` header
  - SSE responses for streaming results

**Authentication:**
- Primary: Drupal session cookie (for browser-adjacent tools)
- Secondary: API token via `Authorization: Bearer` header. Uses Drupal's `basic_auth` module or a lightweight custom token validator. The token maps to a Drupal user for permission checks.

**Permissions:**
- Read operations (get_page_layout, get_component_catalog, get_component_schema, get_component_props, match_direct_edit): require `use ai agents canvas direct edit` permission (existing).
- Write operations (update_component_props, add_component, move_component): require `use ai agents canvas direct edit` permission (existing) -- the Tool plugins already enforce this via `checkAccess()`.

**Config:**
- `ai_agents_canvas_direct_edit_mcp.settings` -- MCP server enable/disable toggle, allowed origins for CORS, session TTL.

### Implementation Tasks

| # | Task | Depends On | Complexity | Acceptance Criteria |
|---|------|-----------|------------|-------------------|
| 2.1 | Create submodule scaffold | -- | Low | `ai_agents_canvas_direct_edit_mcp.info.yml`, `*.routing.yml`, `*.services.yml`, `*.permissions.yml`. Depends on `ai_agents_canvas_direct_edit` and `tool`. |
| 2.2 | Implement `McpToolBridge` | 2.1 | Medium | Converts 8 Tool plugin definitions to MCP tool schemas. `listTools()` returns array of `{name, description, inputSchema}`. `executeTool(name, arguments, account)` invokes the plugin and returns result. |
| 2.3 | Implement MCP JSON-RPC handler | 2.2 | High | Handles `initialize`, `notifications/initialized`, `tools/list`, `tools/call` methods per MCP spec. Stateless between requests (session state in Drupal tempstore). |
| 2.4 | Implement `McpServerController` | 2.3 | Medium | Route at `POST /api/mcp/canvas`. Returns proper JSON-RPC responses. Validates `Content-Type`, handles `Mcp-Session-Id`. CSRF exemption for API token auth. |
| 2.5 | Add API token authentication | 2.4 | Medium | `Authorization: Bearer {token}` resolves to Drupal user. Token stored in user data or as simple config entity. Falls back to session cookie. |
| 2.6 | Add config + schema | 2.1 | Low | Config: `enabled`, `allowed_origins`, `session_ttl`. Schema validates types. |
| 2.7 | Add kernel tests for MCP tool bridge | 2.2 | Medium | Tests: tool listing returns all 8 tools with valid JSON Schema, tool execution respects permissions, read vs write operations. |
| 2.8 | Add integration test with Claude Desktop config | 2.4 | Low | Document `claude_desktop_config.json` MCP server entry pointing to the Drupal endpoint. Manual verification checklist. |

### Config Schema

```yaml
# config/install/ai_agents_canvas_direct_edit_mcp.settings.yml
enabled: true
allowed_origins: []
session_ttl: 3600

# config/schema/ai_agents_canvas_direct_edit_mcp.schema.yml
ai_agents_canvas_direct_edit_mcp.settings:
  type: config_object
  label: 'Canvas MCP Server settings'
  mapping:
    enabled:
      type: boolean
      label: 'Enable MCP server endpoint'
    allowed_origins:
      type: sequence
      label: 'Allowed CORS origins'
      sequence:
        type: string
        label: 'Origin'
    session_ttl:
      type: integer
      label: 'MCP session TTL in seconds'
```

### Test Strategy

| Type | Scenarios |
|------|-----------|
| Kernel | `McpToolBridge::listTools()` returns all 8 tools with correct names and JSON Schema input definitions. |
| Kernel | `McpToolBridge::executeTool()` executes a read tool and returns expected result. |
| Kernel | `McpToolBridge::executeTool()` on write tool without permission returns access denied. |
| Kernel | `McpServerController` returns valid JSON-RPC responses for `initialize` and `tools/list`. |
| Kernel | `McpServerController` rejects requests without valid authentication. |
| Kernel | `McpServerController` respects `enabled: false` config (returns 503). |

### Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| MCP spec is still evolving (2025-03-26) | Medium | Medium | Implement the core subset (initialize + tools). Avoid advanced features (resources, prompts, sampling) initially. Pin to spec version in code comments. |
| CSRF protection conflicts with API access | High | High | Exempt the MCP route from CSRF when Bearer token auth is used. Session cookie auth retains CSRF requirement. |
| Tempstore requires active Canvas session | High | Medium | MCP write operations need the Canvas page to be loaded in the editor (tempstore populated). Document this prerequisite. Read operations (catalog, schema) work without tempstore. |
| Performance of JSON-RPC parsing overhead | Low | Low | Minimal overhead -- single JSON decode per request. No streaming complexity for tool calls (results are small). |

---

## Initiative 3: Prompt Caching Integration

### Problem
The AI agent loop sends redundant system prompts on every iteration. After loop 0, the system prompt is stable. Anthropic prompt caching can cache the stable prefix for 50-90% cost reduction per call.

### Architecture

**Key Discovery:** The `ai` module 1.3.0-rc2 uses `OpenAiBasedProviderClientBase::chat()` which builds an OpenAI-compatible payload. The Anthropic provider extends this base class. The `PreGenerateResponseEvent` fires before the API call and allows modifying `input` and `configuration`. However:

- The base `chat()` method builds the system prompt as a plain `{'role': 'system', 'content': string}` message.
- Anthropic's cache_control requires the system prompt to use the structured `[{type: 'text', text: '...', cache_control: {type: 'ephemeral'}}]` format.
- The `configuration` array is spread into the payload, so provider-specific config (like `anthropic-beta` header) could theoretically be injected.

**Approach: EventSubscriber on PreGenerateResponseEvent**

- `CanvasPromptCacheSubscriber` -- listens to `ai.pre_generate_response`
- Only activates when: (a) provider is `anthropic`, (b) tags include `canvas_ai` or agent tags
- Modifies the `ChatInput` to restructure the system prompt with `cache_control` breakpoints
- Injects `anthropic-beta: prompt-caching-2024-07-31` into the configuration (if the provider supports passing custom headers via config)

**Challenge:** The base class `chat()` method handles system prompt as a flat string (`$this->chatSystemRole`), not structured content blocks. The event fires BEFORE `chat()` builds the payload, so we can modify the `ChatInput` but the system prompt extraction happens inside `chat()`.

**Realistic Approach:** A custom Anthropic provider decorator or a patch to `OpenAiBasedProviderClientBase::chat()` that checks for structured system content. Alternatively, a custom `ChatInput` subclass that carries cache_control metadata.

**Recommended Path:** EventSubscriber that sets a metadata flag on the event + a small patch to `ai_provider_anthropic` that reads this flag and applies cache_control when building the API payload. This keeps the cache logic clean and provider-specific.

**New Services:**
- `CanvasPromptCacheSubscriber` (EventSubscriber) -- sets cache metadata on `PreGenerateResponseEvent`

**New Config:**
- Add `prompt_caching_enabled` to `ai_agents_canvas_direct_edit.settings`

### Implementation Tasks

| # | Task | Depends On | Complexity | Acceptance Criteria |
|---|------|-----------|------------|-------------------|
| 3.1 | Research: verify Anthropic API cache_control with current SDK | -- | Medium | Document exact payload format needed. Confirm `anthropic-beta` header handling in ai_provider_anthropic. Test manually with a raw curl call. |
| 3.2 | Create `CanvasPromptCacheSubscriber` | 3.1 | Medium | EventSubscriber on `ai.pre_generate_response`. Activates only for Anthropic provider. Sets metadata flag `canvas_prompt_cache: true` on event. Sets `cache_control` structure in configuration array. |
| 3.3 | Patch or extend Anthropic provider for cache_control | 3.1 | High | Either: (a) patch `ai_provider_anthropic` to read cache_control from configuration and apply to system prompt, or (b) create a decorator provider that wraps the Anthropic provider and modifies the payload pre-send. Option (b) preferred for no-patch constraint. |
| 3.4 | Add telemetry for cache hit/miss | 3.2 | Low | Listen to `ai.post_generate_response` event. Log cache hit/miss from Anthropic response headers (`x-anthropic-cache-creation-input-tokens`, `x-anthropic-cache-read-input-tokens`). |
| 3.5 | Add config toggle | 3.2 | Low | Add `prompt_caching_enabled: true` to config install. Add to schema. Subscriber checks config before activating. |
| 3.6 | Add kernel tests | 3.2, 3.3 | Medium | Tests: subscriber only fires for Anthropic, metadata is set correctly, cache_control structure is valid, no-op for non-Anthropic providers, respects config toggle. |

### Config Schema Addition

```yaml
# Addition to ai_agents_canvas_direct_edit.settings
prompt_caching_enabled:
  type: boolean
  label: 'Enable Anthropic prompt caching for Canvas AI agents'
```

### Test Strategy

| Type | Scenarios |
|------|-----------|
| Unit | `CanvasPromptCacheSubscriber` only activates for `anthropic` provider ID. |
| Unit | Subscriber is no-op when `prompt_caching_enabled: false`. |
| Kernel | Event fires and metadata is set on mock PreGenerateResponseEvent with anthropic provider. |
| Kernel | Cache telemetry logs cache hit/miss counts from mock PostGenerateResponseEvent. |

### Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| `ai` module 1.3.0 doesn't pass provider-specific config cleanly to API payload | High | High | Research required in task 3.1. If config passthrough is blocked, fall back to a provider decorator pattern. |
| Upstream `ai_provider_anthropic` changes break patch | Medium | Medium | Keep patch minimal. Track upstream issue. Contribute cache_control support upstream. |
| Cache invalidation when system prompt changes | Low | Low | Anthropic handles this automatically -- different prompt prefix = cache miss. Document that system prompt changes increase cost temporarily. |
| P2 patch (#3582288) not yet merged upstream | Known | Medium | Initiative can proceed with local patch. Cache benefit is independent of P2. P2 stabilizes the prompt further, improving hit rate. |

---

## Initiative 4: Model Routing by Complexity

### Problem
All AI fallback edits use the same model (typically Sonnet via `chat_with_complex_json` default). Simple AI edits ("make the heading more engaging") could use Haiku (faster, cheaper). Complex edits (multi-component layout changes) need Sonnet.

### Architecture

**Key Discovery:** `AiAgentBase::__construct()` resolves the provider/model from `AiProviderPluginManager::getDefaultProviderForOperationType('chat_with_complex_json')`. The `modelId` on `AiProviderRequestBaseEvent` is read-only (no setter). This means model routing CANNOT happen via the PreGenerateResponseEvent -- it must happen earlier, at the agent configuration level.

**Approach:** Complexity-based routing happens in the DirectEditMatcher and DirectEditController, which can set routing hints that the agent or event subscriber reads.

**Modified Services:**
- `DirectEditMatcher::match()` -- returns `MatchResult` DTO instead of raw array. Includes `confidence` score (0.0-1.0), `nearestMiss` metadata (which tier got closest), `complexitySignal` (enum: `trivial`, `simple`, `complex`).
- `DirectEditController::edit()` -- on 422 no_match, includes `complexity_signal` and `confidence` in the response body, so the frontend can pass it to the AI agent.
- `ComplexityModelRouter` -- new service. Takes a complexity signal and returns `{provider_id, model_id}` based on configurable thresholds.

**New EventSubscriber:**
- `ModelRoutingSubscriber` -- listens to `ai.pre_generate_response`. Reads `complexity_signal` from event metadata/tags. If present and provider supports the target model, creates a new provider instance with the routed model. Since `modelId` is read-only on the event, the subscriber would need to use `setForcedOutputObject()` with a re-routed call, OR we modify the approach to set routing at the agent level.

**Revised Approach:** The cleanest path is:
1. Matcher returns confidence/complexity metadata
2. Controller passes this as context to the AI agent invocation
3. A custom agent plugin or agent configuration uses the complexity signal to select the model before the provider proxy is invoked

Since `ai_agents` resolves the model in the constructor, the practical approach is:
- Use `PreGenerateResponseEvent` to read a custom tag like `canvas_complexity:simple`
- In the subscriber, create a NEW provider proxy with the Haiku model and call it directly, returning the result via `setForcedOutputObject()`
- This is a "short-circuit and re-route" pattern

**New Config:**
```yaml
model_routing:
  enabled: true
  thresholds:
    simple_max_confidence: 0.6
    complex_min_confidence: 0.3
  models:
    simple: 'claude-haiku-3-20250307'
    complex: 'claude-sonnet-4-20250514'
```

### Implementation Tasks

| # | Task | Depends On | Complexity | Acceptance Criteria |
|---|------|-----------|------------|-------------------|
| 4.1 | Add confidence scoring to `DirectEditMatcher` | -- | Medium | `match()` returns `MatchResult` object with: `matched` (bool), `changes` (array or null), `confidence` (float 0-1), `nearestTier` (int or null), `complexitySignal` (string: trivial/simple/complex). Backward-compatible: existing callers can still use array access. |
| 4.2 | Create `MatchResult` value object | -- | Low | Immutable DTO. Implements `\ArrayAccess` for backward compat with existing tests/callers. Properties: `matched`, `changes`, `confidence`, `nearestTier`, `complexitySignal`. |
| 4.3 | Create `ComplexityModelRouter` service | 4.1 | Medium | Takes `complexitySignal` string, returns `{provider_id, model_id}` from config. Falls back to default if routing disabled or signal unknown. |
| 4.4 | Modify `DirectEditController` 422 response | 4.1 | Low | Include `complexity_signal` and `confidence` in the no_match response JSON body. |
| 4.5 | Create `ModelRoutingSubscriber` | 4.3 | High | EventSubscriber on `ai.pre_generate_response`. Reads `canvas_complexity` tag. If present, re-routes to appropriate model via new provider proxy call with `setForcedOutputObject()`. |
| 4.6 | Add config for model routing | 4.3 | Low | Add `model_routing` section to config install and schema. |
| 4.7 | Add kernel tests | 4.1-4.5 | Medium | Tests: confidence scores for known match patterns, complexity signals for various edit types, model router returns correct model for each signal, backward compat with existing 52 tests. |

### Config Schema Addition

```yaml
# Addition to ai_agents_canvas_direct_edit.settings
model_routing:
  type: mapping
  label: 'Complexity-based model routing'
  mapping:
    enabled:
      type: boolean
      label: 'Enable complexity-based model routing'
    thresholds:
      type: mapping
      label: 'Complexity classification thresholds'
      mapping:
        simple_max_confidence:
          type: float
          label: 'Max confidence for simple classification (0-1)'
        complex_min_confidence:
          type: float
          label: 'Min confidence for complex classification (0-1)'
    models:
      type: mapping
      label: 'Model assignments by complexity'
      mapping:
        simple:
          type: string
          label: 'Model ID for simple edits (e.g. claude-haiku)'
        complex:
          type: string
          label: 'Model ID for complex edits (e.g. claude-sonnet)'
```

### Test Strategy

| Type | Scenarios |
|------|-----------|
| Unit | `MatchResult` value object: confidence score, complexity signal, ArrayAccess backward compat. |
| Kernel | Matcher returns confidence for exact match (1.0), partial match (0.5-0.8), no match (0.0-0.3). |
| Kernel | `ComplexityModelRouter` returns haiku for `simple`, sonnet for `complex`, default for unknown. |
| Kernel | Existing 52 kernel tests still pass unchanged (backward compatibility via ArrayAccess). |
| Kernel | `DirectEditController` 422 response includes `complexity_signal` field. |

### Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| `modelId` is read-only on `PreGenerateResponseEvent` | Known | High | Use `setForcedOutputObject()` pattern to re-route. Document this as a known limitation of ai module 1.x. Propose `setModelId()` upstream for 2.0. |
| Haiku model insufficient for some "simple" edits | Medium | Medium | Conservative initial thresholds. Only route to Haiku when confidence is very low (near-miss on deterministic) and the edit is clearly a single-prop text change. Log routing decisions for telemetry review. |
| Breaking backward compat of `match()` return type | High | High | `MatchResult` implements `ArrayAccess` so `$result['prop']`, `$result['changes']` etc. still work. Existing callers don't break. New callers can use `$result->getConfidence()`. |

---

## Initiative 5: Real-World Telemetry

### Problem
Hit rate (60%) and performance (38ms) are from synthetic benchmarks. Need real-world validation from actual demo site usage with structured logging and aggregation.

### Architecture

**Modified Services:**
- `TelemetryCollector` -- new service. Accepts structured edit event data, writes to a dedicated database table via Drupal's database API. Replaces the current inline JSON-encoded logger calls in DirectEditController.
- `DirectEditController::edit()` -- delegates telemetry to `TelemetryCollector` instead of inline logging.

**New Services:**
- `TelemetryAggregator` -- reads the telemetry table and computes aggregates: hit rate, tier distribution, latency percentiles (p50/p95/p99), model selection breakdown, AI fallback rate.
- `TelemetryExportController` -- route at `/admin/reports/canvas-direct-edit/telemetry` that returns aggregated data as JSON (for external analysis) or renders a simple admin page.

**Database Schema (hook_schema):**
```
canvas_direct_edit_telemetry:
  - id (serial, primary key)
  - timestamp (int, unix timestamp)
  - component_name (varchar 128)
  - tier (varchar 16) -- 'exact', 'alias', 'enum', 'relative', 'boolean', 'reset', 'compound', 'reject'
  - matched (boolean)
  - prop_name (varchar 64, nullable)
  - confidence (float, nullable) -- from Initiative 4
  - complexity_signal (varchar 16, nullable) -- from Initiative 4
  - model_used (varchar 64, nullable) -- null for deterministic
  - latency_us (int) -- microseconds
  - message_length (int)
  - message_hash (varchar 64) -- SHA-256 for dedup without storing content
  - redacted_message (text, nullable) -- only stored if redaction disabled in config
  - ai_fallback (boolean) -- whether this edit went to AI after deterministic miss
  - ai_latency_ms (int, nullable) -- AI path latency if fallback occurred
```

**Config:**
```yaml
telemetry:
  enabled: true
  store_messages: false  # PII-safe default
  retention_days: 90
  export_enabled: true
```

**Privacy:**
- Default: message content NOT stored (only hash for dedup analysis)
- Configurable: `store_messages: true` stores the raw message (for demo/dev sites only)
- Component names and prop names are not PII -- safe to store

### Implementation Tasks

| # | Task | Depends On | Complexity | Acceptance Criteria |
|---|------|-----------|------------|-------------------|
| 5.1 | Create database schema via `hook_schema()` | -- | Low | Table `canvas_direct_edit_telemetry` with all fields. Runs on module install. |
| 5.2 | Create `TelemetryCollector` service | 5.1 | Medium | `record(TelemetryEvent $event): void`. Checks config `telemetry.enabled`. Writes to DB table. Handles message redaction based on `store_messages` config. |
| 5.3 | Create `TelemetryEvent` value object | -- | Low | Immutable DTO carrying all telemetry fields. Builder pattern for construction. |
| 5.4 | Refactor `DirectEditController` to use TelemetryCollector | 5.2 | Low | Remove inline `$this->logger->info('DirectEdit telemetry:...')` calls. Replace with `$this->telemetryCollector->record(...)`. Both match and no-match paths instrumented. |
| 5.5 | Create `TelemetryAggregator` service | 5.1 | Medium | Methods: `getHitRate(DateRange)`, `getTierDistribution(DateRange)`, `getLatencyPercentiles(DateRange)`, `getModelBreakdown(DateRange)`, `getAiFallbackRate(DateRange)`. All return structured arrays. |
| 5.6 | Create `TelemetryExportController` | 5.5 | Medium | Route `/admin/reports/canvas-direct-edit/telemetry`. Returns JSON aggregation. Permission: `administer ai agents canvas direct edit`. Optional: simple HTML table admin page. |
| 5.7 | Add retention cleanup via cron | 5.1 | Low | `hook_cron()` deletes records older than `retention_days`. |
| 5.8 | Add config and schema | 5.2 | Low | Extend existing config with `telemetry` section. Update schema. |
| 5.9 | Add kernel tests | 5.2-5.6 | Medium | Tests: collector writes to DB, aggregator computes correct stats, redaction works, cron cleanup works, export controller returns valid JSON. |

### Config Schema Addition

```yaml
# Replace existing telemetry_enabled with richer structure:
telemetry:
  type: mapping
  label: 'Telemetry configuration'
  mapping:
    enabled:
      type: boolean
      label: 'Enable telemetry collection'
    store_messages:
      type: boolean
      label: 'Store raw message content (disable for PII safety)'
    retention_days:
      type: integer
      label: 'Days to retain telemetry records'
    export_enabled:
      type: boolean
      label: 'Enable telemetry export endpoint'
```

### Test Strategy

| Type | Scenarios |
|------|-----------|
| Kernel | `TelemetryCollector::record()` writes row to database. Verify all fields persisted correctly. |
| Kernel | Collector respects `enabled: false` -- no rows written. |
| Kernel | Collector redacts message when `store_messages: false` (message_hash present, redacted_message null). |
| Kernel | `TelemetryAggregator::getHitRate()` computes correctly from seeded data. |
| Kernel | `TelemetryAggregator::getLatencyPercentiles()` returns p50/p95/p99 for seeded data. |
| Kernel | Cron hook deletes records older than retention period. |
| Kernel | Export controller returns valid JSON with correct aggregation structure. |
| Kernel | Export controller requires `administer ai agents canvas direct edit` permission. |

### Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| Database table grows too large on high-traffic sites | Low (demo site) | Low | Cron-based retention cleanup. Default 90 days. Index on `timestamp` for efficient cleanup. |
| Telemetry write adds latency to edit path | Low | Medium | DB insert is a single row write (~1ms). Wrap in try/catch so telemetry failure never blocks the edit response. |
| Backward compat with existing `telemetry_enabled` config key | Known | Low | Migration: read old `telemetry_enabled` bool, map to new `telemetry.enabled`. Support both in a transition period via `hook_update_N()`. |

---

## Recommended Execution Order

### Dependency Graph

```
Initiative 5 (Telemetry)     Initiative 1 (Canvas Lite)
        |                            |
        v                            v
Initiative 4 (Model Routing)  Initiative 2 (MCP Server)
        |
        v
Initiative 3 (Prompt Caching)
```

### Recommended Sequence

| Phase | Initiative | Rationale | Est. Effort |
|-------|-----------|-----------|-------------|
| **Phase 1** | 5. Telemetry | No dependencies. Provides measurement infrastructure for all other initiatives. Must be in place before optimizing. | 2-3 days |
| **Phase 1** | 1. Canvas Lite | No dependencies. Smallest scope. Immediate demo value -- Canvas works without API keys. | 1 day |
| **Phase 2** | 4. Model Routing | Benefits from telemetry data to tune thresholds. Requires matcher changes that should happen before MCP exposes the tools. | 2-3 days |
| **Phase 3** | 2. MCP Server | Benefits from Canvas Lite (works without keys) and Model Routing (confidence in responses). Largest scope. | 3-4 days |
| **Phase 4** | 3. Prompt Caching | Requires research into ai module internals. May need upstream patch. Blocked on P2 patch for full benefit. Lowest urgency -- optimization after the system is proven. | 2-3 days |

**Total estimated effort: 10-14 days**

### Cross-Initiative Dependencies

1. **Telemetry (I5) feeds Model Routing (I4):** Telemetry data validates the confidence scoring thresholds. Without telemetry, model routing thresholds are guesswork.
2. **Model Routing (I4) enriches Telemetry (I5):** Once model routing is in place, telemetry records which model was selected per edit, enabling cost analysis.
3. **Canvas Lite (I1) benefits MCP Server (I2):** If Canvas Lite is done first, the MCP server can serve useful read/deterministic-write operations even to users without AI keys.
4. **Prompt Caching (I3) depends on upstream research:** Task 3.1 may reveal that the current ai module abstraction layer makes cache_control injection impractical without a patch. This research should happen early even if implementation is Phase 4.

### Backward Compatibility Guarantee

All 5 initiatives MUST maintain backward compatibility with the existing 52 kernel tests. Specifically:
- `MatchResult` implements `ArrayAccess` so existing test assertions on `$result['prop']` continue to work
- New config keys have defaults that match current behavior (`telemetry.enabled: false` matches `telemetry_enabled: false`)
- New services are additive -- no existing service signatures change
- MCP server is a separate submodule that can be enabled/disabled independently
- Prompt caching subscriber is gated on config toggle (default: enabled, no-op for non-Anthropic)

---

## Open Questions

See `.omc/plans/open-questions.md` for tracked items.
