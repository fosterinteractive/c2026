/**
 * @file Benchmark: Direct-edit endpoint latency and deterministic hit rate.
 *
 * Phase 1: One UI round-trip to prove the path works and capture session data.
 * Phase 2: N=12 direct API calls (2 warm-up + 10 measured) for server latency.
 * Phase 3: 20 mixed edits via API for hit rate measurement.
 *
 * Uses API calls (not UI interaction) for repeated measurements to isolate
 * server-side performance from Deep Chat component state management.
 *
 * Warm-up protocol: first 2 runs discarded (JIT, cache priming, connection pool).
 * Environment: DDEV local, single-tenant, no concurrent load.
 */
import { writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import {
  expect,
  test,
} from '../../web/modules/contrib/canvas/node_modules/@playwright/test';

const editorPath =
  process.env.DIRECT_EDIT_TEST_EDITOR_PATH || '/canvas/editor/canvas_page/13';
const activePreviewSelector =
  '[data-test-canvas-content-initialized="true"][data-canvas-swap-active="true"]';

const WARM_UP = 2;
const MEASURED = 10;

interface LatencyRun {
  run: number;
  warmUp: boolean;
  roundTripMs: number;
  status: number;
  message: string;
}

interface HitRateRun {
  message: string;
  expectedHit: boolean;
  status: number;
  actualHit: boolean;
  correct: boolean;
  roundTripMs: number;
}

interface Stats {
  n: number;
  mean: number;
  sd: number;
  ci95Lower: number;
  ci95Upper: number;
  median: number;
  min: number;
  max: number;
}

function runDrush(args: string[]): string {
  return execFileSync('ddev', ['drush', ...args], {
    cwd: process.cwd(),
    encoding: 'utf8',
  }).trim();
}

function computeStats(values: number[]): Stats {
  const n = values.length;
  if (n === 0) {
    return { n: 0, mean: 0, sd: 0, ci95Lower: 0, ci95Upper: 0, median: 0, min: 0, max: 0 };
  }
  const mean = values.reduce((a, b) => a + b, 0) / n;
  const sd = n > 1
    ? Math.sqrt(values.reduce((sum, v) => sum + (v - mean) ** 2, 0) / (n - 1))
    : 0;
  // t-critical for 95% CI, df=9 (N=10).
  const tCrit = 2.262;
  const margin = tCrit * (sd / Math.sqrt(n));
  const sorted = [...values].sort((a, b) => a - b);
  const median = n % 2 === 0
    ? (sorted[n / 2 - 1] + sorted[n / 2]) / 2
    : sorted[Math.floor(n / 2)];
  return {
    n,
    mean: Math.round(mean * 10) / 10,
    sd: Math.round(sd * 10) / 10,
    ci95Lower: Math.round((mean - margin) * 10) / 10,
    ci95Upper: Math.round((mean + margin) * 10) / 10,
    median: Math.round(median * 10) / 10,
    min: Math.round(Math.min(...values) * 10) / 10,
    max: Math.round(Math.max(...values) * 10) / 10,
  };
}

test('benchmark: direct-edit latency (N=12) + hit rate (20 mixed)', async ({
  page,
  baseURL,
}) => {
  test.setTimeout(300_000);

  // --- Phase 0: Setup ---
  runDrush(['state:set', 'canvas_ai_scoping.telemetry_enabled', '1']);
  runDrush([
    'php:eval',
    '$tempstore = \\Drupal::service("canvas_ai.tempstore"); $tempstore->deleteAll();',
  ]);

  const loginUrl = runDrush(['uli', '--no-browser']);
  await page.goto(loginUrl);
  await page.goto(`${baseURL}${editorPath}`);

  await expect(page.getByTestId('canvas-side-menu')).toBeAttached();
  await expect(page.getByTestId('canvas-topbar')).toBeAttached();
  await expect(page.locator(activePreviewSelector)).toBeAttached();

  // Select the heading component.
  const previewFrame = page.locator(activePreviewSelector).contentFrame();
  await previewFrame.locator('h1').first().click();
  await expect(page).toHaveURL(/\/component\//);

  // Open AI panel.
  await page.getByRole('button', { name: 'Open AI Panel' }).click();
  const promptBox = page.getByRole('textbox', { name: 'Build me a' });
  await expect(promptBox).toBeVisible();

  // --- Phase 1: One UI round-trip to capture CSRF token + component data ---
  let csrfToken = '';
  let componentUuid = '';
  let componentName = '';
  let layoutPayload = '';

  page.on('request', (req) => {
    if (
      req.url().includes('/admin/api/canvas/direct-edit') &&
      req.method() === 'POST'
    ) {
      csrfToken = req.headers()['x-csrf-token'] || '';
      try {
        const body = JSON.parse(req.postData() || '{}');
        if (!componentUuid) {
          componentUuid = body.component_uuid || '';
          componentName = body.component_name || '';
          layoutPayload = body.layout || '';
        }
      } catch { /* ignore */ }
    }
  });

  const proofHeading = `Proof ${Date.now()}`;
  const proofResponse = page.waitForResponse(
    (r) =>
      r.url().includes('/admin/api/canvas/direct-edit') &&
      r.request().method() === 'POST',
  );

  await promptBox.fill(`Change the heading to ${proofHeading}`);
  await promptBox.press('Enter');

  const uiResponse = await proofResponse;
  expect(uiResponse.status()).toBe(200);
  await expect(previewFrame.locator('h1').first()).toHaveText(proofHeading);

  // Verify we captured the session data.
  expect(csrfToken).not.toBe('');
  expect(componentUuid).not.toBe('');
  expect(componentName).toMatch(/^sdc\./);

  console.log(`\nCaptured: component=${componentName}, uuid=${componentUuid.slice(0, 8)}...`);

  // --- Phase 2: Direct API latency benchmark (N=12) ---
  const latencyRuns: LatencyRun[] = [];

  for (let i = 0; i < WARM_UP + MEASURED; i++) {
    const heading = `Bench ${i + 1} t${Date.now()}`;
    const message = `Change the heading to ${heading}`;
    const isWarmUp = i < WARM_UP;

    const start = performance.now();
    const response = await page.request.post(
      `${baseURL}/admin/api/canvas/direct-edit`,
      {
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        data: {
          message,
          component_uuid: componentUuid,
          component_name: componentName,
          layout: layoutPayload,
        },
      },
    );
    const roundTripMs = performance.now() - start;

    latencyRuns.push({
      run: i + 1,
      warmUp: isWarmUp,
      roundTripMs,
      status: response.status(),
      message,
    });
  }

  const measuredLatencies = latencyRuns
    .filter((r) => !r.warmUp)
    .map((r) => r.roundTripMs);
  const latencyStats = computeStats(measuredLatencies);

  // --- Phase 3: Hit rate measurement (20 mixed edits via API) ---
  const hitRateMessages: { message: string; expectedHit: boolean }[] = [
    // Deterministic (should return 200).
    { message: 'Change the heading to Welcome to FinDrop', expectedHit: true },
    { message: 'Set the color to blue', expectedHit: true },
    { message: 'Set the alignment to center', expectedHit: true },
    { message: 'Set the level to 3', expectedHit: true },
    { message: 'heading: Performance Test', expectedHit: true },
    { message: 'set color = primary', expectedHit: true },
    // "blue" is a natural alias, not a raw enum value — bare value inference
    // only indexes raw enum values in the reverse index. Use "primary" instead.
    { message: 'primary', expectedHit: true },
    { message: 'center', expectedHit: true },
    { message: 'make it primary', expectedHit: true },
    { message: 'Set the color to white', expectedHit: true },
    { message: 'Set the level to 1', expectedHit: true },
    { message: 'Change the heading to Hello and set the color to blue', expectedHit: true },
    // Non-deterministic (should return 422).
    { message: 'make this heading more engaging', expectedHit: false },
    { message: 'add a subtitle below this', expectedHit: false },
    { message: 'generate a catchy alternative title', expectedHit: false },
    { message: 'fix this', expectedHit: false },
    { message: 'rainbow', expectedHit: false },
    { message: 'make it look more professional', expectedHit: false },
    { message: 'create another heading', expectedHit: false },
    { message: 'can you suggest a better title?', expectedHit: false },
  ];

  const hitRateRuns: HitRateRun[] = [];

  for (const { message, expectedHit } of hitRateMessages) {
    const start = performance.now();
    const response = await page.request.post(
      `${baseURL}/admin/api/canvas/direct-edit`,
      {
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        data: {
          message,
          component_uuid: componentUuid,
          component_name: componentName,
          layout: layoutPayload,
        },
      },
    );
    const roundTripMs = performance.now() - start;
    const status = response.status();
    const actualHit = status === 200;

    hitRateRuns.push({
      message,
      expectedHit,
      status,
      actualHit,
      correct: actualHit === expectedHit,
      roundTripMs,
    });
  }

  const hits = hitRateRuns.filter((r) => r.actualHit).length;
  const misses = hitRateRuns.filter((r) => !r.actualHit).length;
  const hitRate = (hits / hitRateRuns.length) * 100;
  const allCorrect = hitRateRuns.every((r) => r.correct);
  const hitLatencies = hitRateRuns
    .filter((r) => r.actualHit)
    .map((r) => r.roundTripMs);
  const missLatencies = hitRateRuns
    .filter((r) => !r.actualHit)
    .map((r) => r.roundTripMs);

  // --- Phase 4: Report ---
  const report = {
    benchmark: 'direct-edit-latency-and-hit-rate',
    date: new Date().toISOString(),
    environment: {
      baseURL,
      editorPath,
      phpVersion: runDrush(['php:eval', 'echo phpversion();']),
      nodeVersion: process.version,
      component: componentName,
    },
    latency: {
      protocol: {
        totalRuns: WARM_UP + MEASURED,
        warmUpRuns: WARM_UP,
        measuredRuns: MEASURED,
        method: 'Direct API POST via Playwright request context (shared session)',
        note: 'First 2 runs discarded as warm-up (JIT, cache, connection pool)',
      },
      allRuns: latencyRuns,
      stats: latencyStats,
    },
    hitRate: {
      total: hitRateRuns.length,
      hits,
      misses,
      hitRatePercent: Math.round(hitRate * 10) / 10,
      allPredictionsCorrect: allCorrect,
      hitLatencyStats: computeStats(hitLatencies),
      missLatencyStats: computeStats(missLatencies),
      runs: hitRateRuns,
    },
    uiProof: {
      status: uiResponse.status(),
      heading: proofHeading,
      note: 'Single UI round-trip proving end-to-end Canvas integration',
    },
  };

  const reportPath = `${process.cwd()}/benchmark-results-${Date.now()}.json`;
  writeFileSync(reportPath, JSON.stringify(report, null, 2));

  // Console summary.
  console.log('\n========================================');
  console.log('  DIRECT-EDIT BENCHMARK RESULTS');
  console.log('========================================');
  console.log(`\nUI proof: ${uiResponse.status()} (heading: "${proofHeading}")`);
  console.log(`\nServer Latency (N=${MEASURED}, warm-up=${WARM_UP}):`);
  console.log(`  Mean:   ${latencyStats.mean}ms`);
  console.log(`  SD:     ${latencyStats.sd}ms`);
  console.log(`  Median: ${latencyStats.median}ms`);
  console.log(`  95% CI: [${latencyStats.ci95Lower}, ${latencyStats.ci95Upper}]ms`);
  console.log(`  Range:  [${latencyStats.min}, ${latencyStats.max}]ms`);
  console.log(
    `  All 200: ${latencyRuns.every((r) => r.status === 200) ? 'YES' : 'NO'}`,
  );
  console.log(`\nHit Rate (${hitRateRuns.length} mixed edits):`);
  console.log(`  Hits:   ${hits}/${hitRateRuns.length} (${hitRate.toFixed(1)}%)`);
  console.log(`  Misses: ${misses}/${hitRateRuns.length}`);
  console.log(`  Hit latency mean:  ${computeStats(hitLatencies).mean}ms`);
  console.log(`  Miss latency mean: ${computeStats(missLatencies).mean}ms`);
  console.log(`  All predictions correct: ${allCorrect ? 'YES' : 'NO'}`);
  if (!allCorrect) {
    const wrong = hitRateRuns.filter((r) => !r.correct);
    for (const r of wrong) {
      console.log(
        `    MISMATCH: "${r.message}" expected ${r.expectedHit ? '200' : '422'}, got ${r.status}`,
      );
    }
  }
  console.log(`\nReport: ${reportPath}`);
  console.log('========================================\n');

  // Assertions.
  expect(latencyRuns.every((r) => r.status === 200)).toBe(true);
  expect(latencyStats.mean).toBeLessThan(5000);
  expect(hitRate).toBeGreaterThan(50);
});
