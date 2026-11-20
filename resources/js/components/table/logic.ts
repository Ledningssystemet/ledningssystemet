import type { Item, SortState } from "./types";

export function filterItems(items: Item[], search: string, searchKeys: string[], filters: Record<string, string | string[]>): Item[] {
  const q = search.trim().toLowerCase();
  return items.filter((it) => {
    for (const [k, v] of Object.entries(filters)) {
      const wanted = Array.isArray(v) ? v : v ? [v] : [];
      if (!wanted.length) continue;
      const have = Array.isArray(it[k]) ? it[k].map(String) : [String(it[k] ?? "")];
      if (!wanted.some((w) => have.includes(w))) return false;
    }
    if (!q) return true;
    return searchKeys.some((k) => {
      const val = it[k];
      return (Array.isArray(val) ? val.join(" ") : String(val ?? "")).toLowerCase().includes(q);
    });
  });
}

export function sortItems(items: Item[], sort: SortState): Item[] {
  if (!sort.key) return items;
  const k = sort.key;
  const m = sort.dir === "asc" ? 1 : -1;
  return [...items].sort((a, b) => String(a[k] ?? "").localeCompare(String(b[k] ?? ""), "sv") * m);
}

export function paginate<T>(items: T[], page: number, perPage: number) {
  const totalPages = Math.max(1, Math.ceil(items.length / perPage));
  const current = Math.min(Math.max(1, page), totalPages);
  return { pageItems: items.slice((current - 1) * perPage, current * perPage), totalPages, current };
}

export const SEARCH_MAX = 200;
export const SEARCH_DEBOUNCE_MS = 250;
/** FK-11: högst 200 tecken, inledande/avslutande blanksteg tas bort. */
export function normalizeSearch(s: string) {
  return s.slice(0, SEARCH_MAX).trim();
}

/** FK-16: intervall som visas, t.ex. "11–12 av 12". */
export function pageRange(current: number, perPage: number, total: number) {
  if (total === 0) return { from: 0, to: 0, total };
  const from = (current - 1) * perPage + 1;
  return { from, to: Math.min(total, current * perPage), total };
}

export function normalizeOptions(opts: (string | { value: string; label?: string; group?: string; disabled?: boolean; description?: string })[] = []) {
  return opts.map((o) => (typeof o === "string" ? { value: o, label: o } : { ...o, label: o.label ?? o.value }));
}

/** FK-05: datum visas som YYYY-MM-DD, datum/tid som YYYY-MM-DD HH:mm. */
export function formatDate(v: any, withTime = false) {
  if (!v) return "";
  const s = String(v);
  const m = s.match(/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2}))?/);
  if (m) return withTime && m[2] ? `${m[1]} ${m[2]}` : m[1];
  const d = new Date(s);
  if (isNaN(d.getTime())) return s;
  const iso = d.toISOString();
  return withTime ? `${iso.slice(0, 10)} ${iso.slice(11, 16)}` : iso.slice(0, 10);
}

interface Rule {
  label: string; type?: string; required?: boolean; minLength?: number; maxLength?: number;
  pattern?: string; patternMessage?: string; min?: number; max?: number; step?: number; accept?: string;
}
/** FK-22/23: returnerar felmeddelande eller null. */
export function validateField(c: Rule, value: any): string | null {
  const empty = value == null || value === "" || (Array.isArray(value) && value.length === 0);
  if (empty) return c.required ? `${c.label} måste fyllas i.` : null;
  if (c.type === "number") {
    const n = Number(value);
    if (Number.isNaN(n)) return `${c.label} måste vara ett tal.`;
    if (c.min != null && n < c.min) return `${c.label} får inte vara mindre än ${c.min}.`;
    if (c.max != null && n > c.max) return `${c.label} får inte vara större än ${c.max}.`;
    if (c.step != null) {
      const base = c.min ?? 0;
      const q = (n - base) / c.step;
      if (Math.abs(q - Math.round(q)) > 1e-9) return `${c.label} måste anges i steg om ${c.step}.`;
    }
    return null;
  }
  if (c.type === "file") {
    if (c.accept && value?.name) {
      const ok = c.accept.split(",").map((a) => a.trim().toLowerCase()).some((a) => value.name.toLowerCase().endsWith(a));
      if (!ok) return `${c.label}: tillåtna filtyper är ${c.accept}.`;
    }
    return null;
  }
  const s = String(value);
  if (c.minLength != null && s.length < c.minLength) return `${c.label} måste ha minst ${c.minLength} tecken.`;
  if (c.maxLength != null && s.length > c.maxLength) return `${c.label} får ha högst ${c.maxLength} tecken.`;
  if (c.pattern && !new RegExp(`^(?:${c.pattern})$`).test(s)) return c.patternMessage ?? `${c.label} har fel format.`;
  return null;
}

/** Flytta en post till ett nytt index. Returnerar ny ordning och posten som nu ligger före (null = först). */
export function moveItem<T extends { id: string }>(list: T[], id: string, toIndex: number): { list: T[]; previous: T | null; moved: boolean } {
  const from = list.findIndex((x) => x.id === id);
  if (from < 0) return { list, previous: null, moved: false };
  const to = Math.max(0, Math.min(list.length - 1, toIndex));
  if (from === to) return { list, previous: list[to - 1] ?? null, moved: false };
  const next = [...list];
  const [it] = next.splice(from, 1);
  next.splice(to, 0, it);
  return { list: next, previous: next[to - 1] ?? null, moved: true };
}

/** Massredigering: sätt valda fält på markerade poster som får ändras. */
export function bulkPatch<T extends { id: string }>(items: T[], ids: string[], fields: string[], values: Record<string, any>, canUpdate: (it: T) => boolean = () => true) {
  const patch = Object.fromEntries(fields.map((f) => [f, values[f]]));
  const targets = items.filter((i) => ids.includes(i.id));
  const allowed = targets.filter(canUpdate);
  return {
    patch,
    allowedIds: allowed.map((i) => i.id),
    skippedIds: targets.filter((i) => !canUpdate(i)).map((i) => i.id),
    items: items.map((i) => (allowed.includes(i) ? { ...i, ...patch } : i)),
  };
}
