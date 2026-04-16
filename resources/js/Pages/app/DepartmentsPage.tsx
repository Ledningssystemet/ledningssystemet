import { useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import { Building2, ArrowRightLeft } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { AppSectionRoute } from '@/app/routes';

interface DepartmentsPageProps {
    route: AppSectionRoute;
}

interface SharedProps extends PageProps {
    settings?: {
        departments?: {
            external_sync_enabled?: boolean;
            external_provider_name?: string;
            findings_enabled?: boolean;
        };
    };
}

interface DepartmentOption {
    id: number;
    name: string;
}

interface ReassignFormData {
    processes: string;
    risks: string;
    findings: string;
}

export default function DepartmentsPage({ route }: DepartmentsPageProps) {
    const { t } = useTranslations();
    const page = usePage<SharedProps>();

    const [reassignOpen, setReassignOpen] = useState(false);
    const [reassignPending, setReassignPending] = useState(false);
    const [crudRenderKey, setCrudRenderKey] = useState(0);
    const [activeDepartment, setActiveDepartment] = useState<Record<string, any> | null>(null);
    const [departmentOptions, setDepartmentOptions] = useState<DepartmentOption[]>([]);
    const [reassignForm, setReassignForm] = useState<ReassignFormData>({
        processes: '',
        risks: '',
        findings: '',
    });

    const externalSyncEnabled = Boolean(page.props.settings?.departments?.external_sync_enabled);
    const externalProviderName =
        page.props.settings?.departments?.external_provider_name?.trim() ||
        t('pages.departments.external_provider_default');
    const findingsEnabled = page.props.settings?.departments?.findings_enabled !== false;

    useEffect(() => {
        const loadDepartmentOptions = async () => {
            const response = await fetch('/api/crud/departments?paginate=0&%24select=id,name&sort=name', {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const json = await response.json();
            const rows = Array.isArray(json) ? json : json.data || [];
            setDepartmentOptions(rows.map((row: Record<string, any>) => ({ id: Number(row.id), name: String(row.name || '') })));
        };

        void loadDepartmentOptions();
    }, []);

    const openReassignDialog = (department: Record<string, any>) => {
        const departmentId = String(department.id ?? '');

        setActiveDepartment(department);
        setReassignForm({
            processes: department.processcount ? departmentId : '',
            risks: department.departmentriskcount ? departmentId : '',
            findings: department.departmentfindingcount ? departmentId : '',
        });
        setReassignOpen(true);
    };

    const submitReassign = async () => {
        if (!activeDepartment?.id) {
            return;
        }

        setReassignPending(true);

        try {
            const payload: Record<string, number> = {};

            if (reassignForm.processes) {
                payload.processes = Number(reassignForm.processes);
            }
            if (reassignForm.risks) {
                payload.risks = Number(reassignForm.risks);
            }
            if (findingsEnabled && reassignForm.findings) {
                payload.findings = Number(reassignForm.findings);
            }

            const response = await fetch(`/api/departments/${activeDepartment.id}/reassign`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(t('pages.departments.reassign_failed'));
            }

            setReassignOpen(false);
            setActiveDepartment(null);
            setCrudRenderKey((prev) => prev + 1);
        } catch {
            window.alert(t('pages.departments.reassign_failed'));
        } finally {
            setReassignPending(false);
        }
    };

    const config: CrudModuleConfig = useMemo(() => {
        const fields = [
            {
                key: 'name',
                label: t('pages.departments.column_name'),
                type: 'text' as const,
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.departments.category_general'),
            },
            {
                key: 'site_id',
                label: t('pages.departments.column_site'),
                type: 'select' as const,
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/sites?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.departments.none_option'),
                category: t('pages.departments.category_structure'),
            },
            {
                key: 'parent_department_id',
                label: t('pages.departments.column_parent_department'),
                type: 'select' as const,
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.departments.none_option'),
                category: t('pages.departments.category_structure'),
            },
            {
                key: 'user_ids',
                label: t('pages.departments.column_users'),
                type: 'multiselect' as const,
                sortable: false,
                editable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.departments.select_users_placeholder'),
                helpText: externalSyncEnabled ? t('pages.departments.users_sync_warning') : undefined,
                category: t('pages.departments.category_members'),
            },
            {
                key: 'department_objects',
                label: t('pages.departments.column_department_objects'),
                type: 'text' as const,
                editable: false,
                sortable: false,
                renderCell: (_value: unknown, item: Record<string, any>) => {
                    const segments: string[] = [];

                    if (item.processcount) {
                        segments.push(`${item.processcount} ${t('pages.departments.objects_processes')}`);
                    }
                    if (item.departmentriskcount) {
                        segments.push(`${item.departmentriskcount} ${t('pages.departments.objects_risks')}`);
                    }
                    if (findingsEnabled && item.departmentfindingcount) {
                        segments.push(`${item.departmentfindingcount} ${t('pages.departments.objects_findings')}`);
                    }

                    if (segments.length === 0) {
                        return <span className="text-muted-foreground">{t('pages.departments.objects_none')}</span>;
                    }

                    return (
                        <div className="space-y-1">
                            {segments.map((segment) => (
                                <div key={segment}>{segment}</div>
                            ))}
                        </div>
                    );
                },
                category: t('pages.departments.category_usage'),
            },
        ];

        const selectFields = [
            'id',
            'name',
            'site_id',
            'parent_department_id',
            'user_ids',
            'processcount',
            'departmentriskcount',
            'departmentfindingcount',
            'can_delete',
        ];

        if (externalSyncEnabled) {
            fields.splice(3, 0, {
                key: 'external_provider_group_id',
                label: `${externalProviderName} ${t('pages.departments.group_suffix')}`,
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/external-provider-groups?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.departments.none_option'),
                category: t('pages.departments.category_structure'),
            });

            selectFields.push('external_provider_group_id');
        }

        return {
            apiUrl: '/api/crud/departments',
            perPage: 25,
            defaultSort: 'name',
            selectFields,
            createTitle: t('pages.departments.create_title'),
            editTitle: t('pages.departments.edit_title'),
            deletableKey: 'can_delete',
            rowActions: [
                {
                    key: 'reassign',
                    label: t('pages.departments.reassign_action'),
                    icon: <ArrowRightLeft className="h-4 w-4" />,
                    variant: 'outline',
                    refreshOnComplete: false,
                    isVisible: (item) =>
                        Boolean(item.processcount || item.departmentriskcount || (findingsEnabled && item.departmentfindingcount)),
                    onClick: async (item) => {
                        openReassignDialog(item);
                    },
                },
            ],
            fields,
        } satisfies CrudModuleConfig;
    }, [externalProviderName, externalSyncEnabled, findingsEnabled, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.departments.title')}
                    description={t('pages.departments.description')}
                    icon={<Building2 className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule key={`departments-crud-${crudRenderKey}`} config={config} />
                </section>
            </div>

            <Dialog open={reassignOpen} onOpenChange={setReassignOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('pages.departments.reassign_dialog_title')}</DialogTitle>
                        <DialogDescription>
                            {t('pages.departments.reassign_dialog_description', {
                                department: String(activeDepartment?.name || ''),
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {Boolean(activeDepartment?.processcount) && (
                            <div className="space-y-2">
                                <Label htmlFor="department-reassign-processes">{t('pages.departments.reassign_processes_label')}</Label>
                                <select
                                    id="department-reassign-processes"
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={reassignForm.processes}
                                    onChange={(event) =>
                                        setReassignForm((prev) => ({
                                            ...prev,
                                            processes: event.target.value,
                                        }))
                                    }
                                >
                                    {departmentOptions.map((option) => (
                                        <option key={`process-${option.id}`} value={String(option.id)}>
                                            {option.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {Boolean(activeDepartment?.departmentriskcount) && (
                            <div className="space-y-2">
                                <Label htmlFor="department-reassign-risks">{t('pages.departments.reassign_risks_label')}</Label>
                                <select
                                    id="department-reassign-risks"
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={reassignForm.risks}
                                    onChange={(event) =>
                                        setReassignForm((prev) => ({
                                            ...prev,
                                            risks: event.target.value,
                                        }))
                                    }
                                >
                                    {departmentOptions.map((option) => (
                                        <option key={`risk-${option.id}`} value={String(option.id)}>
                                            {option.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {findingsEnabled && Boolean(activeDepartment?.departmentfindingcount) && (
                            <div className="space-y-2">
                                <Label htmlFor="department-reassign-findings">{t('pages.departments.reassign_findings_label')}</Label>
                                <select
                                    id="department-reassign-findings"
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={reassignForm.findings}
                                    onChange={(event) =>
                                        setReassignForm((prev) => ({
                                            ...prev,
                                            findings: event.target.value,
                                        }))
                                    }
                                >
                                    {departmentOptions.map((option) => (
                                        <option key={`finding-${option.id}`} value={String(option.id)}>
                                            {option.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setReassignOpen(false)} disabled={reassignPending}>
                            {t('pages.departments.reassign_cancel')}
                        </Button>
                        <Button type="button" onClick={() => void submitReassign()} disabled={reassignPending}>
                            {t('pages.departments.reassign_submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
