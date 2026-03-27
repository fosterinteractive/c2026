# WS4: Stable Canvas Release + Deployment Recipes

**Status:** Draft
**Created:** 2026-03-26
**Estimated Scope:** LARGE (patch audit, upstream coordination, two deployment targets, recipe architecture)
**Dependencies:** WS1 (efficiency), WS3 (markdown config format)
**Unblocked by:** WS2 completion (WS2 results inform final architecture, but WS4 can start patch audit in parallel)

---

## Problem Statement

Canvas is pinned to a dev release (`1.x-dev#0bff26f`) with 3 local patches applied. This is fragile -- any upstream update could break patches, and deployment platforms (amazee.io, Drupal Forge) may not support dev releases or custom patches. The site also has a byte_theme patch and a Drupal core patch. Deployment requires platform-specific recipes for different infrastructure (Milvus vs PostgreSQL vector DB, different AI providers).

## Current State

### Patch Inventory

From `composer.json` (lines 113-125):

| Package | Patch | File | Purpose |
|---------|-------|------|---------|
| `drupal/canvas` | Combined 9-issue patch | `patches/canvas/issues-3549232-3533079-3545816-3558241-3548718-3551315-3569120-3571988-3541873.patch` | UI fixes, AI panel, component schema improvements |
| `drupal/canvas` | Content/performance | `patches/canvas/canvas-content-performance.patch` | AiPanel and AiWizard component updates |
| `drupal/canvas` | JSON-LD publishing fix | `patches/canvas/fix-long-json-in-schema_jsonld-field-blocks-page-publishing.patch` | Fixes large JSON-LD blocking page publishing |
| `drupal/byte_theme` | Icon card aspect ratio | `patches/byte_theme/remove-default-aspect-ration-from-icon-card-component.patch` | Remove default aspect ratio from icon card |
| `drupal/core` | Navigation 404 fix | `patches/drupal/navigation.patch` | Navigation fatal error on 404 pages (issue 3565886) |

### Canvas Version

- Pinned to `1.x-dev` with a specific commit hash
- The combined patch references 9 drupal.org issue numbers: 3549232, 3533079, 3545816, 3558241, 3548718, 3551315, 3569120, 3571988, 3541873
- Canvas UI assets must be built on the host (`npm install` + `npm run build`)

### Deployment Targets

**amazee.io:**
- Already has `ai_provider_amazeeio` module installed (recipe line 55)
- Config exists: `custom_recipes/findrop/config/ai_provider_amazeeio.settings.yml`
- Key config: `custom_recipes/findrop/config/key.key.amazeeio_ai_database.yml`
- PostgreSQL-based vector DB (no Milvus)
- Hosted LLM endpoint via amazee.io's AI proxy

**Drupal Forge:**
- Drupal's official hosting platform
- Infrastructure details need research
- May have different vector DB and AI provider options

### Current Infrastructure (DDEV)

- MariaDB 10.11
- PHP 8.3, nginx-fpm
- Milvus 2.5 (with etcd + MinIO) on port 19530
- Attu (Milvus UI) on port 8521

## Proposed Approach

### Phase 1: Patch Audit

**Step 1: Audit upstream status of all patches**

For each patch, check whether it has been merged upstream:

**Canvas combined patch (9 issues):**
Check each issue on drupal.org:
- 3549232, 3533079, 3545816, 3558241, 3548718, 3551315, 3569120, 3571988, 3541873
- For each: Is it committed? In which release? Still open?
- Document which patches are still needed vs. already in the latest dev

**Canvas content/performance patch:**
- Check if AiPanel/AiWizard changes have been upstreamed
- These are component-level changes that may need to be contributed as issues

**Canvas JSON-LD publishing fix:**
- This is a custom fix (per audit report). Check if there is a drupal.org issue for it
- If not, create one and submit the patch

**byte_theme patch:**
- Check if the icon card aspect ratio fix has been merged

**Drupal core navigation patch:**
- Issue 3565886 -- check if it is in Drupal 11.3.x

**Acceptance criteria:** Spreadsheet/table documenting upstream status of every patch. Each patch categorized as: MERGED (can remove), OPEN (still needed), NEEDS_CONTRIBUTION (our fix, not yet submitted).

**Step 2: Reduce patch surface area**

For patches that have been merged upstream:
1. Update Canvas to the latest dev release (or stable if available)
2. Remove merged patches from `composer.json`
3. Test that the site still works with fewer patches
4. For patches not yet upstream, submit them to drupal.org issue queues

For the JSON-LD publishing fix (our custom fix):
1. Create a drupal.org issue if one doesn't exist
2. Submit the patch as a merge request
3. Keep the local patch until it is merged

**Acceptance criteria:** Patch count reduced. All remaining patches have corresponding drupal.org issues. Site builds and passes the driesnote demo with the updated patch set.

### Phase 2: Canvas Release Tracking

**Step 3: Assess path to stable Canvas release**

Research:
- What is the Canvas module's release cycle?
- When is the next tagged release expected?
- What issues block a stable release?
- Can we pin to a tagged alpha/beta/RC instead of dev+commit?

If a stable release is imminent (within 1-2 months):
- Plan to upgrade when available
- Track blocking issues

If a stable release is distant:
- Pin to the latest dev with remaining patches
- Accept the fragility for now, but minimize patch count

**Acceptance criteria:** Canvas release timeline documented. Upgrade plan in place (either "wait for stable" or "pin to latest dev with minimal patches").

### Phase 3: Platform-Specific Deployment Recipes

**Step 4: Design recipe architecture**

Create a layered recipe structure:

```
custom_recipes/
  findrop/                    # Base recipe (current)
    recipe.yml
    config/
    content/
  findrop_amazeeio/           # amazee.io overlay
    recipe.yml                # Applies after base recipe
    config/                   # Platform-specific config overrides
  findrop_forge/              # Drupal Forge overlay
    recipe.yml
    config/
```

The base recipe installs all modules and content. Platform overlays:
- Override AI provider settings (which LLM endpoint to use)
- Override vector DB settings (Milvus vs PostgreSQL)
- Add platform-specific modules
- Set infrastructure-appropriate defaults

**Acceptance criteria:** Recipe directory structure created. Base recipe remains functional for DDEV. At least one overlay recipe is functional.

**Step 5: Build amazee.io deployment recipe**

The amazee.io overlay recipe needs to:

1. **AI Provider:** Configure `ai_provider_amazeeio` as the default provider. Disable or deprioritize `ai_provider_anthropic` and `ai_provider_openai` (these require direct API keys; amazee.io proxies through their endpoint).

2. **Vector DB:** Replace Milvus with PostgreSQL vector. This means:
   - Different search_api backend configuration
   - Different index settings
   - The `ai_vdb_provider_milvus` module may need to be swapped for a PostgreSQL vector provider
   - Check if `ai_search` supports PostgreSQL vector natively

3. **Infrastructure:** No DDEV-specific services (no Milvus containers, no etcd/MinIO)

4. **Environment variables:** Map amazee.io environment variables to Drupal config (API keys, database credentials)

5. **Canvas build:** Ensure Canvas UI assets are built during deployment (CI/CD step)

**Acceptance criteria:** amazee.io overlay recipe applies cleanly on top of base recipe. AI operations work through amazee.io's LLM proxy. Vector search works with PostgreSQL. No Milvus dependency.

**Step 6: Build Drupal Forge deployment recipe**

Research Drupal Forge's infrastructure first:
- What database does Forge provide? (MariaDB? PostgreSQL?)
- Does Forge support vector databases? If so, which?
- What AI provider options are available?
- What are Forge's deployment constraints (composer, npm build steps)?

Build the overlay recipe based on findings.

**Acceptance criteria:** Forge infrastructure documented. Overlay recipe created (if feasible given Forge's current capabilities). If Forge doesn't support vector DB, document the limitation and propose alternatives (external Milvus, degraded search).

### Phase 4: Integration Testing

**Step 7: End-to-end deployment verification**

For each deployment target:
1. Apply base recipe + platform overlay
2. Verify site installation completes
3. Run the driesnote demo (full page build)
4. Verify AI operations work (LLM calls succeed)
5. Verify vector search works (content indexing + RAG search)
6. Verify Canvas page building works (no regressions from patch changes)

**Acceptance criteria:** Each deployment target has a documented test protocol and passing results. Any platform-specific limitations are documented.

## Cross-References

- **WS1 (Efficiency):** Token efficiency is critical for amazee.io deployment where LLM costs may be metered differently. WS1 must achieve its target reduction before deployment recipes are finalized. The `max_loops` and `return_directly` settings from WS1 should be in the base recipe.
- **WS2 (Branching):** If WS2 produces a custom module (`canvas_ai_orchestration`), it must be included in the base recipe's install list. The module's config must be exported and included in the recipe.
- **WS3 (Markdown Config):** If WS3 produces a custom module (`canvas_ai_prompts`) and a `ai_agent_prompts/` directory, both must be included in deployment recipes. Platform overlays should NOT need to modify prompts (prompts are platform-independent). The recipe structure needs to handle file-based content (markdown prompts) alongside config exports.

## Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Canvas stable release is months away | HIGH | MEDIUM | Continue with dev pin. Minimize patches. Track upstream closely. |
| amazee.io PostgreSQL vector doesn't support all Milvus features | MEDIUM | HIGH | Test vector search quality early. Accept some search degradation if needed. |
| Drupal Forge doesn't support vector DB at all | HIGH | HIGH | Provide a degraded-mode recipe without vector search. AI agents still work, just without RAG image search. |
| Patch removal breaks functionality | MEDIUM | HIGH | Test each patch removal individually. Keep removed patches in a `patches/archive/` directory for rollback. |
| Platform overlay recipes become stale | MEDIUM | MEDIUM | Keep overlays minimal -- only platform-specific config. Base recipe handles all content and modules. |
| npm build step fails on deployment platforms | MEDIUM | HIGH | Pre-build Canvas assets and commit them. Or ensure CI/CD has Node.js 20.19+. |

## Success Criteria

1. Patch count reduced (ideally by 50%+ through upstream merges)
2. All remaining patches have drupal.org issues
3. amazee.io deployment recipe functional with PostgreSQL vector + hosted LLM
4. Drupal Forge deployment recipe created (or limitation documented)
5. Base recipe unchanged and still works in DDEV
6. End-to-end demo works on at least one deployment platform
7. Recipe architecture documented for future platform additions
