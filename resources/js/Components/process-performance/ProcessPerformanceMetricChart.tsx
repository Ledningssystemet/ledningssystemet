import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslations } from '@/hooks/useTranslations';
import { useGoogleCharts } from '@/hooks/useGoogleCharts';
import type { ProcessPerformanceMetricItem, ProcessPerformanceMetricReportItem } from '@/types/processPerformance';

interface ProcessPerformanceMetricChartProps {
    metric: ProcessPerformanceMetricItem;
    compact?: boolean;
}

export function ProcessPerformanceMetricChart({ metric, compact = false }: ProcessPerformanceMetricChartProps) {
    const { t } = useTranslations();
    const chartsLoaded = useGoogleCharts();
    const containerRef = useRef<HTMLDivElement | null>(null);
    const [reports, setReports] = useState<ProcessPerformanceMetricReportItem[]>([]);
    const [loading, setLoading] = useState(metric.metric_type !== 3);
    const [error, setError] = useState(false);

    useEffect(() => {
        if (metric.metric_type === 3) {
            setReports([]);
            setLoading(false);
            setError(false);
            return;
        }

        const controller = new AbortController();
        setLoading(true);
        setError(false);

        fetch(
            `/api/crud/process_performance_metric_reports?paginate=0&sort=reporting_date_at&filter[process_performance_metric_id]=${metric.id}&%24select=id,process_performance_metric_id,value,reportedprecision,reporting_date_at`,
            {
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                },
            }
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Could not load metric reports.');
                }

                return response.json();
            })
            .then((json: ProcessPerformanceMetricReportItem[] | { data?: ProcessPerformanceMetricReportItem[] }) => {
                const nextReports = Array.isArray(json) ? json : json.data || [];
                setReports(nextReports);
                setLoading(false);
            })
            .catch((fetchError: unknown) => {
                if ((fetchError as { name?: string })?.name === 'AbortError') {
                    return;
                }

                setError(true);
                setLoading(false);
            });

        return () => controller.abort();
    }, [metric.id, metric.metric_type]);

    const chartRows = useMemo(() => {
        return reports
            .filter((report) => typeof report.calculatedvalue === 'number' || typeof report.reportvalue === 'number')
            .map((report) => [
                new Date(report.reporting_date_at),
                Number(report.calculatedvalue ?? report.reportvalue ?? 0),
            ]) as Array<[Date, number]>;
    }, [reports]);

    useEffect(() => {
        if (!chartsLoaded || !containerRef.current || chartRows.length === 0 || metric.metric_type === 3) {
            return;
        }

        const google = window.google;
        if (!google?.visualization?.DataTable || !google.visualization.LineChart) {
            return;
        }

        const dataTable = new google.visualization.DataTable();
        dataTable.addColumn('date', t('pages.process_performance.chart_axis_date'));
        dataTable.addColumn('number', metric.name);
        dataTable.addRows(chartRows);

        const chart = new google.visualization.LineChart(containerRef.current);
        chart.draw(dataTable, {
            backgroundColor: 'transparent',
            chartArea: {
                left: compact ? 8 : 36,
                top: compact ? 8 : 16,
                width: compact ? '96%' : '88%',
                height: compact ? '78%' : '72%',
            },
            colors: ['#2563eb'],
            curveType: 'function',
            fontName: 'Inter',
            hAxis: {
                textPosition: compact ? 'none' : 'out',
                gridlines: { color: '#e5e7eb' },
                baselineColor: '#d1d5db',
            },
            legend: { position: 'none' },
            lineWidth: 2,
            pointSize: chartRows.length === 1 ? 4 : 2,
            tooltip: { trigger: 'focus' },
            vAxis: {
                format: metric.unit ? `#,##0.## ${metric.unit}` : '#,##0.##',
                gridlines: { color: '#e5e7eb' },
                baselineColor: '#d1d5db',
                textPosition: compact ? 'none' : 'out',
                viewWindow:
                    typeof metric.minvalue === 'number' || typeof metric.maxvalue === 'number'
                        ? {
                              min: typeof metric.minvalue === 'number' ? metric.minvalue : undefined,
                              max: typeof metric.maxvalue === 'number' ? metric.maxvalue : undefined,
                          }
                        : undefined,
            },
            width: '100%',
            height: compact ? 88 : 220,
        });
    }, [chartRows, chartsLoaded, compact, metric.maxvalue, metric.metric_type, metric.minvalue, metric.name, metric.unit, t]);

    if (metric.metric_type === 3) {
        return <span className="text-xs text-muted-foreground">{t('pages.process_performance.chart_not_available')}</span>;
    }

    if (loading) {
        return <span className="text-xs text-muted-foreground">{t('pages.process_performance.chart_loading')}</span>;
    }

    if (error) {
        return <span className="text-xs text-destructive">{t('pages.process_performance.chart_load_error')}</span>;
    }

    if (chartRows.length === 0) {
        return <span className="text-xs text-muted-foreground">{t('pages.process_performance.chart_empty')}</span>;
    }

    return <div ref={containerRef} className={compact ? 'h-[88px] w-[240px] min-w-[240px]' : 'h-[220px] w-full'} />;
}

