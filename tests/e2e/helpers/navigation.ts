import type { Page } from '@playwright/test';

/**
 * Navigation helper functions for e2e tests
 */

/**
 * Navigate to a path and wait for page load
 * Handles Inertia.js navigation
 */
export async function navigateTo(page: Page, path: string): Promise<void> {
    await page.goto(path, { timeout: 60000 });
    // Wait for any Inertia navigation to complete
    await page.waitForLoadState();
}

/**
 * Navigate to a CRUD resource list page
 * @param page Playwright page
 * @param resource Resource name (e.g., 'users', 'tags', 'access-groups')
 */
export async function navigateToCrudList(page: Page, resource: string): Promise<void> {
    await navigateTo(page, `/app/${resource}`);
}

/**
 * Navigate to a CRUD resource create page
 */
export async function navigateToCrudCreate(page: Page, resource: string): Promise<void> {
    await navigateTo(page, `/app/${resource}/create`);
}

/**
 * Navigate to a CRUD resource edit page
 */
export async function navigateToCrudEdit(page: Page, resource: string, id: string | number): Promise<void> {
    await navigateTo(page, `/app/${resource}/${id}/edit`);
}

/**
 * Wait for Inertia navigation to complete
 * Useful when a navigation is triggered by form submission or button click
 */
export async function waitForInertiaNavigation(page: Page): Promise<void> {
    await page.waitForLoadState();
}

/**
 * Check if current page is accessible (not unauthorized/forbidden)
 */
export async function isPageAccessible(page: Page): Promise<boolean> {
    const status = page.url();
    // If redirected to login or error page, not accessible
    return !status.includes('/login') && !status.includes('/error');
}

/**
 * Get current page title
 */
export async function getPageTitle(page: Page): Promise<string> {
    return await page.title();
}

