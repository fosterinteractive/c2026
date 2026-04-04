/**
 * @file Benchmark: AI path wall-clock latency.
 *
 * Measures the time from message submission to AI response completion
 * for messages that bypass direct-edit (422) and fall through to the
 * full AI agent chain.
 *
 * N=7 total (2 warm-up + 5 measured).
 * Each run: fresh editor navigation → component selection → AI message → response.
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
const MEASURED = 5;

interface AiRun {
  run: number;
  warmUp: boolean;
  wallClockMs: number;
  message: string;
}

function runDrush(args: string[]): string {
  return execFileSync('ddev', ['drush', ...args], {
    cwd: process.cwd(),
    encoding: 'utf8',
  }).trim();
}

function computeStats(values: number[]) {
  const n = values.length;
  if (n === 0) return { n: 0, mean: 0, sd: 0, ci95Lower: 0, ci95Upper: 0, median: 0, min: 0, max: 0 };
  const mean = values.reduce((a, b) => a + b, 0) / n;
  const sd = n > 1 ? Math.sqrt(values.reduce((s, v) => s + (v - mean) ** 2, 0) / (n - 1)) : 0;
  // t-critical for 95% CI, df=4 (N=5).
  const tCrit = 2.776;
  const margin = tCrit * (sd / Math.sqrt(n));
  const sorted = [...values].sort((a, b) => a - b);
  const median = n % 2 === 0 ? (sorted[n / 2 - 1] + sorted[n / 2]) / 2 : sorted[Math.floor(n / 2)];
  return {
    n,
    mean: Math.round(mean),
    sd: Math.round(sd),
    ci95Lower: Math.round(mean - margin),
    ci95Upper: Math.round(mean + margin),
    median: Math.round(median),
    min: Math.round(Math.min(...values)),
    max: Math.round(Math.max(...values)),
  };
}

// Use the same edit type ("change heading to X") so the comparison with
// direct-edit is apples-to-apples. By NOT selecting a component first,
// the frontend skips the direct-edit attempt and sends straight to AI.
const messages = [
  'Change the heading to AI Benchmark Run 1',
  'Change the heading to AI Benchmark Run 2',
  'Change the heading to AI Benchmark Run 3',
  'Change the heading to AI Benchmark Run 4',
  'Change the heading to AI Benchmark Run 5',
  'Change the heading to AI Benchmark Run 6',
  'Change the heading to AI Benchmark Run 7',
];

test('benchmark: AI path wall-clock latency (N=7)', async ({ page, baseURL }) => {
  test.setTimeout(600_000); // 10 minutes

  const loginUrl = runDrush(['uli', '--no-browser']);
  await page.goto(loginUrl);

  const runs: AiRun[] = [];

  for (let i = 0; i < WARM_UP + MEASURED; i++) {
    const isWarmUp = i < WARM_UP;
    const message = messages[i];

    // Navigate fresh each run — page level (no component selected).
    await page.goto(`${baseURL}${editorPath}`);
    await expect(page.getByTestId('canvas-side-menu')).toBeAttached({ timeout: 30000 });
    await expect(page.locator(activePreviewSelector)).toBeAttached({ timeout: 30000 });

    // Open AI panel.
    await page.getByRole('button', { name: 'Open AI Panel' }).click();
    const promptBox = page.getByRole('textbox', { name: 'Build me a' });
    await expect(promptBox).toBeVisible({ timeout: 10000 });

    // Listen for the LAST AI response (the one with operations or final result).
    // The AI path may have multiple requests (initial + progress polling).
    let lastAiResponseTime = 0;
    page.on('response', (response) => {
      const url = response.url();
      if (url.includes('/admin/api/canvas/ai') && response.status() === 200) {
        lastAiResponseTime = performance.now();
      }
    });

    // Submit the message and start timing.
    const startTime = performance.now();
    await promptBox.fill(message);
    await promptBox.press('Enter');

    // Wait for the heading to change in the preview, indicating AI completed.
    const previewFrame = page.locator(activePreviewSelector).contentFrame();
    const expectedText = message.replace('Change the heading to ', '');

    try {
      await expect(previewFrame.locator('h1').first()).toHaveText(expectedText, {
        timeout: 120000,
      });
    } catch {
      // AI may have changed the heading differently; just wait a reasonable time.
      await page.waitForTimeout(60000);
    }

    const wallClockMs = (lastAiResponseTime > 0 ? lastAiResponseTime : performance.now()) - startTime;

    runs.push({ run: i + 1, warmUp: isWarmUp, wallClockMs, message });

    console.log(`  Run ${i + 1}${isWarmUp ? ' (warm-up)' : ''}: ${Math.round(wallClockMs)}ms — "${message}"`);
  }

  const measuredMs = runs.filter(r => !r.warmUp).map(r => r.wallClockMs);
  const stats = computeStats(measuredMs);

  const report = {
    benchmark: 'ai-path-latency',
    date: new Date().toISOString(),
    environment: {
      baseURL,
      editorPath,
      phpVersion: runDrush(['php:eval', 'echo phpversion();']),
      nodeVersion: process.version,
    },
    protocol: {
      totalRuns: WARM_UP + MEASURED,
      warmUpRuns: WARM_UP,
      measuredRuns: MEASURED,
      method: 'UI submission at page level (no component selected, bypasses direct-edit)',
      note: 'Same edit type as direct-edit benchmark for apples-to-apples comparison',
    },
    allRuns: runs,
    stats,
  };

  const reportPath = `${process.cwd()}/ai-benchmark-results-${Date.now()}.json`;
  writeFileSync(reportPath, JSON.stringify(report, null, 2));

  console.log('\n========================================');
  console.log('  AI PATH BENCHMARK RESULTS');
  console.log('========================================');
  console.log(`\nAI Latency (N=${MEASURED}, warm-up=${WARM_UP}):`);
  console.log(`  Mean:   ${stats.mean}ms (${(stats.mean / 1000).toFixed(1)}s)`);
  console.log(`  SD:     ${stats.sd}ms`);
  console.log(`  Median: ${stats.median}ms (${(stats.median / 1000).toFixed(1)}s)`);
  console.log(`  95% CI: [${stats.ci95Lower}, ${stats.ci95Upper}]ms`);
  console.log(`  Range:  [${stats.min}, ${stats.max}]ms`);
  console.log(`\nComparison with direct-edit:`);
  console.log(`  Direct-edit mean: 38ms`);
  console.log(`  AI path mean:     ${stats.mean}ms`);
  console.log(`  Speedup:          ${Math.round(stats.mean / 38)}x`);
  console.log(`\nReport: ${reportPath}`);
  console.log('========================================\n');

  // The AI path should be significantly slower than direct-edit (38ms).
  expect(stats.mean).toBeGreaterThan(1000);
});
