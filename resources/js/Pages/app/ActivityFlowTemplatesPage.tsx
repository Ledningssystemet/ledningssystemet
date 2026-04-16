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

interface ActivityFlowTemplatesPageProps {
    route: AppSectionRoute;
}

export default function ActivityFlowTemplatesPage({ route }: ActivityFlowTemplatesPageProps) {
    const { t } = useTranslations();
    const [activeTemplate, setActiveTemplate] = useState<Record<string, any> | null>(null);

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/activity-flow-templates',
        perPage: 25,
        defaultSort: 'name',
        selectFields: ['id', 'name', 'description', 'user_instantiatable', 'created_at'],
        createTitle: t('pages.activity_flow_templates.create_title'),
        editTitle: t('pages.activity_flow_templates.edit_title'),
        fields: [
            {
                key: 'name',
                label: t('pages.activity_flow_templates.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.activity_flow_templates.category_general'),
            },
            {
                key: 'description',
                label: t('pages.activity_flow_templates.column_description'),
                type: 'textarea',
                sortable: false,
                editable: true,
                hiddenInTable: true,
                masterDescription: true,
                category: t('pages.activity_flow_templates.category_general'),
            },
            {
                key: 'user_instantiatable',
                label: t('pages.activity_flow_templates.column_user_instantiatable'),
                type: 'boolean',
                sortable: true,
                editable: true,
                required: true,
                category: t('pages.activity_flow_templates.category_behavior'),
            },
            {
                key: 'template_items',
                label: t('pages.activity_flow_templates.column_template_items'),
                type: 'text',
                sortable: false,
                editable: false,
                category: t('pages.activity_flow_templates.category_behavior'),
                renderCell: (_, row) => (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="gap-1"
                        onClick={() => setActiveTemplate(row)}
                    >
                        <ListTree className="h-4 w-4" />
                        {t('pages.activity_flow_templates.open_items_button')}
                    </Button>
                ),
                renderDetail: (_, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveTemplate(row)}>
                        <ListTree className="h-4 w-4" />
                        {t('pages.activity_flow_templates.open_items_button')}
                    </Button>
                ),
            },
            {
                key: 'created_at',
                label: t('pages.activity_flow_templates.column_created_at'),
                type: 'date',
                sortable: true,
                editable: false,
                category: t('pages.activity_flow_templates.category_behavior'),
                renderCell: (value) => {
                    if (!value) return '—';
                    const date = new Date(String(value));
                    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString();
                },
                renderDetail: (value) => {
                    if (!value) return '—';
                    const date = new Date(String(value));
                    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
                },
            },
        ],
    }), [t]);

    const templateItemsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeTemplate?.id) {
            return null;
        }

        return {
            apiUrl: '/api/crud/activity-flow-template-items',
            perPage: 25,
            defaultSort: 'ordinal',
            fixedFilters: {
                activity_flow_template_id: activeTemplate.id,
            },
            createDefaults: {
                activity_flow_template_id: activeTemplate.id,
            },
            selectFields: [
                'id',
                'name',
                'type',
                'ordinal',
                'description',
                'waitforpreceeding',
                'dueoffsetdays',
                'activity_flow_template_id',
                'created_at',
            ],
            createTitle: t('pages.activity_flow_templates.items.create_title'),
            editTitle: t('pages.activity_flow_templates.items.edit_title'),
            fields: [
                {
                    key: 'name',
                    label: t('pages.activity_flow_templates.items.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.activity_flow_templates.items.category_general'),
                },
                {
                    key: 'type',
                    label: t('pages.activity_flow_templates.items.column_type'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    required: true,
                    options: [
                        {
                            value: 'header',
                            label: t('pages.activity_flow_templates.items.type_header'),
                        },
                        {
                            value: 'item',
                            label: t('pages.activity_flow_templates.items.type_item'),
                        },
                    ],
                    category: t('pages.activity_flow_templates.items.category_general'),
                },
                {
                    key: 'ordinal',
                    label: t('pages.activity_flow_templates.items.column_ordinal'),
                    type: 'number',
                    sortable: true,
                    editable: false,
                    hidden: true,
                    hiddenInTable: true,
                    category: t('pages.activity_flow_templates.items.category_ordering'),
                },
                {
                    key: 'description',
                    label: t('pages.activity_flow_templates.items.column_description'),
                    type: 'textarea',
                    editable: true,
                    hiddenInTable: true,
                    masterDescription: true,
                    category: t('pages.activity_flow_templates.items.category_general'),
                },
                {
                    key: 'waitforpreceeding',
                    label: t('pages.activity_flow_templates.items.column_wait_for_preceding'),
                    type: 'boolean',
                    sortable: true,
                    editable: true,
                    required: true,
                    category: t('pages.activity_flow_templates.items.category_behavior'),
                },
                {
                    key: 'dueoffsetdays',
                    label: t('pages.activity_flow_templates.items.column_due_offset_days'),
                    type: 'number',
                    sortable: true,
                    editable: true,
                    required: true,
                    category: t('pages.activity_flow_templates.items.category_behavior'),
                },
                {
                    key: 'activity_flow_template_id',
                    label: t('pages.activity_flow_templates.items.column_activity_flow_template'),
                    type: 'number',
                    editable: false,
                    hidden: true,
                },
                {
                    key: 'created_at',
                    label: t('pages.activity_flow_templates.items.column_created_at'),
                    type: 'date',
                    sortable: true,
                    editable: false,
                    category: t('pages.activity_flow_templates.items.category_behavior'),
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
        };
    }, [activeTemplate?.id, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.activity_flow_templates.title')}
                    description={t('pages.activity_flow_templates.description')}
                    icon={<Workflow className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            <Dialog open={Boolean(activeTemplate)} onOpenChange={() => {}} modal={false}>
                <DialogContent
                    className="!inset-0 !translate-x-0 !translate-y-0 !h-[100dvh] !w-screen !max-h-[100dvh] !max-w-none !gap-0 overflow-hidden rounded-none p-0 data-[state=open]:slide-in-from-right data-[state=closed]:slide-out-to-right"
                    onPointerDownOutside={(event) => event.preventDefault()}
                    onInteractOutside={(event) => event.preventDefault()}
                    onEscapeKeyDown={(event) => event.preventDefault()}
                >
                    <Button
                        type="button"
                        variant="default"
                        size="sm"
                        className="absolute right-4 top-4 z-50 gap-2"
                        onClick={() => setActiveTemplate(null)}
                    >
                        <X className="h-4 w-4" />
                        {t('pages.activity_flow_templates.items.close_panel_button')}
                    </Button>

                    <div className="flex h-full min-h-0 min-w-0 flex-col">
                        <div className="sticky top-0 z-10 border-b bg-background p-4 pr-36">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0">
                                <DialogTitle className="text-xl">
                                    {t('pages.activity_flow_templates.items.panel_title', {
                                        template: String(activeTemplate?.name || ''),
                                    })}
                                </DialogTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('pages.activity_flow_templates.items.panel_description')}
                                </p>
                                </div>
                            </div>
                        </div>

                        <div className="min-h-0 min-w-0 flex-1 overflow-auto p-5">
                            {templateItemsConfig && (
                                <div className="min-w-0">
                                    <CrudModule key={`activity-flow-template-items-${activeTemplate?.id}`} config={templateItemsConfig} />
                                </div>
                            )}
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
