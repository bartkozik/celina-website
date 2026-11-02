import { defineConfig, devices } from '@playwright/test';

const port = process.env.CELINA_CONTENT_E2E_PORT ?? '8894';

export default defineConfig( {
  testDir: './tests/e2e-content',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  use: {
    baseURL: `http://localhost:${ port }`,
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices[ 'Desktop Chrome' ] },
    },
  ],
} );
