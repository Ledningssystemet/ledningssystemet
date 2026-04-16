import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ListChecks, ShieldAlert } from 'lucide-react';
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

interface ProjectTypesPageProps {
    route: AppSectionRoute;
}

export default function ProjectTypesPage({ route }: ProjectTypesPageProps) {
    const { t } = useTranslations();
    const [activeProjectTypeForTemplates, setActiveProjectTypeForTemplates] = useState<Record<string, any> | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.project_types.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/risk-project-types',
            perPage: 25,
            defaultSort: 'name',
            createTitle: t('pages.project_types.create_title'),
            editTitle: t('pages.project_types.edit_title'),
            selectFields: ['id', 'name', 'description', 'partner_id', 'partner_name'],
            rowActions: [
                {
                    key: 'templates',
                    label: t('pages.project_types.open_templates_button'),
                    variant: 'outline',
                    refreshOnComplete: false,
                    onClick: (item) => setActiveProjectTypeForTemplates(item),
                },
            ],
            fields: [
                {
                    key: 'name',
                    label: t('pages.project_types.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.project_types.category_general'),
                },
                {
                    key: 'partner_info',
                    label: t('pages.project_types.column_partner_info'),
                    type: 'text',
                    sortable: false,
                    editable: false,
                    renderCell: (_, row) =>
                        row.partner_id
                            ? t('pages.project_types.partner_info', {
                                  partner: String(row.partner_name ?? ''),
                              })
                            : '',
                    renderDetail: (_, row) =>
                        row.partner_id
                            ? t('pages.project_types.partner_info', {
                                  partner: String(row.partner_name ?? ''),
                              })
                            : '',
                    category: t('pages.project_types.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.project_types.column_description'),
                    type: 'textarea',
                    sortable: false,
                    editable: true,
                    required: true,
                    masterDescription: true,
                    category: t('pages.project_types.category_general'),
                },
            ],
        }),
        [t]
    );

    const templatesConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeProjectTypeForTemplates?.id) {
            return null;
        }

        const isReadOnly = Boolean(activeProjectTypeForTemplates.partner_id);

        return {
            apiUrl: '/api/crud/risk-project-type-risk-templates',
            perPage: 100,
            defaultSort: 'name',
            fixedFilters: {
                project_type_id: Number(activeProjectTypeForTemplates.id),
            },
            createDefaults: {
                project_type_id: Number(activeProjectTypeForTemplates.id),
            },
            canCreate: !isReadOnly,
            canEdit: !isReadOnly,
            canDelete: !isReadOnly,
            createTitle: t('pages.project_types.templates.create_title'),
            editTitle: t('pages.project_types.templates.edit_title'),
            selectFields: [
                'id',
                'project_type_id',
                'name',
                'scenariodescription',
                'consequencedescription',
                'probability_id',
                'consequence_id',
                'controls',
            ],
            fields: [
                {
                    key: 'project_type_id',
                    label: t('pages.project_types.templates.column_project_type'),
                    type: 'number',
                    editable: false,
                    hidden: true,
                },
                {
                    key: 'name',
                    label: t('pages.project_types.templates.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.project_types.templates.category_general'),
                },
                {
                    key: 'scenariodescription',
                    label: t('pages.project_types.templates.column_risk_scenario'),
                    type: 'textarea',
                    sortable: false,
                    editable: true,
                    category: t('pages.project_types.templates.category_assessment'),
                },
                {
                    key: 'consequencedescription',
                    label: t('pages.project_types.templates.column_consequence_description'),
                    type: 'textarea',
                    sortable: false,
                    editable: true,
                    category: t('pages.project_types.templates.category_assessment'),
                },
                {
                    key: 'probability_id',
                    label: t('pages.project_types.templates.column_probability'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    optionsUrl: '/api/crud/probability-levels?paginate=0&%24select=id,name&sort=-ordinal',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.project_types.templates.not_assessed'),
                    category: t('pages.project_types.templates.category_assessment'),
                },
                {
                    key: 'consequence_id',
                    label: t('pages.project_types.templates.column_consequence'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    optionsUrl: '/api/crud/consequence-levels?paginate=0&%24select=id,name&sort=-ordinal',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.project_types.templates.not_assessed'),
                    category: t('pages.project_types.templates.category_assessment'),
                },
                {
                    key: 'controls',
                    label: t('pages.project_types.templates.column_controls'),
                    type: 'multiselect',
                    sortable: false,
                    editable: true,
                    optionsUrl: '/api/crud/controls?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.project_types.templates.category_controls'),
                },
            ],
        };
    }, [activeProjectTypeForTemplates, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.project_types.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <ShieldAlert className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.project_types.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.project_types.description')}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {templatesConfig && (
                <Dialog
                    open={Boolean(activeProjectTypeForTemplates)}
                    onOpenChange={(open) => !open && setActiveProjectTypeForTemplates(null)}
                >
                    <DialogContent className="max-w-5xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ListChecks className="h-5 w-5" />
                                {t('pages.project_types.templates.panel_title', {
                                    type: String(activeProjectTypeForTemplates?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>{t('pages.project_types.templates.panel_description')}</DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button type="button" variant="outline" size="sm" onClick={() => setActiveProjectTypeForTemplates(null)}>
                                    {t('pages.project_types.templates.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule
                                key={`project-type-templates-${activeProjectTypeForTemplates?.id}`}
                                config={templatesConfig}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
