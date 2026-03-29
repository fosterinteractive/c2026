# Handoff: Session 4 (Final)

**Date:** 2026-03-27
**Current branch:** `feat/ws1-efficiency-optimization` (branched from `feat/add-claude-md`)
**Parent PR:** fosterinteractive/c2026#1 (FROZEN)
**Site:** Running at https://c2026.ddev.site via DDEV

## What Was Delivered

### Working code
1. **`canvas_ai_scoping` module** (`web/modules/custom/canvas_ai_scoping/`)
   - `LayoutScopingSubscriber` — section-level layout scoping via BuildSystemPromptEvent (79% layout reduction, TESTED AND WORKING)
   - `ContextScopingSubscriber` — ai_context item stripping during edits (WRITTEN BUT NOT FIRING — needs separator format debugging)

2. **Config changes** (all in `custom_recipes/`)
   - Orchestrator examples: 24 → 13
   - page_builder max_loops: 30 → 15
   - template_builder max_loops: 10 → 8, available_on_loop on both tools
   - SEO agent max_loops: 10 → 5
   - Sales Training Deck removed from always_include (recipe only — needs demo-setup to apply)
   - Module added to recipe install list

3. **Documents**
   - `docs/proposals/canvas-ai-region-scoping.md` — Foster Interactive proposal
   - `.omc/plans/token-reduction-remaining-levers.md` — Revised plan per meta-critic

### Measurement results

| Scenario | Tokens | Calls | Notes |
|----------|--------|-------|-------|
| Baseline (page build, pre-optimization) | 253,593 | 10 | Original measurement |
| Phase A (page build, config changes) | 259,649 | 12 | No improvement for builds |
| Phase B1 (edit, region scoping) | 125,607 | 5 | 13% layout reduction |
| Phase B2 (edit, section scoping) | 111,004 | 5 | 79% layout reduction |
| Phase B3 (edit, section + context strip attempt) | 108,839 | 5 | Context strip didn't fire |

## What Needs Doing Next Session

### Immediate: Fix ContextScopingSubscriber
The subscriber doesn't fire — most likely the `-----------------------------------------------` separator doesn't match what ai_context actually renders. Debug by:
1. Enable ai_observability `log_input: true` to capture the full system prompt
2. Check the actual separator/format in the logged prompt
3. Fix the string matching in `ContextScopingSubscriber`

This is the highest-leverage remaining item — stripping 4 context items (Content Structure Product Pages at 29KB alone) should save 10-20K tokens per edit.

### Immediate: Apply Sales Training Deck removal
Run `ddev demo-setup` to apply the recipe change, or update active config via drush.

### Commit all changes
Everything is working but uncommitted. Remove the `\Drupal::logger()` debug calls (or convert to debug-level) before committing.

### Upstream proposals to write/file
1. **ai_context module**: Operation-type-aware context loading (tag items as "build"/"edit"/"all")
2. **ai_agents module**: Chat history windowing (`max_history_messages` config)
3. **Canvas module**: Native region scoping (proposal already written at `docs/proposals/canvas-ai-region-scoping.md`)
4. **Canvas module**: Lightweight edit path (skip LLM for simple prop changes)

## Key Findings (preserve for future sessions)

1. **`available_on_loop` doesn't save tokens** — it moves data between system prompt and chat history but total per-call tokens are identical
2. **Config-only changes (prompt trim, loop caps) don't meaningfully help** — measured 259K vs 253K baseline
3. **Section-level layout scoping works** — 79% layout reduction, but layout is only ~10-15% of per-call cost
4. **The dominant costs are system prompt + ai_context items** — ~16-20K per call that can't be reduced without either stripping content or framework changes
5. **111K tokens for a heading change is structural** — the agent architecture requires multiple LLM round-trips with full context per trip
6. **`return_directly: 1` breaks title/metadata generation** — can't be safely enabled (meta-critic finding)
7. **Workflow A collapsing is unsafe** — `active_component_uuid` is present for both edits AND add-relative-to-selection (meta-critic finding)

## Environment State
- DDEV running, canvas_ai_scoping enabled
- Anthropic key set, OpenAI key NOT set
- ai_observability enabled
- canvas_page/10 (Home): heading changed to "Take Control of Every Dollar" (unsaved, in tempstore)
- Recipe changes NOT applied to active config (need demo-setup)

## Decisions Made (All Sessions)
- Drupal Forge deployment is in scope
- LiteLLM banned (supply chain compromise March 2026)
- Component agent JS generation: BLOCKING FOR PRODUCTION
- "Human review gate" for AI-generated component code: MANDATORY
- Token budget per request: needs product lead input
- Layout scoping works but is insufficient alone
- Context stripping is the next highest-leverage lever
- Upstream proposals needed for structural improvements (ai_agents history windowing, ai_context operation scoping, Canvas lightweight edit path)
