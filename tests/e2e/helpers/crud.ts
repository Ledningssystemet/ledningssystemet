import type { Page } from '@playwright/test';

/**
 * CRUD helper functions for e2e tests
 * These functions automate common CRUD operations through the UI
 */

/**
 * Fill and submit a form with given field values
 * Handles text inputs, selects, checkboxes, etc.
 */
export async function fillAndSubmitForm(
    page: Page,
    formData: Record<string, string | boolean | number>
): Promise<void> {
    for (const [key, value] of Object.entries(formData)) {
        const field = page.locator(`[name="${key}"]`).first();

        if (typeof value === 'boolean') {
            // Handle checkboxes
            if (value) {
                await field.check();
            } else {
                await field.uncheck();
            }
        } else if (value === null || value === '') {
            // Skip empty values
            continue;
        } else {
            // Handle text inputs and selects
            const inputType = await field.getAttribute('type');

            if (inputType === 'checkbox') {
                // Already handled above
            } else if (inputType === 'radio') {
                await field.check();
            } else {
                await field.fill(String(value));
            }
        }
    }

    // Submit the form
    const submitButton = page.locator('button[type="submit"]').first();
    await submitButton.click();

    // Wait for navigation to complete
    await page.waitForLoadState('networkidle');
}

/**
 * Create a new entity by navigating to create page and filling form
 */
export async function createEntity(
    page: Page,
    resource: string,
    formData: Record<string, string | boolean | number>
): Promise<void> {
    await page.goto(`/app/${resource}/create`);
    await fillAndSubmitForm(page, formData);
}

/**
 * Edit an existing entity
 */
export async function editEntity(
    page: Page,
    resource: string,
    id: string | number,
    updates: Record<string, string | boolean | number>
): Promise<void> {
    await page.goto(`/app/${resource}/${id}/edit`);
    await fillAndSubmitForm(page, updates);
}

/**
 * Delete an entity by clicking delete button and confirming
 */
export async function deleteEntity(page: Page, resource: string, id: string | number): Promise<void> {
    await page.goto(`/app/${resource}/${id}/edit`);

    // Click delete button
    const deleteButton = page.locator('[data-testid="delete-button"]').first();
    await deleteButton.click();

    // Confirm deletion in dialog/modal
    const confirmButton = page.locator('[data-testid="confirm-delete"]').first();
    if (await confirmButton.isVisible()) {
        await confirmButton.click();
    }

    // Wait for redirect
    await page.waitForLoadState('networkidle');
}

/**
 * Get all rows from a CRUD list table
 */
export async function getTableRows(page: Page): Promise<string[][]> {
    const rows: string[][] = [];
    const rowElements = await page.locator('tbody tr').all();

    for (const row of rowElements) {
        const cells = await row.locator('td').allTextContents();
        rows.push(cells);
    }

    return rows;
}

/**
 * Find an entity by name/identifier in the list
 */
export async function findEntityInList(page: Page, searchTerm: string): Promise<boolean> {
    const rows = await getTableRows(page);
    return rows.some((row) => row.some((cell) => cell.includes(searchTerm)));
}

/**
 * Click on an entity row to open it for editing
 */
export async function openEntityFromList(page: Page, name: string): Promise<void> {
    const row = page.locator(`tbody tr:has-text("${name}")`).first();
    await row.click();
    await page.waitForLoadState('networkidle');
}

/**
 * Search in CRUD list (if search is available)
 */
export async function searchInList(page: Page, searchTerm: string): Promise<void> {
    const searchInput = page.locator('input[placeholder*="Search"], input[placeholder*="search"]').first();

    if (await searchInput.isVisible()) {
        await searchInput.fill(searchTerm);
        await page.waitForLoadState('networkidle');
    }
}

/**
 * Paginate to next page (if pagination is available)
 */
export async function goToNextPage(page: Page): Promise<void> {
    const nextButton = page.locator('button[aria-label="Next page"]').first();

    if (await nextButton.isVisible()) {
        await nextButton.click();
        await page.waitForLoadState('networkidle');
    }
}

