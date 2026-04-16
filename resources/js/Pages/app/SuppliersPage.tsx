import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import { Download, Handshake, ListChecks, Truck, Waypoints } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface SuppliersPageProps {
    route: AppSectionRoute;
}

interface SharedProps extends PageProps {
    auth?: {
        user?: {
            id: number;
        } | null;
    };
}

export default function SuppliersPage({ route }: SuppliersPageProps) {
    const { t } = useTranslations();
    const page = usePage<SharedProps>();
    const currentUserId = page.props.auth?.user?.id ?? null;

    const [activeSupplierForCategories, setActiveSupplierForCategories] = useState<Record<string, any> | null>(null);
    const [activeSupplierForEvaluation, setActiveSupplierForEvaluation] = useState<Record<string, any> | null>(null);
    const [activeSupplierForAgreements, setActiveSupplierForAgreements] = useState<Record<string, any> | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.suppliers.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const supplierConfig: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/suppliers',
            perPage: 25,
            defaultSort: 'name',
            createTitle: t('pages.suppliers.create_title'),
            editTitle: t('pages.suppliers.edit_title'),
            createDefaults: currentUserId ? { responsible_user_id: currentUserId } : undefined,
            selectFields: [
                'id',
                'name',
                'description',
                'responsible_user_id',
                'external_supplier_id',
                'tags',
                'classified',
                'has_category_issues',
                'has_evaluation_issues',
                'process_activities_summary',
                'assets_summary',
                'supplier_categories_summary',
                'agreementscount',
            ],
            customQueryParams: (filters) => ({
                tag_id: filters.tag_id || undefined,
                supplier_category_id: filters.supplier_category_id || undefined,
                responsible_user_id: filters.responsible_user_id || undefined,
                show_my_only: filters.show_my_only || undefined,
                hide_without_issues: filters.hide_without_issues || undefined,
            }),
            getItemStatus: (item) => {
                if (!item.classified || item.has_category_issues || item.has_evaluation_issues) {
                    return 'danger';
                }

                return null;
            },
            rowActions: [
                {
                    key: 'categories',
                    label: t('pages.suppliers.categories.open_button'),
                    variant: 'outline',
                    refreshOnComplete: false,
                    onClick: (item) => setActiveSupplierForCategories(item),
                },
                {
                    key: 'evaluation',
                    label: t('pages.suppliers.evaluation.open_button'),
                    variant: 'outline',
                    refreshOnComplete: false,
                    onClick: (item) => setActiveSupplierForEvaluation(item),
                },
                {
                    key: 'agreements',
                    label: t('pages.suppliers.agreements.open_button'),
                    variant: 'outline',
                    refreshOnComplete: false,
                    onClick: (item) => setActiveSupplierForAgreements(item),
                },
            ],
            fields: [
                {
                    key: 'name',
                    label: t('pages.suppliers.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.suppliers.category_general'),
                },
                {
                    key: 'tags',
                    label: t('pages.suppliers.column_tags'),
                    type: 'inline-tags',
                    editable: true,
                    sortable: false,
                    tags: true,
                    optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                    createOptionUrl: '/api/crud/tags',
                    optionValueKey: 'name',
                    optionLabelKey: 'name',
                    createOptionPayloadKey: 'name',
                    category: t('pages.suppliers.category_general'),
                },
                {
                    key: 'responsible_user_id',
                    label: t('pages.suppliers.column_responsible_user'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    filterable: true,
                    required: true,
                    optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.suppliers.none_assigned'),
                    category: t('pages.suppliers.category_general'),
                },
                {
                    key: 'external_supplier_id',
                    label: t('pages.suppliers.column_external_supplier_id'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    category: t('pages.suppliers.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.suppliers.column_description'),
                    type: 'textarea',
                    editable: true,
                    masterDescription: true,
                    category: t('pages.suppliers.category_general'),
                },
                {
                    key: 'process_activities_summary',
                    label: t('pages.suppliers.column_process_activities'),
                    type: 'text',
                    editable: false,
                    renderCell: (value) => renderSummaryList(value, t('pages.suppliers.none_related_items')),
                    renderDetail: (value) => renderSummaryList(value, t('pages.suppliers.none_related_items')),
                    category: t('pages.suppliers.category_relations'),
                },
                {
                    key: 'assets_summary',
                    label: t('pages.suppliers.column_assets'),
                    type: 'text',
                    editable: false,
                    renderCell: (value) => renderSummaryList(value, t('pages.suppliers.none_related_items')),
                    renderDetail: (value) => renderSummaryList(value, t('pages.suppliers.none_related_items')),
                    category: t('pages.suppliers.category_relations'),
                },
                {
                    key: 'supplier_categories_summary',
                    label: t('pages.suppliers.column_supplier_categories'),
                    type: 'text',
                    editable: false,
                    renderCell: (value) => renderCategorySummary(value, t),
                    renderDetail: (value) => renderCategorySummary(value, t),
                    category: t('pages.suppliers.category_relations'),
                },
                {
                    key: 'agreementscount',
                    label: t('pages.suppliers.column_agreements_count'),
                    type: 'number',
                    sortable: false,
                    editable: false,
                    category: t('pages.suppliers.category_relations'),
                },
                {
                    key: 'tag_id',
                    label: t('pages.suppliers.filter_tag'),
                    type: 'select',
                    hidden: true,
                    editable: false,
                    filterable: true,
                    optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                },
                {
                    key: 'supplier_category_id',
                    label: t('pages.suppliers.filter_supplier_category'),
                    type: 'select',
                    hidden: true,
                    editable: false,
                    filterable: true,
                    optionsUrl: '/api/suppliers/category-options',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                },
                {
                    key: 'show_my_only',
                    label: t('pages.suppliers.filter_show_my_only'),
                    type: 'boolean',
                    hidden: true,
                    editable: false,
                    filterable: true,
                    options: [{ value: '1', label: t('pages.suppliers.option_yes') }],
                },
                {
                    key: 'hide_without_issues',
                    label: t('pages.suppliers.filter_hide_without_issues'),
                    type: 'boolean',
                    hidden: true,
                    editable: false,
                    filterable: true,
                    options: [{ value: '1', label: t('pages.suppliers.option_yes') }],
                },
            ],
        }),
        [currentUserId, t]
    );

    const categoryConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeSupplierForCategories?.id) {
            return null;
        }

        return {
            apiUrl: `/api/suppliers/${activeSupplierForCategories.id}/categories`,
            perPage: 100,
            searchable: false,
            selectable: false,
            canCreate: false,
            canDelete: false,
            defaultSort: 'name',
            createTitle: t('pages.suppliers.categories.edit_title'),
            editTitle: t('pages.suppliers.categories.edit_title'),
            getItemStatus: (item) => (item.applicable === null ? 'danger' : null),
            fields: [
                {
                    key: 'name',
                    label: t('pages.suppliers.categories.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: false,
                    masterLabel: true,
                    category: t('pages.suppliers.categories.category_general'),
                },
                {
                    key: 'applicable',
                    label: t('pages.suppliers.categories.column_applicable'),
                    type: 'boolean',
                    sortable: false,
                    editable: true,
                    required: true,
                    options: [
                        { value: '1', label: t('pages.suppliers.option_yes') },
                        { value: '0', label: t('pages.suppliers.option_no') },
                    ],
                    category: t('pages.suppliers.categories.category_general'),
                },
                {
                    key: 'updated_by_name',
                    label: t('pages.suppliers.categories.column_updated_by'),
                    type: 'text',
                    sortable: false,
                    editable: false,
                    category: t('pages.suppliers.categories.category_metadata'),
                },
                {
                    key: 'updated_at',
                    label: t('pages.suppliers.categories.column_updated_at'),
                    type: 'datetime',
                    sortable: false,
                    editable: false,
                    category: t('pages.suppliers.categories.category_metadata'),
                },
            ],
        };
    }, [activeSupplierForCategories?.id, t]);

    const evaluationConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeSupplierForEvaluation?.id) {
            return null;
        }

        return {
            apiUrl: `/api/suppliers/${activeSupplierForEvaluation.id}/evaluation`,
            perPage: 100,
            searchable: false,
            selectable: false,
            canCreate: false,
            canDelete: false,
            defaultSort: 'name',
            createTitle: t('pages.suppliers.evaluation.edit_title'),
            editTitle: t('pages.suppliers.evaluation.edit_title'),
            getItemStatus: (item) => {
                if (!item.evaluated_at || item.satisfactory === false) {
                    return 'danger';
                }

                return null;
            },
            fields: [
                {
                    key: 'name',
                    label: t('pages.suppliers.evaluation.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: false,
                    masterLabel: true,
                    category: t('pages.suppliers.evaluation.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.suppliers.evaluation.column_description'),
                    type: 'textarea',
                    editable: false,
                    category: t('pages.suppliers.evaluation.category_general'),
                },
                {
                    key: 'reassessment',
                    label: t('pages.suppliers.evaluation.column_reassessment'),
                    type: 'boolean',
                    editable: false,
                    category: t('pages.suppliers.evaluation.category_general'),
                },
                {
                    key: 'evaluated_at',
                    label: t('pages.suppliers.evaluation.column_evaluated_at'),
                    type: 'datetime',
                    editable: false,
                    renderCell: (value, row) => renderEvaluatedAt(value, row, t),
                    renderDetail: (value, row) => renderEvaluatedAt(value, row, t),
                    category: t('pages.suppliers.evaluation.category_status'),
                },
                {
                    key: 'satisfactory',
                    label: t('pages.suppliers.evaluation.column_satisfactory'),
                    type: 'boolean',
                    editable: true,
                    required: true,
                    options: [
                        { value: '1', label: t('pages.suppliers.option_yes') },
                        { value: '0', label: t('pages.suppliers.option_no') },
                    ],
                    category: t('pages.suppliers.evaluation.category_status'),
                },
                {
                    key: 'note',
                    label: t('pages.suppliers.evaluation.column_note'),
                    type: 'textarea',
                    editable: true,
                    category: t('pages.suppliers.evaluation.category_status'),
                },
            ],
        };
    }, [activeSupplierForEvaluation?.id, t]);

    const agreementsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeSupplierForAgreements?.id) {
            return null;
        }

        return {
            apiUrl: '/api/crud/agreements',
            perPage: 50,
            defaultSort: 'name',
            fixedFilters: {
                supplier_id: Number(activeSupplierForAgreements.id),
            },
            createDefaults: {
                supplier_id: Number(activeSupplierForAgreements.id),
                responsible_user_id: activeSupplierForAgreements.responsible_user_id ?? currentUserId ?? undefined,
            },
            selectFields: [
                'id',
                'name',
                'description',
                'responsible_user_id',
                'supplier_id',
                'startdate',
                'reminderdate',
                'enddate',
            ],
            createTitle: t('pages.suppliers.agreements.create_title'),
            editTitle: t('pages.suppliers.agreements.edit_title'),
            fields: [
                {
                    key: 'name',
                    label: t('pages.suppliers.agreements.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.suppliers.agreements.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.suppliers.agreements.column_description'),
                    type: 'textarea',
                    editable: true,
                    masterDescription: true,
                    category: t('pages.suppliers.agreements.category_general'),
                },
                {
                    key: 'responsible_user_id',
                    label: t('pages.suppliers.agreements.column_responsible_user'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    required: true,
                    optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.suppliers.none_assigned'),
                    category: t('pages.suppliers.agreements.category_general'),
                },
                {
                    key: 'startdate',
                    label: t('pages.suppliers.agreements.column_startdate'),
                    type: 'date',
                    sortable: true,
                    editable: true,
                    category: t('pages.suppliers.agreements.category_dates'),
                },
                {
                    key: 'reminderdate',
                    label: t('pages.suppliers.agreements.column_reminderdate'),
                    type: 'date',
                    sortable: true,
                    editable: true,
                    category: t('pages.suppliers.agreements.category_dates'),
                },
                {
                    key: 'enddate',
                    label: t('pages.suppliers.agreements.column_enddate'),
                    type: 'date',
                    sortable: true,
                    editable: true,
                    category: t('pages.suppliers.agreements.category_dates'),
                },
            ],
        };
    }, [activeSupplierForAgreements, currentUserId, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.suppliers.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                                <Truck className="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    {t('pages.suppliers.title')}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {route.description ?? t('pages.suppliers.description')}
                                </p>
                            </div>
                        </div>
                        <a
                            href="/api/v1/ReportCentral/Suppliers/0"
                            className="flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            <Download className="h-4 w-4" />
                            {t('pages.suppliers.export_excel')}
                        </a>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={supplierConfig} />
                </section>
            </div>

            {categoryConfig && (
                <Dialog open={Boolean(activeSupplierForCategories)} onOpenChange={(open) => !open && setActiveSupplierForCategories(null)}>
                    <DialogContent className="max-w-4xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Waypoints className="h-5 w-5" />
                                {t('pages.suppliers.categories.panel_title', {
                                    supplier: String(activeSupplierForCategories?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>{t('pages.suppliers.categories.panel_description')}</DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button type="button" variant="outline" size="sm" onClick={() => setActiveSupplierForCategories(null)}>
                                    {t('pages.suppliers.categories.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule key={`supplier-categories-${activeSupplierForCategories?.id}`} config={categoryConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}

            {evaluationConfig && (
                <Dialog open={Boolean(activeSupplierForEvaluation)} onOpenChange={(open) => !open && setActiveSupplierForEvaluation(null)}>
                    <DialogContent className="max-w-5xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ListChecks className="h-5 w-5" />
                                {t('pages.suppliers.evaluation.panel_title', {
                                    supplier: String(activeSupplierForEvaluation?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>{t('pages.suppliers.evaluation.panel_description')}</DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button type="button" variant="outline" size="sm" onClick={() => setActiveSupplierForEvaluation(null)}>
                                    {t('pages.suppliers.evaluation.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule key={`supplier-evaluation-${activeSupplierForEvaluation?.id}`} config={evaluationConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}

            {agreementsConfig && (
                <Dialog open={Boolean(activeSupplierForAgreements)} onOpenChange={(open) => !open && setActiveSupplierForAgreements(null)}>
                    <DialogContent className="max-w-5xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Handshake className="h-5 w-5" />
                                {t('pages.suppliers.agreements.panel_title', {
                                    supplier: String(activeSupplierForAgreements?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>{t('pages.suppliers.agreements.panel_description')}</DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button type="button" variant="outline" size="sm" onClick={() => setActiveSupplierForAgreements(null)}>
                                    {t('pages.suppliers.agreements.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule key={`supplier-agreements-${activeSupplierForAgreements?.id}`} config={agreementsConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}

function renderSummaryList(value: unknown, emptyLabel: string) {
    if (!Array.isArray(value) || value.length === 0) {
        return <span className="text-muted-foreground">{emptyLabel}</span>;
    }

    return (
        <div className="space-y-1">
            {value.map((entry, index) => (
                <div key={`${String(entry)}-${index}`}>{String(entry)}</div>
            ))}
        </div>
    );
}

function renderCategorySummary(
    value: unknown,
    t: (key: string, replacements?: Record<string, string | number>) => string
) {
    if (!Array.isArray(value) || value.length === 0) {
        return <span className="text-muted-foreground">{t('pages.suppliers.none_related_items')}</span>;
    }

    const items = value as Array<{ id?: number; name?: string; applicable?: boolean | null }>;
    const applicableItems = items.filter((item) => item.applicable === true);
    const missingItems = items.filter((item) => item.applicable === null);

    return (
        <div className="space-y-1">
            {applicableItems.length > 0 ? (
                applicableItems.map((item) => <div key={item.id ?? item.name}>{item.name}</div>)
            ) : (
                <div className="text-muted-foreground">{t('pages.suppliers.none_related_items')}</div>
            )}
            {missingItems.length > 0 && (
                <div className="text-xs text-destructive">
                    {t('pages.suppliers.categories.missing_assignments', { count: missingItems.length })}
                </div>
            )}
        </div>
    );
}

function renderEvaluatedAt(
    value: unknown,
    row: Record<string, any>,
    t: (key: string, replacements?: Record<string, string | number>) => string
) {
    if (!value) {
        return <span className="text-destructive">{t('pages.suppliers.evaluation.never')}</span>;
    }

    const evaluator = row.evaluated_by_name ? ` ${t('pages.suppliers.evaluation.by')} ${String(row.evaluated_by_name)}` : '';

    return `${String(value)}${evaluator}`;
}
