import { useMemo, useState } from 'react';
import { ListTree, Workflow, X } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ActivityFlowsPageProps {
    route: AppSectionRoute;
}

export default function ActivityFlowsPage({ route }: ActivityFlowsPageProps) {
    const { t } = useTranslations();
    const [activeFlow, setActiveFlow] = useState<Record<string, any> | null>(null);

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/activity-flows',
        perPage: 25,
        defaultSort: '-created_at',
        selectFields: ['id', 'name', 'description', 'responsible_user_id', 'activity_flow_template_id', 'started_at', 'created_at'],
        createTitle: t('pages.activity_flows.create_title'),
        editTitle: t('pages.activity_flows.edit_title'),
        fields: [
            {
                key: 'name',
                label: t('pages.activity_flows.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.activity_flows.category_general'),
            },
            {
                key: 'description',
                label: t('pages.activity_flows.column_description'),
                type: 'textarea',
                sortable: false,
                editable: true,
                hiddenInTable: true,
                masterDescription: true,
                category: t('pages.activity_flows.category_general'),
            },
            {
                key: 'activity_flow_template_id',
                label: t('pages.activity_flows.column_template'),
                type: 'select',
                sortable: true,
                editable: true,
                editableOnUpdate: false,
                required: true,
                optionsUrl: '/api/crud/activity-flow-templates?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                category: t('pages.activity_flows.category_relations'),
            },
            {
                key: 'responsible_user_id',
                label: t('pages.activity_flows.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                required: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                category: t('pages.activity_flows.category_relations'),
            },
            {
                key: 'started_at',
                label: t('pages.activity_flows.column_started_at'),
                type: 'date',
                sortable: true,
                editable: true,
                category: t('pages.activity_flows.category_schedule'),
            },
            {
                key: 'flow_items',
                label: t('pages.activity_flows.column_flow_items'),
                type: 'text',
                sortable: false,
                editable: false,
                category: t('pages.activity_flows.category_relations'),
                renderCell: (_, row) => (
                    <Button data-testid="activity-flow-open-items" type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveFlow(row)}>
                        <ListTree className="h-4 w-4" />
                        {t('pages.activity_flows.open_items_button')}
                    </Button>
                ),
                renderDetail: (_, row) => (
                    <Button data-testid="activity-flow-open-items" type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveFlow(row)}>
                        <ListTree className="h-4 w-4" />
                        {t('pages.activity_flows.open_items_button')}
                    </Button>
                ),
            },
            {
                key: 'created_at',
                label: t('pages.activity_flows.column_created_at'),
                type: 'date',
                sortable: true,
                editable: false,
                category: t('pages.activity_flows.category_schedule'),
                renderCell: (value) => {
                    if (!value) return '-';
                    const date = new Date(String(value));
                    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString();
                },
                renderDetail: (value) => {
                    if (!value) return '-';
                    const date = new Date(String(value));
                    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
                },
            },
        ],
    }), [t]);

    const flowItemsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeFlow?.id) {
            return null;
        }

        return {
            apiUrl: '/api/crud/activities',
            perPage: 25,
            defaultSort: 'due',
            fixedFilters: {
                activity_flow_id: activeFlow.id,
            },
            selectFields: [
                'id',
                'name',
                'description',
                'due',
                'completed_at',
                'activity_flow_id',
                'activity_flow_template_item_id',
                'responsible_user_id',
            ],
            createTitle: t('pages.activity_flows.items.create_title'),
            editTitle: t('pages.activity_flows.items.edit_title'),
            canCreate: false,
            fields: [
                {
                    key: 'name',
                    label: t('pages.activity_flows.items.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.activity_flows.items.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.activity_flows.items.column_description'),
                    type: 'textarea',
                    editable: true,
                    required: true,
                    hiddenInTable: true,
                    masterDescription: true,
                    category: t('pages.activity_flows.items.category_general'),
                },
                {
                    key: 'due',
                    label: t('pages.activity_flows.items.column_due'),
                    type: 'date',
                    sortable: true,
                    editable: true,
                    required: true,
                    category: t('pages.activity_flows.items.category_schedule'),
                },
                {
                    key: 'completed_at',
                    label: t('pages.activity_flows.items.column_completed_at'),
                    type: 'date',
                    sortable: true,
                    editable: true,
                    category: t('pages.activity_flows.items.category_schedule'),
                },
                {
                    key: 'responsible_user_id',
                    label: t('pages.activity_flows.items.column_responsible_user'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.activity_flows.items.category_relations'),
                },
                {
                    key: 'activity_flow_template_item_id',
                    label: t('pages.activity_flows.items.column_template_item'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    optionsUrl: '/api/crud/activity-flow-template-items?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.activity_flows.items.category_relations'),
                },
                {
                    key: 'activity_flow_id',
                    label: t('pages.activity_flows.items.column_activity_flow'),
                    type: 'number',
                    editable: false,
                    hidden: true,
                },
            ],
        };
    }, [activeFlow?.id, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.activity_flows.title')}
                    description={t('pages.activity_flows.description')}
                    icon={<Workflow className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            <Dialog open={Boolean(activeFlow)} onOpenChange={() => {}} modal={false}>
                <DialogContent
                    className="!inset-0 !translate-x-0 !translate-y-0 !h-[100dvh] !w-screen !max-h-[100dvh] !max-w-none !gap-0 overflow-hidden rounded-none p-0 data-[state=open]:slide-in-from-right data-[state=closed]:slide-out-to-right"
                    onPointerDownOutside={(event) => event.preventDefault()}
                    onInteractOutside={(event) => event.preventDefault()}
                    onEscapeKeyDown={(event) => event.preventDefault()}
                >
                    <Button
                        data-testid="activity-flow-close-items"
                        type="button"
                        variant="default"
                        size="sm"
                        className="absolute right-4 top-4 z-50 gap-2"
                        onClick={() => setActiveFlow(null)}
                    >
                        <X className="h-4 w-4" />
                        {t('pages.activity_flows.items.close_panel_button')}
                    </Button>

                    <div className="flex h-full min-h-0 min-w-0 flex-col" data-testid="activity-flow-items-panel">
                        <div className="sticky top-0 z-10 border-b bg-background p-4 pr-36">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0">
                                    <DialogTitle className="text-xl">
                                        {t('pages.activity_flows.items.panel_title', {
                                            flow: String(activeFlow?.name || ''),
                                        })}
                                    </DialogTitle>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {t('pages.activity_flows.items.panel_description')}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="min-h-0 min-w-0 flex-1 overflow-auto p-5">
                            {flowItemsConfig && (
                                <div className="min-w-0">
                                    <CrudModule key={`activity-flow-items-${activeFlow?.id}`} config={flowItemsConfig} />
                                </div>
                            )}
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
