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
});
