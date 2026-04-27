import { expect, test, type Page } from '@playwright/test';
import { loginAsUser } from '../helpers/auth';
import { navigateTo } from '../helpers/navigation';

async function dismissSessionGuardIfVisible(page: Page) {
    const sessionDialog = page.getByRole('dialog');
    if (!(await sessionDialog.isVisible().catch(() => false))) {
        return;
    }

    // First action button verifies restored session and closes the dialog when the session is alive.
    await sessionDialog.locator('button').first().click();
    await expect(sessionDialog).toBeHidden({ timeout: 10000 });
}

test.describe('App Layout Preference', () => {
    test.beforeEach(async ({ page }) => {
        await page.route('**/api/session/ping', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ ok: true }),
            });
        });

        await loginAsUser(page);
    });

    test('user can switch between side menu and mega menu and preference is saved in cookie', async ({ page }) => {
        await navigateTo(page, '/app/my-profile');
        await dismissSessionGuardIfVisible(page);

        await page.locator('[data-testid="layout-option-side-menu"]').click();

        await expect(page.locator('[data-testid="side-layout-header"]')).toBeVisible();
        await expect(page.locator('[data-testid="side-menu"]')).toBeVisible();

        const sideCookie = await page.evaluate(() => document.cookie);
        expect(sideCookie).toContain('menu_layout_preference=side-menu');

        await navigateTo(page, '/app/users');
        await dismissSessionGuardIfVisible(page);
        await expect(page.locator('[data-testid="side-menu"]')).toBeVisible();

        await navigateTo(page, '/app/my-profile');
        await dismissSessionGuardIfVisible(page);
        await page.locator('[data-testid="layout-option-mega-menu"]').click();

        await expect(page.locator('[data-testid="mega-nav"]')).toBeVisible();

        const megaCookie = await page.evaluate(() => document.cookie);
        expect(megaCookie).toContain('menu_layout_preference=mega-menu');
    });

    test('mobile always uses mega nav even when side-menu preference is selected', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await navigateTo(page, '/app/my-profile');
        await dismissSessionGuardIfVisible(page);

        await page.locator('[data-testid="layout-option-side-menu"]').click();

        const sideCookie = await page.evaluate(() => document.cookie);
        expect(sideCookie).toContain('menu_layout_preference=side-menu');

        await navigateTo(page, '/app/users');
        await dismissSessionGuardIfVisible(page);

        await expect(page.locator('[data-testid="mega-nav"]')).toBeVisible();
        await expect(page.locator('[data-testid="side-layout-header"]')).toBeHidden();
        await expect(page.locator('[data-testid="side-menu"]')).toBeHidden();
    });
});
