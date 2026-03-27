# Handoff: Next Session Priorities

**Date:** 2026-03-26
**Branch:** `feat/add-claude-md` (PR fosterinteractive/c2026#1)
**Site:** Running at https://c2026.ddev.site via DDEV

## What Was Accomplished

1. **Static audit** of all 12 Canvas AI agents — report at `.omc/plans/canvas-agent-static-audit.md`
2. **6 critical fixes** committed: XSS in JSON-LD, hardcoded creds/dates in GA plugin, context gaps for title/metadata/SEO agents, orchestrator rule numbering, page builder retry ceiling
3. **25 test scenarios remapped** to use actual agent/tool IDs
4. **CLAUDE.md** created for project onboarding
5. **ai_observability** contrib module enabled with full input/output logging
6. **3 Claude Code skills** created: canvas-ai-audit, ai-observability-setup, canvas-webapp-testing
7. **Driesnote demo script** (steps 01-05) validated via Playwright — page build, FAQ creation, Schema.org all working

## What's Blocked on OpenAI Key

- `ddev drush sapi-i` — content indexing in Milvus (embeddings)
- Demo step 02 — hero image swap via RAG media search
- Demo step 04 — internal cross-linking via page search
- Steps 06-08 — GA diagnosis, competitor catch, publish review (untested)

## Next Session Priorities

### 1. Agent Efficiency Optimization (HIGH)
The agents are too verbose and wasteful:
- Page builder loads full layout + component catalog on every loop (30 max loops)
- Template builder does verbose planning before acting
- Context injection adds 10-12K tokens per page builder call
- System prompts are oversized (orchestrator: 4,500 tokens with 24 examples)

Actions:
- Trim orchestrator examples (24 → 10-12, remove duplicative ones)
- Consider `return_directly: 1` on title/metadata sub-agents
- Reduce context injection: strip Sales Training Deck from page builders (competitor name risk + 2,500 wasted tokens)
- Evaluate lazy-loading for `default_information_tools`

### 2. OpenAI Key + Embeddings (MEDIUM)
- Set `OPENAI_API_KEY` in `.ddev/.env`
- Run `ddev drush sapi-i` to index content
- Re-run demo steps 02, 04, 06-08 with full capabilities

### 3. Stable Canvas Release Path (HIGH)
- Audit which patches have been upstreamed to Canvas contrib
- Identify patches that can be dropped vs. those still needed
- Move from `1.x-dev#commit` to a stable release when available
- Goal: eliminate `creating_patch_for_canvas/` workflow

### 4. Deployment Recipes (MEDIUM)
- **amazee.io recipe**: AI provider configured for amazee.io LLM endpoint (already has `ai_provider_amazeeio` config), PostgreSQL vector DB, hosted infrastructure
- **Drupal Forge recipe**: Adapt for Forge's infrastructure, likely different vector DB and AI provider setup
- Both need: all custom modules, context items, ai_observability, Canvas UI build step

### 5. Remaining Audit Items (LOW)
- Component agent has no XSS prevention rules for generated JS code
- Sales Training Deck with competitor names in page builder context
- Nested agent calls (SEO → page builder) have no aggregate cost ceiling
- Test coverage gaps: entity validation, component agent, error recovery

## Environment State

- DDEV: running (MariaDB, Milvus, PHP 8.3)
- Drupal: installed from recipes, ai_context applied, ai_observability enabled
- Canvas UI: built
- Anthropic key: set in `.ddev/.env`
- OpenAI key: NOT set
- Composer dev deps: phpunit + drupal/core-dev installed (not committed)
- Uncommitted: composer.json/lock changes (dev deps), .gitignore trailing newline

## Files to Know

| File | Purpose |
|------|---------|
| `.omc/plans/canvas-agent-static-audit.md` | Full 12-agent audit report |
| `.omc/plans/findrop-audit-infrastructure.md` | Infrastructure plan |
| `.omc/handoff-codex-embeddings.md` | Codex handoff for OpenAI key + indexing |
| `.claude/skills/canvas-ai-audit.md` | Repeatable driesnote demo test |
| `.claude/skills/ai-observability-module.md` | Enable/configure contrib observability |
| `.claude/skills/canvas-webapp-testing.md` | Playwright patterns for Canvas |
| `web/sites/default/settings.local.php` | Prompt logging override |
| `ai-agent-audit-fixes.patch` | Patch file for sharing (all fixes) |
