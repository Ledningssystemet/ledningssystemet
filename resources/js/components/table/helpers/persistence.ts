import type { ModuleConfig, ViewMode } from "./types";

export const DEFAULT_PAGE_SIZES = [10, 25, 50, 100, 250];
const VIEWS: ViewMode[] = ["master-detail", "table", "accordion"];

function readCookie(name: string) {
  const match = document.cookie.match(new RegExp("(?:^|; )" + name + "=([^;]*)"));
  return match ? decodeURIComponent(match[1]) : null;
}

export function writeCookie(name: string, value: string) {
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=31536000; SameSite=Lax`;
}

export function initialView(config: ModuleConfig): ViewMode {
  if (config.persistState === false) return config.defaultView;
  const cookie = readCookie("md_view") as ViewMode | null;
  return cookie && VIEWS.includes(cookie) ? cookie : config.defaultView;
}

export function initialPageSize(config: ModuleConfig) {
  const options = config.pageSizeOptions ?? DEFAULT_PAGE_SIZES;
  const cookie = config.persistState === false ? NaN : Number(readCookie("md_pageSize"));
  if (options.includes(cookie)) return cookie;
  return options.includes(config.perPage) ? config.perPage : options[0];
}
