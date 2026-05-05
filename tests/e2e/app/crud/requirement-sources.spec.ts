import { expect, test } from '@playwright/test';
import { loginAsUser } from '../../helpers/auth';
import { navigateToCrudList } from '../../helpers/navigation';

test.describe('Requirement sources requirements ordering', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
    });

    test('requirements dialog exposes reorder controls for ordinal-based sorting', async ({ page }) => {
        const sourceReference = `SRC-${Date.now()}`;
        const sourceName = `Requirement Source ${Date.now()}`;
        const firstRequirementReference = `REQ-${Date.now()}-1`;
        const secondRequirementReference = `REQ-${Date.now()}-2`;

        await navigateToCrudList(page, 'requirement-sources');

        await page.getByTestId('crud-add-button').first().click();
        const sourceEditDialog = page.getByTestId('crud-edit-dialog');
        await expect(sourceEditDialog).toBeVisible();
        await sourceEditDialog.locator('input[name="reference"]').fill(sourceReference);
        await sourceEditDialog.locator('input[name="name"]').fill(sourceName);
        await sourceEditDialog.getByTestId('crud-save-button').click();
        await expect(sourceEditDialog).toBeHidden();

        const sourceRow = page.locator('tr', { hasText: sourceReference }).first();
        await expect(sourceRow).toBeVisible();
        await sourceRow.getByRole('button', { name: 'Show requirements' }).click();

        const requirementsDialog = page.getByRole('dialog').filter({ hasText: 'Requirements for' }).last();
        await expect(requirementsDialog).toBeVisible();

        for (const [reference, name] of [
            [firstRequirementReference, 'First requirement'],
            [secondRequirementReference, 'Second requirement'],
        ] as const) {
            await requirementsDialog.getByTestId('crud-add-button').click();

            const requirementEditDialog = page.getByTestId('crud-edit-dialog');
            await expect(requirementEditDialog).toBeVisible();
            await requirementEditDialog.locator('input[name="reference"]').fill(reference);
            await requirementEditDialog.locator('input[name="name"]').fill(name);
            await requirementEditDialog.getByTestId('crud-save-button').click();
            await expect(requirementEditDialog).toBeHidden();
        }

        await expect(requirementsDialog.getByText('Drag the handle to reorder items')).toBeVisible();
        await expect(requirementsDialog.locator('[title="Drag to reorder"]')).toHaveCount(2);
    });
});

