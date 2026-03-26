---
name: ai-observability-setup
description: Enable and configure the contrib ai_observability module for tracking AI token usage, prompts, and responses
triggers:
  - enable observability
  - track AI tokens
  - AI logging
  - ai observability
---

# AI Observability Setup

Enables and configures the contrib `ai_observability` module (part of `drupal/ai`) for tracking all AI API calls, token usage, prompts, and responses.

## What It Does

The `ai_observability` module subscribes to the AI module's Symfony events and logs:
- Provider, model, and operation type for every AI call
- Token usage (input/output/total) from the API response
- Request duration and thread IDs (for tracing agent chains)
- Optionally: full input prompts and output responses
- OpenTelemetry spans and metrics (optional, for production)

## When to Use

- Auditing what Canvas AI agents are sending to Anthropic/OpenAI
- Tracking token costs per agent or per page build
- Debugging agent behavior by inspecting full prompts
- Setting up production monitoring with OpenTelemetry

## Steps

### 1. Enable the module

```bash
ddev drush en ai_observability -y
```

### 2. Configure for audit mode (full logging)

```bash
ddev drush config:set ai_observability.settings log_input true -y
ddev drush config:set ai_observability.settings log_output true -y
ddev drush config:set ai_observability.settings logging_enabled true -y
```

### 3. View logs

```bash
# Watch AI events in real time
ddev drush watchdog:show --type=ai_observability --count=20

# Filter by severity
ddev drush watchdog:show --type=ai_observability --severity=info --count=50
```

### 4. Export config for the recipe

After enabling and configuring:
```bash
ddev drush config:export --destination=/tmp/config-check
cp /tmp/config-check/ai_observability.settings.yml custom_recipes/findrop/config/
```

Then add `ai_observability` to the findrop recipe's install list in `custom_recipes/findrop/recipe.yml`.

### 5. Recommended settings by environment

**Development/Audit** (full visibility):
```yaml
logging_enabled: true
log_input: true
log_output: true
log_tags: {}
otel_enabled: false
```

**Demo** (lightweight):
```yaml
logging_enabled: true
log_input: false
log_output: false
log_tags: {}
otel_enabled: false
```

**Production** (OpenTelemetry):
```yaml
logging_enabled: false
otel_enabled: true
otel_spans: true
otel_spans_store_input: false
otel_spans_store_output: false
otel_metrics: true
```

## What the module logs

Each AI API call produces a log entry with:
- `provider` — anthropic, openai, etc.
- `model` — claude-sonnet-4-6, text-embedding-3-small, etc.
- `operation_type` — chat, chat_with_tools, embeddings, etc.
- `token_usage.total` — total tokens consumed
- `token_usage.input` — input/prompt tokens
- `token_usage.output` — completion tokens
- `provider_request_id` — unique request thread ID
- `provider_request_parent_id` — parent request (for tracing nested agent calls)
- `input` — full prompt text (when log_input is true)
- `output` — full response text (when log_output is true)
- `tags` — contextual tags from the calling code

## Relation to other logging

- `ai.settings.prompt_logging` — The AI module's own prompt logging. Less structured. Prefer `ai_observability`.
- `ai_dashboard` — Operational status block, doesn't log individual calls.

## Admin UI

Settings form at: `/admin/config/ai/observability`
