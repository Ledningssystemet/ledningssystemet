import { useMemo, useState, useEffect } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import type { AppSectionRoute } from '@/app/routes';

interface UsersPageProps {
    route: AppSectionRoute;
}

interface UserOption {
    id: number;
    name: string;
}

const REASSIGN_KEYS = [
    'activities',
    'assets',
    'controls',
    'control_actions',
    'incidents',
    'information_types',
    'objectives',
    'processes',
    'process_performance_metrics',
    'risks',
    'suppliers',
] as const;

type ReassignKey = (typeof REASSIGN_KEYS)[number];
type ReassignFormData = Record<ReassignKey, string>;

export default function UsersPage({ route }: UsersPageProps) {
    const { t } = useTranslations();

    const [crudRenderKey, setCrudRenderKey] = useState(0);
    const [reassignOpen, setReassignOpen] = useState(false);
    const [reassignPending, setReassignPending] = useState(false);
    const [activeUser, setActiveUser] = useState<Record<string, any> | null>(null);
    const [userOptions, setUserOptions] = useState<UserOption[]>([]);
    const [reassignForm, setReassignForm] = useState<ReassignFormData>({
        activities: '',
        assets: '',
        controls: '',
        control_actions: '',
        incidents: '',
        information_types: '',
        objectives: '',
        processes: '',
        process_performance_metrics: '',
        risks: '',
        suppliers: '',
    });

    useEffect(() => {
        const loadUserOptions = async () => {
            const response = await fetch('/api/crud/users?paginate=0&%24select=id,name&sort=name', {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const json = await response.json();
            const rows = Array.isArray(json) ? json : json.data || [];
            setUserOptions(rows.map((row: Record<string, any>) => ({ id: Number(row.id), name: String(row.name || '') })));
        };

        void loadUserOptions();
    }, []);

    const openReassignDialog = (user: Record<string, any>) => {
        const userId = String(user.id ?? '');

        setActiveUser(user);
        setReassignForm({
            activities: user.activitiescount ? userId : '',
            assets: user.assetscount ? userId : '',
            controls: user.controlscount ? userId : '',
            control_actions: user.control_actionscount ? userId : '',
            incidents: user.incidentscount ? userId : '',
            information_types: user.information_typescount ? userId : '',
            objectives: user.objectivescount ? userId : '',
            processes: user.processescount ? userId : '',
            process_performance_metrics: user.process_performance_metricscount ? userId : '',
            risks: user.riskscount ? userId : '',
            suppliers: user.supplierscount ? userId : '',
        });
        setReassignOpen(true);
    };

    const submitReassign = async () => {
        if (!activeUser?.id) {
            return;
        }

        setReassignPending(true);

        try {
            const payload: Partial<Record<ReassignKey, number>> = {};
            for (const key of REASSIGN_KEYS) {
                if (reassignForm[key]) {
                    payload[key] = Number(reassignForm[key]);
                }
            }

            const response = await fetch(`/api/users/${activeUser.id}/reassign`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(t('pages.users.reassign_failed'));
            }

            setReassignOpen(false);
            setActiveUser(null);
            setCrudRenderKey((prev) => prev + 1);
        } catch {
            window.alert(t('pages.users.reassign_failed'));
        } finally {
            setReassignPending(false);
        }
    };

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/users',
        perPage: 25,
        defaultSort: 'name',
        selectFields: [
            'id',
            'name',
            'title',
            'enabled',
            'manager_user_id',
            'email',
            'last_login_at',
            'departments',
            'roles',
            'accessgroups',
            'direct_reports',
            'activitiescount',
            'assetscount',
            'controlscount',
            'control_actionscount',
            'findingscount',
            'incidentscount',
            'information_typescount',
            'objectivescount',
            'processescount',
            'process_performance_metricscount',
            'riskscount',
            'supplierscount',
            'external_id',
            'can_delete',
        ],
        createTitle: t('pages.users.create_title'),
        editTitle: t('pages.users.edit_title'),
        deletableKey: 'can_delete',
        fields: [
            {
                key: 'name',
                label: t('pages.users.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.users.category_general'),
            },
            {
                key: 'title',
                label: t('pages.users.column_title'),
                type: 'text',
                sortable: true,
                editable: true,
                category: t('pages.users.category_general'),
            },
            {
                key: 'enabled',
                label: t('pages.users.column_enabled'),
                type: 'boolean',
                sortable: true,
                editable: true,
                required: true,
                category: t('pages.users.category_general'),
            },
            {
                key: 'manager_user_id',
                label: t('pages.users.column_manager'),
                type: 'select',
                sortable: true,
                editable: true,
                editableOnCreate: false,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.users.none_option'),
                category: t('pages.users.category_general'),
            },
            {
                key: 'email',
                label: t('pages.users.column_email'),
                type: 'email',
                sortable: true,
                editable: true,
                required: true,
                category: t('pages.users.category_general'),
            },
            {
                key: 'last_login_at',
                label: t('pages.users.column_last_login'),
                type: 'datetime',
                sortable: true,
                editable: false,
                category: t('pages.users.category_general'),
            },
            {
                key: 'departments',
                label: t('pages.users.column_departments'),
                type: 'multiselect',
                sortable: false,
                editable: true,
                editableOnCreate: false,
                optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.users.select_departments_placeholder'),
                category: t('pages.users.category_assignments'),
            },
            {
                key: 'roles',
                label: t('pages.users.column_roles'),
                type: 'multiselect',
                sortable: false,
                editable: true,
                editableOnCreate: false,
                optionsUrl: '/api/crud/roles?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.users.select_roles_placeholder'),
                category: t('pages.users.category_assignments'),
            },
            {
                key: 'accessgroups',
                label: t('pages.users.column_access_groups'),
                type: 'multiselect',
                sortable: false,
                editable: true,
                editableOnCreate: false,
                optionsUrl: '/api/crud/access-groups?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.users.select_access_groups_placeholder'),
                category: t('pages.users.category_assignments'),
            },
            {
                key: 'direct_reports',
                label: t('pages.users.column_direct_reports'),
                type: 'text',
                sortable: false,
                editable: false,
                renderCell: (value: unknown) => {
                    const reports = Array.isArray(value) ? value : [];
                    if (reports.length === 0) {
                        return <span className="text-muted-foreground">{t('pages.users.none_option')}</span>;
                    }

                    return (
                        <div className="space-y-1">
                            {reports.map((report: Record<string, any>) => (
                                <div key={String(report.id ?? report.name)}>{String(report.name ?? '')}</div>
                            ))}
                        </div>
                    );
                },
                category: t('pages.users.category_assignments'),
            },
        ],
        rowActions: [
            {
                key: 'reassign',
                label: t('pages.users.reassign_action'),
                icon: <MaterialSymbol name="swap_horiz" className="h-4 w-4" />,
                variant: 'outline',
                refreshOnComplete: false,
                isVisible: (item) => Boolean(
                    item.activitiescount ||
                    item.assetscount ||
                    item.controlscount ||
                    item.control_actionscount ||
                    item.incidentscount ||
                    item.information_typescount ||
                    item.objectivescount ||
                    item.processescount ||
                    item.process_performance_metricscount ||
                    item.riskscount ||
                    item.supplierscount,
                ),
                onClick: async (item) => {
                    openReassignDialog(item);
                },
            },
            {
                key: 'password_reset',
                label: t('pages.users.password_reset_action'),
                icon: <MaterialSymbol name="vpn_key" className="h-4 w-4" />,
                variant: 'outline',
                refreshOnComplete: false,
                onClick: async (item) => {
                    if (!window.confirm(t('pages.users.password_reset_confirm'))) {
                        return;
                    }

                    const response = await fetch(`/api/users/${item.id}/password-reset`, {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                    });

                    const json = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        window.alert(String(json.message || t('pages.users.password_reset_failed')));
                        return;
                    }

                    window.alert(String(json.message || t('pages.users.password_reset_sent')));
                },
            },
        ],
    }), [t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.users.title')}
                    description={t('pages.users.description')}
                    icon={<MaterialSymbol name="group" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule key={`users-crud-${crudRenderKey}`} config={config} />
                </section>
            </div>

            <Dialog open={reassignOpen} onOpenChange={setReassignOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('pages.users.reassign_dialog_title')}</DialogTitle>
                        <DialogDescription>
                            {t('pages.users.reassign_dialog_description', {
                                user: String(activeUser?.name || ''),
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {REASSIGN_KEYS.map((key) => {
                            const countKey = key === 'control_actions' ? 'control_actionscount' : `${key}count`;
                            if (!activeUser?.[countKey]) {
                                return null;
                            }

                            return (
                                <div key={key} className="space-y-2">
                                    <Label htmlFor={`user-reassign-${key}`}>{t(`pages.users.reassign_${key}_label`)}</Label>
                                    <select
                                        id={`user-reassign-${key}`}
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        value={reassignForm[key]}
                                        onChange={(event) =>
                                            setReassignForm((prev) => ({
                                                ...prev,
                                                [key]: event.target.value,
                                            }))
                                        }
                                    >
                                        {userOptions.map((option) => (
                                            <option key={`${key}-${option.id}`} value={String(option.id)}>
                                                {option.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            );
                        })}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setReassignOpen(false)} disabled={reassignPending}>
                            {t('pages.users.reassign_cancel')}
                        </Button>
                        <Button type="button" onClick={() => void submitReassign()} disabled={reassignPending}>
                            {t('pages.users.reassign_submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
