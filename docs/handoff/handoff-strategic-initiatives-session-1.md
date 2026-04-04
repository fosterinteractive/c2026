# Handoff: Strategic Initiatives — Session 1

**Date:** 2026-03-31
**Branch:** `feat/strategic-initiatives` (9 commits, from `feat/show-and-prove-session-2`)
**Module:** `web/modules/custom/ai_agents_canvas_direct_edit/`

---

## What Was Accomplished

### Spec-Kitty Planning
- Wrote spec for 5 strategic initiatives at `kitty-specs/strategic-initiatives/spec.md`
- drupal-planner (opus) produced full technical plan at `.omc/plans/strategic-initiatives.md`
- 6 open questions tracked at `.omc/plans/open-questions.md`
- 20 work packages created in `kitty-specs/strategic-initiatives/wp/WP01-WP20.md`

### 9 Commits

| Commit | WPs | What |
|--------|-----|------|
| `df66dea` | — | Spec + 18 work packages |
| `91645d3` | WP19-20 | d.o. contrib publishing WPs |
| `ddd229c` | WP01+02+07 | Telemetry DB schema, TelemetryEvent DTO, AiProviderAvailabilityChecker |
| `59907b0` | WP16 | Prompt caching research (blocked by OpenAI SDK abstraction) |
| `ef0ca83` | WP03+08+09 | TelemetryCollector, Canvas Lite 503 response, MatchResult VO |
| `f5c3f49` | WP04+05 | Controller telemetry wiring, TelemetryAggregator |
| `d65048a` | WP10 | MatchResult + confidence scoring integrated into DirectEditMatcher |
| `a4b57e7` | WP06+11 | Telemetry export/cron/config, ComplexityModelRouter |

### New Files Created (16)
- `src/Telemetry/TelemetryEvent.php` — Immutable DTO with builder pattern
- `src/Telemetry/Builder.php` — Fluent builder for TelemetryEvent
- `src/Telemetry/TelemetryCollector.php` — Exception-safe DB writer
- `src/Telemetry/TelemetryCollectorInterface.php`
- `src/Telemetry/TelemetryAggregator.php` — Hit rate, tier distribution, latency percentiles
- `src/Telemetry/TelemetryAggregatorInterface.php`
- `src/Controller/TelemetryExportController.php` — JSON export at /admin/reports/canvas-direct-edit/telemetry
- `src/Service/AiProviderAvailabilityChecker.php` — Checks if AI chat provider is configured
- `src/Service/AiProviderAvailabilityCheckerInterface.php`
- `src/Service/MatchResult.php` — Immutable VO with ArrayAccess backward compat, confidence scoring
- `src/Service/ComplexityModelRouter.php` — Maps complexity signals to model IDs
- `src/Service/ComplexityModelRouterInterface.php`
- `ai_agents_canvas_direct_edit.module` — hook_cron() for telemetry retention
- `config/install/ai_agents_canvas_direct_edit.settings.yml` — Nested telemetry + model_routing config
- `config/schema/ai_agents_canvas_direct_edit.schema.yml` — Full config schema

### Modified Files (7)
- `ai_agents_canvas_direct_edit.install` — Added hook_schema() for telemetry table
- `ai_agents_canvas_direct_edit.services.yml` — 4 new services registered
- `ai_agents_canvas_direct_edit.routing.yml` — Added telemetry export route
- `src/Controller/DirectEditController.php` — Canvas Lite 503 + telemetry wiring
- `src/Plugin/tool/Tool/MatchDirectEdit.php` — ai_available field in no-match
- `src/Service/DirectEditMatcher.php` — Returns MatchResult with confidence scoring
- `src/Service/AiProviderAvailabilityChecker.php` — Nullable AI provider (optional dep)

### Key Research Finding (WP16)
**Prompt caching is architecturally blocked.** The ai_provider_anthropic module uses an OpenAI-compatible SDK that doesn't support Anthropic's native `cache_control` on system prompts. Full findings at `.omc/plans/prompt-caching-research.md`. Recommendation: file upstream issue, defer WP17/WP18.

---

## What Remains

### Immediate: Run Tests
Tests need re-running in DDEV after the WP04/WP06/WP10/WP11 changes. WP08 agent confirmed 59 tests passing before those changes, but the matcher return type change (WP10) and config namespace migration (WP06) may cause failures that need fixing.

```bash
ddev exec php vendor/bin/phpunit -c web/core/phpunit.xml web/modules/custom/ai_agents_canvas_direct_edit/tests
```

### WP12: ModelRoutingSubscriber (HIGH complexity)
- EventSubscriber on `ai.pre_generate_response`
- Reads complexity signal, re-routes to Haiku/Sonnet
- Key constraint: `modelId` is read-only on event — must use `setForcedOutputObject()` pattern
- This may require a research spike first to verify the re-route pattern works

### WP13-15: MCP Server (3-4 days)
- WP13: Submodule scaffold (`ai_agents_canvas_direct_edit_mcp`)
- WP14: McpToolBridge (adapts Tool plugins to MCP tool schemas)
- WP15: JSON-RPC handler + controller + auth (HIGH complexity)

### WP20: Decouple from FinDrop
- Config namespace fully to `ai_agents_canvas_direct_edit.settings` (partially done — telemetry migrated, but TelemetryCollector still reads `ai_agents_canvas_direct_edit.settings` which is correct now)
- Audit for hardcoded FinDrop references
- Generic default config (demo-specific aliases move to recipe)

### WP19: Publish to drupal.org
- Create d.o. project page
- Module descriptions must use Zivtech writing style (`/zivtech-writing-style` skill)
- README, composer.json packaging
- Initial alpha/beta release

### WP17-18: ON HOLD
Prompt caching blocked. File upstream issue on `ai_provider_anthropic` for native Anthropic API support with `cache_control`. Revisit when upstream moves.

---

## Decisions Made This Session

1. **Execution order:** Telemetry+Canvas Lite (Phase 1) → Model Routing (Phase 2) → MCP Server (Phase 3) → Prompt Caching (Phase 4, blocked)
2. **Prompt caching deferred** — OpenAI SDK abstraction makes it impractical without patching ai_provider_anthropic
3. **d.o. publishing is a goal** — User has contributor account, modules must be decoupled from FinDrop
4. **Module descriptions use Zivtech writing style** — saved as memory
5. **Tests via Codex preferred** — but codex-implementer/tester agents hit Bash permission issues in this session. Use `ddev exec phpunit` directly as fallback.
6. **AI provider injection is optional** (`@?ai.provider`) — module works without the ai module for deterministic-only mode

---

## Environment State

- Branch: `feat/strategic-initiatives` (9 commits ahead of `feat/show-and-prove-session-2`)
- DDEV running at https://c2026.ddev.site
- Config files updated with nested telemetry + model_routing structure
- Tests need re-running (last confirmed: 59 pass at WP08 stage)
- No PR created yet for this branch

---

## Resources

| Resource | Location |
|----------|----------|
| Spec | `kitty-specs/strategic-initiatives/spec.md` |
| Full plan | `.omc/plans/strategic-initiatives.md` |
| Open questions | `.omc/plans/open-questions.md` |
| Prompt caching research | `.omc/plans/prompt-caching-research.md` |
| Work packages | `kitty-specs/strategic-initiatives/wp/WP01-WP20.md` |
| Previous handoff | `docs/handoff/handoff-show-and-prove-session-2.md` |
