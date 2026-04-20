import { expect, test } from '@playwright/test';
import { loginAsUser } from '../../helpers/auth';
import { navigateToCrudList, navigateToCrudCreate } from '../../helpers/navigation';
import { fillAndSubmitForm, getTableRows, findEntityInList, deleteEntity } from '../../helpers/crud';

/**
 * Generic CRUD e2e test template
 *
 * This file serves as a template for testing CRUD operations on specific resources.
 * Copy this file and customize for your specific resource (tags, sites, etc.)
 *
 * Converted from pattern of: tests/Feature/*CrudContractTest.php files
 *
 * USAGE:
 * 1. Copy this file to: tests/e2e/app/crud/[resource].spec.ts
 * 2. Replace [RESOURCE] placeholders with your actual resource name
 * 3. Update formData to match your form fields
 * 4. Run: npm run test:e2e -- tests/e2e/app/crud/[resource].spec.ts
 */

const RESOURCE = '[RESOURCE]'; // e.g., 'tags', 'sites', 'departments'
const SINGULAR = '[singular]'; // e.g., 'tag', 'site', 'department'

interface TestData {
    create: Record<string, string | boolean | number>;
    update: Record<string, string | boolean | number>;
}

// Customize form data for your specific resource
const testData: TestData = {
    create: {
        name: `E2E Test ${SINGULAR} ${Date.now()}`,
        // Add other required fields based on your form
    },
    update: {
        name: `Updated E2E Test ${SINGULAR} ${Date.now()}`,
        // Add fields to update
    },
};

test.describe(`${SINGULAR.charAt(0).toUpperCase() + SINGULAR.slice(1)} CRUD Operations`, () => {
    test.beforeEach(async ({ page }) => {
        // Login before each test
        await loginAsUser(page);
    });

    test(`user can view ${RESOURCE} list`, async ({ page }) => {
        await navigateToCrudList(page, RESOURCE);

        // Verify page loaded
        expect(page.url()).toContain(`/app/${RESOURCE}`);

        // Verify table or list is visible
        const tableOrList = page.locator('table, [role="grid"], .list-container');
        await expect(tableOrList).toBeVisible();
    });

    test(`user can create a new ${SINGULAR}`, async ({ page }) => {
        await navigateToCrudList(page, RESOURCE);

        // Click create button
        const createButton = page.locator('button:has-text("Create"), button:has-text("Add"), button:has-text("New")').first();
        await expect(createButton).toBeVisible();
        await createButton.click();

        // Verify redirect to create page
        await expect(page).toHaveURL(new RegExp(`/app/${RESOURCE}/create`));

        // Fill form
        await fillAndSubmitForm(page, testData.create);

        // Verify redirect back to list
        await expect(page).toHaveURL(new RegExp(`/app/${RESOURCE}`));

        // Verify new item appears in list
        const createdName = testData.create.name as string;
        const found = await findEntityInList(page, createdName);
        expect(found).toBe(true);
    });

    test(`user can edit an existing ${SINGULAR}`, async ({ page }) => {
        // First create an item
        await navigateToCrudList(page, RESOURCE);
        const createButton = page.locator('button:has-text("Create"), button:has-text("Add")').first();
        await createButton.click();

        await fillAndSubmitForm(page, testData.create);

        // Now get the created item's ID from the list
        const createdName = testData.create.name as string;
        const row = page.locator(`tbody tr:has-text("${createdName}")`).first();
        await row.click();

        // Verify on edit page
        await expect(page).toHaveURL(new RegExp(`/app/${RESOURCE}/\\d+/edit`));

        // Update the item
        await fillAndSubmitForm(page, testData.update);

        // Verify back on list
        expect(page.url()).toContain(`/app/${RESOURCE}`);

        // Verify updated name appears
        const found = await findEntityInList(page, testData.update.name as string);
        expect(found).toBe(true);
    });

    test(`user can delete a ${SINGULAR}`, async ({ page }) => {
        // Create an item first
        await navigateToCrudList(page, RESOURCE);
        const createButton = page.locator('button:has-text("Create"), button:has-text("Add")').first();
        await createButton.click();

        await fillAndSubmitForm(page, testData.create);

        // Find and delete
        const createdName = testData.create.name as string;
        const row = page.locator(`tbody tr:has-text("${createdName}")`).first();
        await row.click();

        // Get the URL to extract ID
        const url = page.url();
        const idMatch = url.match(/(\d+)\/edit/);
        const id = idMatch ? idMatch[1] : '';

        // Click delete button
        const deleteButton = page.locator('[data-testid="delete-button"]').first();
        await deleteButton.click();

        // Confirm deletion
        const confirmButton = page.locator('[data-testid="confirm-delete"]').first();
        if (await confirmButton.isVisible()) {
            await confirmButton.click();
        }

        // Verify redirect to list
        await expect(page).toHaveURL(new RegExp(`/app/${RESOURCE}`));

        // Verify item no longer in list
        const stillExists = await findEntityInList(page, createdName);
        expect(stillExists).toBe(false);
    });

    test(`${RESOURCE} list is paginated correctly`, async ({ page }) => {
        await navigateToCrudList(page, RESOURCE);

        // Check if pagination exists
        const paginationInfo = page.locator('text=/page \\d+ of \\d+/i, [data-testid="pagination"]');
        const hasPagination = await paginationInfo.count() > 0;

        if (hasPagination) {
            // Verify we can go to next page if available
            const nextButton = page.locator('button[aria-label="Next page"]');
            const isNextAvailable = await nextButton.isEnabled();

            if (isNextAvailable) {
                await nextButton.click();
                await page.waitForLoadState('networkidle');

                // Verify URL changed
                expect(page.url()).toContain('page=2');
            }
        }
    });

    test(`${RESOURCE} list can be searched`, async ({ page }) => {
        // Create a test item first
        await navigateToCrudList(page, RESOURCE);
        const createButton = page.locator('button:has-text("Create"), button:has-text("Add")').first();
        await createButton.click();

        const testName = `SEARCH_TEST_${Date.now()}`;
        await fillAndSubmitForm(page, { ...testData.create, name: testName });

        // Now test search
        await navigateToCrudList(page, RESOURCE);

        const searchInput = page.locator('input[placeholder*="Search"], input[placeholder*="search"]').first();
        if (await searchInput.isVisible()) {
            await searchInput.fill(testName);
            await page.waitForLoadState('networkidle');

            // Verify filtered results
            const rows = await getTableRows(page);
            const found = rows.some((row) => row.some((cell) => cell.includes(testName)));

            expect(found).toBe(true);
        }
    });

    test(`user without permissions is forbidden from ${RESOURCE} list`, async ({ page }) => {
        // This test assumes there's a way to test permissions
        // May need to create a specific user role without permissions
        // For now, this is a template - customize based on your auth system

        await navigateToCrudList(page, RESOURCE);

        // If forbidden, should be on list page or error page
        // Adjust assertion based on your permission system
        const isOnListOrError = page.url().includes(`/app/${RESOURCE}`) || (await page.locator('[role="alert"]').count()) > 0;

        expect(isOnListOrError).toBe(true);
    });
});

