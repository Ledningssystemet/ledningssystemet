import { useMemo, useState } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface QualificationsPageProps {
    route: AppSectionRoute;
}

export default function QualificationsPage({ route }: QualificationsPageProps) {
    const { t } = useTranslations();
    const [activeQualificationForEmployees, setActiveQualificationForEmployees] = useState<Record<string, any> | null>(null);
    const [activeQualificationForRoles, setActiveQualificationForRoles] = useState<Record<string, any> | null>(null);

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/qualifications',
        perPage: 25,
        defaultSort: 'name',
        selectFields: ['id', 'name', 'description', 'expires'],
        createTitle: t('pages.qualifications_page.create_title'),
        editTitle: t('pages.qualifications_page.edit_title'),
        fields: [
            {
                key: 'name',
                label: t('pages.qualifications_page.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.qualifications_page.category_general'),
            },
            {
                key: 'description',
                label: t('pages.qualifications_page.column_description'),
                type: 'textarea',
                editable: true,
                masterDescription: true,
                category: t('pages.qualifications_page.category_general'),
            },
            {
                key: 'expires',
                label: t('pages.qualifications_page.column_expires'),
                type: 'boolean',
                sortable: true,
                editable: true,
                required: true,
                options: [
                    { value: '1', label: t('pages.qualifications_page.option_yes') },
                    { value: '0', label: t('pages.qualifications_page.option_no') },
                ],
                category: t('pages.qualifications_page.category_general'),
            },
            {
                key: 'employees_action',
                label: t('pages.qualifications_page.column_employees'),
                type: 'text',
                sortable: false,
                editable: false,
                category: t('pages.qualifications_page.category_relations'),
                renderCell: (_value, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveQualificationForEmployees(row)}>
                        <MaterialSymbol name="group" className="h-4 w-4" />
                        {t('pages.qualifications_page.open_employees_button')}
                    </Button>
                ),
                renderDetail: (_value, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveQualificationForEmployees(row)}>
                        <MaterialSymbol name="group" className="h-4 w-4" />
                        {t('pages.qualifications_page.open_employees_button')}
                    </Button>
                ),
            },
            {
                key: 'roles_action',
                label: t('pages.qualifications_page.column_roles'),
                type: 'text',
                sortable: false,
                editable: false,
                category: t('pages.qualifications_page.category_relations'),
                renderCell: (_value, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveQualificationForRoles(row)}>
                        <MaterialSymbol name="shield" className="h-4 w-4" />
                        {t('pages.qualifications_page.open_roles_button')}
                    </Button>
                ),
                renderDetail: (_value, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveQualificationForRoles(row)}>
                        <MaterialSymbol name="shield" className="h-4 w-4" />
                        {t('pages.qualifications_page.open_roles_button')}
                    </Button>
                ),
            },
        ],
    }), [t]);

    const employeesConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeQualificationForEmployees?.id) {
            return null;
        }

        return {
            apiUrl: '/api/crud/qualification-users',
            perPage: 50,
            defaultSort: '-id',
            fixedFilters: { qualification_id: Number(activeQualificationForEmployees.id) },
            createDefaults: { qualification_id: Number(activeQualificationForEmployees.id) },
            selectFields: ['id', 'qualification_id', 'user_id', 'finished_at', 'expires_at', 'planned_at', 'note'],
            createTitle: t('pages.qualifications_page.employees.create_title'),
            editTitle: t('pages.qualifications_page.employees.edit_title'),
            fields: [
                {
                    key: 'user_id',
                    label: t('pages.qualifications_page.employees.column_user'),
                    type: 'select',
                    sortable: false,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.qualifications_page.employees.category_general'),
                },
                {
                    key: 'finished_at',
                    label: t('pages.qualifications_page.employees.column_finished_at'),
                    type: 'date',
                    sortable: true,
                    editable: true,
                    category: t('pages.qualifications_page.employees.category_general'),
                },
                {
                    key: 'expires_at',
                    label: t('pages.qualifications_page.employees.column_expires_at'),
                    type: 'date',
                    sortable: true,
                    editable: true,
                    category: t('pages.qualifications_page.employees.category_general'),
                },
                {
                    key: 'planned_at',
                    label: t('pages.qualifications_page.employees.column_planned_at'),
                    type: 'date',
                    sortable: true,
                    editable: true,
                    category: t('pages.qualifications_page.employees.category_general'),
                },
                {
                    key: 'note',
                    label: t('pages.qualifications_page.employees.column_note'),
                    type: 'textarea',
                    editable: true,
                    category: t('pages.qualifications_page.employees.category_general'),
                },
                {
                    key: 'qualification_id',
                    label: 'qualification_id',
                    type: 'number',
                    editable: false,
                    hidden: true,
                },
            ],
        };
    }, [activeQualificationForEmployees?.id, t]);

    const rolesConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeQualificationForRoles?.id) {
            return null;
        }

        return {
            apiUrl: '/api/crud/qualification-roles',
            perPage: 50,
            defaultSort: '-id',
            fixedFilters: { qualification_id: Number(activeQualificationForRoles.id) },
            createDefaults: { qualification_id: Number(activeQualificationForRoles.id) },
            selectFields: ['id', 'qualification_id', 'role_id', 'mandatory'],
            createTitle: t('pages.qualifications_page.roles.create_title'),
            editTitle: t('pages.qualifications_page.roles.edit_title'),
            fields: [
                {
                    key: 'role_id',
                    label: t('pages.qualifications_page.roles.column_role'),
                    type: 'select',
                    sortable: false,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    optionsUrl: '/api/crud/roles?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.qualifications_page.roles.category_general'),
                },
                {
                    key: 'mandatory',
                    label: t('pages.qualifications_page.roles.column_mandatory'),
                    type: 'boolean',
                    sortable: true,
                    editable: true,
                    required: true,
                    category: t('pages.qualifications_page.roles.category_general'),
                },
                {
                    key: 'qualification_id',
                    label: 'qualification_id',
                    type: 'number',
                    editable: false,
                    hidden: true,
                },
            ],
        };
    }, [activeQualificationForRoles?.id, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.qualifications_page.title')}
                    description={t('pages.qualifications_page.description')}
                    icon={<MaterialSymbol name="school" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {employeesConfig && (
                <Dialog open={!!activeQualificationForEmployees} onOpenChange={(open) => { if (!open) setActiveQualificationForEmployees(null); }}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t('pages.qualifications_page.employees.panel_title', {
                                    qualification: activeQualificationForEmployees?.name ?? '',
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('pages.qualifications_page.employees.panel_description')}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="mt-2">
                            <CrudModule config={employeesConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}

            {rolesConfig && (
                <Dialog open={!!activeQualificationForRoles} onOpenChange={(open) => { if (!open) setActiveQualificationForRoles(null); }}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t('pages.qualifications_page.roles.panel_title', {
                                    qualification: activeQualificationForRoles?.name ?? '',
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('pages.qualifications_page.roles.panel_description')}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="mt-2">
                            <CrudModule config={rolesConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
