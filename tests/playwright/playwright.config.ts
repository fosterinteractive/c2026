import {
  defineConfig,
  devices,
} from '../../web/modules/contrib/canvas/node_modules/@playwright/test';

export default defineConfig({
  testDir: '.',
  timeout: 90_000,
  expect: {
    timeout: 10_000,
  },
  use: {
    baseURL: process.env.DIRECT_EDIT_TEST_BASE_URL || 'https://c2026.ddev.site',
    ignoreHTTPSErrors: true,
    testIdAttribute: 'data-testid',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        channel: 'chrome',
        viewport: { width: 1920, height: 1080 },
      },
    },
  ],
});
