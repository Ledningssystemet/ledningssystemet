import { Workflow } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ActivitiesPageProps {
    route: AppSectionRoute;
}

export default function ActivitiesPage({ route }: ActivitiesPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = {
        apiUrl: '/api/crud/activities',
        perPage: 25,
        defaultSort: 'due',
        selectFields: [
            'id',
            'name',
            'description',
            'due',
            'intervalnum',
            'intervaltype',
            'completed_at',
            'responsible_user_id',
            'activity_flow_id',
            'activity_flow_template_item_id',
        ],
        createTitle: t('pages.activities.create_title'),
        editTitle: t('pages.activities.edit_title'),
        fields: [
            {
                key: 'name',
                label: t('pages.activities.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.activities.category_general'),
            },
            {
                key: 'description',
                label: t('pages.activities.column_description'),
                type: 'textarea',
                editable: true,
                required: true,
                masterDescription: true,
                hiddenInTable: true,
                category: t('pages.activities.category_general'),
            },
            {
                key: 'due',
                label: t('pages.activities.column_due'),
                type: 'date',
                sortable: true,
                editable: true,
                required: true,
                category: t('pages.activities.category_schedule'),
            },
            {
                key: 'intervalnum',
                label: t('pages.activities.column_interval_number'),
                type: 'number',
                sortable: true,
                editable: true,
                required: true,
                category: t('pages.activities.category_schedule'),
            },
            {
                key: 'intervaltype',
                label: t('pages.activities.column_interval_type'),
                type: 'text',
                sortable: true,
                editable: true,
                placeholder: t('pages.activities.interval_type_placeholder'),
                category: t('pages.activities.category_schedule'),
            },
            {
                key: 'completed_at',
                label: t('pages.activities.column_completed_at'),
                type: 'date',
                sortable: true,
                editable: true,
                category: t('pages.activities.category_status'),
            },
            {
                key: 'responsible_user_id',
                label: t('pages.activities.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.activities.none_option'),
                category: t('pages.activities.category_relations'),
            },
            {
                key: 'activity_flow_id',
                label: t('pages.activities.column_activity_flow'),
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/activity-flows?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.activities.none_option'),
                category: t('pages.activities.category_relations'),
            },
            {
                key: 'activity_flow_template_item_id',
                label: t('pages.activities.column_activity_flow_template_item'),
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/activity-flow-template-items?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.activities.none_option'),
                category: t('pages.activities.category_relations'),
            },
        ],
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.activities.title')}
                    description={t('pages.activities.description')}
                    icon={<Workflow className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
