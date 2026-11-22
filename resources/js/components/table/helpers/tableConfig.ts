import { normalizeOptions } from "./logic";
import type { ColumnConfig, CustomProperty, ModuleConfig } from "./types";
import { t } from "../../../lib/i18n";

/** Alla fält som behövs för listor, detaljer, formulär och export. */
export function tableDataSource(config: ModuleConfig): ModuleConfig["dataSource"] {
  const ds = config.dataSource;
  if (!ds) return undefined;
  const excluded = new Set(config.columns.filter((column) => column.apiField === false).map((column) => column.key));
  const fields = [
    config.titleKey,
    ...config.subtitleKeys,
    ...config.columns.map((column) => column.key),
    ...config.tableColumns,
    ...config.detailSections.filter((section) => !section.childConfig).map((section) => section.key),
  ].filter((field) => field && !excluded.has(field));
  return { ...ds, fields: Array.from(new Set(["id", ...fields, ...(ds.fields ?? [])])) };
}

export const colOptions = (column: ColumnConfig, draft?: Record<string, any>) =>
  normalizeOptions(column.optionsFor && draft ? column.optionsFor(draft) : column.optionsFor ? column.optionsFor({}) : column.options);

/** FK-38: anpassade egenskaper blir vanliga kolumner. */
export function customToColumns(props: CustomProperty[] = [], references: ModuleConfig["references"] = {}): ColumnConfig[] {
  return props.filter((property) => property.visible !== false).map((property) => ({
    key: property.key, label: property.label, required: property.required,
    form: property.editable === false ? "readonly-edit" : "both",
    type: property.type === "text" ? "text" : property.type === "textarea" ? "textarea" : "select",
    options: property.type === "boolean" ? [{ value: "true", label: t("Yes") }, { value: "false", label: t("No") }] : property.type === "text" || property.type === "textarea" ? undefined : references?.[property.type] ?? [],
    tab: t("Custom properties"),
  }));
}

/** FK-15: läs jtId[tableId] och jtFilter[tableId][filter] ur adressen. */
export function linkState(tableId?: string) {
  if (!tableId || typeof window === "undefined") return { id: null as string | null, filters: {} as Record<string, string[]> };
  const query = new URLSearchParams(window.location.search);
  const filters: Record<string, string[]> = {};
  const prefix = `jtFilter[${tableId}][`;
  query.forEach((value, key) => {
    if (key.startsWith(prefix) && key.endsWith("]")) {
      const filter = key.slice(prefix.length, -1);
      filters[filter] = [...(filters[filter] ?? []), ...value.split(",").filter(Boolean)];
    }
  });
  return { id: query.get(`jtId[${tableId}]`), filters };
}
