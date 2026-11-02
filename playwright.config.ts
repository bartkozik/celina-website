import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
  testDir: './tests/e2e',
  fullyParallel: false,
  // Every browser test mutates the same disposable WordPress database. Running
  // files in parallel can race WordPress session-token writes for the admin user.
  workers: 1,
  retries: 0,
  use: {
    baseURL: 'http://localhost:8892',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices[ 'Desktop Chrome' ] },
    },
  ],
  webServer: {
    command: 'pnpm wp:start',
    url: 'http://localhost:8892/fixtures/e2e-ready.php',
    reuseExistingServer: false,
    timeout: 600_000,
  },
} );
