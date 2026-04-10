import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Workflow, SquareArrowOutUpRight } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import BpmnProcessViewer from '@/components/dashboard/BpmnProcessViewer';
import { APP_HOME_PATH, buildProcessEditorPath } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ProcessesPageProps {
    route: AppSectionRoute;
}

export default function ProcessesPage({ route }: ProcessesPageProps) {
    const { t } = useTranslations();

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.processes.title') });
        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const config: CrudModuleConfig = {
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
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.processes.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <Workflow className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.processes.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.processes.description')}
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

