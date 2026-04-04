# WP16: Prompt Caching Research Findings

**Date:** 2026-03-31
**ai module version:** 1.3.0-rc2
**ai_provider_anthropic:** installed (extends OpenAiBasedProviderClientBase)

---

## 1. Provider Architecture

`AnthropicProvider` extends `OpenAiBasedProviderClientBase` and does NOT override `chat()`. All chat handling is in the base class, which uses an **OpenAI-compatible SDK client** (`$this->client->chat()->create($payload)`).

The Anthropic endpoint is `https://api.anthropic.com/v1` — Anthropic's API has an OpenAI-compatible layer, but it does NOT support all native Anthropic features (including `cache_control`).

## 2. How System Prompts Are Built

`OpenAiBasedProviderClientBase::chat()` (line 251-255):
```php
if ($this->chatSystemRole) {
    $chat_input[] = [
        'role' => 'system',
        'content' => $this->chatSystemRole,  // Plain string
    ];
}
```

The system prompt is a **flat string** in `{role: 'system', content: string}` format. Anthropic prompt caching requires **structured content blocks**:
```json
{
  "role": "system",
  "content": [{
    "type": "text",
    "text": "...",
    "cache_control": {"type": "ephemeral"}
  }]
}
```

**This format is incompatible** — the base class sends a string where an array-of-objects is needed.

## 3. Configuration Passthrough

Line 300-303 of the base class:
```php
$payload = [
    'model' => $model_id,
    'messages' => $chat_input,
] + $this->configuration;
```

`$this->configuration` IS spread into the payload. And `PreGenerateResponseEvent` has `setConfiguration(array $configuration)` (line 158 of the event). So an event subscriber CAN inject arbitrary top-level keys into the API payload.

**However:** `cache_control` is not a top-level payload key — it's nested inside message content blocks. Configuration passthrough doesn't help for the system prompt cache_control.

## 4. Event System Capabilities

### PreGenerateResponseEvent
- `setConfiguration()` — can modify top-level config keys
- `setAuthentication()` — can change auth
- `setForcedOutputObject()` — can short-circuit with cached response
- `getInput()` — returns `ChatInput` object (not the raw messages array)
- **NO** `setInput()` or `setMessages()` — can't modify the messages array
- **NO** `setModelId()` — model is read-only

### PostGenerateResponseEvent
- Extends `AiProviderResponseBaseEvent`
- Returns `OutputInterface` (the response)
- **No access to raw HTTP response headers** — cache hit/miss metrics from `x-anthropic-cache-*` headers are NOT accessible

## 5. Anthropic Prompt Caching Status

As of early 2026, prompt caching is GA for the Anthropic Messages API (the `anthropic-beta: prompt-caching-2024-07-31` header is no longer required). However, it requires:
- Using the **native Anthropic Messages API**, not the OpenAI-compatible endpoint
- System prompt as structured content blocks with `cache_control` markers
- The `anthropic-version` header (which the SDK handles)

## 6. Blocking Issues

### Issue A: OpenAI-compatible SDK (BLOCKING)
The Anthropic provider uses the OpenAI PHP SDK client. The OpenAI compatibility endpoint does NOT support `cache_control`. Prompt caching requires the **native Anthropic Messages API** with its own client.

### Issue B: System prompt as plain string (BLOCKING)
The base class builds system prompt as `{content: string}`. Cache_control needs `{content: [{type: text, text: ..., cache_control: ...}]}`. No way to inject this via events.

### Issue C: No input modification on event (BLOCKING)
`PreGenerateResponseEvent` has no setter for the messages array or ChatInput. Even if we could build the right format, we can't inject it.

### Issue D: No response header access (LIMITS TELEMETRY)
`PostGenerateResponseEvent` returns only the parsed output, not raw HTTP headers. Cache hit/miss metrics require `x-anthropic-cache-creation-input-tokens` and `x-anthropic-cache-read-input-tokens` headers.

## 7. Recommended Approach

### Option A: Patch ai_provider_anthropic (RECOMMENDED)
**Effort: Medium-High. Upstream-friendly.**

1. Add an Anthropic-native PHP client alongside the OpenAI SDK client
2. Override `chat()` in `AnthropicProvider` to use the native Messages API
3. Add `cache_control` support when configuration flag is set
4. Expose response headers for telemetry
5. Contribute as a patch to the `ai_provider_anthropic` project

**Pros:** Clean, upstream-contributable, enables all features
**Cons:** Larger patch, needs maintainer buy-in

### Option B: Provider decorator (ALTERNATIVE)
**Effort: Medium. Local only.**

1. Create a decorator provider that wraps `AnthropicProvider`
2. Intercept `chat()` calls, rebuild the payload with native Anthropic API format
3. Use a direct HTTP client to call the Messages API with cache_control
4. Parse response headers for cache metrics

**Pros:** No patches needed, self-contained
**Cons:** Fragile (wrapping internals), not upstream-friendly

### Option C: Defer to ai module 2.0 (CONSERVATIVE)
**Effort: None now.**

1. Wait for the ai module to add native provider support (not OpenAI-only)
2. Track upstream issues for cache_control support
3. Focus on other initiatives first

**Pros:** Zero effort, clean eventual solution
**Cons:** Unknown timeline, may never happen

### Recommendation
**Start with Option C (defer), but file an upstream issue on ai_provider_anthropic requesting native API support with cache_control.** The current architecture makes prompt caching impractical without significant patching. The ROI calculation changes once the other initiatives are done:

- With 80% hit rate (post-alias-index), only 20% of edits hit AI
- Those 20% cost ~$0.15-0.50/edit with Sonnet
- Prompt caching would save 50-90% on those → ~$0.02-0.10/edit
- Net savings: small in absolute terms for a demo site

**Bottom line:** Prompt caching is architecturally blocked by the OpenAI SDK abstraction in ai_provider_anthropic. File upstream, revisit when native Anthropic support lands.

## 8. Impact on WP17/WP18

- **WP17 (CanvasPromptCacheSubscriber):** ON HOLD. No viable injection point in current architecture.
- **WP18 (Provider extension + telemetry):** ON HOLD. Requires Option A (patch) to proceed.
- **Open question resolved:** ai module cache_control passthrough → answer is NO for system prompt content blocks, YES for top-level config keys (but cache_control isn't top-level).
