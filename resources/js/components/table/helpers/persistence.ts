import type { ModuleConfig, ViewMode } from "./types";

export const DEFAULT_PAGE_SIZES = [10, 25, 50, 100, 250];
const VIEWS: ViewMode[] = ["master-detail", "table", "list"];

export function availableViews(config: ModuleConfig): ViewMode[] {
  const views = VIEWS.filter((view) => {
    const key = view === "master-detail" ? "masterDetail" : view;
    return config.viewModes?.[key] !== false;
  });
  if (!views.length) throw new Error("TableModule: at least one view mode must be enabled.");
  if (config.reorder && !views.includes("table")) throw new Error("TableModule: reorder requires the table view to be enabled.");
  return views;
}

export function resolveView(config: ModuleConfig, views: ViewMode[], preferred: ViewMode | null): ViewMode {
  if (preferred && views.includes(preferred)) return preferred;
  return views.includes(config.defaultView) ? config.defaultView : views[0];
}

function readCookie(name: string) {
  const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const match = document.cookie.match(new RegExp("(?:^|; )" + escapedName + "=([^;]*)"));
  if (!match) return null;
  try { return decodeURIComponent(match[1]); }
  catch (error) { if (error instanceof URIError) return null; throw error; }
}

export function writeCookie(name: string, value: string) {
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=31536000; SameSite=Lax`;
}

function tableColumnsCookie(config: ModuleConfig): string | null {
  return config.persistState !== false && config.tableId ? `md_columns_${encodeURIComponent(config.tableId)}` : null;
}

export function initialTableColumns(config: ModuleConfig): string[] {
  const defaults = config.defaultTableColumns ?? [];
  const cookie = tableColumnsCookie(config);
  if (!cookie) return defaults;
  const value = readCookie(cookie);
  if (value === null) return defaults;
  try {
    const saved: unknown = JSON.parse(value);
    if (Array.isArray(saved) && saved.every((key) => typeof key === "string")) {
      return config.tableColumns.filter((key) => saved.includes(key));
    }
  } catch (error) {
    if (!(error instanceof SyntaxError)) throw error;
  }
  return defaults;
}

export function writeTableColumns(config: ModuleConfig, columns: string[] | null) {
  const cookie = tableColumnsCookie(config);
  if (!cookie) return;
  if (columns === null) {
    document.cookie = `${cookie}=; path=/; max-age=0; SameSite=Lax`;
  } else {
    writeCookie(cookie, JSON.stringify(columns));
  }
}

export function initialView(config: ModuleConfig, views = availableViews(config)): ViewMode {
  const cookie = config.persistState === false ? null : readCookie("md_view") as ViewMode | null;
  return resolveView(config, views, cookie);
}

export function initialPageSize(config: ModuleConfig) {
  const options = config.pageSizeOptions ?? DEFAULT_PAGE_SIZES;
  const cookie = config.persistState === false ? NaN : Number(readCookie("md_pageSize"));
  if (options.includes(cookie)) return cookie;
  return options.includes(config.perPage) ? config.perPage : options[0];
}
