import { useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import { Building2, TriangleAlert } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig, FieldConfig } from '@/components/crud';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface SitesPageProps {
    route: AppSectionRoute;
}

interface SharedProps extends PageProps {
    settings?: {
        sites?: {
            external_sync_enabled?: boolean;
            external_provider_name?: string;
        };
    };
}

export default function SitesPage({ route }: SitesPageProps) {
    const { t } = useTranslations();
    const page = usePage<SharedProps>();

    const externalSyncEnabled = Boolean(page.props.settings?.sites?.external_sync_enabled);
    const externalProviderName =
        page.props.settings?.sites?.external_provider_name?.trim() || t('pages.sites.external_provider_default');

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.sites.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const fields: FieldConfig[] = useMemo(() => {
        const editableRelationsCategory = t('pages.sites.category_assignments');

        const list: FieldConfig[] = [
            {
                key: 'name',
                label: t('pages.sites.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.sites.category_general'),
            },
            {
                key: 'tags',
                label: t('pages.sites.column_tags'),
                type: 'inline-tags',
                editable: true,
                sortable: false,
                tags: true,
                optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                createOptionUrl: '/api/crud/tags',
                optionValueKey: 'name',
                optionLabelKey: 'name',
                createOptionPayloadKey: 'name',
                category: t('pages.sites.category_general'),
            },
            {
                key: 'responsible_user_id',
                label: t('pages.sites.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.sites.none_assigned'),
                category: t('pages.sites.category_general'),
            },
            {
                key: 'users',
                label: t('pages.sites.column_users'),
                type: 'multiselect',
                sortable: false,
                editable: true,
                editableOnCreate: false,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.sites.select_users_placeholder'),
                helpText: externalSyncEnabled ? t('pages.sites.users_sync_warning') : undefined,
                category: editableRelationsCategory,
            },
            {
                key: 'departments',
                label: t('pages.sites.column_departments'),
                type: 'multiselect',
                sortable: false,
                editable: true,
                editableOnCreate: false,
                optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.sites.select_departments_placeholder'),
                category: editableRelationsCategory,
            },
            {
                key: 'assets',
                label: t('pages.sites.column_assets'),
                type: 'multiselect',
                sortable: false,
                editable: true,
                editableOnCreate: false,
                optionsUrl: '/api/crud/assets?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.sites.select_assets_placeholder'),
                category: editableRelationsCategory,
            },
            {
                key: 'tag_id',
                label: t('pages.sites.filter_tag'),
                type: 'select',
                hidden: true,
                editable: false,
                filterable: true,
                optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
            },
            {
                key: 'hide_without_issues',
                label: t('pages.sites.filter_hide_without_issues'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [{ value: '1', label: t('pages.sites.option_yes') }],
            },
        ];

        if (externalSyncEnabled) {
            list.splice(3, 0, {
                key: 'external_provider_group_id',
                label: `${externalProviderName} ${t('pages.sites.group_suffix')}`,
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/external-provider-groups?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.sites.none_option'),
                category: t('pages.sites.category_general'),
            });
        }

        return list;
    }, [externalProviderName, externalSyncEnabled, t]);

    const config: CrudModuleConfig = useMemo(() => {
        const selectFields = [
            'id',
            'name',
            'responsible_user_id',
            'users',
            'departments',
            'assets',
            'userscount',
            'departmentscount',
            'assetscount',
            'classified',
            'can_delete',
            'tags',
        ];

        if (externalSyncEnabled) {
            selectFields.push('external_provider_group_id');
        }

        return {
            apiUrl: '/api/crud/sites',
            perPage: 25,
            defaultSort: 'name',
            selectFields,
            createTitle: t('pages.sites.create_title'),
            editTitle: t('pages.sites.edit_title'),
            deletableKey: 'can_delete',
            customQueryParams: (filters) => ({
                tag_id: filters.tag_id || undefined,
                responsible_user_id: filters.responsible_user_id || undefined,
                hide_without_issues: filters.hide_without_issues || undefined,
            }),
            getItemStatus: (item) => {
                if (!item.classified) {
                    return 'danger';
                }

                return null;
            },
            fields,
        } satisfies CrudModuleConfig;
    }, [externalSyncEnabled, fields, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.sites.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <Building2 className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.sites.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.sites.description')}
                            </p>
                        </div>
                    </div>
                </section>

                {externalSyncEnabled && (
                    <section className="rounded-2xl border border-warning/30 bg-warning/5 p-4 shadow-sm">
                        <div className="flex items-start gap-3 text-sm text-foreground">
                            <TriangleAlert className="mt-0.5 h-4 w-4 text-warning" />
                            <p>{t('pages.sites.users_sync_warning')}</p>
                        </div>
                    </section>
                )}

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
