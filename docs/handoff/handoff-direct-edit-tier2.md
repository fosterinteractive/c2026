# Handoff: Direct Edit Stabilization and Tier 2

**Date:** 2026-03-29  
**Branch:** `codex/direct-edit-ui-integration`  
**Site:** `https://c2026.ddev.site`

## What Was Completed

### 1. Tier 1 direct-edit-first path is stabilized

- The frontend now tries `/admin/api/canvas/direct-edit` first for deterministic selected-SDC text edits.
- The first-submit `400` was root-caused and fixed.
- Root cause: `DirectEditController` validated component existence against `CanvasAiTempStore::COMPONENTS_IN_PAGE_WITH_PROP_VALUES_KEY`, but that key was only guaranteed to be primed after the normal AI controller path.
- Fix: [web/modules/custom/canvas_ai_scoping/src/Controller/DirectEditController.php](../../web/modules/custom/canvas_ai_scoping/src/Controller/DirectEditController.php) now seeds the same tempstore key from the existing client `layout` payload before validation.

### 2. Cold-start validation passed

Cold-start proof was run against the live site after:

1. rebuilding Canvas UI in DDEV
2. clearing Drupal caches
3. resetting `canvas_ai.tempstore`
4. reopening the editor fresh

Observed result:

- select heading
- open AI panel
- submit first deterministic prompt
- only `POST /admin/api/canvas/direct-edit`
- no `POST /admin/api/canvas/ai`
- preview heading updated immediately

### 3. Durable regression coverage added

- Local Playwright regression:
  [tests/playwright/direct-edit-cold-start.spec.ts](../../tests/playwright/direct-edit-cold-start.spec.ts)
- Local Playwright config:
  [tests/playwright/playwright.config.ts](../../tests/playwright/playwright.config.ts)
- Drupal-aware controller unit test:
  [web/modules/custom/canvas_ai_scoping/tests/src/Unit/DirectEditControllerTest.php](../../web/modules/custom/canvas_ai_scoping/tests/src/Unit/DirectEditControllerTest.php)

### 4. Tier 2 compound splitting implemented

- Matcher now detects compound deterministic edits before single-prop matching.
- It splits only on conservative boundaries:
  - conjunctions before an edit verb
  - commas before an edit verb
  - semicolons before an edit verb
- It rejects the whole message if:
  - any fragment fails Tier 1 matching
  - two fragments target the same prop

Files:

- [web/modules/custom/canvas_ai_scoping/src/Service/DirectEditMatcher.php](../../web/modules/custom/canvas_ai_scoping/src/Service/DirectEditMatcher.php)
- [web/modules/custom/canvas_ai_scoping/tests/src/Unit/DirectEditMatcherTest.php](../../web/modules/custom/canvas_ai_scoping/tests/src/Unit/DirectEditMatcherTest.php)
- [tests/intent-testing/tier2-compound-split.yml](../../tests/intent-testing/tier2-compound-split.yml)

## Patch / Config State

Contrib-side Canvas changes are still carried via patching:

- [patches/canvas/canvas-direct-edit-ui-routing.patch](../../patches/canvas/canvas-direct-edit-ui-routing.patch)
- [composer.json](../../composer.json)
- [patches.lock.json](../../patches.lock.json)

Notes:

- `composer.lock` was intentionally cleaned back out of this work because it contained unrelated dependency churn.
- The installed `web/modules/contrib/canvas/` tree was rebuilt during validation, but the source of truth remains the patch file above.

## Verification That Passed

### Browser

```bash
./web/modules/contrib/canvas/node_modules/.bin/playwright test \
  tests/playwright/direct-edit-cold-start.spec.ts \
  -c tests/playwright/playwright.config.ts \
  --project=chromium
```

Result: `1 passed`

### PHPUnit

```bash
ddev exec bash -lc 'cd /var/www/html && ./vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/canvas_ai_scoping/tests/src/Unit/DirectEditMatcherTest.php \
  web/modules/custom/canvas_ai_scoping/tests/src/Unit/DirectEditControllerTest.php'
```

Result: `41 tests, 107 assertions, OK`

## Remaining Work

### 1. Add browser coverage for Tier 2

The current local Playwright regression proves Tier 1 cold-start direct edit.  
It does **not** yet cover a compound prompt like:

- `change the heading to Welcome and set the color to blue`

Recommended next step:

- add a second Playwright spec or extend the existing one to assert:
  - one `POST /admin/api/canvas/direct-edit`
  - no `POST /admin/api/canvas/ai`
  - both heading text and color change

### 2. Decide whether to keep the local Playwright harness

Current browser regression lives under repo-local `tests/playwright/`.
That is pragmatic for this repo, but it is not an upstream-generic Canvas test harness.

Decision needed:

- keep it as repo-specific regression coverage
- or port the scenario into Canvas contrib’s own Playwright suite later

### 3. Commit hygiene

Stage only the direct-edit/Tier 2 files. Leave unrelated session artifacts alone.

## Known Risks

- Tier 2 has unit coverage but not live browser coverage yet.
- The local Playwright test targets the real demo site/editor path and uses `ddev drush`; it is durable for this repo but intentionally environment-specific.
- There are unrelated workspace/session files still dirty or untracked outside this workstream.
