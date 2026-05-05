import { expect, test } from '@playwright/test';
import { loginAsUser } from '../../helpers/auth';
import { navigateToCrudList } from '../../helpers/navigation';

async function dismissSessionEndedDialogIfPresent(page: import('@playwright/test').Page): Promise<void> {
    for (let attempt = 0; attempt < 3; attempt += 1) {
        const sessionDialog = page.getByRole('dialog', { name: /your session has ended/i }).first();
        const isVisible = await sessionDialog.isVisible().catch(() => false);

        if (!isVisible) {
            try {
                await sessionDialog.waitFor({ state: 'visible', timeout: 1500 });
            } catch {
                break;
            }
        }

        const recoveryButton = page.getByRole('button', { name: /i have signed in/i }).first();
        await recoveryButton.click();
        await expect(sessionDialog).toBeHidden({ timeout: 5000 });
    }
}

test.describe('CRUD dialog select interactions', () => {
    test.beforeEach(async ({ page }) => {
        await page.route('**/api/session/ping', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ ok: true }),
            });
        });

        await page.route('**/api/crud/activity-flow-templates**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify([
                    { id: 101, name: 'Template Alpha' },
                    { id: 102, name: 'Template Beta' },
                ]),
            });
        });

        await page.route('**/api/crud/users**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify([
                    { id: 201, name: 'Test User Alpha' },
                    { id: 202, name: 'Test User Beta' },
                ]),
            });
        });

        await loginAsUser(page);
    });

    test('select options remain clickable in open edit dialogs', async ({ page }) => {
        await navigateToCrudList(page, 'activity-flows');
        await dismissSessionEndedDialogIfPresent(page);

        await page.getByTestId('crud-add-button').first().click();

        const dialog = page.getByTestId('crud-edit-dialog');
        await expect(dialog).toBeVisible();

        const tabs = dialog.getByRole('tab');
        const tabCount = await tabs.count();

        let controls = dialog.locator('.select2__control');
        let controlCount = await controls.count();

        for (let tabIndex = 0; tabIndex < tabCount && controlCount === 0; tabIndex += 1) {
            await tabs.nth(tabIndex).click();
            controls = dialog.locator('.select2__control');
            controlCount = await controls.count();
        }

        expect(controlCount).toBeGreaterThan(0);

        let selectedOptionText: string | null = null;

        for (let index = 0; index < controlCount; index += 1) {
            const control = controls.nth(index);
            await control.click();

            const menuPortal = page.locator('.select2__menu-portal');
            const options = menuPortal.locator('.select2__option');

            try {
                await expect(options.first()).toBeVisible({ timeout: 5000 });
            } catch {
                await page.keyboard.press('Escape');
                continue;
            }

            const controlBox = await control.boundingBox();
            const menuBox = await menuPortal.boundingBox();

            expect(controlBox).not.toBeNull();
            expect(menuBox).not.toBeNull();

            if (controlBox && menuBox) {
                expect(menuBox.y).toBeGreaterThanOrEqual(controlBox.y - 8);
                expect(menuBox.y).toBeLessThanOrEqual(controlBox.y + controlBox.height + 80);
                expect(menuBox.x).toBeGreaterThanOrEqual(controlBox.x - 8);
                expect(menuBox.x).toBeLessThanOrEqual(controlBox.x + 8);
            }

            selectedOptionText = (await options.first().innerText()).trim();
            await options.first().click();

            await expect(page.locator('.select2__menu-portal')).toHaveCount(0);
            await expect(controls.nth(index)).toContainText(selectedOptionText);
            break;
        }

        expect(selectedOptionText).not.toBeNull();
    });
});

