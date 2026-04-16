import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { GraduationCap, Shield, Sparkles } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AppSectionRoute } from '@/app/routes';

interface RolesPageProps {
    route: AppSectionRoute;
}

export default function RolesPage({ route }: RolesPageProps) {
    const { t } = useTranslations();
    const [roleUsersByRoleId, setRoleUsersByRoleId] = useState<Record<number, string[]>>({});
    const [activeRoleForQualifications, setActiveRoleForQualifications] = useState<Record<string, any> | null>(null);
    const [activeRoleForCompetences, setActiveRoleForCompetences] = useState<Record<string, any> | null>(null);
    const [activeRoleCompetenceFormData, setActiveRoleCompetenceFormData] = useState<Record<string, any>>({});

    const selectedCompetenceId = useMemo(() => {
        const parsedCompetenceId = Number(activeRoleCompetenceFormData.competence_id);
        return Number.isFinite(parsedCompetenceId) && parsedCompetenceId > 0
            ? parsedCompetenceId
            : null;
    }, [activeRoleCompetenceFormData.competence_id]);

    const competenceLevelsOptionsUrl = selectedCompetenceId
        ? `/api/crud/competence-levels?paginate=0&%24select=id,name&sort=name&filter[competence_id]=${selectedCompetenceId}`
        : undefined;

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.roles.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    useEffect(() => {
        const loadRoleUsers = async () => {
            const response = await fetch('/api/crud/users?paginate=0&%24select=id,name,roles&sort=name', {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                setRoleUsersByRoleId({});
                return;
            }

            const json = await response.json();
            const rows = Array.isArray(json) ? json : json.data || [];
            const grouped: Record<number, string[]> = {};

            rows.forEach((row: Record<string, any>) => {
                const userName = String(row.name || '').trim();
                if (!userName) {
                    return;
                }

                const roleIds = Array.isArray(row.roles) ? row.roles : [];
                roleIds.forEach((roleId) => {
                    const parsedRoleId = Number(roleId);
                    if (!Number.isFinite(parsedRoleId) || parsedRoleId <= 0) {
                        return;
                    }

                    if (!grouped[parsedRoleId]) {
                        grouped[parsedRoleId] = [];
                    }

                    grouped[parsedRoleId].push(userName);
                });
            });

            setRoleUsersByRoleId(grouped);
        };

        void loadRoleUsers();
    }, []);

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/roles',
        perPage: 25,
        defaultSort: 'name',
        canCreate: false,
        canDelete: false,
        selectFields: ['id', 'name', 'description', 'authorities'],
        editTitle: t('pages.roles.edit_title'),
        fields: [
            {
                key: 'name',
                label: t('pages.roles.column_name'),
                type: 'text',
                sortable: true,
                editable: false,
                required: true,
                masterLabel: true,
                category: t('pages.roles.category_general'),
            },
            {
                key: 'role_users',
                label: t('pages.roles.column_users'),
                type: 'text',
                sortable: false,
                editable: false,
                category: t('pages.roles.category_members'),
                renderCell: (_value, row) => {
                    const roleId = Number(row.id);
                    const users = Number.isFinite(roleId) ? roleUsersByRoleId[roleId] || [] : [];

                    if (users.length === 0) {
                        return <span className="text-muted-foreground">{t('pages.roles.none_assigned')}</span>;
                    }

                    return (
                        <div className="space-y-1">
                            {users.map((name) => (
                                <div key={`${roleId}-${name}`}>{name}</div>
                            ))}
                        </div>
                    );
                },
                renderDetail: (_value, row) => {
                    const roleId = Number(row.id);
                    const users = Number.isFinite(roleId) ? roleUsersByRoleId[roleId] || [] : [];

                    if (users.length === 0) {
                        return <span className="text-muted-foreground">{t('pages.roles.none_assigned')}</span>;
                    }

                    return (
                        <div className="space-y-1">
                            {users.map((name) => (
                                <div key={`detail-${roleId}-${name}`}>{name}</div>
                            ))}
                        </div>
                    );
                },
            },
            {
                key: 'description',
                label: t('pages.roles.column_description'),
                type: 'textarea',
                sortable: true,
                editable: true,
                masterDescription: true,
                category: t('pages.roles.category_general'),
            },
            {
                key: 'authorities',
                label: t('pages.roles.column_authorities'),
                type: 'textarea',
                sortable: true,
                editable: true,
                category: t('pages.roles.category_permissions'),
            },
            {
                key: 'qualifications_action',
                label: t('pages.roles.column_qualifications'),
                type: 'text',
                sortable: false,
                editable: false,
                category: t('pages.roles.category_relations'),
                renderCell: (_value, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveRoleForQualifications(row)}>
                        <GraduationCap className="h-4 w-4" />
                        {t('pages.roles.open_qualifications_button')}
                    </Button>
                ),
                renderDetail: (_value, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveRoleForQualifications(row)}>
                        <GraduationCap className="h-4 w-4" />
                        {t('pages.roles.open_qualifications_button')}
                    </Button>
                ),
            },
            {
                key: 'competences_action',
                label: t('pages.roles.column_competences'),
                type: 'text',
                sortable: false,
                editable: false,
                category: t('pages.roles.category_relations'),
                renderCell: (_value, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveRoleForCompetences(row)}>
                        <Sparkles className="h-4 w-4" />
                        {t('pages.roles.open_competences_button')}
                    </Button>
                ),
                renderDetail: (_value, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveRoleForCompetences(row)}>
                        <Sparkles className="h-4 w-4" />
                        {t('pages.roles.open_competences_button')}
                    </Button>
                ),
            },
        ],
    }), [roleUsersByRoleId, t]);

    const qualificationsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeRoleForQualifications?.id) {
            return null;
        }

        return {
            apiUrl: '/api/crud/qualification-roles',
            perPage: 25,
            defaultSort: '-id',
            fixedFilters: { role_id: Number(activeRoleForQualifications.id) },
            createDefaults: { role_id: Number(activeRoleForQualifications.id) },
            selectFields: ['id', 'role_id', 'qualification_id', 'mandatory'],
            createTitle: t('pages.roles.qualifications.create_title'),
            editTitle: t('pages.roles.qualifications.edit_title'),
            fields: [
                {
                    key: 'qualification_id',
                    label: t('pages.roles.qualifications.column_qualification'),
                    type: 'select',
                    sortable: false,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    optionsUrl: '/api/crud/qualifications?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.roles.qualifications.category_general'),
                },
                {
                    key: 'mandatory',
                    label: t('pages.roles.qualifications.column_mandatory'),
                    type: 'boolean',
                    sortable: true,
                    editable: true,
                    required: true,
                    category: t('pages.roles.qualifications.category_general'),
                },
                {
                    key: 'role_id',
                    label: t('pages.roles.qualifications.column_role'),
                    type: 'number',
                    editable: false,
                    hidden: true,
                },
            ],
        };
    }, [activeRoleForQualifications?.id, t]);

    const competencesConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeRoleForCompetences?.id) {
            return null;
        }

        return {
            apiUrl: '/api/crud/role-competences',
            perPage: 25,
            defaultSort: '-id',
            fixedFilters: { role_id: Number(activeRoleForCompetences.id) },
            createDefaults: { role_id: Number(activeRoleForCompetences.id) },
            selectFields: [
                'id',
                'role_id',
                'competence_id',
                'acceptable_competence_level_id',
                'desired_competence_level_id',
            ],
            createTitle: t('pages.roles.competences.create_title'),
            editTitle: t('pages.roles.competences.edit_title'),
            fields: [
                {
                    key: 'competence_id',
                    label: t('pages.roles.competences.column_competence'),
                    type: 'select',
                    sortable: false,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    optionsUrl: '/api/crud/competences?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.roles.competences.category_general'),
                },
                {
                    key: 'acceptable_competence_level_id',
                    label: t('pages.roles.competences.column_acceptable_level'),
                    type: 'select',
                    sortable: false,
                    editable: true,
                    optionsUrl: competenceLevelsOptionsUrl,
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.roles.competences.category_levels'),
                },
                {
                    key: 'desired_competence_level_id',
                    label: t('pages.roles.competences.column_desired_level'),
                    type: 'select',
                    sortable: false,
                    editable: true,
                    optionsUrl: competenceLevelsOptionsUrl,
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.roles.competences.category_levels'),
                },
                {
                    key: 'role_id',
                    label: t('pages.roles.competences.column_role'),
                    type: 'number',
                    editable: false,
                    hidden: true,
                },
            ],
        };
    }, [activeRoleForCompetences?.id, competenceLevelsOptionsUrl, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.roles.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <Shield className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.roles.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.roles.description')}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {qualificationsConfig && (
                <Dialog
                    open={Boolean(activeRoleForQualifications)}
                    onOpenChange={(open) => !open && setActiveRoleForQualifications(null)}
                >
                    <DialogContent className="max-w-3xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <GraduationCap className="h-5 w-5" />
                                {t('pages.roles.qualifications.panel_title', {
                                    role: String(activeRoleForQualifications?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>{t('pages.roles.qualifications.panel_description')}</DialogDescription>
                        </DialogHeader>

                        <div className="mt-2">
                            <CrudModule key={`role-qualifications-${activeRoleForQualifications?.id}`} config={qualificationsConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}

            {competencesConfig && (
                <Dialog
                    open={Boolean(activeRoleForCompetences)}
                    onOpenChange={(open) => {
                        if (!open) {
                            setActiveRoleForCompetences(null);
                            setActiveRoleCompetenceFormData({});
                        }
                    }}
                >
                    <DialogContent className="max-w-3xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Sparkles className="h-5 w-5" />
                                {t('pages.roles.competences.panel_title', {
                                    role: String(activeRoleForCompetences?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>{t('pages.roles.competences.panel_description')}</DialogDescription>
                        </DialogHeader>

                        <div className="mt-2">
                            <CrudModule
                                key={`role-competences-${activeRoleForCompetences?.id}`}
                                config={competencesConfig}
                                onEditFormDataChange={setActiveRoleCompetenceFormData}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
