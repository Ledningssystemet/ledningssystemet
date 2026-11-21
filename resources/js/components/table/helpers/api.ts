import type { Item, SortState } from "./types";
import { t } from "../../../lib/i18n";
import { translate } from "./translate";

/** Datakälla mot CRUD-API:et /api/v1/crud/{model}. */
export interface DataSource {
  /** Bas-URL, t.ex. "/api/v1/crud" */
  baseUrl: string;
  model: string;
  /** Tillåtna filter som får skickas som filter[fält]. Saknas listan skickas alla aktiva filter. */
  allowedFilters?: string[];
  /** Fälturval: fields[tabell]=id,name (eller fields[_] om tableName saknas). */
  fields?: string[];
  /** Databasens tabellnamn enligt swagger, t.ex. "assets". */
  tableName?: string;
  /** Serverbaserad radflytt: POST /{model}/{id}/reorder med { after_id } (null = först). */
  reorderAction?: string;
  include?: string[];
  /** Serverordning, t.ex. ["name", "-id"]. Användarens sortering går före. */
  sort?: string[];
  /** Kontextparametrar som alltid bevaras, t.ex. { project_id: "7" } */
  params?: Record<string, string>;
  /** Fält som håller förälderns ID i underordnade tabeller (FK-34). */
  parentKey?: string;
  headers?: Record<string, string>;
}

export class ApiError extends Error {
  constructor(public status: number, message: string, public body?: any) { super(message); }
  /** Fältvisa fel vid 422: { fält: "meddelande" } */
  get fieldErrors(): Record<string, string> {
    const e = this.body?.errors;
    if (!e || typeof e !== "object") return {};
    return Object.fromEntries(Object.entries(e).map(([k, v]) => [k, Array.isArray(v) ? String(v[0]) : String(v)]));
  }
}

export interface ListQuery {
  search?: string;
  /** När angiven skickas sökningen som ett Spatie-filter med nycklar förenade av _or. */
  searchKeys?: string[];
  filters?: Record<string, string | string[] | undefined>;
  page?: number;
  perPage?: number;
  sort?: SortState;
}

/** FK-11/13/17/39: bygg list-URL med sökning, filter[..], fields[_], include, sort, page, per_page. */
export function buildListUrl(ds: DataSource, q: ListQuery = {}) {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries(ds.params ?? {})) p.set(k, v);
  const search = q.search?.trim();
  for (const [k, v] of Object.entries(q.filters ?? {})) {
    const vals = Array.isArray(v) ? v : v ? [v] : [];
    if (!vals.length) continue;
    if (ds.allowedFilters && !ds.allowedFilters.includes(k) && k !== ds.parentKey) continue;
    p.set(`filter[${k}]`, vals.join(","));
  }
  if (search) {
    if (q.searchKeys === undefined) {
      p.set("search", search);
    } else {
      if (!q.searchKeys.length || q.searchKeys.some((key) => !key.trim())) {
        throw new ApiError(0, t("Search requires at least one valid search field."));
      }
      const filter = `filter[${q.searchKeys.join("_or_")}]`;
      if (p.has(filter)) throw new ApiError(0, translate("Search filter {filter} is already used by another filter.", { filter }));
      p.set(filter, search);
    }
  }
  if (ds.fields?.length) p.set(`fields[${ds.tableName ?? "_"}]`, ds.fields.join(","));
  if (ds.include?.length) p.set("include", ds.include.join(","));
  const sorts = [...(q.sort?.key ? [(q.sort.dir === "desc" ? "-" : "") + q.sort.key] : []), ...(ds.sort ?? [])];
  if (sorts.length) p.set("sort", Array.from(new Set(sorts)).join(","));
  if (q.perPage) { p.set("per_page", String(q.perPage)); p.set("page", String(q.page ?? 1)); }
  const qs = p.toString();
  return `${ds.baseUrl.replace(/\/$/, "")}/${encodeURIComponent(ds.model)}${qs ? `?${qs}` : ""}`;
}

const numericKeyed = (o: any) => o && typeof o === "object" && !Array.isArray(o) && Object.keys(o).length > 0 && Object.keys(o).every((k) => /^\d+$/.test(k));
const toArray = (v: any): any[] | null => (Array.isArray(v) ? v : numericKeyed(v) ? Object.values(v) : v && typeof v === "object" && Object.keys(v).length === 0 ? [] : null);

/** Tolka listsvar: array, numeriskt nycklat objekt eller pagineringsobjekt med data. */
export function parseList(json: any): { items: Item[]; total: number | null; lastPage: number | null } {
  const direct = toArray(json);
  if (direct && !(json && "data" in json)) return { items: direct.map(normId), total: null, lastPage: null };
  if (json && typeof json === "object" && "data" in json) {
    const items = toArray(json.data);
    if (items) return { items: items.map(normId), total: typeof json.total === "number" ? json.total : null, lastPage: typeof json.last_page === "number" ? json.last_page : null };
  }
  throw new ApiError(0, t("The server returned an unexpected response."));
}
const normId = (r: any): Item => ({ ...r, id: String(r.id) });

async function request(ds: DataSource, method: string, url: string, body?: any) {
  let res: Response;
  try {
    res = await fetch(url, {
      method,
      credentials: "same-origin",
      headers: { Accept: "application/json", ...(body !== undefined ? { "Content-Type": "application/json" } : {}), ...ds.headers },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiError(0, t("Could not reach the server. Check your connection and try again."));
  }
  const text = await res.text();
  let json: any = undefined;
  try { json = text ? JSON.parse(text) : undefined; } catch { /* inte JSON */ }
  if (!res.ok) {
    const msg = json?.message ?? ({ 400: t("Invalid request."), 401: t("You need to log in."), 403: t("You do not have permission."), 404: t("The item does not exist."), 422: t("Check the highlighted fields.") } as Record<number, string>)[res.status] ?? translate("Server error ({status}).", { status: res.status });
    throw new ApiError(res.status, msg, json);
  }
  return json;
}

const itemUrl = (ds: DataSource, id: string) => `${ds.baseUrl.replace(/\/$/, "")}/${encodeURIComponent(ds.model)}/${encodeURIComponent(id)}`;

export const crud = {
  list: async (ds: DataSource, q: ListQuery) => parseList(await request(ds, "GET", buildListUrl(ds, q))),
  get: async (ds: DataSource, id: string) => normId(await request(ds, "GET", itemUrl(ds, id))),
  create: async (ds: DataSource, data: Record<string, any>) => normId(await request(ds, "POST", buildListUrl({ ...ds, fields: undefined, include: undefined, sort: undefined }), data)),
  update: async (ds: DataSource, id: string, data: Record<string, any>) => normId(await request(ds, "PATCH", itemUrl(ds, id), data)),
  /** Modellspecifik åtgärd: /api/v1/crud/{model}/{id}/{action} */
  action: async (ds: DataSource, id: string, action: string, method = "POST", body?: any) => request(ds, method, `${itemUrl(ds, id)}/${encodeURIComponent(action)}`, body),
  remove: async (ds: DataSource, id: string) => { await request(ds, "DELETE", itemUrl(ds, id)); },
};
