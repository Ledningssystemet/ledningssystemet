export interface SustainabilityMetricLevelOption {
    id: number;
    name: string;
    multiplier: number;
    description?: string | null;
}

export interface SustainabilityMetricSelection {
    sustainability_metric_level_id: number;
    id: number;
    name: string;
    multiplier: number;
    description?: string | null;
}

export interface SustainabilityMetricItem {
    id: number;
    name: string;
    description?: string | null;
    level: SustainabilityMetricSelection | null;
    levels: SustainabilityMetricLevelOption[];
}

export interface ProcessSustainabilityAspectItem {
    id: number;
    name: string;
    description?: string | null;
    process_name?: string;
    sustainability_aspect_name?: string;
    sustainability_metrics?: SustainabilityMetricItem[];
}

