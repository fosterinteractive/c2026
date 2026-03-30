# Show & Prove: Execution Plan

**Date:** 2026-03-29
**Goal:** Demonstrable, testable, repeatable, unimpeachable proof that deterministic editing improves Canvas — and proof of AI's utility to the Drupal project.
**Key discovery:** Canvas frontend already calls `/admin/api/canvas/direct-edit` before falling back to AI. Our route is already wired in. No frontend patch needed.

---

## Strategic Framing (from Dries's Canvas 1.0 blog)

Dries said Canvas 1.0 is "step one" and called for community to "build on it, test it, and improve it." Our contribution is step two: making the AI assistant faster, cheaper, and more responsive for the operations that don't need reasoning. This isn't replacing the AI chain — it's complementing it, the same way Drupal's page cache complements the full bootstrap.

This project proves two things:
1. **Canvas can be significantly better** with targeted, measured optimizations
2. **AI (Claude) built those optimizations** — the prototype, the measurements, the testing, the upstream comments were all developed with AI assistance, demonstrating AI's utility as a development partner for the Drupal project

---

## Phase 1: Verify End-to-End (Day 1)

**Goal:** Confirm the direct-edit path works live in the Canvas editor.

### 1.1 Smoke Test (manual, via Playwright)
- Navigate to `https://c2026.ddev.site/admin/canvas-page/8/edit`
- Open the Canvas editor, select a heading component
- Type "change the heading to Welcome to FinDrop"
- **Expected:** Instant response (<100ms), heading updates, no AI spinner
- Type "write a catchy headline for this section"
- **Expected:** 422 from our endpoint, Canvas falls through to AI chain

### 1.2 Verify Request/Response Format
Our `DirectEditController` response format must match what `directEdit.ts` expects:
```json
{
  "status": true,
  "direct_edit": true,
  "operations": [...],
  "matched_prop": "heading_text",
  "message": "Changed heading_text to Welcome to FinDrop"
}
```
Check that `operationsHandler` in `AiWizard.tsx` correctly processes our `operations` array. The `includeUpdateOperations()` call in our controller should produce the right format since it uses the same `CanvasAiPageBuilderHelper`.

### 1.3 Fix Any Gaps
- Verify CSRF token: frontend fetches from `/admin/api/canvas/token` — our controller validates with `canvas_ai.canvas_builder` seed. Confirm these match.
- Verify `layout` field: the frontend sends `body.layout` — our controller reads it at line 134.
- Verify component selection state: `active_component_uuid` must be set in tempstore before our endpoint is called.

### Deliverable
- [ ] Playwright recording: deterministic edit resolves instantly
- [ ] Playwright recording: non-deterministic edit falls through to AI
- [ ] Log excerpt showing 0 tokens for deterministic vs ~101K for AI

---

## Phase 2: Benchmark Suite (Day 1-2)

**Goal:** Automated, repeatable before/after comparison anyone can run.

### 2.1 Benchmark Script: `ddev benchmark-direct-edit`

A drush command or shell script that:
1. Loads a canvas page in the editor (via Playwright)
2. Runs a matrix of edits with the module **enabled**:
   - 10 deterministic edits (Tier 1, Tier 2, Phase 1-3)
   - 5 AI-required edits (content generation, ambiguous)
3. Disables the module (`drush pm:uninstall canvas_ai_scoping`)
4. Runs the same 15 edits through the AI chain
5. Compares: latency, token count, cost

### 2.2 Metrics Captured Per Edit

| Metric | Source | How |
|--------|--------|-----|
| Wall-clock latency | Playwright `performance.now()` | Measure from send to UI update |
| Token count | `TokenBreakdownSubscriber` logs | Parse Drupal watchdog |
| Match tier | `DirectEditController` response | `direct_edit: true` + `matched_prop` |
| API cost | Token count × model pricing | Calculated |

### 2.3 Output Format

Markdown table + JSON artifact:
```
## Benchmark Results (2026-03-29, FinDrop Travel)
| Edit | With Module | Without Module | Savings |
|------|-------------|----------------|---------|
| "change heading to X" | 6ms, 0 tokens | 18.2s, 101K tokens | 100% |
| "make it blue" | 4ms, 0 tokens | 15.8s, 98K tokens | 100% |
| "write a catchy headline" | 16.1s, 101K tokens | 16.3s, 101K tokens | ~0% |
```

### Deliverable
- [ ] `scripts/benchmark-direct-edit.sh` — runnable by anyone with DDEV
- [ ] `docs/benchmarks/baseline-results.md` — first run results
- [ ] JSON artifact for programmatic comparison

---

## Phase 3: E2E Test Suite (Day 2-3)

**Goal:** Playwright tests that prove every claim in the upstream comments.

### 3.1 Test Matrix

```
tests/playwright/
  direct-edit-e2e.spec.ts          — Full E2E through Canvas UI
  direct-edit-benchmark.spec.ts    — Automated before/after timing
  direct-edit-fallback.spec.ts     — 422 → AI chain handoff
  loop-aware-context.spec.ts       — Token count before/after
```

### 3.2 `direct-edit-e2e.spec.ts`

| Test | Input | Expected |
|------|-------|----------|
| Tier 1: explicit edit | "change the heading to Hello" | Instant update, `direct_edit: true` in response |
| Tier 1: colon format | "heading: New Title" | Instant update |
| Tier 2: compound | "change heading to X and set color to blue" | Both props update instantly |
| Phase 1: bare value | "blue" (heading selected) | Color changes to primary |
| Phase 2: boolean | "show the header" (section selected) | Header appears |
| Phase 3: relative | "bigger" (heading selected) | Text size increases one step |
| Rejection → AI | "write a better headline" | AI spinner appears, agent processes |
| Unknown component | Edit on non-Byte-theme component | Falls through to AI |

### 3.3 `direct-edit-benchmark.spec.ts`

For each deterministic edit:
1. Record `performance.now()` before sending
2. Wait for DOM update (component re-renders)
3. Record `performance.now()` after
4. Assert latency < 500ms (generous for CI)
5. Assert response contains `direct_edit: true`
6. Assert response contains `tokens_used: 0`

### 3.4 `loop-aware-context.spec.ts`

1. Send a non-deterministic edit (forces AI chain)
2. Parse Drupal logs for `TokenBreakdown` entries
3. Verify ai_context bytes on loop 0 > 0
4. Verify ai_context bytes on loop 1+ = 0 (stripped by LoopAwareContextSubscriber)
5. Record total tokens for the operation

### Deliverable
- [ ] 4 Playwright spec files with 15+ E2E tests
- [ ] CI-compatible (generous timeouts, no flaky selectors)
- [ ] Results artifact (JSON + human-readable)

---

## Phase 4: Contribution-Ready Patches (Day 3-4)

### 4.1 ai_context Patch (P2 — Loop-Aware Injection)

**Target:** `drupal/ai_context` module
**Change:** Add `loop_aware` boolean to per-agent context configuration

Files to modify:
- `SystemPromptSubscriber.php` — check loop count, skip injection on loop > 0 when `loop_aware` is set
- `ai_context.schema.yml` — add `loop_aware` to agent context config schema
- Config entity/form — expose the toggle in admin UI
- Test coverage — unit test for the skip logic

**Patch format:** `git diff` against the current ai_context HEAD, applicable with `git apply`.

### 4.2 canvas_ai Patch (P4 — Deterministic Edit + P1 — Layout Scoping)

**Target:** `drupal/canvas_ai` module (Canvas AI submodule)
**Changes:**

1. **DirectEditMatcher service** — extracted from our custom module, theme name made configurable via settings
2. **DirectEditController** — route at `/admin/api/canvas/direct-edit` (matches existing frontend)
3. **ComponentSchemaLoader** — dynamic theme discovery instead of hardcoded `byte_theme`
4. **LayoutScopingSubscriber** — region scoping for BuildSystemPromptEvent

Files to create/modify:
- `canvas_ai.services.yml` — register new services
- `canvas_ai.routing.yml` — add direct-edit route
- `src/Service/DirectEditMatcher.php`
- `src/Service/ComponentSchemaLoader.php`
- `src/Controller/DirectEditController.php`
- `src/EventSubscriber/LayoutScopingSubscriber.php`
- Tests for all new code

### 4.3 canvas Patch (Structured Layout API)

**Target:** `drupal/canvas` module (core Canvas)
**Change:** Add `getLayoutData()`/`setLayoutData()` to `BuildSystemPromptEvent`

This is the smallest, cleanest patch — it just exposes the layout as structured data instead of requiring string surgery. Enables both our scoping and any future layout manipulation by other modules.

### Deliverable
- [ ] 3 patch files in `patches/` directory
- [ ] Each patch includes tests
- [ ] README with apply instructions
- [ ] Tested against current contrib HEAD

---

## Phase 5: Demo Package (Day 4-5)

### 5.1 One-Command Demo

```bash
git clone [repo] && cd c2026
cp .env.template .ddev/.env  # add API keys
ddev demo-setup              # installs everything
ddev benchmark-direct-edit   # runs the benchmark
```

Output: benchmark results table showing deterministic vs AI path.

### 5.2 Demo Script (for live presentation)

1. Open `https://c2026.ddev.site/admin/canvas-page/8/edit`
2. Select the hero heading
3. Type: "change the heading to Welcome to FinDrop" → **instant** (show network tab: 0 tokens)
4. Type: "make it blue" → **instant** (bare value inference)
5. Type: "bigger" → **instant** (relative adjustment)
6. Type: "write a headline that captures the excitement of financial freedom" → AI processes (show: 101K tokens, ~15s)
7. Show benchmark results: 15 edits, X seconds saved, Y tokens saved, $Z saved

### 5.3 Upstream Narrative

The demo tells a story Dries already started:
- Canvas 1.0 was "step one" — making page building visual
- The AI assistant is the next frontier — but it's expensive and slow for simple edits
- Deterministic routing is the bridge: instant for the simple stuff, AI for the creative stuff
- **And this entire solution was built, measured, and tested with AI assistance** — proof of AI's utility to the project itself

### Deliverable
- [ ] `docs/demo-script.md` — step-by-step with expected results
- [ ] Updated `CLAUDE.md` with demo commands
- [ ] Updated handoff note

---

## Success Criteria

| Criterion | Measurement | Target |
|-----------|-------------|--------|
| E2E deterministic edit works in Canvas UI | Playwright test | Pass |
| Deterministic latency | Wall-clock in browser | < 500ms |
| AI fallback works | Playwright test | Pass |
| Token savings (deterministic) | TokenBreakdown logs | 100% (0 tokens) |
| Token savings (loop-aware) | TokenBreakdown logs | > 40% |
| Test suite passes | `phpunit` + `playwright` | 126+ unit, 15+ E2E |
| Benchmark is reproducible | Run on fresh DDEV | Same directional results |
| Patches apply cleanly | `git apply` on contrib HEAD | No conflicts |

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| CSRF token mismatch between Canvas frontend and our controller | E2E blocked | Verify token seed matches in Phase 1.3 |
| `operationsHandler` expects different response shape | UI doesn't update | Compare our response to AI endpoint response |
| Tempstore not populated on first edit (cold start) | 422 on first try | Our controller accepts `layout` field to seed tempstore |
| Canvas dev release changes frontend code | Tests break | Pin Canvas version in composer.json |
| AI endpoint requires API key not configured | Benchmark incomplete | `.env.template` documents required keys |

---

## Dependency Chain

```
Phase 1 (verify E2E)
  ↓
Phase 2 (benchmark suite) ← needs working E2E
  ↓
Phase 3 (E2E test suite) ← needs benchmark patterns
  ↓
Phase 4 (patches) ← needs all tests passing as evidence
  ↓
Phase 5 (demo package) ← needs everything above
```

Phase 1 is the critical path. If the E2E smoke test fails, everything else blocks until we fix the integration gap.
