import { useMemo, useState, useEffect } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import { ProcessPerformanceMetricChart } from '@/Components/process-performance/ProcessPerformanceMetricChart';
import { buildProcessPerformanceReportsCrudConfig } from '@/Pages/app/processPerformanceReportsCrudConfig';
import type { ProcessPerformanceMetricItem } from '@/types/processPerformance';
import type { AppSectionRoute } from '@/app/routes';

interface SharedProps extends PageProps {
    auth?: {
        user?: {
            id: number;
        } | null;
    };
}

interface SelectOption {
    id: number;
    name: string;
}

interface ProcessPerformancePageProps {
    route: AppSectionRoute;
}

export default function ProcessPerformancePage({ route }: ProcessPerformancePageProps) {
    const { t } = useTranslations();
    const page = usePage<SharedProps>();
    const currentUserId = page.props.auth?.user?.id ?? null;

    const [metricsRenderKey, setMetricsRenderKey] = useState(0);
    const [tagOptions, setTagOptions] = useState<SelectOption[]>([]);
    const [processOptions, setProcessOptions] = useState<SelectOption[]>([]);
    const [activeMetricForReports, setActiveMetricForReports] = useState<ProcessPerformanceMetricItem | null>(null);
    const [activeTagId, setActiveTagId] = useState('');
    const [activeProcessId, setActiveProcessId] = useState('');
    const [showMyOnly, setShowMyOnly] = useState(false);

    useEffect(() => {
        const controller = new AbortController();

        const loadOptions = async () => {
            const [tagsResponse, processesResponse] = await Promise.all([
                fetch('/api/crud/tags?paginate=0&%24select=id,name&sort=name', {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                }),
                fetch('/api/crud/processes?paginate=0&%24select=id,name&sort=name', {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                }),
            ]);

            if (tagsResponse.ok) {
                const tagsJson = await tagsResponse.json();
                const tagsRows = Array.isArray(tagsJson) ? tagsJson : tagsJson.data || [];
                setTagOptions(tagsRows.map((row: Record<string, any>) => ({ id: Number(row.id), name: String(row.name || '') })));
            }

            if (processesResponse.ok) {
                const processesJson = await processesResponse.json();
                const processRows = Array.isArray(processesJson) ? processesJson : processesJson.data || [];
                setProcessOptions(processRows.map((row: Record<string, any>) => ({ id: Number(row.id), name: String(row.name || '') })));
            }
        };

        void loadOptions().catch(() => undefined);

        return () => controller.abort();
    }, []);

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/process_performance_metrics',
            perPage: 25,
            defaultSort: 'name',
            selectFields: [
                'id',
                'name',
                'description',
                'responsible_user_id',
                'quantitative',
                'biggerisbetter',
                'unit',
                'increment',
                'minvalue',
                'maxvalue',
                'precision',
                'postprocessing',
                'alarm_threshold',
            ],
            createDefaults: currentUserId ? { responsible_user_id: currentUserId } : undefined,
            createTitle: t('pages.process_performance.create_title'),
            editTitle: t('pages.process_performance.edit_title'),
            customQueryParams: () => ({
                tag_id: activeTagId || undefined,
                process_id: activeProcessId || undefined,
                show_my_only: showMyOnly ? '1' : undefined,
            }),
            rowActions: [
                {
                    key: 'reports',
                    label: t('pages.process_performance.reports.open_button'),
                    icon: <MaterialSymbol name="bar_chart" className="h-4 w-4" />,
                    variant: 'outline',
                    refreshOnComplete: false,
                    onClick: (item) => {
                        setActiveMetricForReports(item as ProcessPerformanceMetricItem);
                    },
                },
            ],
            fields: [
                {
                    key: 'name',
                    label: t('pages.process_performance.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.process_performance.category_general'),
                },
                {
                    key: 'graph',
                    label: t('pages.process_performance.column_graph'),
                    type: 'text',
                    editable: false,
                    sortable: false,
                    width: '260px',
                    renderCell: (_value, row) => <ProcessPerformanceMetricChart metric={row as ProcessPerformanceMetricItem} compact />,
                    renderDetail: (_value, row) => <ProcessPerformanceMetricChart metric={row as ProcessPerformanceMetricItem} />,
                    category: t('pages.process_performance.category_reporting'),
                },
                {
                    key: 'tags',
                    label: t('pages.process_performance.column_tags'),
                    type: 'inline-tags',
                    editable: true,
                    sortable: false,
                    tags: true,
                    optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                    createOptionUrl: '/api/crud/tags',
                    optionValueKey: 'name',
                    optionLabelKey: 'name',
                    createOptionPayloadKey: 'name',
                    category: t('pages.process_performance.category_general'),
                },
                {
                    key: 'metric_type',
                    label: t('pages.process_performance.column_metric_type'),
                    type: 'select',
                    sortable: false,
                    editable: true,
                    editableOnUpdate: false,
                    required: true,
                    options: [
                        { value: 1, label: t('pages.process_performance.metric_type_higher_is_better') },
                        { value: 2, label: t('pages.process_performance.metric_type_lower_is_better') },
                        { value: 3, label: t('pages.process_performance.metric_type_non_quantitative') },
                    ],
                    helpText: t('pages.process_performance.metric_type_help'),
                    category: t('pages.process_performance.category_general'),
                },
                {
                    key: 'responsible_user_id',
                    label: t('pages.process_performance.column_responsible_user'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    filterable: true,
                    optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.process_performance.none_assigned'),
                    category: t('pages.process_performance.category_general'),
                },
                {
                    key: 'process_ids',
                    label: t('pages.process_performance.column_processes'),
                    type: 'multiselect',
                    sortable: false,
                    editable: true,
                    optionsUrl: '/api/crud/processes?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.process_performance.process_placeholder'),
                    category: t('pages.process_performance.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.process_performance.column_description'),
                    type: 'textarea',
                    editable: true,
                    masterDescription: true,
                    category: t('pages.process_performance.category_general'),
                },
                {
                    key: 'unit',
                    label: t('pages.process_performance.column_unit'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    category: t('pages.process_performance.category_measurement'),
                },
                {
                    key: 'precision',
                    label: t('pages.process_performance.column_precision'),
                    type: 'number',
                    sortable: true,
                    editable: true,
                    category: t('pages.process_performance.category_measurement'),
                },
                {
                    key: 'minvalue',
                    label: t('pages.process_performance.column_minvalue'),
                    type: 'number',
                    sortable: true,
                    editable: true,
                    category: t('pages.process_performance.category_measurement'),
                },
                {
                    key: 'maxvalue',
                    label: t('pages.process_performance.column_maxvalue'),
                    type: 'number',
                    sortable: true,
                    editable: true,
                    category: t('pages.process_performance.category_measurement'),
                },
                {
                    key: 'alarm_threshold',
                    label: t('pages.process_performance.column_alarm_threshold'),
                    type: 'number',
                    sortable: true,
                    editable: true,
                    category: t('pages.process_performance.category_measurement'),
                },
                {
                    key: 'postprocessing',
                    label: t('pages.process_performance.column_postprocessing'),
                    type: 'textarea',
                    sortable: false,
                    editable: true,
                    hiddenInTable: true,
                    category: t('pages.process_performance.category_reporting'),
                },
            ],
        }),
        [activeProcessId, activeTagId, currentUserId, showMyOnly, t]
    );

    const reportsConfig = useMemo(() => {
        if (!activeMetricForReports) {
            return null;
        }

        return buildProcessPerformanceReportsCrudConfig(t, activeMetricForReports);
    }, [activeMetricForReports, t]);

    const closeReportsDialog = () => {
        setActiveMetricForReports(null);
        setMetricsRenderKey((previous) => previous + 1);
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.process_performance.title')}
                    description={t('pages.process_performance.description')}
                    icon={<MaterialSymbol name="monitoring" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-4">
                        <div className="space-y-2">
                            <Label htmlFor="process-performance-tag-filter">{t('pages.process_performance.filter_tag')}</Label>
                            <select
                                id="process-performance-tag-filter"
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={activeTagId}
                                onChange={(event) => setActiveTagId(event.target.value)}
                            >
                                <option value="">{t('pages.process_performance.show_all')}</option>
                                {tagOptions.map((option) => (
                                    <option key={`tag-${option.id}`} value={String(option.id)}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="process-performance-process-filter">{t('pages.process_performance.filter_process')}</Label>
                            <select
                                id="process-performance-process-filter"
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={activeProcessId}
                                onChange={(event) => setActiveProcessId(event.target.value)}
                            >
                                <option value="">{t('pages.process_performance.show_all')}</option>
                                {processOptions.map((option) => (
                                    <option key={`process-${option.id}`} value={String(option.id)}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex items-end">
                            <label className="flex h-10 items-center gap-2 text-sm text-foreground">
                                <input
                                    type="checkbox"
                                    checked={showMyOnly}
                                    onChange={(event) => setShowMyOnly(event.target.checked)}
                                />
                                <span>{t('pages.process_performance.filter_show_my_only')}</span>
                            </label>
                        </div>

                        <div className="flex items-end justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setActiveTagId('');
                                    setActiveProcessId('');
                                    setShowMyOnly(false);
                                }}
                            >
                                {t('pages.process_performance.reset_filters')}
                            </Button>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule key={`process-performance-${metricsRenderKey}-${activeTagId}-${activeProcessId}-${showMyOnly ? 'mine' : 'all'}`} config={config} />
                </section>
            </div>

            {reportsConfig && activeMetricForReports && (
                <Dialog open={Boolean(activeMetricForReports)} onOpenChange={(open) => !open && closeReportsDialog()}>
                    <DialogContent className="max-w-5xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <MaterialSymbol name="bar_chart" className="h-5 w-5" />
                                {t('pages.process_performance.reports.panel_title', {
                                    metric: String(activeMetricForReports.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('pages.process_performance.reports.panel_description')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="rounded-xl border border-border bg-muted/20 p-4">
                                <div className="mb-3 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                    <span>
                                        {t('pages.process_performance.reports.metric_type_label')}: {' '}
                                        {activeMetricForReports.metric_type === 1
                                            ? t('pages.process_performance.metric_type_higher_is_better')
                                            : activeMetricForReports.metric_type === 2
                                              ? t('pages.process_performance.metric_type_lower_is_better')
                                              : t('pages.process_performance.metric_type_non_quantitative')}
                                    </span>
                                    {activeMetricForReports.unit ? (
                                        <span>
                                            {t('pages.process_performance.reports.unit_label')}: {activeMetricForReports.unit}
                                        </span>
                                    ) : null}
                                    <span>
                                        {t('pages.process_performance.reports.report_count_label')}: {activeMetricForReports.reportcount ?? 0}
                                    </span>
                                </div>
                                <ProcessPerformanceMetricChart metric={activeMetricForReports} />
                            </div>

                            <div className="flex justify-end">
                                <Button type="button" variant="outline" size="sm" onClick={closeReportsDialog}>
                                    {t('pages.process_performance.reports.close_panel_button')}
                                </Button>
                            </div>

                            <CrudModule key={`process-performance-reports-${activeMetricForReports.id}`} config={reportsConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
