# WS3: Markdown-Based Agent Configuration

**Revision: v2 — Revised based on proposal-critic feedback (2026-03-27)**

**Status:** Draft
**Created:** 2026-03-26
**Estimated Scope:** MEDIUM (leverages existing ai_context patterns, primarily config + recipe work)
**Dependencies:** WS1 (efficiency optimization should be done first so we migrate the already-trimmed prompts)
**Blocks:** WS4 (config format affects deployment recipe structure)
**Can run in parallel with:** WS2 (no mutual dependencies)

---

## Changes from v1

1. **Fixed the critical `setSystemPrompt()` clobbering bug** — v1's approach would replace the composited output (prompt + default_information_tools), silently stripping runtime context from 5 of 9 agents. Redesigned the approach with two viable options and an explicit recommendation.
2. **Gave Option D (extending ai_context) serious analysis** — the critic argued it reuses battle-tested infrastructure (entity import, recipe integration, usage tracking) and avoids the composition problem entirely. Option D is now a first-class alternative alongside the revised Option C.
3. **Specified caching strategy** — file reads on every agent loop iteration (5-10+ per request) need caching. Added explicit caching design with Drupal's cache backend.
4. **Added error handling specification** — malformed frontmatter, missing files, agent_id mismatches, file read failures.
5. **Fixed proof-of-concept agent choice** — `canvas_title_generation_agent` has `default_information_tools` (get_entity_context, get_page_data) and would hit the clobbering bug immediately. Changed to `analytics_monitoring_agent` (no default_information_tools, simple prompt).
6. **Clarified file path convention** — `ai_context_data/` is at the project root, not under `web/`. Agent prompt files follow the same convention.
7. **Added testing strategy** — kernel test for the prompt loader, integration test verifying the subscriber modifies the prompt correctly.
8. **Revised recommendation** — after analyzing the clobbering bug, Option D (extending ai_context) is now the recommended approach for its safety and infrastructure reuse. Option C remains viable with the clobbering fix but requires more custom code.

---

## Problem Statement

Agent system prompts are embedded as multiline strings inside YAML config entity files (`ai_agents.ai_agent.*.yml`). This creates several problems:

1. **Hard to review in PRs:** System prompts are the most-changed part of agent configs, but they are buried in YAML with escaping artifacts, making diffs noisy and hard to review.
2. **Not portable:** Prompts are tightly coupled to Drupal's config entity system. They cannot be shared, tested, or versioned independently.
3. **Inconsistent tooling:** Context items ARE already in markdown (`ai_context_data/*.md`) and imported as content entities. Agent prompts use a completely different pattern (inline YAML strings).
4. **No standard developer workflow:** Editing a 300-line system prompt inside a YAML file with proper escaping is error-prone. Markdown files can be edited with any text editor, linted, and diffed cleanly.

The goal is to make agent system prompts work like Claude Code skills -- markdown files that define agent capabilities and are loaded at runtime.

## Current State

### How Context Items Work (the pattern to follow)

The ai_context module already solves the markdown-to-agent-context problem:

1. **Markdown source files** live in `ai_context_data/*.md` (10 files currently) at the **project root** (parent of `web/`), NOT under `DRUPAL_ROOT` (`web/`)
2. **Content entities** are created from these files and exported as recipe content in `custom_recipes/ai_context_items/content/ai_context_item/*.yml`
3. **Entity structure** (from `0ddd4133-6b3c-4b05-8a59-3b1f45ffa4df.yml`): Each entity has `label`, `description`, `purpose`, `content` (the markdown), and `subcontext_type` fields
4. **Agent mapping** happens in `custom_recipes/ai_context_setup/recipe.yml` via the `aiContextAgentsUpdate` config action, which maps context items to agents via `always_include` / `excluded_subcontext`
5. **Runtime injection** happens via `SystemPromptSubscriber.php`: subscribes to `BuildSystemPromptEvent`, calls `AiContextSelector::select()` to get relevant context, **appends** it to the system prompt
6. **Rendering** happens via `AiContextRenderer.php`: loads entities, budgets tokens, renders compact context blocks

### How Agent Prompts Work (the current approach)

1. **System prompts** are stored as the `system_prompt` field on `AiAgent` config entities
2. **At runtime**, `AiAgentEntityWrapper::getSystemPrompt()` (line 872-882) does:
   ```php
   $dynamic = $this->getDefaultInformationTools();  // Executes tools, gets output
   $secured_system_prompt = $this->aiAgent->get('secured_system_prompt');
   // defaults to "[ai_agent:agent_instructions]"
   $prompt = $this->applyTokens($secured_system_prompt);  // Resolves to system_prompt value
   return $prompt . "\n\n" . $dynamic;  // COMPOSITES prompt + tool output
   ```
3. **The `BuildSystemPromptEvent`** fires at line 455-457 with the **composited** string (prompt + default_information_tools output). The event's `setSystemPrompt()` replaces this entire composited string.
4. **Token replacement** (`applyTokens()`) runs AGAIN at line 463 on the event's output.

### The Critical Clobbering Problem (from v1 critique)

v1 recommended Option C (custom module with `BuildSystemPromptEvent`) where the subscriber calls `setSystemPrompt()` to replace the prompt with markdown file content. This would **destroy default_information_tools output** because `setSystemPrompt()` replaces the composited string (prompt + dynamic tool output), not just the agent instructions portion.

**Five agents have non-empty `default_information_tools`:**
- `canvas_title_generation_agent` — get_entity_context, get_page_data
- `canvas_metadata_generation_agent` — get_entity_context, get_page_data
- `canvas_component_agent` — get_js_component, get_props_type, get_node_fields
- `canvas_page_builder_agent` — current_layout, available_components
- `canvas_template_builder_agent` — current_layout, available_components

For these agents, naive `setSystemPrompt()` replacement would silently drop runtime context (entity information, page data, current layout, component props) that is essential for correct agent behavior.

### Agent Prompt Sizes

| Agent | System Prompt Tokens | Complexity | Has default_information_tools |
|-------|---------------------|------------|-------------------------------|
| canvas_ai_orchestrator | ~4,500 (post-WS1: ~2,800) | HIGH | No |
| canvas_page_builder_agent | ~3,200 | HIGH | **Yes** (current_layout, available_components) |
| canvas_template_builder_agent | ~2,000 | MEDIUM | **Yes** (current_layout, available_components) |
| canvas_component_agent | ~4,000 | HIGH | **Yes** (get_js_component, get_props_type, get_node_fields) |
| drupal_canvas_seo_agent | ~3,000 | HIGH | No |
| canvas_metadata_generation_agent | ~500 (post-WS1: ~200) | LOW | **Yes** (get_entity_context, get_page_data) |
| canvas_title_generation_agent | ~50 (post-WS1: ~100) | LOW | **Yes** (get_entity_context, get_page_data) |
| analytics_monitoring_agent | ~300 | LOW | No |
| drupal_cms_assistant | varies | MEDIUM | No |

## Proposed Approach

### Step 1: Define the markdown file format for agent prompts

Create a standard format:

```markdown
---
agent_id: canvas_ai_orchestrator
label: "Drupal Canvas AI Orchestrator"
description: "Orchestration agent that routes user requests to specialized sub-agents"
---

# Canvas AI Orchestrator

You are an expert AI Orchestrator for Drupal Canvas...

## 1. Core Rules
...

## 2. Available Tools
...
```

The frontmatter declares metadata. The body IS the system prompt content that replaces the `system_prompt` field value (NOT the composited output).

**Important:** ALL token patterns in the markdown body (e.g., `[canvas_ai:page_title]`, `[site:name]`) are resolved automatically by `applyTokens()` at runtime, regardless of whether they appear in frontmatter. Do not use token-like patterns as literal examples in prompts -- they will be replaced. If you need to show a token as an example, escape it (e.g., `\[canvas_ai:page_title\]`).

**Excluded from frontmatter:** The v1 `tokens` and `version` fields are removed. `tokens` was decorative (all token patterns are resolved regardless of declaration) and created a false sense of control. `version` had no defined semantics.

**Scope:** Only the 9 Canvas/FinDrop agent prompts are migrated. The 3 contrib agents (`content_type_agent_triage`, `field_agent_triage`, `taxonomy_agent_config`) are excluded because their prompts are maintained upstream.

**Acceptance criteria:** Format documented in `docs/specs/agent-prompt-format.md`. Format supports all current prompt features (tokens, dynamic context references).

### Step 2: Select implementation approach

Two viable approaches remain after the clobbering analysis. Both are analyzed in detail:

**Option C (revised): Custom module with safe prompt replacement**

The original Option C's clobbering bug can be fixed. The subscriber must replace ONLY the `system_prompt` portion of the composited string, preserving the `default_information_tools` output:

Approach: The subscriber runs at a priority higher than ai_context (runs first). It:
1. Gets the current composited prompt via `$event->getSystemPrompt()`
2. Loads the agent's original `system_prompt` value from config
3. Loads the markdown file content for the agent
4. Applies token replacement to the markdown content (using the agent's token context)
5. Performs a string replacement: swap the resolved original `system_prompt` portion with the markdown content, leaving the `default_information_tools` suffix intact
6. Calls `$event->setSystemPrompt()` with the modified composite

**Risk:** This relies on the original `system_prompt` value being a recognizable substring of the composited output. If `applyTokens()` transforms the prompt in ways that make substring matching unreliable, this approach breaks silently. Also, if `secured_system_prompt` uses a non-trivial wrapper (not just `[ai_agent:agent_instructions]`), the substring matching becomes more complex.

**Mitigation:** All FinDrop agents use `secured_system_prompt: '[ai_agent:agent_instructions]'` (simple passthrough). The substring is the resolved `system_prompt` value before `default_information_tools` appending. Add a validation check: if the original prompt text is not found in the composite, log an error and fall back to the config entity prompt.

**Option D (revised): Extend ai_context as "agent prompt" context items**

Store agent prompts as ai_context entities with a special type. The ai_context module's `SystemPromptSubscriber` already handles injection via `BuildSystemPromptEvent` -- but it **appends** content rather than replacing. This avoids the clobbering problem entirely because the default_information_tools output is never touched.

Implementation:
1. Create a new ai_context_item subtype or use a convention (e.g., label prefix `[PROMPT]` or a dedicated `subcontext_type: agent_prompt`)
2. For each agent, create an ai_context_item entity containing the full system prompt as the `content` field
3. Map each prompt entity to its agent via `always_include` in the ai_context agent mapping
4. Modify the `system_prompt` field in agent configs to a minimal stub (e.g., "See context items for full instructions")
5. The ai_context `SystemPromptSubscriber` appends the prompt content after the stub

**Advantages over Option C:**
- Reuses battle-tested infrastructure (entity import, recipe integration, usage tracking, token budgeting)
- No custom module needed (just config/content entities and recipe changes)
- No clobbering risk (appends, never replaces)
- Markdown source files follow the exact same workflow as existing `ai_context_data/*.md` files
- Agent prompt entities can use `always_include` for deterministic injection (bypasses keyword matching)

**Disadvantages:**
- Semantic conflation: agent prompts and supplementary context are different concepts, but they use the same entity type and injection mechanism
- The agent's `system_prompt` field becomes a stub, which is confusing in the config UI
- Prompt content is duplicated: once in the markdown source file, once in the content entity export, once as a stub in the config entity
- Keyword-based context selection could be affected if the stub prompt has different keywords than the full prompt (mitigated by using `always_include`)

**Recommendation: Option D (extending ai_context)**

After analyzing the clobbering bug, Option D is the safer and more pragmatic choice:
- It avoids the clobbering problem entirely
- It reuses existing, tested infrastructure instead of building a parallel system
- The "semantic conflation" concern is theoretical -- at the code level, both context and prompts are string mutations on the same system prompt via the same event
- The markdown workflow is identical to the existing ai_context_data workflow that is already established

Option C remains viable as a fallback if Option D proves unworkable (e.g., if the ai_context token budgeting truncates long prompts, or if the "append vs replace" behavior causes the prompt to appear after context items instead of before).

**Acceptance criteria:** Option selected with documented rationale. Proof-of-concept tested with `analytics_monitoring_agent` (chosen because it has NO default_information_tools and a simple ~300 token prompt -- the safest first test).

### Step 3: Implement the prompt loading mechanism

**If Option D (recommended):**

1. Create markdown source files in `ai_agent_prompts/` at the project root (alongside `ai_context_data/`):
   ```
   ai_agent_prompts/
     canvas_ai_orchestrator.md
     canvas_page_builder_agent.md
     canvas_template_builder_agent.md
     canvas_component_agent.md
     canvas_title_generation_agent.md
     canvas_metadata_generation_agent.md
     drupal_canvas_seo_agent.md
     analytics_monitoring_agent.md
     drupal_cms_assistant.md
   ```

2. Create ai_context_item entities for each agent prompt:
   - `label`: e.g., "[PROMPT] Canvas AI Orchestrator"
   - `content`: the full markdown prompt content
   - `subcontext_type`: use a convention to distinguish prompts from supplementary context
   - `purpose`: "System prompt for {agent_name}"

3. Export entities to `custom_recipes/ai_context_items/content/ai_context_item/`

4. Map each prompt entity to its agent in `custom_recipes/ai_context_setup/recipe.yml`:
   - Add to `always_include` for the matching agent
   - This ensures deterministic injection (no keyword matching)

5. Reduce agent config `system_prompt` fields to minimal stubs:
   - For agents WITHOUT default_information_tools: stub can be empty or a one-liner
   - For agents WITH default_information_tools: stub must preserve any instructions that reference default_information_tools output (e.g., "The current layout is provided above")

6. Verify: the ai_context subscriber appends the prompt entity content to the system prompt. The agent receives: stub + default_information_tools output + prompt entity content + other context items.

**Ordering concern:** ai_context appends AFTER the base prompt + default_information_tools. This means the full prompt instructions appear after the dynamic tool output, not before. Test whether this ordering affects agent behavior. If agents perform worse with instructions after dynamic context, consider adjusting `SystemPromptSubscriber` priority or adding a custom subscriber that reorders the content.

**If Option C (fallback):**

Create `web/modules/custom/canvas_ai_prompts/`:
- `canvas_ai_prompts.info.yml` -- module definition
- `canvas_ai_prompts.services.yml` -- service definitions
- `src/Service/AgentPromptLoader.php` -- loads and parses markdown files, with caching
- `src/EventSubscriber/AgentPromptSubscriber.php` -- subscribes to `BuildSystemPromptEvent` at priority 100 (higher than ai_context at 0), performs safe substring replacement

**Caching strategy (applies to both options):**

For Option D: ai_context already handles caching through Drupal's entity loading cache. No additional caching needed.

For Option C: The `AgentPromptLoader` service must cache parsed markdown to avoid filesystem reads on every loop iteration:
- Use Drupal's `cache.default` backend with cache tag `canvas_ai_prompts`
- Cache key: `agent_prompt:{agent_id}:{file_mtime}` (file modification time for auto-invalidation during development)
- `drush cr` clears the cache (standard Drupal behavior)
- In development: check file mtime on each request. If changed, invalidate cache entry.
- In production: rely on `drush cr` after deployments.

**Error handling (applies to both options):**

- **Malformed frontmatter:** Log a warning, fall back to config entity `system_prompt`. Do not crash the agent.
- **Missing file for an agent_id:** Log a notice, use config entity `system_prompt`. This is the normal case for agents not yet migrated.
- **agent_id mismatch (frontmatter agent_id does not match filename):** Log a warning, skip the file. Use config entity `system_prompt`.
- **File read failure (permissions, missing directory):** Log an error, fall back to config entity `system_prompt`.
- **All errors must be non-fatal.** The agent must always have a working prompt, even if the markdown loading fails.

**Acceptance criteria:** Prompt loading mechanism implemented. Token replacement works. Fallback to config entity works when no file/entity exists. `analytics_monitoring_agent` successfully uses a markdown-based prompt. No regressions for agents with default_information_tools (verify title_generation_agent still receives get_entity_context and get_page_data output).

### Step 4: Migrate existing prompts to markdown files

Extract all 9 agent system prompts from YAML configs into markdown files:

For each file:
1. Extract `system_prompt` from the YAML config
2. Add frontmatter with agent_id, label, description
3. Clean up YAML escaping artifacts (convert `\r\n` to newlines, remove YAML `|-` block scalar syntax)
4. For Option D: create the ai_context_item entity and export it
5. For Option D: update `recipe.yml` to map the entity to the agent
6. Verify the prompt content produces identical agent behavior:
   - Run a page build and compare output quality to pre-migration baseline
   - For agents with default_information_tools: verify dynamic context is still present in the system prompt (check via ai_observability logs)
   - For agents using keyword-based context selection: verify the same context items are selected (check via ai_context usage tracking)

**Migration order (safest first):**
1. `analytics_monitoring_agent` (no default_information_tools, simple prompt, standalone)
2. `drupal_canvas_seo_agent` (no default_information_tools, complex prompt)
3. `canvas_ai_orchestrator` (no default_information_tools, most complex prompt)
4. `drupal_cms_assistant` (no default_information_tools)
5. `canvas_title_generation_agent` (HAS default_information_tools -- verify carefully)
6. `canvas_metadata_generation_agent` (HAS default_information_tools)
7. `canvas_template_builder_agent` (HAS default_information_tools, complex prompt)
8. `canvas_page_builder_agent` (HAS default_information_tools, complex prompt)
9. `canvas_component_agent` (HAS default_information_tools, highest security risk)

**Acceptance criteria:** All 9 agent prompts migrated to markdown files. Each prompt produces identical agent behavior verified by running a page build after each migration. Agents with default_information_tools confirmed to still receive their dynamic context.

### Step 5: Testing

**Automated tests:**

1. **Kernel test: `AgentPromptLoadingTest`**
   - If Option D: Test that an ai_context_item entity with the agent prompt is loaded and injected for the correct agent via `always_include`
   - If Option C: Test that `AgentPromptLoader::load('analytics_monitoring_agent')` returns parsed markdown content with correct frontmatter
   - Test fallback: when no markdown/entity exists, the config entity `system_prompt` is used unchanged
   - Test error handling: malformed frontmatter falls back gracefully

2. **Kernel test: `DefaultInformationToolsPreservationTest`**
   - Create a test agent with `default_information_tools` that returns a known string
   - Apply the prompt loading mechanism
   - Verify the known string is still present in the final system prompt
   - This is the regression test for the clobbering bug

3. **Integration test: `PromptMigrationConsistencyTest`**
   - For each migrated agent, compare the system prompt produced by the markdown mechanism vs. the original config entity mechanism
   - Verify token replacement works identically
   - Verify ai_context items are the same (if using Option D with `always_include`)

**Acceptance criteria:** All tests pass. The clobbering bug has an explicit regression test. Test coverage includes agents with and without default_information_tools.

### Step 6: Document the developer workflow

Create documentation for how developers edit agent prompts:

1. Edit the markdown file in `ai_agent_prompts/` (or update the ai_context_item entity content for Option D)
2. If Option D: re-export content (`ddev export-ai-context`)
3. If Option C: clear Drupal cache (`ddev drush cr`) to pick up file changes
4. Test the agent behavior in the Canvas UI
5. Commit the markdown file (and entity export for Option D)
6. PR review shows clean markdown diffs

**Deployment path (for WS4):**
- The `ai_agent_prompts/` directory lives at the project root, alongside `ai_context_data/`
- For Option D: prompt entities are exported as recipe content (same as existing ai_context_items). Deployment platforms receive them via the recipe, not via filesystem paths.
- For Option C: the markdown files must be accessible at runtime. For deployment platforms that use the full repo (amazee.io, DDEV), files are at `{project_root}/ai_agent_prompts/`. For platforms that only deploy `web/`, the module must handle a configurable base path. Document this in the deployment guide.

**Acceptance criteria:** Developer workflow documented in `docs/guides/editing-agent-prompts.md`. Deployment path clarified for each target platform (DDEV, amazee.io, Drupal Forge).

## Cross-References

- **WS1 (Efficiency):** WS1's prompt trimming (Steps 1 and 3) should be done FIRST in YAML, then the trimmed prompts are migrated to markdown in WS3 Phase 3. This avoids doing the same trimming work twice.
- **WS2 (Branching):** If WS2 restructures the orchestrator prompt for the automatic SEO trigger, that modified prompt should be the version migrated to markdown.
- **WS4 (Deploy):** WS4's deployment recipes need to include the prompt mechanism:
  - Option D: ai_context_item entities are already part of the recipe content export. No additional deployment work.
  - Option C: the `canvas_ai_prompts` module and `ai_agent_prompts/` directory must be in the deployment artifacts.

## Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Option D: ai_context token budgeting truncates long prompts | MEDIUM | HIGH | Test with the orchestrator prompt (~2,800 tokens post-WS1). If truncated, increase the ai_context token budget or switch to Option C. |
| Option D: prompt appears AFTER dynamic context in the system prompt | MEDIUM | MEDIUM | Test with analytics_monitoring_agent first. If ordering matters, adjust subscriber priority or add a reordering subscriber. |
| Option C: substring replacement for clobbering fix is fragile | MEDIUM | HIGH | Add validation check: if original prompt text not found in composite, log error and fall back. Explicit regression test. |
| Keyword-based context selection returns different items with different prompt text | LOW | MEDIUM | All agents use `always_include` for their context items, which bypasses keyword matching. Verify during migration. |
| Recipe export overwrites entity content | MEDIUM | MEDIUM | For Option D: the markdown files are the source of truth. Re-export after editing. Document this workflow. For Option C: markdown files are independent of recipe export. |
| Developers include token-like patterns as literal examples in prompts | LOW | LOW | Document that all `[token:name]` patterns are resolved. Provide escaping guidance. |

## Success Criteria

1. All 9 agent prompts available as markdown files in `ai_agent_prompts/`
2. Prompt loading mechanism works at runtime (Option D via ai_context entities or Option C via custom module)
3. Backward compatible -- agents work without the mechanism (fall back to YAML config)
4. PR diffs for prompt changes show clean markdown instead of YAML noise
5. Developer workflow documented and tested
6. No modifications to `web/modules/contrib/ai_agents/` or `web/modules/contrib/ai_context/`
7. Clobbering bug has an explicit regression test
8. Agents with default_information_tools confirmed to retain their dynamic context after migration
9. Deployment path documented for DDEV, amazee.io, and Drupal Forge
