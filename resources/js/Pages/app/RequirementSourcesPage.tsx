import { useMemo, useState } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import { Button } from '@/Components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { PageHeader } from '@/Components/layout/PageHeader';
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
    const [approveDialogSource, setApproveDialogSource] = useState<Record<string, any> | null>(null);
    const [isApproving, setIsApproving] = useState(false);
    const [crudRefreshToken, setCrudRefreshToken] = useState(0);
    const approvalReasonText = (item: Record<string, any>): string => {
        const reasonTypes = Array.isArray(item.approval_reason_types) ? item.approval_reason_types : [];

        const reasonLabels = reasonTypes
            .map((reasonType) => {
                if (reasonType === 'stale_approval') {
                    return t('pages.requirement_sources.reason_stale_approval_short');
                }

                if (reasonType === 'requirements_changed') {
                    return t('pages.requirement_sources.reason_requirements_changed');
                }

                if (reasonType === 'source_updated') {
                    return t('pages.requirement_sources.reason_source_updated');
                }

                return '';
            })
            .filter((reason): reason is string => reason.length > 0);

        return reasonLabels.join(' ');
    };

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
                'status',
                'needsapproval',
                'approval_reason_types',
                'applicabilitymissingcount',
            ],
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
                    refreshOnComplete: false,
                    isVisible: (item) => {
                        const needsApproval = item.needsapproval !== undefined ? Boolean(item.needsapproval) : !item.approved_at;
                        return Boolean(
                            currentUserId &&
                                needsApproval &&
                                item.responsible_user_id === currentUserId
                        );
                    },
                    onClick: (item) => {
                        setApproveDialogSource(item);
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
                    key: 'approval_status',
                    label: t('pages.requirement_sources.column_approval_status'),
                    type: 'text',
                    sortable: false,
                    editable: false,
                    renderCell: (_value, row) =>
                        Boolean(row.needsapproval)
                            ? t('pages.requirement_sources.status_not_approved')
                            : t('pages.requirement_sources.status_approved'),
                    renderDetail: (_value, row) =>
                        Boolean(row.needsapproval)
                            ? t('pages.requirement_sources.status_not_approved')
                            : t('pages.requirement_sources.status_approved'),
                    category: t('pages.requirement_sources.category_status'),
                },
                {
                    key: 'approval_reason',
                    label: t('pages.requirement_sources.column_approval_reason'),
                    type: 'text',
                    sortable: false,
                    editable: false,
                    renderCell: (_value, row) =>
                        Boolean(row.needsapproval)
                            ? approvalReasonText(row)
                            : '',
                    renderDetail: (_value, row) =>
                        Boolean(row.needsapproval)
                            ? approvalReasonText(row)
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
            ],
        }),
        [currentUserId, locale, t]
    );

    const requirementsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeSourceForRequirements?.id) {
            return null;
        }

        return buildRequirementSourceRequirementsCrudConfig(t, {
            requirementSourceId: Number(activeSourceForRequirements.id),
            lockRequirementSourceId: true,
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
                    <CrudModule key={`requirement-sources-${crudRefreshToken}`} config={config} />
                </section>
            </div>

            <AlertDialog open={Boolean(approveDialogSource)} onOpenChange={(open) => !open && setApproveDialogSource(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('pages.requirement_sources.approve_action')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('pages.requirement_sources.approve_confirm')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isApproving}>{t('ui.crud.action_cancel')}</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={isApproving}
                            onClick={async (event) => {
                                event.preventDefault();
                                if (!approveDialogSource?.id) {
                                    setApproveDialogSource(null);
                                    return;
                                }

                                setIsApproving(true);
                                try {
                                    await patchRequirementSource(Number(approveDialogSource.id), {
                                        approved_at: new Date().toISOString(),
                                    });
                                    setApproveDialogSource(null);
                                    setCrudRefreshToken((current) => current + 1);
                                } finally {
                                    setIsApproving(false);
                                }
                            }}
                        >
                            {t('pages.requirement_sources.approve_action')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {requirementsConfig && (
                <Dialog
                    open={Boolean(activeSourceForRequirements)}
                    onOpenChange={(open) => !open && setActiveSourceForRequirements(null)}
                >
                    <DialogContent>
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
