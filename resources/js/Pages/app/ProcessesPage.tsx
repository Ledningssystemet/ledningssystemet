import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Workflow, SquareArrowOutUpRight } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import BpmnProcessViewer from '@/components/dashboard/BpmnProcessViewer';
import { buildProcessEditorPath } from '@/app/routes';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ProcessesPageProps {
    route: AppSectionRoute;
}

export default function ProcessesPage({ route }: ProcessesPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/processes',
        perPage: 15,
        defaultSort: 'name',
        selectFields: [
            'id',
            'name',
            'description',
            'department_id',
            'responsible_user_id',
            'isstartprocess',
            'dataprocessor',
            'publishedbpmn',
            'updated_at',
        ],
        fields: [
            {
                key: 'name',
                label: t('pages.processes.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
            },
            {
                key: 'description',
                label: t('pages.processes.column_description'),
                type: 'textarea',
                editable: true,
                masterDescription: true,
                hiddenInTable: true,
            },
            {
                key: 'department_id',
                label: t('pages.processes.column_department'),
                type: 'select',
                sortable: true,
                editable: true,
                required: true,
                optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
            },
            {
                key: 'responsible_user_id',
                label: t('pages.processes.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
            },
            {
                key: 'isstartprocess',
                label: t('pages.processes.column_is_start_process'),
                type: 'boolean',
                sortable: true,
                editable: true,
                required: true,
            },
            {
                key: 'dataprocessor',
                label: t('pages.processes.column_data_processor'),
                type: 'boolean',
                sortable: true,
                editable: true,
                required: true,
            },
            {
                key: 'publishedbpmn',
                label: t('pages.processes.column_published_map_preview'),
                type: 'textarea',
                editable: false,
                sortable: false,
                hiddenInTable: true,
                renderDetail: (value) => (
                    <div className="space-y-2">
                        <div className="text-xs text-muted-foreground">{t('pages.processes.preview_hint')}</div>
                        <BpmnProcessViewer
                            xml={typeof value === 'string' ? value : null}
                            emptyMessage={t('pages.dashboard.process.no_published_bpmn')}
                            invalidMessage={t('pages.dashboard.process.invalid_bpmn')}
                            fitButtonLabel={t('pages.dashboard.process.fit_to_screen')}
                            className="h-56 min-h-[14rem]"
                        />
                    </div>
                ),
            },
            {
                key: 'editor',
                label: t('pages.processes.column_editor'),
                type: 'text',
                editable: false,
                sortable: false,
                renderCell: (_, row) => (
                    <Link
                        to={buildProcessEditorPath(row.id)}
                        className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                    >
                        <SquareArrowOutUpRight className="h-3.5 w-3.5" />
                        {t('pages.processes.open_editor')}
                    </Link>
                ),
                renderDetail: (_, row) => (
                    <Link
                        to={buildProcessEditorPath(row.id)}
                        className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                    >
                        <SquareArrowOutUpRight className="h-3.5 w-3.5" />
                        {t('pages.processes.open_editor')}
                    </Link>
                ),
            },
        ],
    }), [t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.processes.title')}
                    description={t('pages.processes.description')}
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

