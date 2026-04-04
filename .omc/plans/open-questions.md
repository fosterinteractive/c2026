# Open Questions

## findrop-audit-infrastructure - 2026-03-26

- [ ] LiteLLM Anthropic version header passthrough — Does LiteLLM correctly forward the `anthropic-version: 2024-02-29` header that the Drupal Anthropic provider sends? Needs testing after setup. If not, may need a custom header mapping in litellm_config.yaml.

- [ ] LiteLLM authentication mode — When Drupal sends API keys in request headers (Bearer token for OpenAI, X-API-Key for Anthropic), does LiteLLM use those keys or its own configured keys? Need to verify whether `api_key` in litellm_config.yaml takes precedence. This affects whether Drupal needs real keys or can use dummy values when proxy is enabled.

- [ ] Memory budget on target machine — The full stack (MariaDB + Milvus + etcd + MinIO + Attu + LiteLLM + PHP/nginx) is estimated at 2.5-3.5GB. What is the available RAM on the target development machine? If under 16GB, may need to add `mem_limit` constraints or skip Attu.

- [ ] OpenAiHelper URL construction discrepancy — The `OpenAiHelper` class builds URLs as `'https://' . $host . '/chat/completions'` while `loadClient()` uses `setEndpoint($host)` directly. When `host` is set to `http://litellm:4000/v1`, the helper would construct `https://http://litellm:4000/v1/chat/completions` (broken). This only affects admin form validation, not runtime AI calls. Confirm this is acceptable or if the helper path needs a workaround.

- [ ] `settings.local.php` inclusion after `drush si` — Drupal's site install creates a fresh `settings.php`. Need to confirm whether DDEV's generated `settings.ddev.php` already includes `settings.local.php`, or if the demo-setup script needs modification to inject the include. This is a prerequisite for Task 2.

- [ ] Canvas page IDs for Playwright testing — The front page is set to `/page/10` in the recipe config. After `ddev demo-setup`, are there Canvas pages with predictable IDs that Playwright can navigate to? Or does the test need to create a page first (as the existing Playwright tests do)?

- [ ] LiteLLM image size and pull time — The `ghcr.io/berriai/litellm:main-latest` image may be large. Should we pin to a specific version tag for reproducibility? Need to check image size and whether a lighter variant exists.

## mcp-tool-api-architecture - 2026-03-30 (RESOLVED 2026-03-30)

- [x] Health/maintenance status of `drupal/mcp_server` — **CAUTION**: Dev only, no tagged release. Use `drupal/mcp` 1.2.x (security-covered) until `mcp_server` reaches stable.
- [x] Health/maintenance status of `drupal/mcp_tools` — **AVOID**: Explicitly no security coverage. Single-company maintainer. Dev tooling, not production.
- [x] Does `drupal/tool` #3575927 define a stable plugin interface? — **Yes, alpha-10.** The `#[Tool]` attribute + `ConditionToolBase` are the stable-enough interface. Pin version, test upgrades. The experimental collection already requires `drupal/tool: ^1.0`.
- [x] Permission model for Phase 2 read tools — **Deferred** to Phase 2 design. Likely separate lower-privilege permission.
- [x] MCP auth model in `mcp_server` — **`drupal/mcp` 1.2.x has native OAuth 2.1.** Skip `simple_oauth_21` entirely. STDIO uses Drupal user context via Drush `--uid`.
- [x] `#[FunctionCall]` vs `#[Tool]` for experimental collection? — **`#[Tool]`**. Collection convention (31/31 submodules). Maintainer consensus supports matching convention. Keep `#[FunctionCall]` wrapper for Path B (canvas_ai).

## strategic-initiatives - 2026-03-31

- [ ] ai module cache_control passthrough — Does `OpenAiBasedProviderClientBase::chat()` pass arbitrary keys from the `configuration` array into the API payload, or only known keys? If only known keys, prompt caching (Initiative 3) requires a patch to `ai_provider_anthropic`. Research task 3.1 must answer this before implementation begins.
- [ ] modelId setter on PreGenerateResponseEvent — The `modelId` property on `AiProviderRequestBaseEvent` has no setter method (read-only after construction). Model routing (Initiative 4) cannot change the model via the event system. Options: (a) use `setForcedOutputObject()` to re-route, (b) propose `setModelId()` upstream for ai module 2.0, (c) route at agent configuration level before the event fires. Decision needed before Initiative 4 task 4.5.
- [ ] MCP Streamable HTTP transport vs SSE-only — The MCP spec (2025-03-26) defines Streamable HTTP as the recommended transport. Should the MCP server (Initiative 2) implement the full Streamable HTTP spec or start with a simpler SSE-only transport? Streamable HTTP is more complex but future-proof.
- [ ] Telemetry table vs Drupal logger backend — Initiative 5 proposes a custom database table for telemetry. Alternative: use a structured logging backend (e.g., monolog with JSON formatter) that can be queried externally. Custom table gives better aggregation queries but adds schema maintenance. Decision affects task 5.1.
- [ ] Canvas Lite frontend behavior on 503 — When DirectEditController returns 503 (no AI provider), does the Canvas frontend JS handle non-422 responses gracefully, or will it show an unhandled error? May need a JS patch to Canvas contrib. Affects Initiative 1 risk profile.
- [ ] MCP authentication: session cookie vs API token — For MCP server (Initiative 2), session cookie auth requires the user to be logged into Drupal. API token auth requires a token management UI. Which is MVP? Decision affects task 2.5 scope.
