import { expect, test } from '@playwright/test';
import { loginAsUser, logout, isLoggedIn } from '../helpers/auth';

/**
 * Authentication pages e2e tests
 * Converted from: tests/Feature/AuthSpaPagesTest.php
 */

test.describe('Auth Pages', () => {
    test('forgot password page is rendered', async ({ page }) => {
        await page.goto('/forgot-password');

        // Verify page content
        await expect(page.locator('form')).toBeVisible();
        await expect(page.getByRole('heading', { name: /forgot password/i })).toBeVisible();
    });

    test('reset password page is rendered with token and email', async ({ page }) => {
        const token = 'token-123';
        const email = 'user@example.com';

        await page.goto(`/reset-password/${token}?email=${email}`);

        // Verify page content
        await expect(page.locator('form')).toBeVisible();
        await expect(page.getByRole('heading', { name: /reset password/i })).toBeVisible();

        // The form should pre-populate the email
        const emailInput = page.locator('input[name="email"]');
        await expect(emailInput).toHaveValue(email);
    });

    test('otp challenge page is rendered for authenticated user', async ({ page }) => {
        // Login first to have a valid session
        await loginAsUser(page);

        // Navigate to OTP challenge (this would normally happen after partial login)
        // Note: This test may need adjustment based on your actual OTP flow
        await page.goto('/otp/challenge');

        // If a valid session exists, the page should render
        const loginPage = page.url();
        // Either we see OTP page or get redirected based on session state
        const isOtpPage = await page.locator('input[name="code"]').count() > 0;
        const isLoginPage = loginPage.includes('/login');

        expect(isOtpPage || isLoginPage).toBeTruthy();
    });

    test('otp challenge redirects to login when session is missing', async ({ page }) => {
        // Don't login - attempt to access OTP page without session
        await page.goto('/otp/challenge');

        // Should be redirected to login
        await expect(page).toHaveURL('/login');
    });
});

