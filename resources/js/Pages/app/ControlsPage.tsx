import { useMemo, useState } from 'react';
import { ClipboardCheck, Download, ListChecks } from 'lucide-react';
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
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';
import { buildControlActionsCrudConfig } from '@/pages/app/controlActionsCrudConfig';

interface ControlsPageProps {
    route: AppSectionRoute;
}

export default function ControlsPage({ route }: ControlsPageProps) {
    const { t } = useTranslations();
    const [activeControl, setActiveControl] = useState<Record<string, any> | null>(null);

    const config: CrudModuleConfig = {
        apiUrl: '/api/crud/controls',
        perPage: 25,
        defaultSort: 'name',
        selectFields: [
            'id',
            'name',
            'description',
            'statusdescription',
            'responsible_user_id',
            'not_applicable_at',
            'reviewed_at',
            'tags',
        ],
        createTitle: t('pages.controls.create_title'),
        editTitle: t('pages.controls.edit_title'),
        customQueryParams: (filters) => ({
            tag_id: filters.tag_id || undefined,
            responsible_user_id: filters.responsible_user_id || undefined,
            show_my_only: filters.show_my_only || undefined,
            hide_without_issues: filters.hide_without_issues || undefined,
            show_not_applicable: filters.show_not_applicable || undefined,
        }),
        getItemStatus: (item) => {
            if (!item.responsible_user_id) {
                return 'danger';
            }

            if (item.not_applicable_at) {
                return 'info';
            }

            return null;
        },
        rowActions: [
            {
                key: 'toggle-applicability',
                label: t('pages.controls.mark_not_applicable'),
                variant: 'outline',
                isVisible: (item) => !item.not_applicable_at,
                onClick: async (item) => {
                    if (!window.confirm(t('pages.controls.mark_not_applicable_confirm'))) {
                        return;
                    }

                    const response = await fetch(`/api/crud/controls/${item.id}`, {
                        method: 'PATCH',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            not_applicable_at: new Date().toISOString(),
                        }),
                    });

                    if (!response.ok) {
                        throw new Error(t('pages.controls.update_failed'));
                    }
                },
            },
            {
                key: 'toggle-applicability-reset',
                label: t('pages.controls.mark_applicable'),
                variant: 'outline',
                isVisible: (item) => Boolean(item.not_applicable_at),
                onClick: async (item) => {
                    if (!window.confirm(t('pages.controls.mark_applicable_confirm'))) {
                        return;
                    }

                    const response = await fetch(`/api/crud/controls/${item.id}`, {
                        method: 'PATCH',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            not_applicable_at: null,
                        }),
                    });

                    if (!response.ok) {
                        throw new Error(t('pages.controls.update_failed'));
                    }
                },
            },
            {
                key: 'actions',
                label: t('pages.controls.actions_button'),
                variant: 'outline',
                refreshOnComplete: false,
                onClick: (item) => {
                    setActiveControl(item);
                },
            },
        ],
        fields: [
            {
                key: 'name',
                label: t('pages.controls.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.controls.category_general'),
            },
            {
                key: 'tags',
                label: t('pages.controls.column_tags'),
                type: 'inline-tags',
                editable: true,
                sortable: false,
                tags: true,
                optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                createOptionUrl: '/api/crud/tags',
                optionValueKey: 'name',
                optionLabelKey: 'name',
                createOptionPayloadKey: 'name',
                category: t('pages.controls.category_general'),
            },
            {
                key: 'responsible_user_id',
                label: t('pages.controls.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.controls.none_assigned'),
                category: t('pages.controls.category_general'),
            },
            {
                key: 'description',
                label: t('pages.controls.column_description'),
                type: 'textarea',
                editable: true,
                required: true,
                masterDescription: true,
                category: t('pages.controls.category_general'),
            },
            {
                key: 'statusdescription',
                label: t('pages.controls.column_status_description'),
                type: 'textarea',
                editable: true,
                category: t('pages.controls.category_status'),
            },
            {
                key: 'not_applicable_at',
                label: t('pages.controls.column_not_applicable_at'),
                type: 'date',
                sortable: true,
                editable: false,
                category: t('pages.controls.category_status'),
            },
            {
                key: 'reviewed_at',
                label: t('pages.controls.column_reviewed_at'),
                type: 'date',
                sortable: true,
                editable: true,
                category: t('pages.controls.category_status'),
            },
            {
                key: 'tag_id',
                label: t('pages.controls.filter_tag'),
                type: 'select',
                hidden: true,
                editable: false,
                filterable: true,
                optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
            },
            {
                key: 'show_my_only',
                label: t('pages.controls.filter_show_my_only'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [{ value: '1', label: t('pages.controls.option_yes') }],
            },
            {
                key: 'hide_without_issues',
                label: t('pages.controls.filter_hide_without_issues'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [{ value: '1', label: t('pages.controls.option_yes') }],
            },
            {
                key: 'show_not_applicable',
                label: t('pages.controls.filter_show_not_applicable'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [{ value: '1', label: t('pages.controls.option_yes') }],
            },
        ],
    };

    const actionConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeControl?.id) {
            return null;
        }

        return buildControlActionsCrudConfig(t, {
            controlId: Number(activeControl.id),
            lockControlId: true,
        });
    }, [activeControl?.id, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.controls.title')}
                    description={t('pages.controls.description')}
                    icon={<ClipboardCheck className="h-6 w-6 text-primary" />}
                    route={route}
                    actions={
                        <a
                            href="/api/v1/ReportCentral/Controls/0"
                            className="flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            <Download className="h-4 w-4" />
                            {t('pages.controls.export_excel')}
                        </a>
                    }
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {actionConfig && (
                <Dialog open={Boolean(activeControl)} onOpenChange={(open) => !open && setActiveControl(null)}>
                    <DialogContent className="max-w-5xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ListChecks className="h-5 w-5" />
                                {t('pages.controls.actions.panel_title', {
                                    control: String(activeControl?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>{t('pages.controls.actions.panel_description')}</DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button type="button" variant="outline" size="sm" onClick={() => setActiveControl(null)}>
                                    {t('pages.controls.actions.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule key={`control-actions-${activeControl?.id}`} config={actionConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
