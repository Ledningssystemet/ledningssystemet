import {useCallback, useState} from "react";
import {CrudModuleConfig, CrudState, FieldConfig, SelectOption} from "./types";
import {resolveOptions, useAllSelectOptions} from "./optionsCache";

/** Fields eligible for CSV export: all fields that are not completely hidden */
function getExportFields(fields: FieldConfig[]): FieldConfig[] {
    return fields.filter((f) => !f.hidden);
}

function escapeCsvValue(value: string): string {
    return `"${value.replace(/"/g, '""')}"`;
}

function resolveLabel(
    value: any,
    field: FieldConfig,
    optionsCache: Map<string, SelectOption[]>,
): string {
    if (value === null || value === undefined) return "";

    if (field.type === "boolean") {
        return value ? "1" : "0";
    }

    if (
        (field.type === "multiselect" || field.type === "tags" || field.type === "inline-tags") &&
        Array.isArray(value)
    ) {
        const opts = resolveOptions(field, optionsCache);
        return value
            .map((v: any) => {
                const opt = opts.find((o) => String(o.value) === String(v));
                return opt?.label ?? String(v);
            })
            .join(", ");
    }

    if (field.type === "select") {
        const opts = resolveOptions(field, optionsCache);
        const opt = opts.find((o) => String(o.value) === String(value));
        return opt?.label ?? String(value);
    }

    return String(value);
}

function buildCsvContent(
    items: Record<string, any>[],
    exportFields: FieldConfig[],
    optionsCache: Map<string, SelectOption[]>,
): string {
    // UTF-8 BOM for Excel compatibility
    const BOM = "\uFEFF";
    const header = exportFields.map((f) => escapeCsvValue(f.label)).join(";");
    const rows = items.map((item) =>
        exportFields
            .map((f) => escapeCsvValue(resolveLabel(item[f.key], f, optionsCache)))
            .join(";")
    );
    return BOM + [header, ...rows].join("\r\n");
}

function toNumber(value: unknown): number | null {
    return typeof value === "number" && Number.isFinite(value) ? value : null;
}

function downloadCsv(content: string, filename: string) {
    const blob = new Blob([content], {type: "text/csv;charset=utf-8;"});
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

interface UseCsvExportOptions {
    config: CrudModuleConfig;
    state: Pick<CrudState, "items" | "selectedItems" | "search" | "filters" | "sort" | "sortDirection">;
    /** Function from useCrudModule that builds the API query string without pagination */
    buildExportQueryString: () => string;
    title?: string;
}

export function useCsvExport({
                                 config,
                                 state,
                                 buildExportQueryString,
                                 title,
                             }: UseCsvExportOptions) {
    const [exporting, setExporting] = useState(false);
    const optionsCache = useAllSelectOptions(config.fields);
    const exportFields = getExportFields(config.fields);
    const filename = `${(title ?? "export").replace(/[^a-z0-9_\-]/gi, "_")}.csv`;
    const primaryKey = config.primaryKey ?? "id";

    const exportSelected = useCallback(() => {
        const items = state.items.filter((item) =>
            state.selectedItems.has(item[primaryKey])
        );
        const csv = buildCsvContent(items, exportFields, optionsCache);
        downloadCsv(csv, filename);
    }, [state.items, state.selectedItems, exportFields, optionsCache, filename, primaryKey]);

    const exportAll = useCallback(async () => {
        setExporting(true);
        try {
            const baseParams = new URLSearchParams(buildExportQueryString());
            const params = new URLSearchParams(baseParams.toString());
            params.delete("page");
            params.delete("per_page");
            params.delete("paginate");
            const response = await fetch(`${config.apiUrl}?${params.toString()}`, {
                headers: {Accept: "application/json"},
            });

            if (!response.ok) throw new Error("Export failed");

            const payload = await response.json();
            const csv = buildCsvContent(payload, exportFields, optionsCache);
            downloadCsv(csv, filename);
        } finally {
            setExporting(false);
        }
    }, [buildExportQueryString, config.apiUrl, exportFields, optionsCache, filename]);

    return {exportSelected, exportAll, exporting};
}

