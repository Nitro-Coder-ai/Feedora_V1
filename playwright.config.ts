import { defineConfig, devices } from '@playwright/test';

/**
 * Feedora E2E configuration.
 *
 * - A dedicated PHP dev server runs on :8001 pointed at the Feedora_test DB
 *   (via the FEEDORA_DB env var read in config/config.php), so tests never
 *   touch the dev :8000 server or the prod `Feedora` database.
 * - The `setup` project authenticates once and saves storageState; the
 *   `chromium` project reuses it.
 */
const PORT = 8001;
const BASE_URL = `http://127.0.0.1:${PORT}`;

export default defineConfig({
  testDir: './tests',
  // Create/seed Feedora_test before the run and drop it after, so the only
  // standing database is the optimized `Feedora`.
  globalSetup: './tests/global-setup.ts',
  globalTeardown: './tests/global-teardown.ts',
  fullyParallel: false, // shared test account + feed state -> run serially for determinism
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],

  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    navigationTimeout: 45_000, // single-threaded php -S serves assets serially
    actionTimeout: 15_000,
  },

  projects: [
    { name: 'setup', testMatch: /auth\.setup\.ts/ },
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], storageState: '.auth/user.json' },
      dependencies: ['setup'],
    },
  ],

  webServer: {
    command: `php -S 127.0.0.1:${PORT} -t .`,
    url: BASE_URL,
    cwd: '.',
    env: { FEEDORA_DB: 'Feedora_test' },
    reuseExistingServer: false, // always start a fresh server bound to the test DB
    timeout: 30_000,
  },
});
