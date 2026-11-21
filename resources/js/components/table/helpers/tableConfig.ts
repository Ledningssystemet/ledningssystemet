import { normalizeOptions } from "../logic";
import type { ColumnConfig, CustomProperty, ModuleConfig } from "../types";

export const colOptions = (column: ColumnConfig, draft?: Record<string, any>) =>
  normalizeOptions(column.optionsFor && draft ? column.optionsFor(draft) : column.optionsFor ? column.optionsFor({}) : column.options);

/** FK-38: anpassade egenskaper blir vanliga kolumner. */
export function customToColumns(props: CustomProperty[] = [], references: ModuleConfig["references"] = {}): ColumnConfig[] {
  return props.filter((property) => property.visible !== false).map((property) => ({
    key: property.key, label: property.label, required: property.required,
    form: property.editable === false ? "readonly-edit" : "both",
    type: property.type === "text" ? "text" : property.type === "textarea" ? "textarea" : "select",
    options: property.type === "boolean" ? [{ value: "true", label: "Ja" }, { value: "false", label: "Nej" }] : property.type === "text" || property.type === "textarea" ? undefined : references?.[property.type] ?? [],
    tab: "Anpassade egenskaper",
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
