export interface ProcessPerformanceMetricItem {
    id: number;
    name: string;
    description?: string | null;
    responsible_user_id?: number | null;
    metric_type: number;
    process_ids: number[];
    tags: string[];
    unit?: string | null;
    minvalue?: number | null;
    maxvalue?: number | null;
    precision?: number | null;
    reportcount?: number;
}

export interface ProcessPerformanceMetricReportItem {
    id: number;
    process_performance_metric_id: number;
    reporting_date_at: string;
    reportvalue?: number | null;
    calculatedvalue?: number | null;
    comment?: string | null;
    reported_by_name?: string | null;
}

declare global {
    interface Window {
        google?: {
            charts?: {
                load: (version: string, settings: { packages: string[] }) => void;
                setOnLoadCallback: (callback: () => void) => void;
            };
            visualization?: {
                DataTable: new () => {
                    addColumn: (type: string, label: string) => void;
                    addRows: (rows: Array<[Date, number]>) => void;
                };
                LineChart: new (element: Element) => {
                    draw: (data: unknown, options: Record<string, unknown>) => void;
                };
            };
        };
    }
}

export {};

