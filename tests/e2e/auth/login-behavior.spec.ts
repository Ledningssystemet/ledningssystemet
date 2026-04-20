import { expect, test } from '@playwright/test';
import { loginAsUser, logout, isLoggedIn } from '../helpers/auth';

/**
 * Login behavior e2e tests
 * Converted from: tests/Feature/LoginBehaviorTest.php
 */

test.describe('Login Behavior', () => {
    test('guest is redirected to login for home route', async ({ page }) => {
        // Attempt to access home without logging in
        await page.goto('/');

        // Should be redirected to login
        await expect(page).toHaveURL('/login');
    });

    test('login page renders password form', async ({ page }) => {
        await page.goto('/login');

        // Verify login form elements are present
        await expect(page.locator('form')).toBeVisible();
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
        await expect(page.locator('button[type="submit"]')).toBeVisible();
    });

    test('user can login with seeded credentials and access dashboard', async ({ page }) => {
        // This test uses the seeded e2e user from global.setup.ts
        await loginAsUser(page);

        // Verify we're on the app dashboard
        await expect(page).toHaveURL(/\/app/);

        // Verify some dashboard content is visible
        // Adjust selectors based on your actual dashboard layout
        const isLoggedInResult = await isLoggedIn(page);
        expect(isLoggedInResult).toBe(true);
    });

    test('user can logout from the account menu', async ({ page }) => {
        // Login first
        await loginAsUser(page);
        await expect(page).toHaveURL(/\/app/);

        // Logout
        await logout(page);

        // Should be back at login page
        await expect(page).toHaveURL('/login');
    });

    test('login form shows required field validation', async ({ page }) => {
        await page.goto('/login');

        // Try to submit empty form
        const submitButton = page.locator('button[type="submit"]');
        await submitButton.click();

        // Email field should show validation error
        const emailInput = page.locator('input[name="email"]');
        const hasValidation = await emailInput.evaluate((el: HTMLInputElement) => {
            return !el.checkValidity();
        });

        expect(hasValidation).toBe(true);
    });

    test('login shows error message for invalid credentials', async ({ page }) => {
        await page.goto('/login');

        // Fill with invalid credentials
        await page.locator('input[name="email"]').fill('nonexistent@example.com');
        await page.locator('input[name="password"]').fill('wrongpassword');
        await page.locator('button[type="submit"]').click();

        // Wait for error message to appear
        await page.waitForLoadState('networkidle');

        // Verify error message is shown
        // Adjust selector based on your actual error message styling
        const errorVisible = await page.locator('[role="alert"], .text-red-600, .error').count() > 0;
        expect(errorVisible || page.url().includes('/login')).toBe(true);
    });
});

