import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Workflow } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ActivitiesPageProps {
    route: AppSectionRoute;
}

export default function ActivitiesPage({ route }: ActivitiesPageProps) {
    const { t } = useTranslations();

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.activities.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

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
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.activities.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <Workflow className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.activities.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.activities.description')}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
