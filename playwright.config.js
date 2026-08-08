import { defineConfig, devices } from '@playwright/test';

const PORT = 8099;

export default defineConfig({
  testDir: './Tests/Acceptance',
  // Accessibility violations are a build failure, not a warning.
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: process.env.CI ? 'github' : 'list',
  outputDir: './Tests/Acceptance/_output',
  use: {
    ...devices['Desktop Chrome'],
    baseURL: `http://127.0.0.1:${PORT}`,
  },
  // Over file:// the browser refuses to load the ES module, and the keyboard
  // tests would then pass against a page that has no JavaScript at all.
  webServer: {
    command: `node Tests/Acceptance/server.mjs .`,
    url: `http://127.0.0.1:${PORT}/Tests/Acceptance/fixtures/labelled-page.html`,
    reuseExistingServer: !process.env.CI,
    stdout: 'ignore',
  },
});
