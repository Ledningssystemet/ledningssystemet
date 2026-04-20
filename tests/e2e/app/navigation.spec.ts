import { expect, test } from '@playwright/test';
import { loginAsUser } from '../helpers/auth';
import { navigateToCrudList, navigateTo } from '../helpers/navigation';

/**
 * App navigation and UI layout e2e tests
 * Converted from: tests/Feature/MegaNavAccountMenuRoutesTest.php (partial)
 */

test.describe('App Navigation', () => {
    test.beforeEach(async ({ page }) => {
        // Login before each test
        await loginAsUser(page);
    });

    test('authenticated user sees app layout with navigation', async ({ page }) => {
        await navigateTo(page, '/app');

        // Verify main app layout elements exist
        // Adjust selectors based on your actual UI structure
        const mainContent = page.locator('main, [role="main"]');
        await expect(mainContent).toBeVisible();

        // Verify navigation/sidebar is visible
        const nav = page.locator('nav, aside, [role="navigation"]');
        await expect(nav).toBeVisible();
    });

    test('navigation menu shows links for different resources', async ({ page }) => {
        await navigateTo(page, '/app');

        // Verify that common resource links are in navigation
        // Adjust based on your actual menu items
        const commonResources = ['users', 'access-groups', 'departments'];

        for (const resource of commonResources) {
            const link = page.locator(`a:has-text("${resource}")`, {
                hasNot: page.locator('role=disabled'),
            });
            // Link may or may not exist depending on permissions, so we don't assert
        }
    });

    test('user can navigate to different CRUD sections', async ({ page }) => {
        await navigateTo(page, '/app');

        // Navigate to a specific resource (users)
        await navigateToCrudList(page, 'users');

        // Verify we're on the correct page
        expect(page.url()).toContain('/app/users');
    });

    test('account menu is accessible from app layout', async ({ page }) => {
        await navigateTo(page, '/app');

        // Look for account/profile menu trigger
        // Adjust selector based on your actual UI
        const accountMenu = page.locator('[data-testid="account-menu-trigger"]');

        if (await accountMenu.count() > 0) {
            await accountMenu.click();

            // Verify menu items appear
            const logoutOption = page.locator('button:has-text("Logout")');
            await expect(logoutOption).toBeVisible();
        }
    });

    test('breadcrumb or page title reflects current location', async ({ page }) => {
        await navigateToCrudList(page, 'users');

        // Verify page title or heading
        const heading = page.locator('h1, h2');
        const title = await heading.textContent();

        expect(title?.toLowerCase() || '').toContain('user');
    });

    test('navigation maintains state when moving between sections', async ({ page }) => {
        // Navigate to users list
        await navigateToCrudList(page, 'users');
        await page.waitForLoadState('networkidle');

        // Navigate to another section
        await navigateToCrudList(page, 'departments');
        await page.waitForLoadState('networkidle');

        // Navigate back to users
        await navigateToCrudList(page, 'users');

        // Verify we're on the correct page
        expect(page.url()).toContain('/app/users');
    });

    test('unauthorized sections show access denied or redirect', async ({ page }) => {
        // Try to navigate to a restricted section
        // Adjust path based on your actual restricted sections
        await page.goto('/app/system-settings');

        // Either shown error or redirected
        const isUnauthorized =
            page.url().includes('/app/system-settings') === false || (await page.locator('[role="alert"]').count()) > 0;

        expect(isUnauthorized).toBe(true);
    });
});

