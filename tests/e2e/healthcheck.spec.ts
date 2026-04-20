import { expect, test } from '@playwright/test';

test('application health endpoint is up', async ({ page }) => {
    await page.goto('/up');

    await expect(page.getByText('Application up')).toBeVisible();
});

