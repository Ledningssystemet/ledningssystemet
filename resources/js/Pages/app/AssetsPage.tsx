import { useMemo } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface AssetsPageProps {
    route: AppSectionRoute;
}

export default function AssetsPage({ route }: AssetsPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/assets',
            perPage: 25,
            defaultSort: 'name',
            selectFields: [
                'id',
                'name',
                'description',
                'responsible_user_id',
                'site_id',
                'confidentiality_class_id',
                'integrity_class_id',
                'availability_class_id',
                'supplier_id',
                'tags',
            ],
            createTitle: t('pages.assets.create_title'),
            editTitle: t('pages.assets.edit_title'),
            customQueryParams: (filters) => ({
                tag_id: filters.tag_id || undefined,
                process_id: filters.process_id || undefined,
                show_my_only: filters.show_my_only || undefined,
                hide_without_issues: filters.hide_without_issues || undefined,
            }),
            subTableActions: [
                {
                    key: 'supporting_assets',
                    label: t('pages.assets.supporting_assets_button'),
                    dialogMaxWidth: 'max-w-4xl',
                    dialogTitle: (item) =>
                        t('pages.assets.supporting_assets_title', {
                            name: String(item.name ?? ''),
                        }),
                    getConfig: (item): CrudModuleConfig => ({
                        apiUrl: '/api/crud/asset-asset-dependancies',
                        perPage: 50,
                        defaultSort: 'id',
                        selectFields: [
                            'id',
                            'dependant_asset_id',
                            'depending_asset_id',
                            'description',
                            'inherit_confidentiality',
                            'inherit_integrity',
                            'inherit_availability',
                        ],
                        createTitle: t('pages.assets.supporting_assets_button'),
                        editTitle: t('pages.assets.supporting_assets_button'),
                        fixedFilters: { dependant_asset_id: item.id },
                        createDefaults: {
                            dependant_asset_id: item.id,
                            inherit_confidentiality: false,
                            inherit_integrity: false,
                            inherit_availability: false,
                        },
                        fields: [
                            {
                                key: 'depending_asset_id',
                                label: t('pages.assets.column_depending_asset'),
                                type: 'select',
                                editable: true,
                                required: true,
                                sortable: true,
                                optionsUrl:
                                    '/api/crud/assets?paginate=0&%24select=id,name&sort=name',
                                optionValueKey: 'id',
                                optionLabelKey: 'name',
                                placeholder: t('pages.assets.none_option'),
                            },
                            {
                                key: 'description',
                                label: t('pages.assets.column_description'),
                                type: 'textarea',
                                editable: true,
                            },
                            {
                                key: 'inherit_confidentiality',
                                label: t('pages.assets.column_inherit_confidentiality'),
                                type: 'boolean',
                                editable: true,
                                options: [
                                    { value: '1', label: t('pages.assets.option_yes') },
                                    { value: '0', label: t('pages.assets.option_no') },
                                ],
                            },
                            {
                                key: 'inherit_integrity',
                                label: t('pages.assets.column_inherit_integrity'),
                                type: 'boolean',
                                editable: true,
                                options: [
                                    { value: '1', label: t('pages.assets.option_yes') },
                                    { value: '0', label: t('pages.assets.option_no') },
                                ],
                            },
                            {
                                key: 'inherit_availability',
                                label: t('pages.assets.column_inherit_availability'),
                                type: 'boolean',
                                editable: true,
                                options: [
                                    { value: '1', label: t('pages.assets.option_yes') },
                                    { value: '0', label: t('pages.assets.option_no') },
                                ],
                            },
                        ],
                    }),
                },
            ],
            fields: [
                {
                    key: 'name',
                    label: t('pages.assets.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.assets.category_general'),
                },
                {
                    key: 'tags',
                    label: t('pages.assets.column_tags'),
                    type: 'inline-tags',
                    editable: true,
                    sortable: false,
                    tags: true,
                    optionsUrl:
                        '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                    createOptionUrl: '/api/crud/tags',
                    optionValueKey: 'name',
                    optionLabelKey: 'name',
                    createOptionPayloadKey: 'name',
                    category: t('pages.assets.category_general'),
                },
                {
                    key: 'responsible_user_id',
                    label: t('pages.assets.column_responsible_user'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    filterable: true,
                    required: true,
                    optionsUrl:
                        '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.assets.none_assigned'),
                    category: t('pages.assets.category_general'),
                },
                {
                    key: 'site_id',
                    label: t('pages.assets.column_site'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    filterable: true,
                    optionsUrl:
                        '/api/crud/sites?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.assets.none_option'),
                    category: t('pages.assets.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.assets.column_description'),
                    type: 'textarea',
                    editable: true,
                    masterDescription: true,
                    category: t('pages.assets.category_general'),
                },
                {
                    key: 'confidentiality_class_id',
                    label: t('pages.assets.column_confidentiality_class'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    filterable: true,
                    hiddenInTable: true,
                    hiddenInDetails: true,
                    optionsUrl:
                        '/api/crud/confidentiality-classes?paginate=0&%24select=id,name&sort=-ordinal',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.assets.not_classified'),
                    category: t('pages.assets.category_classification'),
                },
                {
                    key: 'integrity_class_id',
                    label: t('pages.assets.column_integrity_class'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    filterable: true,
                    hiddenInTable: true,
                    hiddenInDetails: true,
                    optionsUrl:
                        '/api/crud/integrity-classes?paginate=0&%24select=id,name&sort=-ordinal',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.assets.not_classified'),
                    category: t('pages.assets.category_classification'),
                },
                {
                    key: 'availability_class_id',
                    label: t('pages.assets.column_availability_class'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    filterable: true,
                    hiddenInTable: true,
                    hiddenInDetails: true,
                    optionsUrl:
                        '/api/crud/availability-classes?paginate=0&%24select=id,name&sort=-ordinal',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.assets.not_classified'),
                    category: t('pages.assets.category_classification'),
                },
                {
                    key: 'effective_confidentiality_class_id',
                    label: t('pages.assets.column_confidentiality_class'),
                    type: 'select',
                    sortable: false,
                    editable: false,
                    filterable: false,
                    optionsUrl:
                        '/api/crud/confidentiality-classes?paginate=0&%24select=id,name&sort=-ordinal',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.assets.not_classified'),
                    category: t('pages.assets.category_classification'),
                },
                {
                    key: 'effective_integrity_class_id',
                    label: t('pages.assets.column_integrity_class'),
                    type: 'select',
                    sortable: false,
                    editable: false,
                    filterable: false,
                    optionsUrl:
                        '/api/crud/integrity-classes?paginate=0&%24select=id,name&sort=-ordinal',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.assets.not_classified'),
                    category: t('pages.assets.category_classification'),
                },
                {
                    key: 'effective_availability_class_id',
                    label: t('pages.assets.column_availability_class'),
                    type: 'select',
                    sortable: false,
                    editable: false,
                    filterable: false,
                    optionsUrl:
                        '/api/crud/availability-classes?paginate=0&%24select=id,name&sort=-ordinal',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.assets.not_classified'),
                    category: t('pages.assets.category_classification'),
                },
                {
                    key: 'supplier_id',
                    label: t('pages.assets.column_supplier'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    hiddenInTable: true,
                    optionsUrl:
                        '/api/crud/suppliers?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.assets.none_option'),
                    category: t('pages.assets.category_association'),
                },
            ],
            filterFields: [
                {
                    key: 'tag_id',
                    label: t('pages.assets.filter_tag'),
                    type: 'select',
                    optionsUrl:
                        '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                },
                {
                    key: 'process_id',
                    label: t('pages.assets.filter_process'),
                    type: 'select',
                    optionsUrl:
                        '/api/crud/processes?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                },
                {
                    key: 'show_my_only',
                    label: t('pages.assets.filter_show_my_only'),
                    type: 'boolean',
                    options: [{ value: '1', label: t('pages.assets.option_yes') }],
                },
                {
                    key: 'hide_without_issues',
                    label: t('pages.assets.filter_hide_without_issues'),
                    type: 'boolean',
                    options: [{ value: '1', label: t('pages.assets.option_yes') }],
                },
            ],
        }),
        [t]
    );

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.assets.title')}
                    description={t('pages.assets.description')}
                    icon={<MaterialSymbol name="hard_drive_2" className="h-6 w-6 text-primary" />}
                    route={route}
                />
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
