import { useMemo, useState } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
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
import { buildRequirementSourceRequirementsCrudConfig } from './requirementSourceRequirementsCrudConfig';

interface RequirementSourcesPageProps {
    route: AppSectionRoute;
}

interface SharedProps extends PageProps {
    auth?: {
        user?: {
            id: number;
        } | null;
    };
}

export default function RequirementSourcesPage({ route }: RequirementSourcesPageProps) {
    const { t, locale } = useTranslations();
    const page = usePage<SharedProps>();
    const currentUserId = page.props.auth?.user?.id ?? null;
    const [activeSourceForRequirements, setActiveSourceForRequirements] = useState<Record<string, any> | null>(null);

    const patchRequirementSource = async (id: number, updates: Record<string, any>): Promise<void> => {
        const response = await fetch(`/api/crud/requirement_sources/${id}`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(updates),
        });

        if (!response.ok) {
            throw new Error(t('pages.requirement_sources.update_failed'));
        }
    };

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/requirement_sources',
            perPage: 25,
            defaultSort: 'reference',
            createTitle: t('pages.requirement_sources.create_title'),
            editTitle: t('pages.requirement_sources.edit_title'),
            createDefaults: currentUserId ? { responsible_user_id: currentUserId } : undefined,
            selectFields: [
                'id',
                'reference',
                'name',
                'description',
                'responsible_user_id',
                'max_sanction_fee',
                'approved_at',
                'not_applicable_at',
                'partner_id',
                'partner_name',
                'needsapproval',
                'applicabilitymissingcount',
            ],
            getItemStatus: (item) => {
                if (!item.responsible_user_id) {
                    return 'danger';
                }

                if (item.not_applicable_at) {
                    return 'info';
                }

                if (item.needsapproval && currentUserId && item.responsible_user_id === currentUserId) {
                    return 'warning';
                }

                return null;
            },
            rowActions: [
                {
                    key: 'requirements',
                    label: t('pages.requirement_sources.show_requirements'),
                    variant: 'outline',
                    refreshOnComplete: false,
                    onClick: (item) => {
                        setActiveSourceForRequirements(item);
                    },
                },
                {
                    key: 'approve',
                    label: t('pages.requirement_sources.approve_action'),
                    variant: 'outline',
                    isVisible: (item) => {
                        const needsApproval = item.needsapproval !== undefined ? Boolean(item.needsapproval) : !item.approved_at;
                        return Boolean(
                            currentUserId &&
                                !item.not_applicable_at &&
                                needsApproval &&
                                item.responsible_user_id === currentUserId
                        );
                    },
                    onClick: async (item) => {
                        if (!window.confirm(t('pages.requirement_sources.approve_confirm'))) {
                            return;
                        }

                        await patchRequirementSource(Number(item.id), {
                            approved_at: new Date().toISOString(),
                        });
                    },
                },
                {
                    key: 'mark-not-applicable',
                    label: t('pages.requirement_sources.mark_not_applicable'),
                    variant: 'outline',
                    isVisible: (item) => Boolean(item.partner_id && !item.not_applicable_at),
                    onClick: async (item) => {
                        if (!window.confirm(t('pages.requirement_sources.mark_not_applicable_confirm'))) {
                            return;
                        }

                        await patchRequirementSource(Number(item.id), {
                            not_applicable_at: new Date().toISOString(),
                        });
                    },
                },
                {
                    key: 'mark-applicable',
                    label: t('pages.requirement_sources.mark_applicable'),
                    variant: 'outline',
                    isVisible: (item) => Boolean(item.partner_id && item.not_applicable_at),
                    onClick: async (item) => {
                        if (!window.confirm(t('pages.requirement_sources.mark_applicable_confirm'))) {
                            return;
                        }

                        await patchRequirementSource(Number(item.id), {
                            not_applicable_at: null,
                        });
                    },
                },
                {
                    key: 'soa-export',
                    label: t('pages.requirement_sources.export_soa'),
                    variant: 'outline',
                    refreshOnComplete: false,
                    isVisible: (item) => !(item.partner_id && item.not_applicable_at),
                    onClick: (item) => {
                        window.open(`/api/v1/ReportCentral/StatementOfApplicability/${item.id}`, '_blank', 'noopener,noreferrer');
                    },
                },
            ],
            fields: [
                {
                    key: 'reference',
                    label: t('pages.requirement_sources.column_reference'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    renderCell: (_, row) => `${String(row.reference ?? '')} ${String(row.name ?? '')}`.trim(),
                    renderDetail: (_, row) => `${String(row.reference ?? '')} ${String(row.name ?? '')}`.trim(),
                    category: t('pages.requirement_sources.category_general'),
                },
                {
                    key: 'name',
                    label: t('pages.requirement_sources.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    hiddenInTable: true,
                    category: t('pages.requirement_sources.category_general'),
                },
                {
                    key: 'responsible_user_id',
                    label: t('pages.requirement_sources.column_responsible_user'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    filterable: true,
                    optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.requirement_sources.none_assigned'),
                    category: t('pages.requirement_sources.category_general'),
                },
                {
                    key: 'max_sanction_fee',
                    label: t('pages.requirement_sources.column_max_sanction_fee'),
                    type: 'number',
                    sortable: true,
                    editable: true,
                    renderCell: (value) => {
                        if (value === null || value === undefined || value === '') {
                            return '-';
                        }

                        return new Intl.NumberFormat(locale === 'sv' ? 'sv-SE' : 'en-US', {
                            style: 'currency',
                            currency: 'SEK',
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0,
                        }).format(Number(value));
                    },
                    category: t('pages.requirement_sources.category_finance'),
                },
                {
                    key: 'description',
                    label: t('pages.requirement_sources.column_description'),
                    type: 'textarea',
                    sortable: false,
                    editable: true,
                    masterDescription: true,
                    category: t('pages.requirement_sources.category_general'),
                },
                {
                    key: 'partner_info',
                    label: t('pages.requirement_sources.column_partner_info'),
                    type: 'text',
                    sortable: false,
                    editable: false,
                    renderCell: (_, row) =>
                        row.partner_id
                            ? t('pages.requirement_sources.partner_info', {
                                  partner: String(row.partner_name ?? ''),
                              })
                            : '',
                    renderDetail: (_, row) =>
                        row.partner_id
                            ? t('pages.requirement_sources.partner_info', {
                                  partner: String(row.partner_name ?? ''),
                              })
                            : '',
                    category: t('pages.requirement_sources.category_status'),
                },
                {
                    key: 'approved_at',
                    label: t('pages.requirement_sources.column_approved_at'),
                    type: 'date',
                    sortable: true,
                    editable: false,
                    hiddenInTable: true,
                    category: t('pages.requirement_sources.category_status'),
                },
                {
                    key: 'not_applicable_at',
                    label: t('pages.requirement_sources.column_not_applicable_at'),
                    type: 'date',
                    sortable: true,
                    editable: false,
                    hiddenInTable: true,
                    category: t('pages.requirement_sources.category_status'),
                },
            ],
        }),
        [currentUserId, locale, t]
    );

    const requirementsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeSourceForRequirements?.id) {
            return null;
        }

        const isReadOnly = Boolean(activeSourceForRequirements.not_applicable_at || activeSourceForRequirements.partner_id);

        return buildRequirementSourceRequirementsCrudConfig(t, {
            requirementSourceId: Number(activeSourceForRequirements.id),
            lockRequirementSourceId: true,
            readOnly: isReadOnly,
        });
    }, [activeSourceForRequirements, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.requirement_sources.title')}
                    description={t('pages.requirement_sources.description')}
                    icon={<MaterialSymbol name="balance" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {requirementsConfig && (
                <Dialog
                    open={Boolean(activeSourceForRequirements)}
                    onOpenChange={(open) => !open && setActiveSourceForRequirements(null)}
                >
                    <DialogContent className="max-w-5xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <MaterialSymbol name="checklist" className="h-5 w-5" />
                                {t('pages.requirement_sources.requirements.panel_title', {
                                    source: String(activeSourceForRequirements?.reference || activeSourceForRequirements?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('pages.requirement_sources.requirements.panel_description')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button type="button" variant="outline" size="sm" onClick={() => setActiveSourceForRequirements(null)}>
                                    {t('pages.requirement_sources.requirements.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule
                                key={`requirements-${activeSourceForRequirements?.id}`}
                                config={requirementsConfig}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
