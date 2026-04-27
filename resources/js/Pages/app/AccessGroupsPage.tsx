import { useMemo } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig, FieldConfig } from '@/components/crud';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface AccessGroupsPageProps {
    route: AppSectionRoute;
}

interface SharedProps extends PageProps {
    settings?: {
        access_groups?: {
            external_sync_enabled?: boolean;
            external_provider_name?: string;
        };
    };
}

export default function AccessGroupsPage({ route }: AccessGroupsPageProps) {
    const { t } = useTranslations();
    const page = usePage<SharedProps>();

    const externalSyncEnabled = Boolean(page.props.settings?.access_groups?.external_sync_enabled);
    const externalProviderName =
        page.props.settings?.access_groups?.external_provider_name?.trim() || t('pages.access_groups.external_provider_default');

    const config: CrudModuleConfig = useMemo(() => {
        const fields: FieldConfig[] = [
            {
                key: 'name',
                label: t('pages.access_groups.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.access_groups.category_general'),
            },
            {
                key: 'user_ids',
                label: t('pages.access_groups.column_users'),
                type: 'multiselect',
                sortable: false,
                editable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.access_groups.select_users_placeholder'),
                helpText: externalSyncEnabled ? t('pages.access_groups.users_sync_warning') : undefined,
                category: t('pages.access_groups.category_members'),
            },
            {
                key: 'risk_level_id',
                label: t('pages.access_groups.column_risk_level'),
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/risk-levels?paginate=0&%24select=id,name&sort=ordinal,name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.access_groups.none_option'),
                category: t('pages.access_groups.category_limits'),
            },
            {
                key: 'claims',
                label: t('pages.access_groups.column_claims'),
                type: 'multiselect',
                sortable: false,
                editable: true,
                optionsUrl: '/api/access-groups/claims',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.access_groups.select_claims_placeholder'),
                category: t('pages.access_groups.category_permissions'),
            },
        ];

        if (externalSyncEnabled) {
            fields.splice(1, 0, {
                key: 'external_provider_group_id',
                label: `${externalProviderName} ${t('pages.access_groups.group_suffix')}`,
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/external-provider-groups?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.access_groups.none_option'),
                category: t('pages.access_groups.category_general'),
            });
        }

        const selectFields = ['id', 'name', 'risk_level_id', 'claims', 'user_ids'];
        if (externalSyncEnabled) {
            selectFields.push('external_provider_group_id');
        }

        return {
            apiUrl: '/api/crud/access-groups',
            perPage: 25,
            defaultSort: 'name',
            selectFields,
            createTitle: t('pages.access_groups.create_title'),
            editTitle: t('pages.access_groups.edit_title'),
            fields,
        };
    }, [t, externalSyncEnabled, externalProviderName]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.access_groups.title')}
                    description={t('pages.access_groups.description')}
                    icon={<MaterialSymbol name="vpn_key" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                {externalSyncEnabled && (
                    <section className="rounded-2xl border border-warning/30 bg-warning/5 p-4 shadow-sm">
                        <div className="flex items-start gap-3 text-sm text-foreground">
                            <MaterialSymbol name="warning" className="mt-0.5 h-4 w-4 text-warning" />
                            <p>{t('pages.access_groups.users_sync_warning')}</p>
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
