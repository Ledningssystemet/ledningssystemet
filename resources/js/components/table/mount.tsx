import { createRoot } from "react-dom/client";
import React, { createRef } from "react";
import { TableModule } from "./TableModule";
import type { ModuleConfig, ModuleController, ViewMode } from "./helpers/types";
import { translate } from "./helpers/translate";

const registry = new Map<string, ModuleConfig>();
/** Registrera en grundkonfiguration (med funktioner som render/optionsFor) som data-attribut kan peka på. */
export function registerConfig(name: string, config: ModuleConfig) { registry.set(name, config); }

const list = (v?: string) => (v ? v.split(",").map((s) => s.trim()).filter(Boolean) : undefined);
const params = (v?: string): Record<string, string> | undefined => {
  if (!v) return undefined;
  try { return JSON.parse(v); } catch {
    const entries: [string, string][] = [];
    new URLSearchParams(v).forEach((value, key) => entries.push([key, value]));
    return Object.fromEntries(entries);
  }
};

/** Läs data-attribut till en partiell konfiguration. Ren funktion, testbar. */
export function configFromDataset(d: Record<string, string | undefined>): Partial<ModuleConfig> {
  const out: Partial<ModuleConfig> = d.config ? JSON.parse(d.config) : {};
  if (d.title) out.listTitle = d.title;
  if (d.tableId) out.tableId = d.tableId;
  if (d.perPage) out.perPage = Number(d.perPage);
  if (d.pageSizes) out.pageSizeOptions = list(d.pageSizes)!.map(Number);
  if (d.defaultView) out.defaultView = d.defaultView as ViewMode;
  if (d.editMode) out.editMode = d.editMode as "inline" | "popup";
  if (d.api || d.model) {
    out.dataSource = {
      ...out.dataSource,
      baseUrl: d.api ?? out.dataSource?.baseUrl ?? "/api/v1/crud",
      model: d.model ?? out.dataSource?.model ?? "",
      ...(d.allowedFilters ? { allowedFilters: list(d.allowedFilters) } : {}),
      ...(d.fields ? { fields: list(d.fields) } : {}),
      ...(d.include ? { include: list(d.include) } : {}),
      ...(d.sort ? { sort: list(d.sort) } : {}),
      ...(d.params ? { params: params(d.params) } : {}),
    };
  }
  return out;
}

/** Starta modulen i ett element utifrån dess data-attribut. */
export function mountMasterDetail(el: HTMLElement, overrides: Partial<ModuleConfig> = {}) {
  const base = el.dataset.configName ? registry.get(el.dataset.configName) : undefined;
  if (el.dataset.configName && !base) throw new Error(translate("Unknown configuration: {name}", { name: el.dataset.configName }));
  const fromData = configFromDataset({ ...el.dataset });
  const config = { ...(base ?? {}), ...fromData, ...overrides, dataSource: (base?.dataSource || fromData.dataSource || overrides.dataSource) ? { ...base?.dataSource, ...fromData.dataSource, ...overrides.dataSource } : undefined } as ModuleConfig;
  const ref = createRef<ModuleController>() as { current: ModuleController | null };
  const root = createRoot(el);
  root.render(<TableModule config={config} data={[]} controllerRef={ref} />);
  return { controller: ref, unmount: () => root.unmount() };
}

/** Starta alla element med data-master-detail på sidan. */
export function autoMount(rootEl: ParentNode = document) {
  const out = new Map<string, ReturnType<typeof mountMasterDetail>>();
  rootEl.querySelectorAll<HTMLElement>("[data-master-detail]").forEach((el, i) => out.set(el.dataset.tableId ?? el.id ?? String(i), mountMasterDetail(el)));
  return out;
}
