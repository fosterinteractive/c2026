# Spec: Canvas Direct-Edit Strategic Initiatives

**Feature:** 5 strategic initiatives to evolve the Canvas direct-edit system from demo to production-grade contribution
**Branch:** `feat/strategic-initiatives` (from `feat/show-and-prove-session-2`)
**Module:** `web/modules/custom/ai_agents_canvas_direct_edit/`

## Context

The Canvas direct-edit system has a working 5-tier semantic matcher that resolves 60% of edits deterministically (38ms, 0 tokens) vs the AI path (16.4s, thousands of tokens). The module provides 8 Tool API plugins, an HTTP bridge controller, and 52 kernel tests.

### Existing Architecture
- **DirectEditMatcher** — 5-tier matching: exact prop name → semantic alias → enum value → relative adjustment → bare value
- **ComponentSchemaLoader** — Reads Byte theme SDC YAML schemas, builds prop/alias/enum maps, caches
- **DirectEditController** — HTTP bridge at POST `/admin/api/canvas/direct-edit`
- **8 Tool plugins** — GetPageLayout, GetComponentCatalog, GetComponentSchema, GetComponentProps, MatchDirectEdit, UpdateComponentProps, AddComponent, MoveComponent
- **Config** — `canvas_ai_scoping.settings` with telemetry toggle, edit verbs, enum aliases
- **Tests** — 52 kernel tests, 216 assertions

### Measured Performance
| Path | Mean | N | Cost |
|------|------|---|------|
| Direct-edit | 38ms | 10 | $0.00 |
| AI path | 16,358ms | 5 | ~$0.15-0.50/edit |
| Full page build | — | — | ~$6-15 |

## Initiative 1: Canvas Lite (API-Key-Free Mode)

### Problem
Canvas currently requires AI API keys to function. 60-70% of edits are simple prop changes that don't need AI. Sites without API keys configured should still be able to edit components deterministically.

### Requirements
- Canvas edit UI works without any AI API key configured
- Deterministic edits resolve normally via DirectEditMatcher
- When a non-deterministic edit is attempted and no AI key exists, show a clear message: "This edit requires AI. Configure an API key to enable AI-powered editing."
- When AI keys ARE configured, behavior is unchanged (deterministic-first, AI fallback)
- No new module dependencies beyond what exists

### Acceptance Criteria
- [ ] Site with zero API keys: simple edits work, complex edits show helpful message
- [ ] Site with API keys: unchanged behavior (deterministic-first, AI fallback)
- [ ] No JavaScript changes required (server-side routing only)
- [ ] Degradation is graceful, never an unhandled error

## Initiative 2: Canvas MCP Server

### Problem
AI edits cost $3-15/MTok via server-side API keys. Users with Claude Desktop Pro ($20/mo) or ChatGPT Plus ($20/mo) have effectively unlimited tokens. An MCP server would let desktop AI tools edit Canvas pages using the user's subscription instead of site API keys.

### Requirements
- MCP server exposes the 8 existing Tool plugins as MCP tools
- Desktop Claude/ChatGPT can discover and invoke Canvas edit operations
- Authentication via Drupal session cookie or API token
- Read operations (layout, catalog, schema, props) are safe for any authenticated user
- Write operations (update props, add/move component) require appropriate permissions
- Server runs as a Drupal module endpoint, not a standalone process

### Acceptance Criteria
- [ ] MCP tool discovery returns all 8 tools with schemas
- [ ] Desktop Claude can read page layout and component props
- [ ] Desktop Claude can update a component prop via MCP
- [ ] Permission checks enforced on write operations
- [ ] Works with Claude Desktop MCP configuration

## Initiative 3: Prompt Caching Integration

### Problem
The AI agent loop sends redundant system prompts on every iteration. After loop 0, the system prompt is stable (P2 patch, drupal.org #3582288). Anthropic prompt caching could cache the stable prefix, cutting per-call cost by up to 90%.

### Requirements
- Detect when the Anthropic provider is in use
- Set cache control breakpoints on stable system prompt sections
- Measure cache hit rate and cost reduction
- No behavioral changes — only cost optimization
- Works with the existing `ai` module's provider abstraction

### Acceptance Criteria
- [ ] Cache breakpoints set on system prompt after loop 0
- [ ] Measurable cost reduction (target: 50-90% on cached calls)
- [ ] No impact on AI response quality
- [ ] Telemetry logs cache hit/miss rates
- [ ] Graceful no-op when non-Anthropic provider is used

## Initiative 4: Model Routing by Complexity

### Problem
All AI edits currently use the same model (typically Sonnet). Simple edits that need AI (e.g., "make the heading more engaging") could use Haiku (faster, cheaper) while complex operations (multi-component layout changes) need Sonnet or Opus.

### Requirements
- DirectEditMatcher returns a confidence score (0-1) alongside match results
- When match fails (AI fallback needed), the confidence of the nearest-miss informs model selection
- Low-complexity AI edits → Haiku (fast, cheap)
- High-complexity AI edits → Sonnet (capable)
- Model routing is configurable via Drupal config
- Complexity thresholds are tunable

### Acceptance Criteria
- [ ] Matcher returns confidence metadata on both match and miss
- [ ] Model router selects appropriate model based on complexity signal
- [ ] Config schema for complexity thresholds and model mapping
- [ ] Telemetry logs model selection decisions
- [ ] Simple AI edits measurably faster/cheaper with Haiku

## Initiative 5: Real-World Telemetry

### Problem
Hit rate (60%) and performance (38ms) are measured in benchmarks with synthetic edits. Need real-world validation from actual demo site usage to guide optimization priorities.

### Requirements
- Extend existing telemetry config (`canvas_ai_scoping.settings.telemetry_enabled`)
- Log every edit attempt: message, component, match result, tier, latency, model used
- Aggregate dashboard: hit rate, tier distribution, latency percentiles, AI fallback rate
- Privacy-safe: no PII, configurable redaction of message content
- Target: collect 100+ edits from demo site usage

### Acceptance Criteria
- [ ] Every edit logged with structured data (tier, latency, match/miss, model)
- [ ] Aggregation query/view available for analysis
- [ ] Message content redaction configurable
- [ ] No performance impact when telemetry disabled
- [ ] Export capability for offline analysis

## Dependencies Between Initiatives

```
Initiative 5 (Telemetry) ← no deps, can start immediately
Initiative 1 (Canvas Lite) ← no deps, can start immediately
Initiative 4 (Model Routing) ← benefits from Telemetry data but not blocked
Initiative 3 (Prompt Caching) ← requires P2 patch merged upstream
Initiative 2 (MCP Server) ← benefits from Canvas Lite but not blocked
```

## Constraints
- Drupal 11.3, PHP 8.3
- Must work with existing `ai`, `ai_agents`, `tool`, `canvas`, `canvas_ai` modules
- No changes to contrib modules (patches only if unavoidable)
- Must maintain backward compatibility with existing 52 kernel tests
- Config exportable via `drush cex`
