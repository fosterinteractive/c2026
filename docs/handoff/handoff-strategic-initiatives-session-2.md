# Handoff: Strategic Initiatives — Session 2

**Date:** 2026-03-31
**Branch:** `feat/strategic-initiatives` (12 commits)
**Module:** `web/modules/custom/ai_agents_canvas_direct_edit/`

---

## What Was Accomplished

### 2 Commits This Session

| Commit | WPs | What |
|--------|-----|------|
| `920f702` | — | Fix controller tests: 9th constructor arg (TelemetryCollectorInterface), telemetry logger expectations (exactly(2) → once()) |
| `8e94aaf` | WP12+13+14+15 | Complexity metadata in 422/503 + full MCP server submodule |

### WP12: Complexity Metadata (Model Routing)

**Research finding:** ModelRoutingSubscriber is architecturally blocked. `PreGenerateResponseEvent` has `getModelId()` but no `setModelId()`. `ProviderProxy` never re-reads modelId from the event after dispatch. `setForcedOutputObject()` bypasses the AI call entirely (guardrails pattern). Recommend filing upstream issue on `ai` module.

**What was implemented (simpler path):**
- `DirectEditMatcher::match()` return type changed from `?MatchResult` to `MatchResult` — always returns a result, never NULL
- Wired the previously dead `$noMatchConfidence` variable into `MatchResult::noMatch()`
- Controller 422 and 503 responses now include `complexity_signal` and `confidence`
- `MatchDirectEdit` tool plugin no_match response includes `complexity_signal` and `confidence`
- WP12 spec updated with research findings and deferred status for EventSubscriber

### WP13-15: MCP Server Submodule

Full implementation in `modules/ai_agents_canvas_direct_edit_mcp/`:

**WP13 — Scaffold:**
- `ai_agents_canvas_direct_edit_mcp.info.yml` — depends on parent + tool module
- `ai_agents_canvas_direct_edit_mcp.routing.yml` — POST `/api/mcp/canvas`
- `ai_agents_canvas_direct_edit_mcp.services.yml` — McpToolBridge + McpRequestHandler
- `ai_agents_canvas_direct_edit_mcp.permissions.yml` — `access canvas mcp server`
- `config/install/*.settings.yml` — enabled: true, allowed_origins: [], session_ttl: 3600
- `config/schema/*.schema.yml` — full config schema

**WP14 — McpToolBridge:**
- `listTools()` — filters `ToolManager::getDefinitions()` by `ai_agents_canvas_direct_edit:` prefix, converts InputDefinition to JSON Schema
- `executeTool()` — loads plugin, checks access, executes, returns MCP-format result
- `buildInputSchema()` — maps Drupal typed data types to JSON Schema types

**WP15 — JSON-RPC Handler + Controller:**
- `McpRequestHandler` — JSON-RPC 2.0 (initialize, tools/list, tools/call), protocol version 2025-03-26
- `McpServerController` — HTTP endpoint, validates Content-Type, 503 when disabled, CORS from config, Mcp-Session-Id header passthrough
- Auth via Drupal permission system (`_permission: 'access canvas mcp server'`)

### Test Fix

- `DirectEditControllerTest::createController()` was passing 8 args, constructor expects 9 (TelemetryCollectorInterface added in WP04)
- Two telemetry tests expected `info()` called twice — actual implementation uses `TelemetryCollector::record()` for data persistence, logger only for timing

### Test Results

**59 tests, 0 errors, 0 failures** (2040 PHPUnit deprecations from Drupal core, not ours)

---

## What Remains

### WP20: Decouple from FinDrop
- Audit for hardcoded FinDrop references
- Generic default config (demo-specific aliases move to recipe)
- Ensure module works standalone without FinDrop recipe

### WP19: Publish to drupal.org
- Create d.o. project page
- Module descriptions must use Zivtech writing style (`/zivtech-writing-style` skill)
- README, composer.json packaging
- Initial alpha/beta release

### WP17-18: ON HOLD
Prompt caching blocked by OpenAI SDK abstraction in ai_provider_anthropic. File upstream issue. Revisit when upstream moves.

### MCP Submodule Needs Testing
- WP13-15 implementation is complete but has no tests yet
- Should add kernel tests for McpToolBridge (listTools, executeTool, access checks)
- Should add kernel tests for McpRequestHandler (JSON-RPC protocol compliance)
- Module enable/disable test via DDEV

---

## Decisions Made This Session

1. **EventSubscriber deferred** — modelId read-only on PreGenerateResponseEvent, no upstream setModelId()
2. **Complexity metadata in responses** — simpler path: expose in 422/503 so downstream consumers decide model
3. **match() always returns MatchResult** — eliminated NULL return, wired dead noMatchConfidence code
4. **Full MCP implementation in one pass** — WP13+14+15 done together since they're tightly coupled
5. **Codex routing failed** — codex-tester/codex-implementer hit Bash permission issues, fell back to direct execution

---

## Environment State

- Branch: `feat/strategic-initiatives` (12 commits)
- DDEV running at https://c2026.ddev.site
- 59 tests passing
- No PR created yet
- Dirty working tree has unrelated modifications (CLAUDE.md, patches, recipes) — pre-existing

---

## WP Completion Summary

| WP | Status | Session |
|----|--------|---------|
| WP01-06 | Done | Session 1 |
| WP07-08 | Done | Session 1 |
| WP09-11 | Done | Session 1 |
| WP12 | Done (subscriber deferred) | Session 2 |
| WP13-15 | Done (needs tests) | Session 2 |
| WP16 | Blocked (research done) | Session 1 |
| WP17-18 | On hold | — |
| WP19 | Remaining | — |
| WP20 | Remaining | — |
