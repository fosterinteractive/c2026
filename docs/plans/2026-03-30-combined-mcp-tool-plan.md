# Combined Plan: Canvas Direct Edit + MCP Server Architecture

**Date:** 2026-03-30
**Branch:** `feat/show-and-prove-session-2`
**Status:** Plan approved for execution
**Inputs:** WS1 drupal-planner, WS2+WS3 plan-writer, quality audit

---

## Executive Summary

Three workstreams converging on a single architecture: expose the proven DirectEditMatcher (144 tests, 632 assertions, 60% hit rate, 0 tokens, <7ms) through Drupal's Tool API plugin system, gaining automatic MCP protocol exposure, Drush CLI access, and AI agent function-calling — all from one implementation.

**Phase 1 (MVP, days):** Single `#[Tool]` plugin in the `ai_agents_experimental_collection`. File as P4 Path A.
**Phase 2 (expanded, weeks):** Full Canvas editing MCP surface — read/write tools for page layout, component catalog, and property editing.
**Phase 3 (strategic, months):** Canvas MCP Server narrative — route AI edits through desktop subscriptions instead of site API keys.

---

## Dependency Landscape

### Quality Audit Verdicts (2026-03-30)

| Module | Version | Security Covered | Verdict | Role |
|--------|---------|-----------------|---------|------|
| `drupal/tool` | 1.0.0-alpha10 | Not active (alpha) | **ACCEPTABLE** | Tool API plugin surface. Required by experimental collection. Pin version. |
| `drupal/mcp` | 1.2.x | **Yes** | **ACCEPTABLE** | Only production-viable MCP option today. Sunset mode — plan migration to mcp_server. |
| `drupal/mcp_server` | Dev only | Not active | **CAUTION** | Designated successor to drupal/mcp. No tagged release yet. Use after first stable. |
| `drupal/mcp_tools` | 1.0.0-beta4 | **Explicitly NOT** | **AVOID** | No security coverage. Dev tooling, not production dependency. |
| `drupal/mcp_client` | 1.0.0-alpha1 | Not active | **CAUTION** | Outbound MCP client. Not needed for our use case. |
| `drupal/simple_oauth_21` | v1.13.0 | N/A (not on d.o) | **CAUTION** | 5-month commit gap. MCP 1.2+ has native OAuth — skip this. |
| `ai_agents_experimental_collection` | 1.0.0-alpha1 | **Never** | **Filing target** | Correct for experimental filing. Not a production dependency. |

### Fallback Strategies

| Scenario | Impact | Fallback |
|----------|--------|----------|
| `tool` module never stabilizes | No `#[Tool]` surface | Ship as `#[FunctionCall]` plugin (works today via `ai` module). Keep service layer identical. |
| `mcp_server` abandoned | No MCP exposure | Use `drupal/mcp` 1.2.x (security-covered). Or expose via custom Drush command. |
| `mcp_tools` bridge broken | Tools not MCP-visible | Register directly with `mcp_server` hook/event system. |
| `ai_agents` restructuring (#3556141) | Plugin surface may change | Phase 1 has no `ai_agents` dependency. Phase 2 watches for changes. |
| Experimental collection goes dormant | Filing target disappears | File as standalone `drupal/canvas_direct_edit` project. Same code. |

---

## Plugin Type Decision

Decision: Match the collection's convention (`#[Tool]`). The collection's 31 existing submodules all use `#[Tool]`. Keep a `#[FunctionCall]` wrapper ready for Path B (canvas_ai contribution).

**Key factors (ranked):**
1. Test coverage (144 tests = strong differentiator)
2. Code quality / not looking AI-generated (larowlan gate)
3. Fail-open reliability (lauriii's pain point)
4. Plugin attribute choice (distant fourth)

---

## Phase 1: MVP — Experimental Collection Filing

### Module: `ai_agents_canvas_direct_edit`

A standalone `#[Tool]` plugin module for the `ai_agents_experimental_collection` that deterministically resolves simple Canvas component property edits.

### Architecture Decision: Read-Only Tool

The tool returns **match data only** — it does NOT apply edits. This eliminates all `canvas_ai` coupling:

```
[User Message + Component Context]
        |
        v
[match_direct_edit Tool Plugin]
        |
        +--> [DirectEditMatcher service]
        |         |
        |         +--> [ComponentSchemaLoader service]
        |                    |
        |                    +--> [Theme SDC YAML schemas]
        |                    +--> [cache.default backend]
        |                    +--> [ai_agents_canvas_direct_edit.settings config]
        |
        v
[Structured match result OR fail-open miss]
        |
        v
[Agent decides: use matched values via update_component_inputs, or fall back to LLM]
```

The agent calls `match_direct_edit` first. On match → calls existing `update_component_inputs` with the matched values. On miss → proceeds with normal LLM reasoning. Zero `canvas_ai` internals touched.

### File Structure

```
modules/ai_agents_canvas_direct_edit/
  ai_agents_canvas_direct_edit.info.yml       # package: "AI Tools", experimental: true
  ai_agents_canvas_direct_edit.install         # hook_uninstall deletes agent config
  ai_agents_canvas_direct_edit.permissions.yml # one permission
  ai_agents_canvas_direct_edit.services.yml
  config/
    install/
      ai_agents_canvas_direct_edit.settings.yml  # edit verbs, enum aliases
    schema/
      ai_agents_canvas_direct_edit.schema.yml
    optional/
      ai_agents.ai_agent.canvas_direct_edit.yml  # optional turnkey agent
  src/
    Plugin/tool/Tool/
      MatchDirectEdit.php                      # #[Tool] plugin
    Service/
      DirectEditMatcher.php                    # pattern matching (632 lines, proven)
      ComponentSchemaLoader.php                # schema discovery (735 lines, proven)
      ComponentSchemaLoaderInterface.php       # contract
  tests/src/Kernel/Tool/
    DirectEditToolTestBase.php                 # shared base
    MatchDirectEditTest.php                    # kernel test
  docs/
    example_prompts.md
```

### Tool Plugin: `MatchDirectEdit`

```php
#[Tool(
  id: 'ai_agents_canvas_direct_edit:match_direct_edit',
  label: new TranslatableMarkup('Match Direct Edit'),
  description: new TranslatableMarkup('Attempts to resolve a simple Canvas component
    property edit deterministically from SDC schemas. Returns matched prop/value
    pairs on success, or a structured miss when the edit requires AI reasoning.
    Call this before update_component_inputs to skip the LLM for trivial changes.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'message' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('User Message'),
      description: new TranslatableMarkup('The user chat message to match.'),
      required: TRUE,
    ),
    'component_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component Name'),
      description: new TranslatableMarkup('SDC component ID (e.g. sdc.byte_theme.heading).'),
      required: TRUE,
    ),
    'current_prop_values' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Current Prop Values'),
      description: new TranslatableMarkup('JSON object of current prop values for
        relative adjustments (bigger/smaller). Optional.'),
      required: FALSE,
    ),
  ],
)]
class MatchDirectEdit extends ConditionToolBase implements ContainerFactoryPluginInterface {
```

### Output Contract

**On match:**
```yaml
status: matched
changes:
  - prop: heading_text
    value: Welcome
component_name: sdc.byte_theme.heading
```

**On miss:**
```yaml
status: no_match
reason: ambiguous_reference
component_name: sdc.byte_theme.heading
```

### Dependencies

```yaml
# ai_agents_canvas_direct_edit.info.yml
name: 'AI Agents Canvas Direct Edit'
type: module
description: 'Deterministic Canvas property editing without LLM.'
package: AI Tools
core_version_requirement: ^10 || ^11
experimental: true
dependencies:
  - ai_agents:ai_agents
  - tool:tool
  - canvas:canvas
```

No dependency on `canvas_ai`. The tool reads SDC schemas from the theme filesystem directly.

### Agent Config (optional)

Goes in `config/optional/` — the primary integration path is adding the tool to the existing Canvas Page Manager agent:

```yaml
# In the existing canvas_page_manager agent config:
tools:
  'tool:ai_agents_canvas_direct_edit:match_direct_edit': true
  'tool:ai_agents_canvas:update_component_inputs': true
  # ... existing tools
```

### Service Layer Migration

Pure namespace + config key changes from the prototype. No algorithmic changes:

| Service | From | To | Changes |
|---------|------|----|---------|
| DirectEditMatcher | `canvas_ai_scoping\Service` | `ai_agents_canvas_direct_edit\Service` | Namespace, config key |
| ComponentSchemaLoader | `canvas_ai_scoping\Service` | `ai_agents_canvas_direct_edit\Service` | Namespace, cache tag, config key |
| ComponentSchemaLoaderInterface | `canvas_ai_scoping\Service` | `ai_agents_canvas_direct_edit\Service` | Namespace only |

### Test Strategy

Kernel tests following the collection's convention:

- `DirectEditToolTestBase` extends `KernelTestBase` with `plugin.manager.tool`
- `MatchDirectEditTest`: plugin exists, happy path (match), miss (no_match), compound edits, boolean toggles, relative adjustments, reset patterns, add-keyword rejection, bare values
- Uses `$plugin->setInputValue()` / `$plugin->execute()` / `$plugin->getResult()` pattern

### Filing Checklist

- [ ] Module scaffold matches collection convention exactly
- [ ] `#[Tool]` attribute follows `ai_agents_canvas` patterns
- [ ] No `canvas_ai` imports anywhere in the module
- [ ] Kernel tests pass via `plugin.manager.tool`
- [ ] Code passes human-quality review (larowlan gate)
- [ ] `hook_uninstall` deletes agent config
- [ ] `example_prompts.md` shows sample usage
- [ ] Issue filed on `ai_agents_experimental_collection` with architecture description
- [ ] MR opened from issue fork on git.drupalcode.org

---

## Phase 2: Expanded Canvas MCP Surface

### Overview

Full read/write Canvas editing tools exposed via MCP. Uses `drupal/mcp` 1.2.x (the only security-covered MCP module) for protocol transport.

### Read Tools (stateless, safe to expose broadly)

| Tool | Operation | Description |
|------|-----------|-------------|
| `canvas_page_layout` | Read | Returns current page layout tree |
| `canvas_component_catalog` | Read | Available components with SDC names, labels |
| `canvas_component_schema` | Read | Full prop schema for a component (types, enums, defaults) |
| `canvas_component_props` | Read | Current prop values for a component by UUID |

### Write Tools (state-changing)

| Tool | Operation | Description |
|------|-----------|-------------|
| `canvas_direct_edit` | Read | Phase 1 matcher (deterministic resolution) |
| `canvas_update_props` | Write | Direct prop update by UUID (exact values) |
| `canvas_add_component` | Write | Add component to a region |
| `canvas_remove_component` | Write | Remove component by UUID |
| `canvas_move_component` | Write | Reorder/relocate by UUID |

### Deterministic Routing Flow (MCP)

```
Claude Desktop / Cursor / Claude Code
    |
    | (MCP protocol via drupal/mcp 1.2.x)
    |
    v
Drupal MCP Server
    |
    +--> canvas_direct_edit (try deterministic first)
    |         |
    |         +--> MATCH: return prop values (0 tokens, <7ms)
    |         |
    |         +--> MISS: client proceeds to...
    |
    +--> canvas_update_props (explicit values from AI reasoning)
    |
    +--> canvas_component_schema (read schema for AI context)
```

### Phase 2 Dependencies

Phase 2 write tools require `canvas_ai` internals:
- `CanvasAiTempStore` (page state)
- `AiResponseValidator` (schema validation)
- `CanvasAiPageBuilderHelper` (response formatting)

These have **no interface contracts** (Wim Leers #3579810). Breakage risk is real. This is appropriate for Phase 2 (after Phase 1 proves the concept) and for Path B (canvas_ai contribution).

### MCP Dependency Choice

Use `drupal/mcp` 1.2.x — the only security-covered option:
- STDIO via Drush for local dev (Claude Desktop, Claude Code)
- HTTP transport for remote access
- Native OAuth 2.1 (no `simple_oauth_21` dependency needed)
- Plan migration to `mcp_server` when it reaches a stable release

### Timeline

- **Phase 2a (read tools):** 1-2 weeks after Phase 1 acceptance
- **Phase 2b (write tools):** 2-4 weeks, depends on canvas_ai coupling decisions
- **MCP integration:** After Phase 2a, once read tools are proven

---

## Phase 3: Strategic — Canvas MCP Server Narrative

### The Pitch

Canvas AI edits currently cost ~$0.30/operation via site-managed API keys. A Canvas MCP server lets users route AI reasoning through their $20/mo Claude/ChatGPT desktop subscription — zero per-operation cost for the site operator.

Combined with deterministic routing (60% of edits at 0 tokens), the remaining 40% routes through the user's own AI subscription. Site operators pay nothing for AI after the initial Canvas setup.

### When to Raise

Only after Phase 1 gets maintainer engagement. This is a strategic conversation, not a technical filing. Frame as a natural implication of the Tool API architecture: "we built deterministic editing as a Tool plugin, and the MCP server emerged from that same surface."

### Ecosystem Position

No `mcp_tools_canvas` exists today. The gap:
- `mcp_tools_layout_builder` has 9 tools (Layout Builder, different paradigm)
- `figma_canvas_ai` is inbound (Figma → Canvas), not outbound
- `ai_context` issue #3567791 spiked CCC-to-MCP integration

A Canvas MCP server fills a real gap in the ecosystem.

---

## Architecture Document Plan

### Deliverable

`docs/architecture/deterministic-routing-architecture.md` — standalone reference covering:

1. **Problem statement** — Canvas AI costs, latency, reliability
2. **System overview** — three optimization layers (P2 loop-aware, P1 layout scoping, P4 deterministic routing)
3. **DirectEditMatcher pipeline** — message → pattern match → schema resolution → validation → response/miss
4. **ComponentSchemaLoader** — theme discovery, YAML parsing, alias generation, enum maps, reverse indexes, caching
5. **Fail-open design** — conservative matching, 422 fallthrough, zero false positives
6. **Measured results** — 0 tokens/<7ms deterministic, 101K/16.4s baseline, 60% hit rate
7. **Tool API integration** — `#[Tool]` plugin, automatic MCP + CLI exposure
8. **MCP server design** — Phase 1 MVP → Phase 2 expanded surface
9. **Dependency risk matrix** — with quality audit verdicts

### Cross-References to Maintain

- `patch-3-deterministic-routing-architecture.md` — Phase 1 must be consistent
- `p4a-tool-plugin-architecture.md` — three-layer split preserved
- `p4a-experimental-collection-FINAL.md` — filing text must stay accurate
- `2026-03-30-upstream-filing-plan.md` — P4 strategy must account for Tool API angle

---

## Open Questions (Resolved)

| Question | Answer | Source |
|----------|--------|--------|
| `mcp_server` health? | CAUTION — dev only, no tagged release | Quality audit |
| `mcp_tools` health? | AVOID — no security coverage | Quality audit |
| `tool` stable interface? | Alpha-10, BC breaks possible. Pin version. | Quality audit |
| MCP auth model? | `drupal/mcp` 1.2.x has native OAuth 2.1. Skip `simple_oauth_21`. | Quality audit |
| `#[FunctionCall]` vs `#[Tool]`? | `#[Tool]` for collection, `#[FunctionCall]` for canvas_ai | Maintainer consensus |
| Phase 2 read tool permissions? | Separate lower-privilege permission (deferred to Phase 2 design) | Open |

---

## Execution Sequence

| Step | Workstream | Deliverable | Depends On |
|------|-----------|-------------|------------|
| 1 | WS1 | Scaffold `ai_agents_canvas_direct_edit` module | This plan |
| 2 | WS1 | Implement `MatchDirectEdit` `#[Tool]` plugin | Step 1 |
| 3 | WS1 | Migrate DirectEditMatcher + ComponentSchemaLoader services | Step 1 |
| 4 | WS1 | Write kernel tests following collection convention | Steps 2-3 |
| 5 | WS1 | Human code review (larowlan gate checklist) | Step 4 |
| 6 | WS3 | Write architecture document | Steps 1-4 |
| 7 | WS1 | File issue + MR on `ai_agents_experimental_collection` | Steps 5-6 |
| 8 | WS1 | Update P4 Path A filing text for `#[Tool]` | Step 7 |
| 9 | WS2 | Phase 2a: read tools design | Phase 1 acceptance |
| 10 | WS2 | Phase 2b: write tools + MCP integration | Phase 2a |

---

## Risk Register

| Risk | Impact | Likelihood | Mitigation |
|------|--------|-----------|------------|
| larowlan rejects code as AI-generated | Critical | High | Human review pass, match existing canvas_ai code style |
| `tool` module BC break in next alpha | Medium | Medium | Pin to alpha-10, test upgrades before bumping |
| `mcp_server` never reaches stable | Medium | Low | Stay on `drupal/mcp` 1.2.x (security-covered) |
| Canvas AI refactors break Phase 2 deps | Medium | Medium | Phase 1 has zero canvas_ai deps. Phase 2 documents breakage risk per-class. |
| Experimental collection maintainers prefer `#[FunctionCall]` | Low | Low | Have wrapper ready. Service layer identical either way. |

---

## Companion Critics

- `/drupal-critic` — Reviews tool plugin implementation against Drupal/Canvas conventions
- `/proposal-critic` — Reviews MCP architecture plan for gaps and assumptions
- `/harsh-critic` — Reviews architecture document for completeness
