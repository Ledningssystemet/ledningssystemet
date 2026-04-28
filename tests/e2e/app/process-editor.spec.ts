import { expect, test } from '@playwright/test';
import { loginAsUser } from '../helpers/auth';
import { navigateTo } from '../helpers/navigation';

type CrudRowsPayload = {
    data?: Array<{ id?: number | string }>;
};

const SEEDED_E2E_USER_ID = 1;

async function dismissSessionDialogIfVisible(page: import('@playwright/test').Page): Promise<void> {
    const sessionDialog = page.getByRole('dialog', { name: /session has ended/i });
    await sessionDialog.waitFor({ state: 'visible', timeout: 1500 }).catch(() => undefined);

    const restoreButton = page.getByRole('button', { name: /signed in|restored/i });

    if (await restoreButton.isVisible().catch(() => false)) {
        await restoreButton.click();
        await expect(restoreButton).not.toBeVisible();
    }
}

async function ensureProcessId(page: import('@playwright/test').Page): Promise<number> {
    const result = await page.evaluate(async (seededUserId: number) => {
        const getRows = async (url: string): Promise<Array<{ id?: number | string }>> => {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                return [];
            }

            const payload = (await response.json()) as CrudRowsPayload | Array<{ id?: number | string }>;

            return Array.isArray(payload) ? payload : Array.isArray(payload.data) ? payload.data : [];
        };

        const createRecord = async (url: string, payload: Record<string, unknown>): Promise<{ ok: boolean; id: number; status: number; body: unknown }> => {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const created = await response.json().catch(() => null);

            return {
                ok: response.ok,
                id: Number(created?.id ?? 0),
                status: response.status,
                body: created,
            };
        };

        const updateRecord = async (url: string, payload: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: unknown }> => {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });

            return {
                ok: response.ok,
                status: response.status,
                body: await response.json().catch(() => null),
            };
        };

        const userId = seededUserId;

        if (!userId) {
            return {
                ok: false,
                id: 0,
                reason: 'missing-user-id',
            };
        }

        const accessGroup = await createRecord('/api/crud/access-groups', {
            name: `E2E Process Access ${Date.now()}`,
            claims: ['processes.read', 'processes.edit'],
        });

        if (!accessGroup.ok || !accessGroup.id) {
            return {
                ok: false,
                id: 0,
                reason: 'create-access-group-failed',
                details: accessGroup,
            };
        }

        const attachedClaims = await updateRecord(`/api/crud/users/${userId}`, {
            accessgroups: [accessGroup.id],
        });

        if (!attachedClaims.ok) {
            return {
                ok: false,
                id: 0,
                reason: 'attach-access-group-failed',
                details: attachedClaims,
            };
        }

        const existingProcesses = await getRows('/api/crud/processes?paginate=1&page=1&per_page=1&%24select=id&sort=name');
        if (existingProcesses.length > 0) {
            return {
                ok: true,
                id: Number(existingProcesses[0]?.id ?? 0),
            };
        }

        let departments = await getRows('/api/crud/departments?paginate=1&page=1&per_page=1&%24select=id&sort=name');

        if (departments.length === 0) {
            const createdDepartment = await createRecord('/api/crud/departments', {
                name: `E2E Department ${Date.now()}`,
            });

            if (!createdDepartment.ok || !createdDepartment.id) {
                return {
                    ok: false,
                    id: 0,
                    reason: 'create-department-failed',
                    details: createdDepartment,
                };
            }

            departments = [{ id: createdDepartment.id }];
        }

        const departmentId = Number(departments[0]?.id ?? 0);

        if (!departmentId || !userId) {
            return {
                ok: false,
                id: 0,
                reason: 'missing-department-or-user-id',
            };
        }

        const createdProcess = await createRecord('/api/crud/processes', {
            name: `E2E Process ${Date.now()}`,
            description: 'Automated test process',
            department_id: departmentId,
            responsible_user_id: userId,
            isstartprocess: false,
            dataprocessor: false,
        });

        return {
            ok: createdProcess.ok,
            id: createdProcess.id,
            reason: createdProcess.ok ? 'created-process' : 'create-process-failed',
            details: createdProcess,
        };
    }, SEEDED_E2E_USER_ID);

    expect(result.ok, JSON.stringify(result)).toBeTruthy();
    expect(result.id).toBeGreaterThan(0);

    return result.id;
}

test.describe('Process editor', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
    });

    test('BPMN editor renders with full expected height', async ({ page }) => {
        await navigateTo(page, '/app/processes');
        await dismissSessionDialogIfVisible(page);

        const processId = await ensureProcessId(page);

        await navigateTo(page, `/app/processes/${processId}/editor`);
        await dismissSessionDialogIfVisible(page);

        await expect(page.getByRole('heading', { name: /process editor/i })).toBeVisible();

        const editor = page.locator('.bpmn-editor').first();
        await expect(editor).toBeVisible();

        const editorHeight = await editor.evaluate((element) => element.getBoundingClientRect().height);

        expect(editorHeight).toBeGreaterThan(700);
    });

    test('property sidebar allows updating name and color for supported BPMN elements', async ({ page }) => {
        await navigateTo(page, '/app/processes');
        await dismissSessionDialogIfVisible(page);

        const processId = await ensureProcessId(page);

        await navigateTo(page, `/app/processes/${processId}/editor`);
        await dismissSessionDialogIfVisible(page);

        await expect(page.getByRole('heading', { name: /process editor/i })).toBeVisible();
        await expect(page.locator('.bpmn-editor .djs-container')).toBeVisible();

        const startEvent = page.locator('.bpmn-editor .djs-element[data-element-id^="StartEvent"]').first();
        await startEvent.click({ force: true });

        const nameInput = page.locator('#ledning-name').first();
        const fillColorInput = page.locator('#ledning-fill-color').first();
        await expect(nameInput).toBeVisible();
        await expect(fillColorInput).toBeVisible();

        await nameInput.fill('E2E Start Event');
        await fillColorInput.fill('#ff0000');

        const saveRequestPromise = page.waitForRequest((request) => {
            if (request.method() !== 'PATCH') {
                return false;
            }

            const path = new URL(request.url()).pathname;

            return /\/api\/crud\/processes\/\d+$/.test(path);
        });

        await page.getByRole('button', { name: /save process/i }).click();

        const saveRequest = await saveRequestPromise;
        const payload = saveRequest.postDataJSON() as { bpmn?: string };

        expect(payload.bpmn).toContain('name="E2E Start Event"');
        expect(payload.bpmn).toContain('bioc:fill="#ff0000"');
    });

    test('property sidebar allows resizing external labels and strips unsupported label characters', async ({ page }) => {
        await navigateTo(page, '/app/processes');
        await dismissSessionDialogIfVisible(page);

        const processId = await ensureProcessId(page);

        await navigateTo(page, `/app/processes/${processId}/editor`);
        await dismissSessionDialogIfVisible(page);

        await expect(page.getByRole('heading', { name: /process editor/i })).toBeVisible();
        await expect(page.locator('.bpmn-editor .djs-container')).toBeVisible();

        const startEvent = page.locator('.bpmn-editor .djs-element[data-element-id^="StartEvent"]').first();
        await startEvent.click({ force: true });

        const nameInput = page.locator('#ledning-name').first();
        await expect(nameInput).toBeVisible();

        await nameInput.fill('Start123!');
        await expect(nameInput).toHaveValue('Start');

        const externalLabel = page.locator('.bpmn-editor .djs-element[data-element-id$="_label"]').first();
        await expect(externalLabel).toBeVisible();
        await externalLabel.click({ force: true });

        await page.locator('#ledning-width').fill('220');
        await page.locator('#ledning-width').blur();
        await page.locator('#ledning-height').fill('32');
        await page.locator('#ledning-height').blur();

        const saveRequestPromise = page.waitForRequest((request) => {
            if (request.method() !== 'PATCH') {
                return false;
            }

            const path = new URL(request.url()).pathname;

            return /\/api\/crud\/processes\/\d+$/.test(path);
        });

        await page.getByRole('button', { name: /save process/i }).click();

        const saveRequest = await saveRequestPromise;
        const payload = saveRequest.postDataJSON() as { bpmn?: string };

        expect(payload.bpmn).toContain('name="Start"');
        expect(payload.bpmn).not.toContain('Start123!');
        expect(payload.bpmn).toContain('width="220"');
        expect(payload.bpmn).toContain('height="32"');
    });

    test('data object/store creation uses name selection dialog and blocks direct inline label editing', async ({ page }) => {
        await navigateTo(page, '/app/processes');
        await dismissSessionDialogIfVisible(page);

        const processId = await ensureProcessId(page);

        await page.route('**/api/crud/information_types?paginate=0&%24select=id,name&sort=name', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    data: [
                        { id: 1001, name: 'E2E Information Type' },
                        { id: 1002, name: 'E2E Information Type 2' },
                    ],
                }),
            });
        });

        await page.route('**/api/crud/assets?paginate=0&%24select=id,name&sort=name', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    data: [
                        { id: 2001, name: 'E2E Existing Asset' },
                    ],
                }),
            });
        });

        await navigateTo(page, `/app/processes/${processId}/editor`);
        await dismissSessionDialogIfVisible(page);

        await expect(page.getByRole('heading', { name: /process editor/i })).toBeVisible();
        await expect(page.locator('.bpmn-editor .djs-container')).toBeVisible();

        await page.locator('.djs-palette .entry.create-data-object-reference').first().click();
        await expect(page.getByRole('dialog').getByRole('heading', { name: /choose information type name/i })).toBeVisible();
        await page.locator('#ledning-create-existing-name').selectOption('E2E Information Type 2');
        await page.getByRole('dialog').getByRole('button', { name: /use name/i }).click();
        await page.locator('.bpmn-editor .djs-container svg').first().click({ position: { x: 420, y: 240 } });

        await page.locator('.djs-palette .entry.create-data-store-reference').first().click();
        await expect(page.getByRole('dialog').getByRole('heading', { name: /choose asset name/i })).toBeVisible();
        await page.locator('#ledning-create-custom-name').fill('E2E Custom Asset');
        await page.getByRole('dialog').getByRole('button', { name: /use name/i }).click();
        await page.locator('.bpmn-editor .djs-container svg').first().click({ position: { x: 620, y: 240 } });

        const createdDataObject = page.locator('.bpmn-editor .djs-element[data-element-id^="DataObjectReference_"]').first();
        await createdDataObject.click({ force: true });
        await expect(page.locator('#ledning-name')).toBeEnabled();
        await createdDataObject.dblclick({ force: true });
        await expect(page.locator('.djs-direct-editing-content')).toHaveCount(0);

        const createdDataStore = page.locator('.bpmn-editor .djs-element[data-element-id^="DataStoreReference_"]').first();
        await createdDataStore.click({ force: true });
        await expect(page.locator('#ledning-name')).toBeEnabled();

        await expect(page.locator('.bpmn-editor .ledning-new-reference-marker')).toHaveCount(2);

        const saveRequestPromise = page.waitForRequest((request) => {
            if (request.method() !== 'PATCH') {
                return false;
            }

            const path = new URL(request.url()).pathname;

            return /\/api\/crud\/processes\/\d+$/.test(path);
        });

        await page.getByRole('button', { name: /save process/i }).click();

        const saveRequest = await saveRequestPromise;
        const payload = saveRequest.postDataJSON() as { bpmn?: string };

        expect(payload.bpmn).toContain('name="E2E Information Type 2"');
        expect(payload.bpmn).toContain('name="E2E Custom Asset"');
    });

    test('property sidebar persists size, text styling, and task background image as embedded base64', async ({ page }) => {
        await navigateTo(page, '/app/processes');
        await dismissSessionDialogIfVisible(page);

        const processId = await ensureProcessId(page);

        await navigateTo(page, `/app/processes/${processId}/editor`);
        await dismissSessionDialogIfVisible(page);

        await expect(page.getByRole('heading', { name: /process editor/i })).toBeVisible();
        await expect(page.locator('.bpmn-editor .djs-container')).toBeVisible();

        await page.locator('.djs-palette .entry.create-task').first().click();
        await page.locator('.bpmn-editor .djs-container svg').first().click({ position: { x: 420, y: 220 } });

        const createdTask = page.locator('.bpmn-editor .djs-element[data-element-id^="Task_"]').first();
        await createdTask.click({ force: true });

        await page.locator('#ledning-width').fill('190');
        await page.locator('#ledning-width').blur();
        await page.locator('#ledning-height').fill('130');
        await page.locator('#ledning-height').blur();

        await page.locator('#ledning-text-color').fill('#111827');
        await page.locator('#ledning-text-color').blur();
        await page.locator('#ledning-font-size').fill('16');
        await page.locator('#ledning-font-size').blur();

        const svgBuffer = new TextEncoder().encode('<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8"><rect width="8" height="8" fill="#0ea5e9"/></svg>');

        await page.locator('#ledning-task-background-image').setInputFiles({
            name: 'tiny.svg',
            mimeType: 'image/svg+xml',
            buffer: svgBuffer,
        });

        const saveRequestPromise = page.waitForRequest((request) => {
            if (request.method() !== 'PATCH') {
                return false;
            }

            const path = new URL(request.url()).pathname;

            return /\/api\/crud\/processes\/\d+$/.test(path);
        });

        await page.getByRole('button', { name: /save process/i }).click();

        const saveRequest = await saveRequestPromise;
        const payload = saveRequest.postDataJSON() as { bpmn?: string };

        expect(payload.bpmn).toContain('fontSize="16"');
        expect(payload.bpmn).toContain('textColor="#111827"');
        expect(payload.bpmn).toContain('taskBackgroundImage="data:image/svg+xml;base64,');
        expect(payload.bpmn).toContain('width="190"');
        expect(payload.bpmn).toContain('height="130"');
    });

    test('navigation away from a dirty process requires confirmation', async ({ page }) => {
        await navigateTo(page, '/app/processes');
        await dismissSessionDialogIfVisible(page);

        const processId = await ensureProcessId(page);

        await navigateTo(page, `/app/processes/${processId}/editor`);
        await dismissSessionDialogIfVisible(page);

        await expect(page.getByRole('heading', { name: /process editor/i })).toBeVisible();

        const startEvent = page.locator('.bpmn-editor .djs-element[data-element-id^="StartEvent"]').first();
        await startEvent.click({ force: true });

        const nameInput = page.locator('#ledning-name').first();
        await expect(nameInput).toBeVisible();
        await nameInput.fill('Dirty Process');

        const backButton = page.getByRole('button', { name: /back to processes/i });

        page.once('dialog', async (dialog) => {
            expect(dialog.type()).toBe('confirm');
            await dialog.dismiss();
        });

        await backButton.click();

        await expect(page).toHaveURL(new RegExp(`/app/processes/${processId}/editor$`));
        await expect(page.getByRole('heading', { name: /process editor/i })).toBeVisible();

        page.once('dialog', async (dialog) => {
            expect(dialog.type()).toBe('confirm');
            await dialog.accept();
        });

        await backButton.click();

        await expect(page).toHaveURL(/\/app\/processes$/);
    });

    test('publish is blocked while the process has unsaved changes and allowed after save', async ({ page }) => {
        await navigateTo(page, '/app/processes');
        await dismissSessionDialogIfVisible(page);

        const processId = await ensureProcessId(page);

        await navigateTo(page, `/app/processes/${processId}/editor`);
        await dismissSessionDialogIfVisible(page);

        await expect(page.getByRole('heading', { name: /process editor/i })).toBeVisible();

        const startEvent = page.locator('.bpmn-editor .djs-element[data-element-id^="StartEvent"]').first();
        await startEvent.click({ force: true });

        const nameInput = page.locator('#ledning-name').first();
        await expect(nameInput).toBeVisible();
        await nameInput.fill('Publish Dirty State');

        const saveButton = page.getByRole('button', { name: /save process/i });
        const publishButton = page.getByRole('button', { name: /publish process/i });

        await expect(saveButton).toBeEnabled();
        await expect(publishButton).toBeDisabled();

        const saveRequestPromise = page.waitForRequest((request) => {
            if (request.method() !== 'PATCH') {
                return false;
            }

            const path = new URL(request.url()).pathname;

            return /\/api\/crud\/processes\/\d+$/.test(path);
        });

        await saveButton.click();

        const saveRequest = await saveRequestPromise;
        const savePayload = saveRequest.postDataJSON() as { bpmn?: string };

        expect(savePayload.bpmn).toContain('name="Publish Dirty State"');

        await expect(page).toHaveURL(new RegExp(`/app/processes/${processId}/editor$`));
        await expect(publishButton).toBeEnabled();

        const publishRequestPromise = page.waitForRequest((request) => {
            if (request.method() !== 'POST') {
                return false;
            }

            const path = new URL(request.url()).pathname;

            return new RegExp(`/api/processes/${processId}/publish$`).test(path);
        });

        await publishButton.click();

        const publishRequest = await publishRequestPromise;
        const publishPayload = publishRequest.postDataJSON() as { bpmn?: string };

        expect(publishPayload.bpmn).toContain('name="Publish Dirty State"');
    });
});
