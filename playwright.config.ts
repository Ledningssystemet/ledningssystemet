import { defineConfig, devices } from '@playwright/test';

const port = process.env.E2E_PORT ?? '8000';
const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${port}`;

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global.setup.ts',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command: `php artisan --env=testing serve --host=127.0.0.1 --port=${port}`,
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120 * 1000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});

