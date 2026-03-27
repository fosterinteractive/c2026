# WS3: Markdown-Based Agent Configuration

**Status:** Draft
**Created:** 2026-03-26
**Estimated Scope:** MEDIUM (leverages existing ai_context patterns, primarily config + recipe work)
**Dependencies:** WS1 (efficiency optimization should be done first so we migrate the already-trimmed prompts)
**Blocks:** WS4 (config format affects deployment recipe structure)
**Can run in parallel with:** WS2 (no mutual dependencies)

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

1. **Markdown source files** live in `ai_context_data/*.md` (10 files currently)
2. **Content entities** are created from these files and exported as recipe content in `custom_recipes/ai_context_items/content/ai_context_item/*.yml`
3. **Entity structure** (from `0ddd4133-6b3c-4b05-8a59-3b1f45ffa4df.yml`): Each entity has `label`, `description`, `purpose`, `content` (the markdown), and `subcontext_type` fields
4. **Agent mapping** happens in `custom_recipes/ai_context_setup/recipe.yml` via the `aiContextAgentsUpdate` config action, which maps context items to agents via `always_include` / `excluded_subcontext`
5. **Runtime injection** happens via `SystemPromptSubscriber.php`: subscribes to `BuildSystemPromptEvent`, calls `AiContextSelector::select()` to get relevant context, appends it to the system prompt
6. **Rendering** happens via `AiContextRenderer.php`: loads entities, budgets tokens, renders compact context blocks

### How Agent Prompts Work (the current approach)

1. **System prompts** are stored as the `system_prompt` field on `AiAgent` config entities
2. **At runtime**, `AiAgentEntityWrapper::getSystemPrompt()` (line 872) reads `secured_system_prompt`, applies token replacement (`[ai_agent:agent_instructions]` is replaced with the actual `system_prompt`), then appends `default_information_tools` output
3. **Token replacement** (`applyTokens()`) handles dynamic tokens like `[canvas_ai:page_title]`, `[site:name]`, etc.
4. **The `BuildSystemPromptEvent`** fires after the base prompt is assembled, allowing subscribers (like ai_context) to append content

### Key Insight

The `BuildSystemPromptEvent` is the extension point. The ai_context module already uses it to inject context items. A similar pattern could inject agent system prompts from markdown files -- but the approach needs to be different because system prompts REPLACE the base prompt rather than appending to it.

### Agent Prompt Sizes

| Agent | System Prompt Tokens | Complexity |
|-------|---------------------|------------|
| canvas_ai_orchestrator | ~4,500 (post-WS1: ~2,800) | HIGH -- 8 rules, 24 examples (post-WS1: ~12 examples) |
| canvas_page_builder_agent | ~3,200 | HIGH -- 3 workflows, YAML templates, error handling |
| canvas_template_builder_agent | ~2,000 | MEDIUM -- component placement, prop logic |
| canvas_component_agent | ~4,000 | HIGH -- React/Preact code generation |
| drupal_canvas_seo_agent | ~3,000 | HIGH -- 3 modes, good/bad prompt examples |
| canvas_metadata_generation_agent | ~500 (post-WS1: ~200) | LOW |
| canvas_title_generation_agent | ~50 (post-WS1: ~100) | LOW |
| analytics_monitoring_agent | ~300 | LOW |
| drupal_cms_assistant | varies | MEDIUM |

## Proposed Approach

### Phase 1: Design the Markdown-to-Prompt Pattern

**Step 1: Define the markdown file format for agent prompts**

Create a standard format that mirrors Claude Code skills / ai_context_data files:

```markdown
---
agent_id: canvas_ai_orchestrator
label: "Drupal Canvas AI Orchestrator"
description: "Orchestration agent that routes user requests to specialized sub-agents"
version: "2.0"
tokens:
  - canvas_ai:verbose_context_for_orchestrator
  - canvas_ai:entity_type
  - canvas_ai:page_title
---

# Canvas AI Orchestrator

You are an expert AI Orchestrator for Drupal Canvas...

## 1. Core Rules
...

## 2. Available Tools
...
```

The frontmatter declares metadata. The body IS the system prompt. Tokens referenced in the prompt (like `[canvas_ai:page_title]`) are declared in the frontmatter for documentation but resolved at runtime by the existing `applyTokens()` mechanism.

**Acceptance criteria:** Format documented in `docs/specs/agent-prompt-format.md`. Format supports all current prompt features (tokens, dynamic context references). At least 2 team members have reviewed the format.

**Step 2: Evaluate implementation options**

**Option A: File-reference in config entity (lightest weight)**
- Add a `system_prompt_file` field to the AiAgent config entity schema
- When `system_prompt_file` is set, `getSystemPrompt()` reads from that file path instead of the `system_prompt` field
- File path is relative to the Drupal root (e.g., `ai_agent_prompts/canvas_ai_orchestrator.md`)
- The `system_prompt` field becomes a fallback / cache
- Requires a small patch to `AiAgentEntityWrapper::getSystemPrompt()`

**Option B: Config override via settings.php**
- Use Drupal's `$config` override system in `settings.php` or `settings.local.php`
- Override `ai_agents.ai_agent.canvas_ai_orchestrator.system_prompt` with file contents
- Pro: No module changes. Con: Prompts loaded at config init, not lazy. Complex settings.php.

**Option C: Custom module with BuildSystemPromptEvent**
- Create `canvas_ai_prompts` module
- Subscribe to `BuildSystemPromptEvent` (like ai_context does)
- Read markdown files from a defined directory
- Replace the system prompt entirely for matching agent IDs
- Pro: Clean separation, no patches. Con: The event appends, doesn't replace -- would need to call `setSystemPrompt()` which replaces the entire prompt.

**Option D: Extend ai_context module**
- Create a new ai_context_item type called "agent_prompt"
- Store agent prompts as context items with a special scope
- Map them via `always_include` with a "replaces_system_prompt" flag
- Pro: Uses existing infrastructure. Con: Conflates context (supplementary) with prompts (primary).

**Recommended: Option C (custom module with BuildSystemPromptEvent)**

Rationale: `BuildSystemPromptEvent::setSystemPrompt()` already exists and can replace the full prompt. A custom module is the cleanest approach -- no patches to contrib, no conflation with context items, and the markdown files live in the repo as first-class entities. The module is small (one event subscriber, one service to load/parse markdown files).

**Acceptance criteria:** Option selected with documented rationale. Proof-of-concept tested with one agent (title_generation_agent as the simplest case).

### Phase 2: Build the Infrastructure

**Step 3: Implement the markdown prompt loader**

Create `web/modules/custom/canvas_ai_prompts/`:
- `canvas_ai_prompts.info.yml` -- module definition
- `canvas_ai_prompts.services.yml` -- service definitions
- `src/Service/AgentPromptLoader.php` -- loads and parses markdown files from a configured directory
- `src/EventSubscriber/AgentPromptSubscriber.php` -- subscribes to `BuildSystemPromptEvent`, loads markdown for the matching agent_id, applies token replacement, calls `setSystemPrompt()`

Prompt files directory: `ai_agent_prompts/` at the repo root (alongside `ai_context_data/`)

Key behaviors:
- Frontmatter parsed with a YAML parser (same as Drupal recipe parsing)
- Body treated as the system prompt text (NOT converted to HTML -- the prompt goes to the LLM as-is)
- Token replacement (e.g., `[canvas_ai:page_title]`) handled by the existing `applyTokens()` mechanism via the event
- If no markdown file exists for an agent, fall back to the config entity `system_prompt` (backward compatible)
- Event subscriber priority should be higher than ai_context (which runs at default priority) so the base prompt is set before context is appended

**Acceptance criteria:** Module loads prompts from markdown files. Token replacement works. Fallback to config entity works when no file exists. One agent (title_generation_agent) successfully uses a markdown-based prompt.

### Phase 3: Migration

**Step 4: Migrate existing prompts to markdown files**

Extract all agent system prompts from YAML configs into markdown files:

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

For each file:
1. Extract `system_prompt` from the YAML config
2. Add frontmatter with agent_id, label, description, token declarations
3. Clean up YAML escaping artifacts (convert `\r\n` to newlines, remove YAML `|-` block scalar syntax)
4. Verify the prompt content is identical post-migration (diff check)

**Acceptance criteria:** All 9 agent prompts migrated to markdown files. Each prompt produces identical LLM behavior (verified by running the driesnote demo). YAML configs still contain the prompts as fallback but the module overrides them.

### Phase 4: Developer Workflow

**Step 5: Document the developer workflow**

Create documentation for how developers edit agent prompts:

1. Edit the markdown file in `ai_agent_prompts/`
2. Clear Drupal cache (`ddev drush cr`) to pick up changes
3. Test the agent behavior in the Canvas UI
4. Commit the markdown file
5. PR review shows clean markdown diffs instead of YAML noise
6. Recipe export (`ddev export-ai-context`) is not needed for prompt changes -- they are file-based

**Acceptance criteria:** Developer workflow documented in `docs/guides/editing-agent-prompts.md`. At least one prompt change has been made via the new workflow and verified.

## Cross-References

- **WS1 (Efficiency):** WS1's prompt trimming (Steps 1 and 6) should be done FIRST in YAML, then the trimmed prompts are migrated to markdown in WS3 Phase 3. This avoids doing the same trimming work twice.
- **WS2 (Branching):** If WS2 restructures the orchestrator prompt for branching patterns, that restructured prompt should be the version migrated to markdown.
- **WS4 (Deploy):** WS4's deployment recipes need to include the `ai_agent_prompts/` directory and the `canvas_ai_prompts` module. The recipe structure may need a step to copy prompt files to the correct location.

## Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| `BuildSystemPromptEvent::setSystemPrompt()` doesn't fully replace (appends instead) | LOW | HIGH | Test with a simple agent first. If it appends, the event subscriber can clear the prompt first then set it. |
| Token replacement breaks when prompt is loaded from file | LOW | MEDIUM | Use the same `applyTokens()` method. Tokens are string replacements -- they work regardless of source. |
| Developers forget to clear cache after editing prompts | MEDIUM | LOW | Document clearly. Consider adding a file watcher in DDEV for development. |
| Recipe export overwrites the YAML prompts | MEDIUM | MEDIUM | The markdown files are the source of truth. YAML prompts are fallback only. Document this clearly. |
| Frontmatter parsing adds complexity | LOW | LOW | Use Symfony YAML component already available in Drupal. Frontmatter parsing is a few lines of code. |

## Success Criteria

1. All 9 agent prompts available as markdown files in `ai_agent_prompts/`
2. Custom module loads prompts from files at runtime
3. Backward compatible -- agents work without the module (fall back to YAML config)
4. PR diffs for prompt changes show clean markdown instead of YAML noise
5. Developer workflow documented and tested
6. No modifications to `web/modules/contrib/ai_agents/` or `web/modules/contrib/ai_context/`
