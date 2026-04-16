import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { GraduationCap, Users, Shield } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface QualificationsPageProps {
    route: AppSectionRoute;
}

export default function QualificationsPage({ route }: QualificationsPageProps) {
    const { t } = useTranslations();
    const [activeQualificationForEmployees, setActiveQualificationForEmployees] = useState<Record<string, any> | null>(null);
    const [activeQualificationForRoles, setActiveQualificationForRoles] = useState<Record<string, any> | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.qualifications_page.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

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
                        <Users className="h-4 w-4" />
                        {t('pages.qualifications_page.open_employees_button')}
                    </Button>
                ),
                renderDetail: (_value, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveQualificationForEmployees(row)}>
                        <Users className="h-4 w-4" />
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
                        <Shield className="h-4 w-4" />
                        {t('pages.qualifications_page.open_roles_button')}
                    </Button>
                ),
                renderDetail: (_value, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveQualificationForRoles(row)}>
                        <Shield className="h-4 w-4" />
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
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.qualifications_page.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <GraduationCap className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.qualifications_page.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.qualifications_page.description')}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {employeesConfig && (
                <Dialog open={!!activeQualificationForEmployees} onOpenChange={(open) => { if (!open) setActiveQualificationForEmployees(null); }}>
                    <DialogContent className="max-w-4xl">
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
                    <DialogContent className="max-w-4xl">
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
