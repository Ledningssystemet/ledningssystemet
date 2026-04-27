import { useCallback, useEffect, useMemo, useState } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface RolesPageProps {
    route: AppSectionRoute;
}

export default function RolesPage({ route }: RolesPageProps) {
    const { t } = useTranslations();
    const [roleUsersByRoleId, setRoleUsersByRoleId] = useState<Record<number, string[]>>({});
    const [activeRoleForCompetences, setActiveRoleForCompetences] = useState<Record<string, any> | null>(null);
    const [activeRoleCompetenceFormData, setActiveRoleCompetenceFormData] = useState<Record<string, any>>({});

    const selectedCompetenceId = useMemo(() => {
        const parsed = Number(activeRoleCompetenceFormData.competence_id);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
    }, [activeRoleCompetenceFormData.competence_id]);

    const competenceLevelsOptionsUrl = selectedCompetenceId
        ? `/api/crud/competence-levels?paginate=0&%24select=id,name&sort=name&filter[competence_id]=${selectedCompetenceId}`
        : undefined;

    useEffect(() => {
        const loadRoleUsers = async () => {
            const response = await fetch('/api/crud/users?paginate=0&%24select=id,name,roles&sort=name', {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) { setRoleUsersByRoleId({}); return; }
            const json = await response.json();
            const rows = Array.isArray(json) ? json : json.data || [];
            const grouped: Record<number, string[]> = {};
            rows.forEach((row: Record<string, any>) => {
                const userName = String(row.name || '').trim();
                if (!userName) return;
                const roleIds = Array.isArray(row.roles) ? row.roles : [];
                roleIds.forEach((roleId: unknown) => {
                    const parsed = Number(roleId);
                    if (!Number.isFinite(parsed) || parsed <= 0) return;
                    if (!grouped[parsed]) grouped[parsed] = [];
                    grouped[parsed].push(userName);
                });
            });
            setRoleUsersByRoleId(grouped);
        };
        void loadRoleUsers();
    }, []);

    const competencesConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeRoleForCompetences?.id) return null;
        return {
            apiUrl: '/api/crud/role-competences',
            perPage: 25,
            defaultSort: '-id',
            fixedFilters: { role_id: Number(activeRoleForCompetences.id) },
            createDefaults: { role_id: Number(activeRoleForCompetences.id) },
            selectFields: ['id', 'role_id', 'competence_id', 'acceptable_competence_level_id', 'desired_competence_level_id'],
            createTitle: t('pages.roles.competences.create_title'),
            editTitle: t('pages.roles.competences.edit_title'),
            fields: [
                { key: 'competence_id', label: t('pages.roles.competences.column_competence'), type: 'select', sortable: false, editable: true, required: true, masterLabel: true, optionsUrl: '/api/crud/competences?paginate=0&%24select=id,name&sort=name', optionValueKey: 'id', optionLabelKey: 'name', category: t('pages.roles.competences.category_general') },
                { key: 'acceptable_competence_level_id', label: t('pages.roles.competences.column_acceptable_level'), type: 'select', sortable: false, editable: true, optionsUrl: competenceLevelsOptionsUrl, optionValueKey: 'id', optionLabelKey: 'name', category: t('pages.roles.competences.category_levels') },
                { key: 'desired_competence_level_id', label: t('pages.roles.competences.column_desired_level'), type: 'select', sortable: false, editable: true, optionsUrl: competenceLevelsOptionsUrl, optionValueKey: 'id', optionLabelKey: 'name', category: t('pages.roles.competences.category_levels') },
                { key: 'role_id', label: t('pages.roles.competences.column_role'), type: 'number', editable: false, hidden: true },
            ],
        };
    }, [activeRoleForCompetences?.id, competenceLevelsOptionsUrl, t]);

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/roles',
        perPage: 25,
        defaultSort: 'name',
        canCreate: false,
        canDelete: false,
        selectFields: ['id', 'name', 'description', 'authorities'],
        editTitle: t('pages.roles.edit_title'),
        rowActions: [
            {
                key: 'competences',
                label: t('pages.roles.open_competences_button'),
                icon: <MaterialSymbol name="auto_awesome" className="h-4 w-4" />,
                variant: 'outline',
                refreshOnComplete: false,
                onClick: (item) => setActiveRoleForCompetences(item),
            },
        ],
        subTableActions: [
            {
                key: 'qualifications',
                label: t('pages.roles.open_qualifications_button'),
                icon: <MaterialSymbol name="school" className="h-4 w-4" />,
                dialogMaxWidth: 'max-w-3xl',
                dialogTitle: (item) => t('pages.roles.qualifications.panel_title', { role: String(item.name || '') }),
                dialogDescription: t('pages.roles.qualifications.panel_description'),
                getConfig: (item): CrudModuleConfig => ({
                    apiUrl: '/api/crud/qualification-roles',
                    perPage: 25,
                    defaultSort: '-id',
                    fixedFilters: { role_id: Number(item.id) },
                    createDefaults: { role_id: Number(item.id) },
                    selectFields: ['id', 'role_id', 'qualification_id', 'mandatory'],
                    createTitle: t('pages.roles.qualifications.create_title'),
                    editTitle: t('pages.roles.qualifications.edit_title'),
                    fields: [
                        { key: 'qualification_id', label: t('pages.roles.qualifications.column_qualification'), type: 'select', sortable: false, editable: true, required: true, masterLabel: true, optionsUrl: '/api/crud/qualifications?paginate=0&%24select=id,name&sort=name', optionValueKey: 'id', optionLabelKey: 'name', category: t('pages.roles.qualifications.category_general') },
                        { key: 'mandatory', label: t('pages.roles.qualifications.column_mandatory'), type: 'boolean', sortable: true, editable: true, required: true, category: t('pages.roles.qualifications.category_general') },
                        { key: 'role_id', label: t('pages.roles.qualifications.column_role'), type: 'number', editable: false, hidden: true },
                    ],
                }),
            },
        ],
        fields: [
            { key: 'name', label: t('pages.roles.column_name'), type: 'text', sortable: true, editable: false, required: true, masterLabel: true, category: t('pages.roles.category_general') },
            {
                key: 'role_users', label: t('pages.roles.column_users'), type: 'text', sortable: false, editable: false,
                category: t('pages.roles.category_members'),
                renderCell: (_v, row) => {
                    const users = roleUsersByRoleId[Number(row.id)] || [];
                    return users.length === 0
                        ? <span className="text-muted-foreground">{t('pages.roles.none_assigned')}</span>
                        : <div className="space-y-1">{users.map((n) => <div key={n}>{n}</div>)}</div>;
                },
                renderDetail: (_v, row) => {
                    const users = roleUsersByRoleId[Number(row.id)] || [];
                    return users.length === 0
                        ? <span className="text-muted-foreground">{t('pages.roles.none_assigned')}</span>
                        : <div className="space-y-1">{users.map((n) => <div key={`d-${n}`}>{n}</div>)}</div>;
                },
            },
            { key: 'description', label: t('pages.roles.column_description'), type: 'textarea', sortable: true, editable: true, masterDescription: true, category: t('pages.roles.category_general') },
            { key: 'authorities', label: t('pages.roles.column_authorities'), type: 'textarea', sortable: true, editable: true, category: t('pages.roles.category_permissions') },
        ],
    }), [roleUsersByRoleId, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.roles.title')}
                    description={t('pages.roles.description')}
                    icon={<MaterialSymbol name="shield" className="h-6 w-6 text-primary" />}
                    route={route}
                />
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {/* Competences dialog kept manual due to dynamic optionsUrl based on form state */}
            {competencesConfig && (
                <Dialog
                    open={Boolean(activeRoleForCompetences)}
                    onOpenChange={(open) => { if (!open) { setActiveRoleForCompetences(null); setActiveRoleCompetenceFormData({}); } }}
                >
                    <DialogContent className="max-w-3xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <MaterialSymbol name="auto_awesome" className="h-5 w-5" />
                                {t('pages.roles.competences.panel_title', { role: String(activeRoleForCompetences?.name || '') })}
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
