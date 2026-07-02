import { useEffect, useMemo, useState } from 'react';
import { useTranslations } from '@/hooks/useTranslations';
import type { ProcessPerformanceMetricItem, ProcessPerformanceMetricReportItem } from '@/types/processPerformance';

interface ProcessPerformanceMetricChartProps {
    metric: ProcessPerformanceMetricItem;
    compact?: boolean;
}

export function ProcessPerformanceMetricChart({ metric, compact = false }: ProcessPerformanceMetricChartProps) {
    const { t } = useTranslations();
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

    const chartGeometry = useMemo(() => {
        const chartWidth = compact ? 240 : 1000;
        const chartHeight = compact ? 88 : 220;
        const padding = compact
            ? { top: 8, right: 8, bottom: 8, left: 8 }
            : { top: 16, right: 16, bottom: 34, left: 44 };

        const sortedRows = [...chartRows].sort((left, right) => left[0].getTime() - right[0].getTime());
        const xValues = sortedRows.map(([date]) => date.getTime());
        const yValues = sortedRows.map(([, value]) => value);

        const minX = xValues[0] ?? 0;
        const maxX = xValues[xValues.length - 1] ?? minX;
        const minYFromData = yValues.length > 0 ? Math.min(...yValues) : 0;
        const maxYFromData = yValues.length > 0 ? Math.max(...yValues) : 0;

        const minY =
            typeof metric.minvalue === 'number' ? Math.min(metric.minvalue, minYFromData) : minYFromData;
        const maxY =
            typeof metric.maxvalue === 'number' ? Math.max(metric.maxvalue, maxYFromData) : maxYFromData;

        const xRange = maxX - minX || 1;
        const yRange = maxY - minY || 1;
        const plotWidth = chartWidth - padding.left - padding.right;
        const plotHeight = chartHeight - padding.top - padding.bottom;

        const points = sortedRows.map(([date, value]) => {
            const x = padding.left + ((date.getTime() - minX) / xRange) * plotWidth;
            const y = padding.top + (1 - (value - minY) / yRange) * plotHeight;

            return { date, value, x, y };
        });

        const path = points
            .map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
            .join(' ');

        return {
            chartWidth,
            chartHeight,
            minX,
            maxX,
            minY,
            maxY,
            points,
            path,
            yTicks: [0, 0.25, 0.5, 0.75, 1].map((ratio) => padding.top + ratio * plotHeight),
        };
    }, [chartRows, compact, metric.maxvalue, metric.minvalue]);

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

    const numberFormatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 });
    const dateFormatter = new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    const axisLabelClassName = 'fill-muted-foreground text-[10px]';

    return (
        <svg
            viewBox={`0 0 ${chartGeometry.chartWidth} ${chartGeometry.chartHeight}`}
            className={compact ? 'h-[88px] w-[240px] min-w-[240px]' : 'h-[220px] w-full'}
            role="img"
            aria-label={metric.name}
        >
            {chartGeometry.yTicks.map((tickY) => (
                <line key={tickY} x1={0} y1={tickY} x2={chartGeometry.chartWidth} y2={tickY} stroke="#e5e7eb" strokeWidth={1} />
            ))}

            <path d={chartGeometry.path} fill="none" stroke="#2563eb" strokeWidth={2} />

            {chartGeometry.points.map((point) => (
                <circle key={`${point.date.toISOString()}-${point.value}`} cx={point.x} cy={point.y} r={chartGeometry.points.length === 1 ? 4 : 2.5} fill="#2563eb">
                    <title>{`${dateFormatter.format(point.date)}: ${numberFormatter.format(point.value)}${metric.unit ? ` ${metric.unit}` : ''}`}</title>
                </circle>
            ))}

            {!compact && (
                <>
                    <text x={0} y={chartGeometry.chartHeight - 6} className={axisLabelClassName}>
                        {dateFormatter.format(new Date(chartGeometry.minX))}
                    </text>
                    <text x={chartGeometry.chartWidth} y={chartGeometry.chartHeight - 6} textAnchor="end" className={axisLabelClassName}>
                        {dateFormatter.format(new Date(chartGeometry.maxX))}
                    </text>
                    <text x={2} y={12} className={axisLabelClassName}>
                        {`${numberFormatter.format(chartGeometry.maxY)}${metric.unit ? ` ${metric.unit}` : ''}`}
                    </text>
                    <text x={2} y={chartGeometry.chartHeight - 38} className={axisLabelClassName}>
                        {`${numberFormatter.format(chartGeometry.minY)}${metric.unit ? ` ${metric.unit}` : ''}`}
                    </text>
                </>
            )}
        </svg>
    );
}

