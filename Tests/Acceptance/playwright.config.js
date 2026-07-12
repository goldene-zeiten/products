import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(__dirname, '.auth/login.json');

export default defineConfig({
  testDir: __dirname,
  timeout: 30 * 1000,
  expect: {
    timeout: 10000,
  },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['list']],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080/',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'login',
      testMatch: 'helper/login.setup.js',
    },
    {
      // Every guest-context scenario - search, category navigation, basket, and
      // checkout-as-a-guest never authenticate, so this project has no dependency on
      // "login" at all.
      name: 'e2e',
      testMatch: 'specs/**/*.spec.js',
      testIgnore: [
        'specs/checkout-logged-in.spec.js',
        'specs/basket-usergroup-discount.spec.js',
        'specs/checkout-withdrawal-logged-in.spec.js',
        'specs/checkout-credit-points.spec.js',
      ],
    },
    {
      // The specs that need an authenticated frontend user (shopper1, who also belongs to the
      // discounted "Wholesale" fe_groups row) - depends on "login" so that setup project runs
      // first and produces the storageState file below.
      name: 'e2e-logged-in',
      testMatch: [
        'specs/checkout-logged-in.spec.js',
        'specs/basket-usergroup-discount.spec.js',
        'specs/checkout-withdrawal-logged-in.spec.js',
        'specs/checkout-credit-points.spec.js',
      ],
      dependencies: ['login'],
      use: {
        storageState: authFile,
      },
    },
  ],
  outputDir: path.join(__dirname, '../../.Build/.cache/playwright-results'),
});
