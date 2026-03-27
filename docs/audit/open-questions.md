# Open Questions

## findrop-audit-infrastructure - 2026-03-26

- [ ] LiteLLM Anthropic version header passthrough — Does LiteLLM correctly forward the `anthropic-version: 2024-02-29` header that the Drupal Anthropic provider sends? Needs testing after setup. If not, may need a custom header mapping in litellm_config.yaml.

- [ ] LiteLLM authentication mode — When Drupal sends API keys in request headers (Bearer token for OpenAI, X-API-Key for Anthropic), does LiteLLM use those keys or its own configured keys? Need to verify whether `api_key` in litellm_config.yaml takes precedence. This affects whether Drupal needs real keys or can use dummy values when proxy is enabled.

- [ ] Memory budget on target machine — The full stack (MariaDB + Milvus + etcd + MinIO + Attu + LiteLLM + PHP/nginx) is estimated at 2.5-3.5GB. What is the available RAM on the target development machine? If under 16GB, may need to add `mem_limit` constraints or skip Attu.

- [ ] OpenAiHelper URL construction discrepancy — The `OpenAiHelper` class builds URLs as `'https://' . $host . '/chat/completions'` while `loadClient()` uses `setEndpoint($host)` directly. When `host` is set to `http://litellm:4000/v1`, the helper would construct `https://http://litellm:4000/v1/chat/completions` (broken). This only affects admin form validation, not runtime AI calls. Confirm this is acceptable or if the helper path needs a workaround.

- [ ] `settings.local.php` inclusion after `drush si` — Drupal's site install creates a fresh `settings.php`. Need to confirm whether DDEV's generated `settings.ddev.php` already includes `settings.local.php`, or if the demo-setup script needs modification to inject the include. This is a prerequisite for Task 2.

- [ ] Canvas page IDs for Playwright testing — The front page is set to `/page/10` in the recipe config. After `ddev demo-setup`, are there Canvas pages with predictable IDs that Playwright can navigate to? Or does the test need to create a page first (as the existing Playwright tests do)?

- [ ] LiteLLM image size and pull time — The `ghcr.io/berriai/litellm:main-latest` image may be large. Should we pin to a specific version tag for reproducibility? Need to check image size and whether a lighter variant exists.
