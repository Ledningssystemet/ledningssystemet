import type { Page } from '@playwright/test';

/**
 * Login helper functions for e2e tests
 * These functions automate the login flow across different user roles
 */

const E2E_USER_EMAIL = 'test@example.com';
const E2E_USER_PASSWORD = 'password';

/**
 * Login with standard e2e test user
 * Assumes user is seeded in global.setup.ts
 */
export async function loginAsUser(
    page: Page,
    email: string = E2E_USER_EMAIL,
    password: string = E2E_USER_PASSWORD
): Promise<void> {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('button[type="submit"]').click();

    // Wait for successful login redirect
    await page.waitForURL(/\/app/);
}

/**
 * Login with admin user
 * Assumes admin user is seeded with specific claims/permissions
 */
export async function loginAsAdmin(page: Page): Promise<void> {
    // Adjust email/password based on your seeded admin user
    await loginAsUser(page, 'admin@example.com', 'password');
}

/**
 * Logout current user
 */
export async function logout(page: Page): Promise<void> {
    // Navigate to account menu and logout
    // Adjust selector based on your actual UI
    await page.locator('[data-testid="account-menu-trigger"]').click();
    await page.locator('button:has-text("Logout")').click();

    // Wait for redirect to login
    await page.waitForURL('/login');
}

/**
 * Check if user is logged in by verifying presence of app layout
 */
export async function isLoggedIn(page: Page): Promise<boolean> {
    // Adjust selector based on your actual app layout
    const layoutElement = await page.locator('[data-testid="app-layout"]').count();
    return layoutElement > 0;
}

/**
 * Get current user info (if available via window object or API)
 * Adjust based on your actual implementation
 */
export async function getCurrentUser(page: Page): Promise<Record<string, any> | null> {
    try {
        const userJson = await page.evaluate(() => {
            // Adjust based on how user data is stored (e.g., window.APP_USER)
            return (window as any).__APP_USER__ || null;
        });
        return userJson;
    } catch {
        return null;
    }
}

