import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertCircle, ListChecks } from 'lucide-react';
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

interface ObservationsPageProps {
    route: AppSectionRoute;
}

export default function ObservationsPage({ route }: ObservationsPageProps) {
    const { t } = useTranslations();
    const [activeObservationForActions, setActiveObservationForActions] = useState<Record<string, any> | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.observations.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const config: CrudModuleConfig = {
        apiUrl: '/api/crud/findings',
        perPage: 25,
        defaultSort: '-created_at',
        selectFields: [
            'id',
            'name',
            'description',
            'department_id',
            'nonconformity',
            'consequence',
            'rootcause',
            'immediateaction',
            'preventativeaction',
            'estimated_cost',
            'distribution_analysis',
            'finished_at',
            'created_at',
            'created_by',
        ],
        createTitle: t('pages.observations.create_title'),
        editTitle: t('pages.observations.edit_title'),
        customQueryParams: (filters) => ({
            nonconformity: filters.nonconformity || undefined,
            show_unhandled: filters.show_unhandled !== false ? '1' : undefined,
            show_handled: filters.show_handled || undefined,
        }),
        getItemStatus: (item) => {
            if (!item.finished_at && item.nonconformity && !item.rootcause) {
                return 'danger';
            }
            return null;
        },
        rowActions: [
            {
                key: 'actions',
                label: t('pages.observations.actions_button'),
                variant: 'outline' as const,
                refreshOnComplete: false,
                onClick: (item: Record<string, any>) => {
                    setActiveObservationForActions(item);
                },
            },
        ],
        fields: [
            {
                key: 'name',
                label: t('pages.observations.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.observations.category_general'),
            },
            {
                key: 'description',
                label: t('pages.observations.column_description'),
                type: 'textarea',
                editable: true,
                required: true,
                masterDescription: true,
                category: t('pages.observations.category_general'),
            },
            {
                key: 'department_id',
                label: t('pages.observations.column_department'),
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                required: true,
                optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.observations.none_assigned'),
                category: t('pages.observations.category_general'),
            },
            {
                key: 'nonconformity',
                label: t('pages.observations.column_nonconformity'),
                type: 'boolean',
                sortable: true,
                editable: true,
                filterable: true,
                required: true,
                options: [
                    { value: '1', label: t('pages.observations.filter_nonconformity_yes') },
                    { value: '0', label: t('pages.observations.filter_nonconformity_no') },
                ],
                category: t('pages.observations.category_general'),
            },
            {
                key: 'estimated_cost',
                label: t('pages.observations.column_estimated_cost'),
                type: 'number',
                editable: true,
                sortable: true,
                category: t('pages.observations.category_general'),
            },
            {
                key: 'consequence',
                label: t('pages.observations.column_consequence'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_nonconformity'),
            },
            {
                key: 'rootcause',
                label: t('pages.observations.column_rootcause'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_nonconformity'),
            },
            {
                key: 'immediateaction',
                label: t('pages.observations.column_immediate_action'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_nonconformity'),
            },
            {
                key: 'preventativeaction',
                label: t('pages.observations.column_preventative_action'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_nonconformity'),
            },
            {
                key: 'distribution_analysis',
                label: t('pages.observations.column_distribution_analysis'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_nonconformity'),
            },
            {
                key: 'finished_at',
                label: t('pages.observations.column_finished_at'),
                type: 'datetime',
                editable: false,
                sortable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_status'),
            },
            {
                key: 'created_at',
                label: t('pages.observations.column_created_at'),
                type: 'datetime',
                editable: false,
                sortable: true,
                category: t('pages.observations.category_status'),
            },
            {
                key: 'created_by',
                label: t('pages.observations.column_created_by'),
                type: 'select',
                editable: false,
                sortable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                hiddenInTable: true,
                category: t('pages.observations.category_status'),
            },
            // Filter-only hidden fields
            {
                key: 'show_unhandled',
                label: t('pages.observations.filter_show_unhandled'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [
                    { value: '1', label: t('pages.observations.option_yes') },
                ],
            },
            {
                key: 'show_handled',
                label: t('pages.observations.filter_show_handled'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [
                    { value: '1', label: t('pages.observations.option_yes') },
                ],
            },
        ],
    };

    const actionsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeObservationForActions?.id) {
            return null;
        }

        return {
            apiUrl: '/api/crud/control-action-mappings',
            perPage: 25,
            defaultSort: 'id',
            fixedFilters: { finding_id: Number(activeObservationForActions.id) },
            createDefaults: { finding_id: Number(activeObservationForActions.id) },
            selectFields: ['id', 'control_action_id', 'finding_id'],
            createTitle: t('pages.observations.actions.create_title'),
            editTitle: t('pages.observations.actions.edit_title'),
            fields: [
                {
                    key: 'control_action_id',
                    label: t('pages.observations.actions.column_control_action'),
                    type: 'select',
                    sortable: false,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    optionsUrl: '/api/crud/control-actions?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.observations.actions.select_action'),
                    category: t('pages.observations.actions.category_general'),
                },
            ],
        };
    }, [activeObservationForActions?.id, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.observations.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <AlertCircle className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.observations.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.observations.description')}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {actionsConfig && (
                <Dialog
                    open={Boolean(activeObservationForActions)}
                    onOpenChange={(open) => !open && setActiveObservationForActions(null)}
                >
                    <DialogContent className="max-w-3xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ListChecks className="h-5 w-5" />
                                {t('pages.observations.actions.panel_title', {
                                    observation: String(activeObservationForActions?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('pages.observations.actions.panel_description')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setActiveObservationForActions(null)}
                                >
                                    {t('pages.observations.actions.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule
                                key={`observation-actions-${activeObservationForActions?.id}`}
                                config={actionsConfig}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
