import { useMemo } from 'react';
import { AlertTriangle, FileText, ListChecks } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';
import { buildIncidentLogsCrudConfig } from '@/pages/app/incidentLogsCrudConfig';

interface IncidentsPageProps {
    route: AppSectionRoute;
}

export default function IncidentsPage({ route }: IncidentsPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = useMemo(
        () => ({
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
                if (!item.responsible_user_id) return 'danger';
                if (item.finished_at) return 'info';
                return null;
            },
            rowActions: [
                {
                    key: 'finish-incident',
                    label: t('pages.incidents.finish_incident'),
                    variant: 'outline',
                    isVisible: (item) => !item.finished_at,
                    onClick: async (item) => {
                        if (!window.confirm(t('pages.incidents.finish_incident_confirm'))) return;
                        const response = await fetch(`/api/crud/incidents/${item.id}`, {
                            method: 'PATCH',
                            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ finished_at: new Date().toISOString() }),
                        });
                        if (!response.ok) throw new Error(t('pages.incidents.update_failed'));
                    },
                },
            ],
            subTableActions: [
                {
                    key: 'logs',
                    label: t('pages.incidents.logs_button'),
                    icon: <FileText className="h-4 w-4" />,
                    dialogMaxWidth: 'max-w-3xl',
                    dialogTitle: (item) => t('pages.incidents.logs.panel_title', { incident: String(item.name || '') }),
                    dialogDescription: t('pages.incidents.logs.panel_description'),
                    getConfig: (item) => buildIncidentLogsCrudConfig(t, { incidentId: Number(item.id), lockIncidentId: true }),
                },
                {
                    key: 'actions',
                    label: t('pages.incidents.actions_button'),
                    icon: <ListChecks className="h-4 w-4" />,
                    dialogMaxWidth: 'max-w-3xl',
                    dialogTitle: (item) => t('pages.incidents.actions.panel_title', { incident: String(item.name || '') }),
                    dialogDescription: t('pages.incidents.actions.panel_description'),
                    getConfig: (item): CrudModuleConfig => ({
                        apiUrl: '/api/crud/control-action-mappings',
                        perPage: 25,
                        defaultSort: 'id',
                        fixedFilters: { incident_id: Number(item.id) },
                        createDefaults: { incident_id: Number(item.id) },
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
                    }),
                },
            ],
            fields: [
                { key: 'name', label: t('pages.incidents.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true, category: t('pages.incidents.category_event') },
                { key: 'responsible_user_id', label: t('pages.incidents.column_responsible_user'), type: 'select', sortable: true, editable: true, required: true, optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name', optionValueKey: 'id', optionLabelKey: 'name', placeholder: t('pages.incidents.none_assigned'), category: t('pages.incidents.category_event') },
                { key: 'started_at', label: t('pages.incidents.column_started_at'), type: 'datetime', sortable: true, editable: true, required: true, category: t('pages.incidents.category_event') },
                { key: 'eventdescription', label: t('pages.incidents.column_eventdescription'), type: 'textarea', editable: true, required: true, masterDescription: true, category: t('pages.incidents.category_event') },
                { key: 'participants', label: t('pages.incidents.column_participants'), type: 'textarea', editable: true, required: false, category: t('pages.incidents.category_event') },
                { key: 'retrospective', label: t('pages.incidents.column_retrospective'), type: 'textarea', editable: true, required: false, category: t('pages.incidents.category_followup') },
                { key: 'finished_at', label: t('pages.incidents.column_finished_at'), type: 'date', sortable: true, editable: false, hiddenInTable: true, category: t('pages.incidents.category_status') },
            ],
            filterFields: [
                { key: 'show_finished', label: t('pages.incidents.filter_show_finished'), type: 'boolean', options: [{ value: '1', label: t('pages.incidents.option_yes') }] },
            ],
        }),
        [t]
    );

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.incidents.title')}
                    description={t('pages.incidents.description')}
                    icon={<AlertTriangle className="h-6 w-6 text-primary" />}
                    route={route}
                />
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
