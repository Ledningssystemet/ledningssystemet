import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { HardDrive, Download } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface AssetsPageProps {
    route: AppSectionRoute;
}

export default function AssetsPage({ route }: AssetsPageProps) {
    const { t } = useTranslations();

    const [supportingAssetsOpen, setSupportingAssetsOpen] = useState(false);
    const [selectedAsset, setSelectedAsset] = useState<Record<string, any> | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.assets.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const config: CrudModuleConfig = {
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
        getItemStatus: (item) => {
            if (
                !item.responsible_user_id ||
                !item.confidentiality_class_id ||
                !item.integrity_class_id ||
                !item.availability_class_id
            ) {
                return 'danger';
            }

            return null;
        },
        rowActions: [
            {
                key: 'supporting_assets',
                label: t('pages.assets.supporting_assets_button'),
                variant: 'outline',
                refreshOnComplete: false,
                onClick: (item) => {
                    setSelectedAsset(item);
                    setSupportingAssetsOpen(true);
                },
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
                optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
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
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
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
                optionsUrl: '/api/crud/sites?paginate=0&%24select=id,name&sort=name',
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
                optionsUrl: '/api/crud/confidentiality-classes?paginate=0&%24select=id,name&sort=-ordinal',
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
                optionsUrl: '/api/crud/integrity-classes?paginate=0&%24select=id,name&sort=-ordinal',
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
                optionsUrl: '/api/crud/availability-classes?paginate=0&%24select=id,name&sort=-ordinal',
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
                optionsUrl: '/api/crud/suppliers?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.assets.none_option'),
                category: t('pages.assets.category_association'),
            },
            // Filter-only hidden fields
            {
                key: 'tag_id',
                label: t('pages.assets.filter_tag'),
                type: 'select',
                hidden: true,
                editable: false,
                filterable: true,
                optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
            },
            {
                key: 'process_id',
                label: t('pages.assets.filter_process'),
                type: 'select',
                hidden: true,
                editable: false,
                filterable: true,
                optionsUrl: '/api/crud/processes?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
            },
            {
                key: 'show_my_only',
                label: t('pages.assets.filter_show_my_only'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [
                    { value: '1', label: t('pages.assets.option_yes') },
                ],
            },
            {
                key: 'hide_without_issues',
                label: t('pages.assets.filter_hide_without_issues'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [
                    { value: '1', label: t('pages.assets.option_yes') },
                ],
            },
        ],
    };

    const supportingAssetsConfig: CrudModuleConfig | null = selectedAsset
        ? {
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
              fixedFilters: { dependant_asset_id: selectedAsset.id },
              createDefaults: {
                  dependant_asset_id: selectedAsset.id,
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
                      optionsUrl: '/api/crud/assets?paginate=0&%24select=id,name&sort=name',
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
          }
        : null;

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.assets.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                                <HardDrive className="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    {t('pages.assets.title')}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {route.description ?? t('pages.assets.description')}
                                </p>
                            </div>
                        </div>
                        <a
                            href="/api/v1/ReportCentral/Assets/0"
                            className="flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            <Download className="h-4 w-4" />
                            {t('pages.assets.export_excel')}
                        </a>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {supportingAssetsConfig && (
                <Dialog open={supportingAssetsOpen} onOpenChange={setSupportingAssetsOpen}>
                    <DialogContent className="max-w-4xl">
                        <DialogHeader>
                            <DialogTitle>
                                {t('pages.assets.supporting_assets_title', {
                                    name: selectedAsset?.name ?? '',
                                })}
                            </DialogTitle>
                        </DialogHeader>
                        <div className="mt-2">
                            <CrudModule config={supportingAssetsConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
