import { useMemo } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig, FieldConfig } from '@/Components/crud';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import { CHEMICAL_DANGER_PROPERTIES } from '@/types/chemicalDangerProperties';
import type { AppSectionRoute } from '@/app/routes';

interface ChemicalRegisterPageProps {
    route: AppSectionRoute;
}

export default function ChemicalRegisterPage({ route }: ChemicalRegisterPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = useMemo(() => {
        const dangerOptions = CHEMICAL_DANGER_PROPERTIES.map((item) => ({
            value: item.key,
            label: `${item.code} - ${t(`pages.chemical_register.danger_property_${item.key}`)}`,
            imageUrl: item.imageUrl,
        }));

        const dangerField: FieldConfig = {
            key: 'danger',
            label: t('pages.chemical_register.column_danger_properties'),
            type: 'pictogram-multiselect',
            sortable: false,
            editable: true,
            options: dangerOptions,
            placeholder: t('pages.chemical_register.select_danger_properties'),
            category: t('pages.chemical_register.category_safety'),
            renderCell: (value) => {
                const selected = Array.isArray(value) ? value : [];

                if (selected.length === 0) {
                    return '—';
                }

                return (
                    <div className="flex flex-wrap gap-2">
                        {selected.map((key: string) => {
                            const definition = CHEMICAL_DANGER_PROPERTIES.find((item) => item.key === key);
                            if (!definition) {
                                return null;
                            }

                            return (
                                <img
                                    key={key}
                                    src={definition.imageUrl}
                                    alt={t(`pages.chemical_register.danger_property_${key}`)}
                                    title={t(`pages.chemical_register.danger_property_${key}`)}
                                    className="h-8 w-8 object-contain"
                                />
                            );
                        })}
                    </div>
                );
            },
            renderDetail: (value) => {
                const selected = Array.isArray(value) ? value : [];
                if (selected.length === 0) {
                    return '—';
                }

                return (
                    <div className="flex flex-wrap gap-2">
                        {selected.map((key: string) => {
                            const definition = CHEMICAL_DANGER_PROPERTIES.find((item) => item.key === key);
                            if (!definition) {
                                return null;
                            }

                            return (
                                <img
                                    key={key}
                                    src={definition.imageUrl}
                                    alt={t(`pages.chemical_register.danger_property_${key}`)}
                                    title={t(`pages.chemical_register.danger_property_${key}`)}
                                    className="h-10 w-10 object-contain"
                                />
                            );
                        })}
                    </div>
                );
            },
        };

        return {
            apiUrl: '/api/crud/chemicals',
            perPage: 25,
            defaultSort: 'name',
            createTitle: t('pages.chemical_register.create_title'),
            editTitle: t('pages.chemical_register.edit_title'),
            selectFields: [
                'id',
                'name',
                'manufacturer',
                'danger',
                'ohs_danger_properties',
                'description',
                'usagedescription',
                'storagedescription',
                'consumptiondescription',
                'riskdescription',
                'handlingguidance',
                'sdbfilename',
                'updated_at',
                'tags',
            ],
            customQueryParams: (filters) => ({
                tag_id: filters.tag_id || undefined,
            }),
            fields: [
                {
                    key: 'name',
                    label: t('pages.chemical_register.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.chemical_register.category_general'),
                },
                {
                    key: 'tags',
                    label: t('pages.chemical_register.column_tags'),
                    type: 'inline-tags',
                    editable: true,
                    sortable: false,
                    tags: true,
                    optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                    createOptionUrl: '/api/crud/tags',
                    optionValueKey: 'name',
                    optionLabelKey: 'name',
                    createOptionPayloadKey: 'name',
                    category: t('pages.chemical_register.category_general'),
                },
                {
                    key: 'manufacturer',
                    label: t('pages.chemical_register.column_manufacturer'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    category: t('pages.chemical_register.category_general'),
                },
            dangerField,
                {
                    key: 'description',
                    label: t('pages.chemical_register.column_description'),
                    type: 'textarea',
                    editable: true,
                    masterDescription: true,
                    category: t('pages.chemical_register.category_usage'),
                },
                {
                    key: 'usagedescription',
                    label: t('pages.chemical_register.column_usage'),
                    type: 'textarea',
                    editable: true,
                    helpText: t('pages.chemical_register.help_usage'),
                    category: t('pages.chemical_register.category_usage'),
                },
                {
                    key: 'storagedescription',
                    label: t('pages.chemical_register.column_storage'),
                    type: 'textarea',
                    editable: true,
                    helpText: t('pages.chemical_register.help_storage'),
                    category: t('pages.chemical_register.category_usage'),
                },
                {
                    key: 'consumptiondescription',
                    label: t('pages.chemical_register.column_annual_consumption'),
                    type: 'textarea',
                    editable: true,
                    helpText: t('pages.chemical_register.help_consumption'),
                    category: t('pages.chemical_register.category_assessment'),
                },
                {
                    key: 'riskdescription',
                    label: t('pages.chemical_register.column_risk_description'),
                    type: 'textarea',
                    editable: true,
                    helpText: t('pages.chemical_register.help_risk'),
                    category: t('pages.chemical_register.category_assessment'),
                },
                {
                    key: 'handlingguidance',
                    label: t('pages.chemical_register.column_handling_guidance'),
                    type: 'textarea',
                    editable: true,
                    helpText: t('pages.chemical_register.help_handling_guidance'),
                    category: t('pages.chemical_register.category_assessment'),
                },
                {
                    key: 'sdbfilename',
                    label: t('pages.chemical_register.column_safety_datasheet'),
                    type: 'text',
                    editable: false,
                    hiddenInTable: true,
                    category: t('pages.chemical_register.category_files'),
                },
                {
                    key: 'sdbfile',
                    label: t('pages.chemical_register.column_upload_safety_datasheet'),
                    type: 'file',
                    editable: true,
                    accept: 'application/pdf',
                    helpText: t('pages.chemical_register.help_upload_safety_datasheet'),
                    hiddenInTable: true,
                    hiddenInDetails: true,
                    category: t('pages.chemical_register.category_files'),
                },
                {
                    key: 'download_safety_datasheet',
                    label: t('pages.chemical_register.column_download'),
                    type: 'text',
                    editable: false,
                    sortable: false,
                    renderCell: (_, row) => {
                        if (!row.sdbfilename) {
                            return '—';
                        }
                        return (
                            <a
                                href={`/api/v1/items/Chemical/${row.id}/download`}
                                className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                            >
                                <MaterialSymbol name="download" className="h-3.5 w-3.5" />
                                {t('pages.chemical_register.download_safety_datasheet')}
                            </a>
                        );
                    },
                    renderDetail: (_, row) => {
                        if (!row.sdbfilename) {
                            return '—';
                        }
                        return (
                            <a
                                href={`/api/v1/items/Chemical/${row.id}/download`}
                                className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                            >
                                <MaterialSymbol name="download" className="h-3.5 w-3.5" />
                                {t('pages.chemical_register.download_safety_datasheet')}
                            </a>
                        );
                    },
                },
                {
                    key: 'tag_id',
                    label: t('pages.chemical_register.filter_tag'),
                    type: 'select',
                    hidden: true,
                    editable: false,
                    filterable: true,
                    optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                },
            ],
        };
    }, [t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.chemical_register.title')}
                    description={t('pages.chemical_register.description')}
                    icon={<MaterialSymbol name="science" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
