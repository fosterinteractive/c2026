# WS4: Stable Canvas Release + Deployment Recipes

**Revision: v2 — Revised based on proposal-critic feedback (2026-03-27)**

**Status:** Draft
**Created:** 2026-03-26
**Estimated Scope:** LARGE (patch audit, upstream coordination, two deployment targets, recipe architecture, security prerequisites)
**Dependencies:** WS1 (efficiency), WS3 (markdown config format)
**Unblocked by:** WS2 completion (WS2 results inform final architecture, but WS4 can start security gate and patch audit in parallel)

---

## Changes from v1

1. **Added Phase 0: Security Gate** — the static audit found critical security issues (XSS in JSON-LD injection, component agent JS generation with no XSS prevention, hardcoded credentials). These are blocking prerequisites for any production deployment. Phase 0 addresses them before anything else.
2. **Addressed plaintext API keys** — `key.key.amazeeio_ai.yml` and `key.key.amazeeio_ai_database.yml` contain credentials committed to the repository. These must be moved to environment variables before building deployment recipes on top of this pattern.
3. **Added patch decomposition step** — the combined 9-issue Canvas patch is monolithic and cannot be tested for individual issue removal. Added a step to assess whether decomposition is needed.
4. **Conditionally scoped Drupal Forge** — if Forge cannot support vector DBs, document limitations instead of building a full recipe. Forge research is timeboxed to 1 day.
5. **Added explicit security dependencies** — WS4 cannot ship to production until the component agent review gate and JSON-LD sanitization are addressed.

---

## Problem Statement

Canvas is pinned to a dev release (`1.x-dev#0bff26f`) with 3 local patches applied. This is fragile -- any upstream update could break patches, and deployment platforms (amazee.io, Drupal Forge) may not support dev releases or custom patches. The site also has a byte_theme patch and a Drupal core patch. Deployment requires platform-specific recipes for different infrastructure (Milvus vs PostgreSQL vector DB, different AI providers).

**Additionally:** The site has critical security issues that must be resolved before any production deployment. The static audit identified XSS in JSON-LD injection, a component agent that generates browser-executable JavaScript with no security guardrails, and plaintext API keys committed to the repository. Shipping deployment recipes without addressing these would deploy a vulnerable application.

## Current State

### Security Issues (BLOCKING for production)

From the static audit (`docs/audit/canvas-agent-static-audit.md`):

| Issue | Severity | File | Status |
|-------|----------|------|--------|
| XSS in JSON-LD injection — LLM output injected into `<script>` tag without sanitization | CRITICAL | `web/modules/custom/canvas_ai_seo/src/Hook/CanvasAiSeoHooks.php:62-67` | Partially fixed (needs verification) |
| Component agent generates browser-executable JS with no XSS prevention | CRITICAL | `ai_agents.ai_agent.canvas_component_agent.yml` | Open |
| Hardcoded GA credentials path in source | CRITICAL | `web/modules/custom/ai_google_analytics/src/Plugin/AiFunctionCall/GoogleAnalytics.php:43` | Open (dead code but exposes filename) |
| Plaintext API key in recipe config | CRITICAL | `custom_recipes/findrop/config/key.key.amazeeio_ai.yml` (contains `sk-kCf6l7...`) | Open |
| Plaintext database credential in recipe config | CRITICAL | `custom_recipes/findrop/config/key.key.amazeeio_ai_database.yml` (contains `660fe08...`) | Open |
| Hardcoded GA date range (stale) | HIGH | `GoogleAnalytics.php:63-66` | Open |

### Patch Inventory

From `composer.json` (lines 113-125):

| Package | Patch | File | Purpose |
|---------|-------|------|---------|
| `drupal/canvas` | Combined 9-issue patch | `patches/canvas/issues-3549232-3533079-3545816-3558241-3548718-3551315-3569120-3571988-3541873.patch` | UI fixes, AI panel, component schema improvements |
| `drupal/canvas` | Content/performance | `patches/canvas/canvas-content-performance.patch` | AiPanel and AiWizard component updates |
| `drupal/canvas` | JSON-LD publishing fix | `patches/canvas/fix-long-json-in-schema_jsonld-field-blocks-page-publishing.patch` | Fixes large JSON-LD blocking page publishing |
| `drupal/byte_theme` | Icon card aspect ratio | `patches/byte_theme/remove-default-aspect-ration-from-icon-card-component.patch` | Remove default aspect ratio from icon card |
| `drupal/core` | Navigation 404 fix | `patches/drupal/navigation.patch` | Navigation fatal error on 404 pages (issue 3565886) |

**Note on the combined 9-issue patch:** This patch is monolithic -- it addresses 9 separate drupal.org issues in a single diff. It cannot be tested for individual issue removal because removing one fix may break the others. The `creating_patch_for_canvas/` directory contains tooling to regenerate the combined patch from individual source files.

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
- Infrastructure details need research (timeboxed)
- May have different vector DB and AI provider options

### Current Infrastructure (DDEV)

- MariaDB 10.11
- PHP 8.3, nginx-fpm
- Milvus 2.5 (with etcd + MinIO) on port 19530
- Attu (Milvus UI) on port 8521

## Proposed Approach

### Phase 0: Security Gate (BLOCKING — must complete before any deployment recipe work)

**Step 0a: Remove plaintext credentials from the repository**

The following files contain credentials that must not be in version control:

1. `custom_recipes/findrop/config/key.key.amazeeio_ai.yml` — contains an API key
2. `custom_recipes/findrop/config/key.key.amazeeio_ai_database.yml` — contains a database credential

Actions:
1. Rotate the exposed credentials (they are compromised once committed to git history)
2. Modify the key config entities to use environment variable providers instead of plaintext values. Drupal's Key module supports `env:VARIABLE_NAME` key providers.
3. Add `key.key.amazeeio_ai.yml` and `key.key.amazeeio_ai_database.yml` to `.gitignore` (or modify them to reference env vars only)
4. Update the deployment recipes to document required environment variables
5. Update `.env.template` to include the amazee.io key variables

**Acceptance criteria:** No plaintext credentials in the repository. Key config entities reference environment variables. `.env.template` documents all required secrets. Credentials rotated.

**Step 0b: Address JSON-LD XSS**

`CanvasAiSeoHooks.php:62-67` injects LLM-generated JSON-LD into a `<script type="application/ld+json">` tag. If the LLM generates `</script><script>alert(1)</script>`, it executes arbitrary JavaScript.

Actions:
1. Verify the current state of the partial fix (the audit notes it was partially addressed)
2. Ensure JSON-LD content is sanitized: at minimum, escape `</script>` sequences within the JSON string
3. Consider using `json_encode()` with `JSON_HEX_TAG` flag to escape `<` and `>` characters
4. Add a test that verifies malicious JSON-LD is sanitized

**Acceptance criteria:** JSON-LD injection is safe. A test proves that `</script>` sequences in LLM output are neutralized. No XSS possible via the JSON-LD path.

**Step 0c: Add security guardrails to the component agent**

The `canvas_component_agent` generates React/Preact JavaScript that is rendered in the browser. Its prompt has no XSS prevention rules, no CSP guidance, and no restrictions on `eval()`, `innerHTML`, or other dangerous patterns.

Actions:
1. Add security rules to the component agent's system prompt:
   - "NEVER use `eval()`, `Function()`, `innerHTML`, `outerHTML`, or `document.write()`"
   - "NEVER generate code that fetches external resources (no external script tags, no fetch to third-party domains)"
   - "All user-provided content must be rendered via React's JSX (which auto-escapes) — never via `dangerouslySetInnerHTML`"
   - "Do not generate code that accesses `document.cookie`, `localStorage`, or `sessionStorage`"
2. Add a post-generation validation step: a simple regex check on the generated JS for banned patterns (`eval(`, `innerHTML`, `document.write`, etc.)
3. Document the security model in `docs/security/component-agent-security.md`

**Acceptance criteria:** Component agent prompt includes security rules. Post-generation validation catches banned patterns. Security model documented. This does not need to be bulletproof for a demo -- it needs to prevent the most obvious attack vectors.

**Step 0d: Clean up remaining security issues**

1. Remove the hardcoded GA credentials path from `GoogleAnalytics.php:43` (dead code)
2. Fix the hardcoded GA date range (`GoogleAnalytics.php:63-66`) — use a dynamic date range
3. Add dependency injection to `GoogleAnalytics.php` (replace static `\Drupal::` calls)

**Acceptance criteria:** No hardcoded credentials paths in source. GA date range is dynamic. GoogleAnalytics service uses DI.

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

**Acceptance criteria:** Table documenting upstream status of every patch. Each patch categorized as: MERGED (can remove), OPEN (still needed), NEEDS_CONTRIBUTION (our fix, not yet submitted).

**Step 2: Reduce patch surface area**

For patches that have been merged upstream:
1. Update Canvas to the latest dev release (or stable if available)
2. Remove merged patches from `composer.json`
3. Test that the site still works with fewer patches
4. For patches not yet upstream, submit them to drupal.org issue queues

**Handling the monolithic combined patch:**
The combined 9-issue patch cannot be tested for individual issue removal because it is a single diff. Two approaches:

A. **Test as a unit:** If ALL 9 issues are merged upstream, remove the entire combined patch. If any are still open, keep the entire patch.
B. **Decompose if needed:** If some issues are merged and others are not, use the `creating_patch_for_canvas/` tooling to regenerate a smaller combined patch containing only the unmerged fixes. This requires verifying that the remaining fixes apply cleanly without the merged ones.

For the JSON-LD publishing fix (our custom fix):
1. Create a drupal.org issue if one does not exist
2. Submit the patch as a merge request
3. Keep the local patch until it is merged

**Acceptance criteria:** Patch count reduced. All remaining patches have corresponding drupal.org issues. Site builds and standard page build test passes with the updated patch set.

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
  findrop_forge/              # Drupal Forge overlay (conditional — see Step 6)
    recipe.yml
    config/
```

The base recipe installs all modules and content. Platform overlays:
- Override AI provider settings (which LLM endpoint to use)
- Override vector DB settings (Milvus vs PostgreSQL)
- Add platform-specific modules
- Set infrastructure-appropriate defaults
- Reference environment variables for credentials (no plaintext keys)

**Acceptance criteria:** Recipe directory structure created. Base recipe remains functional for DDEV. Recipe architecture documented.

**Step 5: Build amazee.io deployment recipe**

The amazee.io overlay recipe needs to:

1. **AI Provider:** Configure `ai_provider_amazeeio` as the default provider. Set provider config to reference environment variables for API keys (using Key module's env provider).

2. **Vector DB:** Replace Milvus with PostgreSQL vector. This is the hardest technical problem in the plan:
   - Different search_api backend configuration
   - Different index settings
   - Check if `ai_search` supports PostgreSQL vector natively (the `ai_vdb_provider_milvus` module may need to be swapped for a PostgreSQL vector provider)
   - If PostgreSQL vector is not fully supported by the current ai_search module, document the gap and propose alternatives (external Milvus service, degraded search without vector)
   - **Timebox the vector DB swap investigation to 3 days.** If it proves infeasible within that timeframe, document the limitation and ship the overlay without vector search.

3. **Infrastructure:** No DDEV-specific services (no Milvus containers, no etcd/MinIO)

4. **Environment variables:** Document all required env vars:
   - AI provider API key
   - Database credentials
   - Any platform-specific configuration
   - Add an `.env.amazeeio.template` file listing all required variables

5. **Canvas build:** Ensure Canvas UI assets are built during deployment:
   - Option A: Pre-build assets and commit them (simplest for deployment)
   - Option B: Ensure CI/CD has Node.js 20.19+ and runs `npm install` + `npm run build` in the canvas module directory

6. **Include WS1/WS2/WS3 outputs:**
   - WS1's `canvas_ai_efficiency` module (token budget enforcement)
   - WS2's custom module (if produced -- automatic SEO trigger)
   - WS3's prompt mechanism (ai_context entities for Option D, or `canvas_ai_prompts` module for Option C)

**Acceptance criteria:** amazee.io overlay recipe applies cleanly on top of base recipe. AI operations work through amazee.io's LLM proxy. Vector search works with PostgreSQL (or limitation documented and accepted). No Milvus dependency. No plaintext credentials in the recipe.

**Step 6: Assess Drupal Forge deployment (timeboxed to 1 day)**

Research Drupal Forge's infrastructure:
- What database does Forge provide? (MariaDB? PostgreSQL?)
- Does Forge support vector databases? If so, which?
- What AI provider options are available?
- What are Forge's deployment constraints (composer, npm build steps)?

**If Forge supports the requirements:**
Build the overlay recipe based on findings (follow the same pattern as amazee.io).

**If Forge does not support vector DB:**
Document the limitation. Create a minimal overlay that:
- Disables vector search features (no ai_search, no Milvus)
- Configures whatever AI provider Forge supports
- Documents what features are available vs. degraded

**If Forge's capabilities are unclear after 1 day of research:**
Document what was found. Create a placeholder recipe directory with a README explaining the gap. Do not block WS4 on Forge research.

**Acceptance criteria:** Forge infrastructure documented. Either: (a) overlay recipe created, or (b) limitations documented with a clear statement of what is and is not possible on Forge.

### Phase 4: Integration Testing

**Step 7: End-to-end deployment verification**

For each deployment target:
1. Apply base recipe + platform overlay
2. Verify site installation completes
3. Run a standard page build test (create a product page with 3+ sections and images)
4. Verify AI operations work (LLM calls succeed, responses are coherent)
5. Verify vector search works (content indexing + search query returns results)
6. Verify Canvas page building works (no regressions from patch changes)
7. Verify security fixes are active:
   - JSON-LD sanitization is in place
   - Component agent security rules are active
   - No plaintext credentials in deployed config
   - Environment variables are correctly resolved

**Acceptance criteria:** Each deployment target has a documented test protocol and passing results. Any platform-specific limitations are documented.

## Cross-References

- **WS1 (Efficiency):** Token efficiency is critical for amazee.io deployment where LLM costs may be metered differently. WS1 must achieve its target reduction before deployment recipes are finalized. The `max_loops`, SEO nesting mitigations, and token budget enforcement from WS1 should be in the base recipe. If WS1 is delayed, WS4 can proceed with Phase 0 (security) and Phase 1 (patch audit) but cannot finalize deployment recipes.
- **WS2 (Branching):** If WS2 produces a custom module (automatic SEO trigger), it must be included in the base recipe's install list. If WS2 is not complete when WS4 ships, the deployment recipe works without it (the orchestrator handles SEO manually as it does today).
- **WS3 (Markdown Config):** If WS3 produces ai_context entities (Option D) or a custom module (Option C), these must be in the deployment artifacts. If WS3 is not complete, the deployment recipe uses YAML-embedded prompts (the current state). WS4 does not depend on WS3 completing.

## Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Canvas stable release is months away | HIGH | MEDIUM | Continue with dev pin. Minimize patches. Track upstream closely. |
| amazee.io PostgreSQL vector does not support all Milvus features | MEDIUM | HIGH | Timebox investigation to 3 days. Accept degraded search if needed. Document gaps. |
| Drupal Forge does not support vector DB at all | HIGH | MEDIUM | Provide a degraded-mode recipe without vector search. AI agents still work, just without RAG image search. |
| Patch removal breaks functionality | MEDIUM | HIGH | Test the combined patch as a unit (remove all or none). Keep removed patches in a `patches/archive/` directory for rollback. |
| Platform overlay recipes become stale | MEDIUM | MEDIUM | Keep overlays minimal -- only platform-specific config. Base recipe handles all content and modules. |
| npm build step fails on deployment platforms | MEDIUM | HIGH | Pre-build Canvas assets and commit them as the default approach. |
| Security fixes for component agent are incomplete | MEDIUM | HIGH | The prompt-based security rules are a first layer, not a complete solution. Document this as a known limitation for the demo. For production, a runtime JS validation step would be needed (out of scope for demo deployment). |
| WS1/WS2/WS3 not complete when WS4 ships | MEDIUM | LOW | Each dependency has a fallback: WS1 not done = deploy with current token usage (expensive but functional). WS2 not done = no automatic SEO trigger. WS3 not done = YAML-embedded prompts. |

## Success Criteria

1. **Phase 0 (Security):** All critical security issues addressed. No plaintext credentials in the repository. JSON-LD XSS fixed with test. Component agent has security guardrails.
2. Patch count reduced (ideally by 50%+ through upstream merges)
3. All remaining patches have drupal.org issues
4. amazee.io deployment recipe functional with PostgreSQL vector + hosted LLM (or vector limitation documented)
5. Drupal Forge deployment assessed (recipe created or limitations documented)
6. Base recipe unchanged and still works in DDEV
7. End-to-end demo works on at least one deployment platform
8. Recipe architecture documented for future platform additions
9. All deployment recipes reference environment variables for credentials (no hardcoded secrets)
