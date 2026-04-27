import { useMemo, useState } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
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

interface SupplierCategoriesPageProps {
    route: AppSectionRoute;
}

export default function SupplierCategoriesPage({ route }: SupplierCategoriesPageProps) {
    const { t } = useTranslations();
    const [activeCategoryForRequirements, setActiveCategoryForRequirements] = useState<Record<string, any> | null>(null);

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/supplier-categories',
            perPage: 25,
            defaultSort: 'name',
            createTitle: t('pages.supplier_categories.create_title'),
            editTitle: t('pages.supplier_categories.edit_title'),
            createDefaults: {
                reassessment_interval: 'never',
                defaultvalue: false,
            },
            selectFields: [
                'id',
                'name',
                'description',
                'reassessment_interval',
                'defaultvalue',
                'formulaname',
                'partner_id',
                'partner_name',
            ],
            fields: [
                {
                    key: 'name',
                    label: t('pages.supplier_categories.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.supplier_categories.category_general'),
                },
                {
                    key: 'partner_info',
                    label: t('pages.supplier_categories.column_partner_info'),
                    type: 'text',
                    sortable: false,
                    editable: false,
                    renderCell: (_, row) =>
                        row.partner_id
                            ? t('pages.supplier_categories.partner_info', {
                                  partner: String(row.partner_name ?? ''),
                              })
                            : '',
                    renderDetail: (_, row) =>
                        row.partner_id
                            ? t('pages.supplier_categories.partner_info', {
                                  partner: String(row.partner_name ?? ''),
                              })
                            : '',
                    category: t('pages.supplier_categories.category_general'),
                },
                {
                    key: 'defaultvalue',
                    label: t('pages.supplier_categories.column_require_assessment'),
                    type: 'boolean',
                    sortable: false,
                    editable: true,
                    editableOnUpdate: false,
                    required: true,
                    hiddenInTable: true,
                    category: t('pages.supplier_categories.category_assessment'),
                },
                {
                    key: 'description',
                    label: t('pages.supplier_categories.column_description'),
                    type: 'textarea',
                    sortable: false,
                    editable: true,
                    required: true,
                    masterDescription: true,
                    category: t('pages.supplier_categories.category_general'),
                },
                {
                    key: 'reassessment_interval',
                    label: t('pages.supplier_categories.column_reassessment_interval'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    category: t('pages.supplier_categories.category_assessment'),
                },
                {
                    key: 'requirements',
                    label: t('pages.supplier_categories.column_requirements'),
                    type: 'text',
                    sortable: false,
                    editable: false,
                    renderCell: (_, row) => (
                        <Button type="button" variant="outline" size="sm" onClick={() => setActiveCategoryForRequirements(row)}>
                            {t('pages.supplier_categories.open_requirements_button')}
                        </Button>
                    ),
                    renderDetail: (_, row) => (
                        <Button type="button" variant="outline" size="sm" onClick={() => setActiveCategoryForRequirements(row)}>
                            {t('pages.supplier_categories.open_requirements_button')}
                        </Button>
                    ),
                    category: t('pages.supplier_categories.category_assessment'),
                },
            ],
        }),
        [t]
    );

    const requirementsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeCategoryForRequirements?.id) {
            return null;
        }

        const isReadOnly = Boolean(activeCategoryForRequirements.partner_id);

        return {
            apiUrl: '/api/crud/supplier-requirements',
            perPage: 100,
            searchable: false,
            defaultSort: 'name',
            fixedFilters: {
                supplier_category_id: Number(activeCategoryForRequirements.id),
            },
            createDefaults: {
                supplier_category_id: Number(activeCategoryForRequirements.id),
                reassessment: false,
            },
            canCreate: !isReadOnly,
            canEdit: !isReadOnly,
            canDelete: !isReadOnly,
            createTitle: t('pages.supplier_categories.requirements.create_title'),
            editTitle: t('pages.supplier_categories.requirements.edit_title'),
            selectFields: ['id', 'name', 'description', 'reassessment', 'supplier_category_id', 'partner_id'],
            fields: [
                {
                    key: 'supplier_category_id',
                    label: t('pages.supplier_categories.requirements.column_supplier_category'),
                    type: 'number',
                    editable: false,
                    hidden: true,
                },
                {
                    key: 'name',
                    label: t('pages.supplier_categories.requirements.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.supplier_categories.requirements.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.supplier_categories.requirements.column_description'),
                    type: 'textarea',
                    editable: true,
                    required: true,
                    masterDescription: true,
                    category: t('pages.supplier_categories.requirements.category_general'),
                },
                {
                    key: 'reassessment',
                    label: t('pages.supplier_categories.requirements.column_reassessment'),
                    type: 'boolean',
                    sortable: false,
                    editable: true,
                    required: true,
                    category: t('pages.supplier_categories.requirements.category_assessment'),
                },
            ],
        };
    }, [activeCategoryForRequirements, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.supplier_categories.title')}
                    description={t('pages.supplier_categories.description')}
                    icon={<MaterialSymbol name="alt_route" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {requirementsConfig && (
                <Dialog
                    open={Boolean(activeCategoryForRequirements)}
                    onOpenChange={(open) => !open && setActiveCategoryForRequirements(null)}
                >
                    <DialogContent className="max-w-5xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <MaterialSymbol name="checklist" className="h-5 w-5" />
                                {t('pages.supplier_categories.requirements.panel_title', {
                                    category: String(activeCategoryForRequirements?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('pages.supplier_categories.requirements.panel_description')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button type="button" variant="outline" size="sm" onClick={() => setActiveCategoryForRequirements(null)}>
                                    {t('pages.supplier_categories.requirements.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule
                                key={`supplier-category-requirements-${activeCategoryForRequirements?.id}`}
                                config={requirementsConfig}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
