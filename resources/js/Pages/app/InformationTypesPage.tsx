import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { FileType, Download } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface InformationTypesPageProps {
    route: AppSectionRoute;
}

export default function InformationTypesPage({ route }: InformationTypesPageProps) {
    const { t } = useTranslations();

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.information_types.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const config: CrudModuleConfig = {
        apiUrl: '/api/crud/information_types',
        perPage: 25,
        defaultSort: 'name',
        selectFields: [
            'id',
            'name',
            'description',
            'responsible_user_id',
            'confidentiality_class_id',
            'integrity_class_id',
            'availability_class_id',
            'retention',
            'piidescription',
            'confidentiality_ground_id',
            'diary_id',
            'archivingdescription',
            'archiveshippingtime',
            'archivemedia',
            'sortinginformation',
            'tags',
        ],
        createTitle: t('pages.information_types.create_title'),
        editTitle: t('pages.information_types.edit_title'),
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
        fields: [
            // General
            {
                key: 'name',
                label: t('pages.information_types.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.information_types.category_general'),
            },
            {
                key: 'tags',
                label: t('pages.information_types.column_tags'),
                type: 'inline-tags',
                editable: true,
                sortable: false,
                tags: true,
                optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                createOptionUrl: '/api/crud/tags',
                optionValueKey: 'name',
                optionLabelKey: 'name',
                createOptionPayloadKey: 'name',
                category: t('pages.information_types.category_general'),
            },
            {
                key: 'responsible_user_id',
                label: t('pages.information_types.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                required: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.information_types.none_assigned'),
                category: t('pages.information_types.category_general'),
            },
            {
                key: 'description',
                label: t('pages.information_types.column_description'),
                type: 'textarea',
                editable: true,
                masterDescription: true,
                category: t('pages.information_types.category_general'),
            },
            // Classification
            {
                key: 'confidentiality_class_id',
                label: t('pages.information_types.column_confidentiality_class'),
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/confidentiality-classes?paginate=0&%24select=id,name&sort=-ordinal',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.information_types.not_classified'),
                category: t('pages.information_types.category_classification'),
            },
            {
                key: 'integrity_class_id',
                label: t('pages.information_types.column_integrity_class'),
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/integrity-classes?paginate=0&%24select=id,name&sort=-ordinal',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.information_types.not_classified'),
                category: t('pages.information_types.category_classification'),
            },
            {
                key: 'availability_class_id',
                label: t('pages.information_types.column_availability_class'),
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/availability-classes?paginate=0&%24select=id,name&sort=-ordinal',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.information_types.not_classified'),
                category: t('pages.information_types.category_classification'),
            },
            // Privacy
            {
                key: 'piidescription',
                label: t('pages.information_types.column_piidescription'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.information_types.category_privacy'),
            },
            // Archival
            {
                key: 'retention',
                label: t('pages.information_types.column_retention'),
                type: 'number',
                editable: true,
                hiddenInTable: true,
                category: t('pages.information_types.category_archival'),
            },
            {
                key: 'confidentiality_ground_id',
                label: t('pages.information_types.column_confidentiality_ground'),
                type: 'select',
                editable: true,
                hiddenInTable: true,
                optionsUrl: '/api/crud/confidentiality-grounds?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.information_types.none_option'),
                category: t('pages.information_types.category_archival'),
            },
            {
                key: 'diary_id',
                label: t('pages.information_types.column_diary'),
                type: 'select',
                editable: true,
                hiddenInTable: true,
                optionsUrl: '/api/crud/diaries?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.information_types.none_option'),
                category: t('pages.information_types.category_archival'),
            },
            {
                key: 'sortinginformation',
                label: t('pages.information_types.column_sortinginformation'),
                type: 'text',
                editable: true,
                hiddenInTable: true,
                category: t('pages.information_types.category_archival'),
            },
            {
                key: 'archivingdescription',
                label: t('pages.information_types.column_archivingdescription'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.information_types.category_archival'),
            },
            {
                key: 'archiveshippingtime',
                label: t('pages.information_types.column_archiveshippingtime'),
                type: 'number',
                editable: true,
                hiddenInTable: true,
                category: t('pages.information_types.category_archival'),
            },
            {
                key: 'archivemedia',
                label: t('pages.information_types.column_archivemedia'),
                type: 'text',
                editable: true,
                hiddenInTable: true,
                category: t('pages.information_types.category_archival'),
            },
            // Filter-only hidden fields
            {
                key: 'tag_id',
                label: t('pages.information_types.filter_tag'),
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
                label: t('pages.information_types.filter_process'),
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
                label: t('pages.information_types.filter_show_my_only'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [
                    { value: '1', label: t('pages.information_types.option_yes') },
                ],
            },
            {
                key: 'hide_without_issues',
                label: t('pages.information_types.filter_hide_without_issues'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [
                    { value: '1', label: t('pages.information_types.option_yes') },
                ],
            },
        ],
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.information_types.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                                <FileType className="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    {t('pages.information_types.title')}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {route.description ?? t('pages.information_types.description')}
                                </p>
                            </div>
                        </div>
                        <a
                            href="/api/v1/ReportCentral/InformationTypes/0"
                            className="flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            <Download className="h-4 w-4" />
                            {t('pages.information_types.export_excel')}
                        </a>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
