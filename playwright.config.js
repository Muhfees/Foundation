import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  use: { baseURL: process.env.BASE_URL || 'http://127.0.0.1:8765', trace: 'retain-on-failure' },
  reporter: [['list'], ['html', { open: 'never' }]],
});
