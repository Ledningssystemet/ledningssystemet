import type { CrudModuleConfig } from '@/Components/crud';
import type { ProcessPerformanceMetricItem } from '@/types/processPerformance';

type TranslateFn = (key: string, replacements?: Record<string, string | number>) => string;

export function buildProcessPerformanceReportsCrudConfig(
    t: TranslateFn,
    metric: ProcessPerformanceMetricItem
): CrudModuleConfig {
    const isQualitative = metric.metric_type === 3;

    return {
        apiUrl: '/api/crud/process_performance_metric_reports',
        perPage: 25,
        defaultSort: '-reporting_date_at',
        fixedFilters: { process_performance_metric_id: metric.id },
        createDefaults: {
            process_performance_metric_id: metric.id,
            reporting_date_at: new Date().toISOString().slice(0, 10),
        },
        selectFields: [
            'id',
            'process_performance_metric_id',
            'value',
            'reportedprecision',
            'reporting_date_at',
            'comment',
            'reported_by_id',
        ],
        createTitle: t('pages.process_performance.reports.create_title'),
        editTitle: t('pages.process_performance.reports.edit_title'),
        canEdit: false,
        fields: [
            {
                key: 'process_performance_metric_id',
                label: t('pages.process_performance.reports.column_metric'),
                type: 'select',
                editable: false,
                hidden: true,
                options: [{ value: metric.id, label: metric.name }],
            },
            {
                key: 'reporting_date_at',
                label: t('pages.process_performance.reports.column_reporting_date'),
                type: 'date',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.process_performance.reports.category_general'),
            },
            ...(!isQualitative
                ? [
                      {
                          key: 'reportvalue',
                          label: t('pages.process_performance.reports.column_reportvalue'),
                          type: 'number' as const,
                          sortable: false,
                          editable: true,
                          required: true,
                          renderCell: (value: unknown) => formatMetricValue(value, metric.unit),
                          renderDetail: (value: unknown) => formatMetricValue(value, metric.unit),
                          category: t('pages.process_performance.reports.category_measurement'),
                      },
                      {
                          key: 'calculatedvalue',
                          label: t('pages.process_performance.reports.column_calculatedvalue'),
                          type: 'number' as const,
                          sortable: false,
                          editable: false,
                          renderCell: (value: unknown) => formatMetricValue(value, metric.unit),
                          renderDetail: (value: unknown) => formatMetricValue(value, metric.unit),
                          category: t('pages.process_performance.reports.category_measurement'),
                      },
                  ]
                : []),
            {
                key: 'comment',
                label: t('pages.process_performance.reports.column_comment'),
                type: 'textarea',
                sortable: false,
                editable: true,
                masterDescription: true,
                category: t('pages.process_performance.reports.category_general'),
            },
            {
                key: 'reported_by_name',
                label: t('pages.process_performance.reports.column_reported_by'),
                type: 'text',
                sortable: false,
                editable: false,
                category: t('pages.process_performance.reports.category_metadata'),
            },
        ],
    };
}

function formatMetricValue(value: unknown, unit?: string | null): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const numericValue = typeof value === 'number' ? value : Number(value);
    const renderedValue = Number.isFinite(numericValue) ? String(numericValue) : String(value);

    return unit ? `${renderedValue} ${unit}` : renderedValue;
}

