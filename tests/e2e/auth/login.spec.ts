import { expect, test } from '@playwright/test';

const e2eUserEmail = 'test@example.com';
const e2eUserPassword = 'password';

test('login page renders password form', async ({ page }) => {
    await page.goto('/login');

    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
});

test('user can login with seeded e2e credentials', async ({ page }) => {
    await page.goto('/login');

    await page.locator('input[name="email"]').fill(e2eUserEmail);
    await page.locator('input[name="password"]').fill(e2eUserPassword);
    await page.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/\/app/);
});

