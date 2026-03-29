import { execFileSync } from 'node:child_process';
import {
  expect,
  test,
} from '../../web/modules/contrib/canvas/node_modules/@playwright/test';

const editorPath =
  process.env.DIRECT_EDIT_TEST_EDITOR_PATH || '/canvas/editor/canvas_page/13';
const activePreviewSelector =
  '[data-test-canvas-content-initialized="true"][data-canvas-swap-active="true"]';

function runDrush(args: string[]): string {
  return execFileSync('ddev', ['drush', ...args], {
    cwd: process.cwd(),
    encoding: 'utf8',
  }).trim();
}

test('cold-start deterministic heading edit stays on direct-edit path', async ({
  page,
  baseURL,
}) => {
  const uniqueHeading = `Cold start regression ${Date.now()}`;

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

  const directEditResponses: string[] = [];
  const aiResponses: string[] = [];
  page.on('response', async (response) => {
    const url = response.url();
    if (url.includes('/admin/api/canvas/direct-edit')) {
      directEditResponses.push(`${response.status()}`);
    }
    if (url.includes('/admin/api/canvas/ai')) {
      aiResponses.push(`${response.status()}`);
    }
  });

  const previewFrame = page.locator(activePreviewSelector).contentFrame();
  await previewFrame.locator('h1').first().click();
  await expect(page).toHaveURL(/\/component\//);

  await page.getByRole('button', { name: 'Open AI Panel' }).click();
  const promptBox = page.getByRole('textbox', { name: 'Build me a' });
  await expect(promptBox).toBeVisible();

  const directEditResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/admin/api/canvas/direct-edit') &&
      response.request().method() === 'POST',
  );

  await promptBox.fill(`Change the heading to ${uniqueHeading}`);
  await promptBox.press('Enter');

  const response = await directEditResponse;
  expect(response.status()).toBe(200);

  await expect(previewFrame.locator('h1').first()).toHaveText(uniqueHeading);

  await page.waitForTimeout(500);
  expect(directEditResponses).toHaveLength(1);
  expect(aiResponses).toHaveLength(0);
});
