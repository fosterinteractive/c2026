# Canvas AI Agent Chain — Static Audit Report

**Date:** 2026-03-26
**Status:** Complete (Phase 1 — zero tokens spent)
**Scope:** All 12 AI agents, context items, function call plugins, test scenarios

---

## 1. Agent Orchestration Map

```
canvas_ai_orchestrator (max_loops: 10)
  ├── canvas_template_builder_agent (max_loops: 10)
  │     Tools: set_template_data, get_metadata_of_components, rag_search
  ├── canvas_page_builder_agent (max_loops: 30)
  │     Tools: set_component_structure, update_component_data, get_component_content,
  │            get_metadata_of_components, move_component_in_page, rag_search
  ├── canvas_component_agent (max_loops: 10, triage: true)
  │     Tools: edit_component_js, create_component, get_props_type,
  │            get_js_component, get_node_fields
  ├── canvas_title_generation_agent (max_loops: 5)
  │     Tools: create_field_content, edit_field_content
  ├── canvas_metadata_generation_agent (max_loops: 5)
  │     Tools: add_metadata
  └── drupal_canvas_seo_agent (max_loops: 10)
        Tools: add_schema_org_json, rag_search, get_component_content,
               get_linkable_components
        └── canvas_page_builder_agent (sub-call for link insertion)

drupal_cms_assistant (max_loops: 10, separate orchestrator)
  ├── content_type_agent_triage (max_loops: 3, triage: true)
  ├── field_agent_triage (max_loops: 15, triage: true)
  └── taxonomy_agent_config (max_loops: 10, triage: true)

analytics_monitoring_agent (max_loops: 3, standalone)
  Tools: get_relevant_context_items
```

### Critical Path (Canvas Page Build)
1. User request → `canvas_ai_orchestrator`
2. Orchestrator validates entity type (must be `canvas_page`)
3. Delegates to `canvas_template_builder_agent` (new page) OR `canvas_page_builder_agent` (edits)
4. In parallel (if title/description empty): `canvas_title_generation_agent` + `canvas_metadata_generation_agent`
5. Sub-agents loop internally (metadata retrieval, RAG image search, component placement)
6. Orchestrator collects responses, surfaces questions or confirms completion

### Recursion Risks

| Agent | max_loops | Risk | Notes |
|-------|-----------|------|-------|
| canvas_page_builder_agent | **30** | **HIGH** | Highest in the chain. 3 retries per image search. |
| drupal_canvas_seo_agent → page_builder | 10 × 30 | **HIGH** | Nested chain: worst case 300 effective loops |
| canvas_ai_orchestrator → page_builder | 10 × 30 | **HIGH** | Same nesting pattern |
| field_agent_triage | 15 | MEDIUM | High for a triage agent |
| analytics_monitoring_agent | 3 | LOW | Appropriately constrained |

---

## 2. System Prompt Quality

### canvas_ai_orchestrator — **CLEAR**
- ~4,500 tokens. Expert PM persona with 24 worked examples.
- **Issues:** Duplicate Rule #8 (two different rules share the number), Rule #7 missing from sequence, no explicit error handling for sub-agent failures.

### canvas_page_builder_agent — **CLEAR**
- ~3,200 tokens + dynamic context (layout JSON, component catalog).
- **Issues:** max_loops:30 with "retry until all succeed" and no upper retry bound. No guidance for component-not-found scenarios.

### canvas_template_builder_agent — **CLEAR**
- ~2,000 tokens. Generates 5+ section templates.
- **Issues:** "Creative Expansion" instruction is a mild hallucination risk. No defense-in-depth on preflight questions (relies on orchestrator).

### canvas_component_agent — **CLEAR BUT COMPLEX**
- ~4,000 tokens. Generates React/Preact code.
- **Issues:** **Highest security risk agent** — generates browser-executable JS with no XSS prevention rules, no CSP guidance, no `eval()` restrictions.

### canvas_title_generation_agent — **INCOMPLETE**
- **~50 tokens.** 3-line prompt. No length constraints, no brand voice, no naming conventions.
- **CRITICAL: Receives ZERO context items.** Not listed in ai_context_setup recipe at all.

### canvas_metadata_generation_agent — **VAGUE**
- ~500 tokens. Has 160-char limit but thin otherwise.
- **CRITICAL: Also receives ZERO context items.**

### drupal_canvas_seo_agent — **CLEAR**
- ~3,000 tokens. Excellent good/bad prompt examples.
- **Issues:** Calls page_builder as sub-agent (deepest nesting). Also receives zero context items.

### analytics_monitoring_agent — **CLEAR**
- ~300 tokens. Simple, focused, appropriate scope.
- **Issue:** structured_output_enabled: false despite having a JSON schema defined.

---

## 3. Red Flags

### CRITICAL

1. **XSS in Schema.org JSON-LD injection.** `CanvasAiSeoHooks.php:62-67` injects LLM-generated JSON-LD directly into a `<script>` tag without sanitization. An LLM generating `</script><script>alert(1)</script>` would execute arbitrary JS.

2. **Hardcoded credentials filename.** `GoogleAnalytics.php:43` contains `putenv('GOOGLE_APPLICATION_CREDENTIALS=/var/www/html/web/sites/default/files/ai-integration-480315-c136045bcc0e.json')` — dead code but exposes the creds filename in source control.

3. **Title and metadata agents have ZERO brand context.** These agents generate the most visible SEO content (search result titles/descriptions) with no brand guidelines, naming conventions, or approved vocabulary.

4. **Competitor names in page builder context.** The Sales Training Deck (always injected into both page builders) contains "Rimp," "Brix," "SAQ Concur," "Navex," "Dill/Bivvy." Brand guidelines prohibit these in external content, but having them in context is a known hallucination trap.

### HIGH

5. **Hardcoded GA date range.** `GoogleAnalytics.php:63-66` hardcodes `end_date: 2026-03-09`. Already stale (today is March 26).

6. **max_loops:30 with unbounded retry.** Page builder prompt says "Retry... Continue until all succeed." No retry ceiling means burning all 30 loops on a persistently failing tool.

7. **Nested agent calls with no cost ceiling.** SEO → Page Builder (30 loops) multiple times within SEO's 10-loop budget. No aggregate token limit.

### MEDIUM

8. **"Vibe coded method"** in `GetLinkableComponents.php:127` — self-documented as AI-generated without thorough review.
9. **GoogleAnalytics.php uses static `\Drupal::` calls** — untestable, violates coding standards.
10. **Uninitialized `$output` variable** in GoogleAnalytics.php if no GA rows returned.
11. **Test scenarios reference wrong agent/tool IDs** — tests are currently unrunnable.

---

## 4. Context Injection Analysis

| Agent | Context Items | Token Cost | Assessment |
|-------|--------------|------------|------------|
| orchestrator | 2 items (guidelines, brand) | ~1,200 | **Good** — lightweight |
| template_builder | 8 items (full brand + content structure) | ~10,000-12,000 | **Excessive** — includes internal sales deck with competitor names |
| page_builder | 8 items (same as template) | ~10,000-12,000 | **Same concern** |
| title_generation | **NONE** | ~50 | **CRITICAL GAP** |
| metadata_generation | **NONE** | ~500 | **CRITICAL GAP** |
| seo_agent | **NONE** | ~3,000 | Moderate gap |
| analytics_monitoring | 1 item (GA benchmarks) | ~300 | **Well configured** |

### Wasted Context
- Sales Training Deck (~2,500 tokens) in page builders: contains competitor names, discovery questions, demo flow — mostly irrelevant to page building and dangerous.

### Missing Context
- Title agent: needs Brand Guidelines + Key Facts at minimum
- Metadata agent: same
- SEO agent: could benefit from Key Facts for Schema.org property values

---

## 5. Test Scenario Coverage

**27 tests across 7 phases.** Covers: happy path page builds, degraded input, SEO, analytics, compliance.

### Missing Coverage
- Zero tests for: entity type validation (Rule #1), component agent (code gen), title agent, metadata agent, error recovery, nested agent calls, brand compliance (competitor name leakage), parallel execution, selected component flow

### Test Quality Issues
- Agent IDs don't match config (`canvas_ai_assistant` vs `canvas_ai_orchestrator`)
- Tool IDs don't match (`ai_agents::canvas::generate_page` vs actual tool names)
- Tests are currently unrunnable without remapping

---

## 6. Recommendations (Prioritized)

### Must Fix Before Demo
1. Sanitize JSON-LD before `<script>` injection (XSS)
2. Add brand context to title and metadata agents
3. Fix hardcoded GA date range
4. Remove hardcoded credentials path

### Should Fix Before Production
5. Strip competitor names from page builder context (or create filtered version)
6. Add retry ceiling (3 max) to page builder error handling
7. Fix duplicate Rule #8 numbering
8. Add DI to GoogleAnalytics.php
9. Fix test scenario agent/tool IDs

### Nice to Have
10. Reduce page_builder max_loops from 30 to 15-20
11. Enable structured_output on analytics agent
12. Fix "strucutre" typos in filenames
