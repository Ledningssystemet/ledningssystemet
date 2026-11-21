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
  const match = document.cookie.match(new RegExp("(?:^|; )" + name + "=([^;]*)"));
  return match ? decodeURIComponent(match[1]) : null;
}

export function writeCookie(name: string, value: string) {
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=31536000; SameSite=Lax`;
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
