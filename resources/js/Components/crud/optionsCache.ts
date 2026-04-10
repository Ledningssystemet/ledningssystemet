import { useState, useEffect } from "react";
import { FieldConfig, SelectOption } from "./types";

// Module-level cache so options are only fetched once per URL+keys combination
const cache = new Map<string, Promise<SelectOption[]>>();

function fetchOptionsFromUrl(
    url: string,
    valueKey: string,
    labelKey: string,
): Promise<SelectOption[]> {
    const cacheKey = `${url}::${valueKey}::${labelKey}`;
    if (cache.has(cacheKey)) return cache.get(cacheKey)!;

    const promise = fetch(url, { headers: { Accept: "application/json" } })
        .then((res) => (res.ok ? res.json() : []))
        .then((json: unknown) => {
            const rows = Array.isArray(json)
                ? json
                : Array.isArray((json as any)?.data)
                  ? (json as any).data
                  : [];

            return (rows as Record<string, any>[])
                .map((row) => {
                    const value = row[valueKey];
                    if (value === undefined || value === null) return null;
                    const label = row[labelKey] ?? row.name ?? row.label ?? value;
                    return { value, label: String(label) } as SelectOption;
                })
                .filter((item): item is SelectOption => item !== null);
        })
        .catch(() => [] as SelectOption[]);

    cache.set(cacheKey, promise);
    return promise;
}

/**
 * Fetches and caches options for all select/multiselect/tags fields that use optionsUrl.
 * Returns a Map from fieldKey → SelectOption[].
 */
export function useAllSelectOptions(fields: FieldConfig[]): Map<string, SelectOption[]> {
    const [optionsMap, setOptionsMap] = useState<Map<string, SelectOption[]>>(new Map());

    useEffect(() => {
        const selectFields = fields.filter(
            (f) =>
                (f.type === "select" ||
                    f.type === "multiselect" ||
                    f.type === "tags" ||
                    f.type === "inline-tags") &&
                f.optionsUrl,
        );

        if (selectFields.length === 0) return;

        Promise.all(
            selectFields.map(async (field) => {
                const options = await fetchOptionsFromUrl(
                    field.optionsUrl!,
                    field.optionValueKey ?? "id",
                    field.optionLabelKey ?? "name",
                );
                return [field.key, options] as [string, SelectOption[]];
            }),
        ).then((entries) => {
            setOptionsMap(new Map(entries));
        });
    }, [fields]);

    return optionsMap;
}

/**
 * Resolves the SelectOption[] for a field, preferring field.options and
 * falling back to the pre-fetched remote options from optionsMap.
 */
export function resolveOptions(
    field: FieldConfig,
    optionsMap: Map<string, SelectOption[]>,
): SelectOption[] {
    if (field.options && field.options.length > 0) return field.options;
    return optionsMap.get(field.key) ?? [];
}

