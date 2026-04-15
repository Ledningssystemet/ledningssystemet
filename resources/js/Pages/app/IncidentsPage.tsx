import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertTriangle, FileText, ListChecks } from 'lucide-react';
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
import { buildIncidentLogsCrudConfig } from '@/pages/app/incidentLogsCrudConfig';

interface IncidentsPageProps {
    route: AppSectionRoute;
}

export default function IncidentsPage({ route }: IncidentsPageProps) {
    const { t } = useTranslations();
    const [activeIncidentForLogs, setActiveIncidentForLogs] = useState<Record<string, any> | null>(null);
    const [activeIncidentForActions, setActiveIncidentForActions] = useState<Record<string, any> | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.incidents.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const config: CrudModuleConfig = {
        apiUrl: '/api/crud/incidents',
        perPage: 25,
        defaultSort: '-started_at',
        selectFields: [
            'id',
            'name',
            'started_at',
            'finished_at',
            'responsible_user_id',
            'eventdescription',
            'participants',
            'retrospective',
        ],
        createTitle: t('pages.incidents.create_title'),
        editTitle: t('pages.incidents.edit_title'),
        customQueryParams: (filters) => ({
            show_finished: filters.show_finished || undefined,
        }),
        getItemStatus: (item) => {
            if (!item.responsible_user_id) {
                return 'danger';
            }

            if (item.finished_at) {
                return 'info';
            }

            return null;
        },
        rowActions: [
            {
                key: 'finish-incident',
                label: t('pages.incidents.finish_incident'),
                variant: 'outline',
                isVisible: (item) => !item.finished_at,
                onClick: async (item) => {
                    if (!window.confirm(t('pages.incidents.finish_incident_confirm'))) {
                        return;
                    }

                    const response = await fetch(`/api/crud/incidents/${item.id}`, {
                        method: 'PATCH',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            finished_at: new Date().toISOString(),
                        }),
                    });

                    if (!response.ok) {
                        throw new Error(t('pages.incidents.update_failed'));
                    }
                },
            },
            {
                key: 'logs',
                label: t('pages.incidents.logs_button'),
                variant: 'outline',
                refreshOnComplete: false,
                onClick: (item) => {
                    setActiveIncidentForLogs(item);
                },
            },
            {
                key: 'actions',
                label: t('pages.incidents.actions_button'),
                variant: 'outline',
                refreshOnComplete: false,
                onClick: (item) => {
                    setActiveIncidentForActions(item);
                },
            },
        ],
        fields: [
            {
                key: 'name',
                label: t('pages.incidents.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.incidents.category_general'),
            },
            {
                key: 'responsible_user_id',
                label: t('pages.incidents.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                required: true,
                filterable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.incidents.none_assigned'),
                category: t('pages.incidents.category_general'),
            },
            {
                key: 'started_at',
                label: t('pages.incidents.column_started_at'),
                type: 'date',
                sortable: true,
                editable: true,
                required: true,
                category: t('pages.incidents.category_general'),
            },
            {
                key: 'eventdescription',
                label: t('pages.incidents.column_eventdescription'),
                type: 'textarea',
                editable: true,
                required: true,
                masterDescription: true,
                category: t('pages.incidents.category_event'),
            },
            {
                key: 'participants',
                label: t('pages.incidents.column_participants'),
                type: 'textarea',
                editable: true,
                required: false,
                category: t('pages.incidents.category_event'),
            },
            {
                key: 'retrospective',
                label: t('pages.incidents.column_retrospective'),
                type: 'textarea',
                editable: true,
                required: false,
                category: t('pages.incidents.category_followup'),
            },
            {
                key: 'finished_at',
                label: t('pages.incidents.column_finished_at'),
                type: 'date',
                sortable: true,
                editable: false,
                hiddenInTable: true,
                category: t('pages.incidents.category_status'),
            },
            {
                key: 'show_finished',
                label: t('pages.incidents.filter_show_finished'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [{ value: '1', label: t('pages.incidents.option_yes') }],
            },
        ],
    };

    const logsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeIncidentForLogs?.id) {
            return null;
        }

        return buildIncidentLogsCrudConfig(t, {
            incidentId: Number(activeIncidentForLogs.id),
            lockIncidentId: true,
        });
    }, [activeIncidentForLogs?.id, t]);

    const actionsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeIncidentForActions?.id) {
            return null;
        }

        return {
            apiUrl: '/api/crud/control-action-mappings',
            perPage: 25,
            defaultSort: 'id',
            fixedFilters: { incident_id: Number(activeIncidentForActions.id) },
            createDefaults: { incident_id: Number(activeIncidentForActions.id) },
            selectFields: ['id', 'control_action_id', 'incident_id'],
            createTitle: t('pages.incidents.actions.create_title'),
            editTitle: t('pages.incidents.actions.edit_title'),
            fields: [
                {
                    key: 'control_action_id',
                    label: t('pages.incidents.actions.column_control_action'),
                    type: 'select',
                    sortable: false,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    optionsUrl: '/api/crud/control_actions?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.incidents.actions.select_action'),
                    category: t('pages.incidents.actions.category_general'),
                },
            ],
        };
    }, [activeIncidentForActions?.id, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.incidents.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <AlertTriangle className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.incidents.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.incidents.description')}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {logsConfig && (
                <Dialog
                    open={Boolean(activeIncidentForLogs)}
                    onOpenChange={(open) => !open && setActiveIncidentForLogs(null)}
                >
                    <DialogContent className="max-w-3xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                {t('pages.incidents.logs.panel_title', {
                                    incident: String(activeIncidentForLogs?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('pages.incidents.logs.panel_description')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setActiveIncidentForLogs(null)}
                                >
                                    {t('pages.incidents.logs.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule
                                key={`incident-logs-${activeIncidentForLogs?.id}`}
                                config={logsConfig}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}

            {actionsConfig && (
                <Dialog
                    open={Boolean(activeIncidentForActions)}
                    onOpenChange={(open) => !open && setActiveIncidentForActions(null)}
                >
                    <DialogContent className="max-w-3xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ListChecks className="h-5 w-5" />
                                {t('pages.incidents.actions.panel_title', {
                                    incident: String(activeIncidentForActions?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('pages.incidents.actions.panel_description')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setActiveIncidentForActions(null)}
                                >
                                    {t('pages.incidents.actions.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule
                                key={`incident-actions-${activeIncidentForActions?.id}`}
                                config={actionsConfig}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
