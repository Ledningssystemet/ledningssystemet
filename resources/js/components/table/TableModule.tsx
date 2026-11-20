import { Fragment, useEffect, useImperativeHandle, useMemo, useRef, useState, type MutableRefObject } from "react";
import * as Tabs from "@radix-ui/react-tabs";
import * as Dialog from "@radix-ui/react-dialog";
import * as AlertDialog from "@radix-ui/react-alert-dialog";
import * as Select from "@radix-ui/react-select";
import * as Accordion from "@radix-ui/react-accordion";
import * as ToggleGroup from "@radix-ui/react-toggle-group";
import * as Label from "@radix-ui/react-label";
import * as Checkbox from "@radix-ui/react-checkbox";
import { ArrowDown, ArrowUp, Check, ChevronDown, GripVertical, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, LayoutList, Pencil, Plus, Rows3, Search, SlidersHorizontal, Table2, Trash2, X } from "lucide-react";
import type { ColumnConfig, CustomProperty, DetailSection, Item, ModuleConfig, ModuleController, OptionDef, SortState, Tone, ViewMode } from "./types";
import { bulkPatch, filterItems, moveItem, formatDate, normalizeOptions, normalizeSearch, pageRange, paginate, SEARCH_DEBOUNCE_MS, SEARCH_MAX, sortItems, validateField } from "./logic";
import { MultiSelect, TagSelect } from "./MultiSelect";
import { ApiError, crud } from "./api";
import React from "react";

const badgeTone: Record<Tone, string> = {
  success: "bg-badge-success text-badge-success-foreground",
  info: "bg-badge-info text-badge-info-foreground",
  warning: "bg-badge-warning text-badge-warning-foreground",
  danger: "bg-[hsl(var(--badge-danger))] text-[hsl(var(--badge-danger-foreground))]",
  secondary: "bg-secondary text-secondary-foreground",
};
const dotTone: Record<Tone, string> = {
  success: "bg-[hsl(var(--dot-success))]",
  warning: "bg-[hsl(var(--dot-warning))]",
  secondary: "bg-[hsl(var(--dot-secondary))]",
  info: "bg-primary",
  danger: "bg-destructive",
};
const btn = "inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50";
const btnPrimary = `${btn} bg-primary text-primary-foreground hover:bg-primary/90`;
const btnOutline = `${btn} border bg-card hover:bg-surface-hover`;
const btnDanger = `${btn} bg-destructive text-destructive-foreground hover:bg-destructive/90`;
const cbCls = "flex h-4 w-4 items-center justify-center rounded border border-input bg-card data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground";
const inputCls = "w-full rounded-md border border-input bg-card px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring";
const overlay = "fixed inset-0 z-40 bg-foreground/40";
const dialogBox = "fixed left-1/2 top-1/2 z-50 max-h-[90vh] w-[95vw] max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg border bg-popover p-5 shadow-xl";

interface Props {
  config: ModuleConfig;
  data: Item[];
  onDataChange?: (data: Item[]) => void;
  /** FK-32: styr tabellen utifrån. */
  controllerRef?: MutableRefObject<ModuleController | null>;
  /** Förälderns ID för underordnad tabell i API-läge (FK-34). */
  parentId?: string;
}

function RSelect({ value, onChange, options, placeholder, label, id, invalid }: { value: string; onChange: (v: string) => void; options: OptionDef[]; placeholder: string; label: string; id?: string; invalid?: boolean }) {
  const groups: [string, OptionDef[]][] = [];
  for (const o of options) {
    const g = o.group ?? "";
    const found = groups.find(([k]) => k === g);
    found ? found[1].push(o) : groups.push([g, [o]]);
  }
  return (
    <Select.Root value={value} onValueChange={onChange}>
      <Select.Trigger id={id} aria-label={label} aria-invalid={invalid || undefined} className={`${inputCls} flex items-center justify-between ${invalid ? "border-destructive" : ""}`}>
        <Select.Value placeholder={placeholder} />
        <Select.Icon><ChevronDown className="h-4 w-4 opacity-60" /></Select.Icon>
      </Select.Trigger>
      <Select.Portal>
        <Select.Content position="popper" sideOffset={4} className="z-[60] max-h-80 min-w-[var(--radix-select-trigger-width)] overflow-y-auto rounded-md border bg-popover p-1 shadow-lg">
          <Select.Viewport>
            {groups.map(([g, opts]) => (
              <Select.Group key={g || "_"}>
                {g && <Select.Label className="px-2 pb-0.5 pt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{g}</Select.Label>}
                {opts.map((o) => (
                  <Select.Item key={o.value} value={o.value} disabled={o.disabled} className="relative flex cursor-pointer select-none flex-col items-start rounded px-7 py-1.5 text-sm outline-none data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground">
                    <Select.ItemIndicator className="absolute left-2 top-2"><Check className="h-3.5 w-3.5" /></Select.ItemIndicator>
                    <Select.ItemText>{o.label}</Select.ItemText>
                    {o.description && <span className="text-xs text-muted-foreground">{o.description}</span>}
                  </Select.Item>
                ))}
              </Select.Group>
            ))}
          </Select.Viewport>
        </Select.Content>
      </Select.Portal>
    </Select.Root>
  );
}

const so = (vals: (string | number)[]) => vals.map((v) => ({ value: String(v), label: String(v) }));

const ALL = "__all__";
const NONE = "__none__";
const DEFAULT_PAGE_SIZES = [10, 25, 50, 100, 250];

const VIEWS: ViewMode[] = ["master-detail", "table", "accordion"];
function readCookie(name: string) {
  const m = document.cookie.match(new RegExp("(?:^|; )" + name + "=([^;]*)"));
  return m ? decodeURIComponent(m[1]) : null;
}
function writeCookie(name: string, value: string) {
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=31536000; SameSite=Lax`;
}

function initialView(config: ModuleConfig): ViewMode {
  if (config.persistState === false) return config.defaultView;
  const c = readCookie("md_view") as ViewMode | null;
  return c && VIEWS.includes(c) ? c : config.defaultView;
}

function initialPageSize(config: ModuleConfig) {
  const opts = config.pageSizeOptions ?? DEFAULT_PAGE_SIZES;
  const c = config.persistState === false ? NaN : Number(readCookie("md_pageSize"));
  if (opts.includes(c)) return c;
  return opts.includes(config.perPage) ? config.perPage : opts[0];
}

const colOptions = (c: ColumnConfig, draft?: Record<string, any>) => normalizeOptions(c.optionsFor && draft ? c.optionsFor(draft) : c.optionsFor ? c.optionsFor({}) : c.options);

/** FK-38: anpassade egenskaper blir vanliga kolumner. */
function customToColumns(props: CustomProperty[] = [], refs: ModuleConfig["references"] = {}): ColumnConfig[] {
  return props.filter((p) => p.visible !== false).map((p) => ({
    key: p.key, label: p.label, required: p.required,
    form: p.editable === false ? "readonly-edit" : "both",
    type: p.type === "text" ? "text" : p.type === "textarea" ? "textarea" : "select",
    options: p.type === "boolean" ? [{ value: "true", label: "Ja" }, { value: "false", label: "Nej" }] : p.type === "text" || p.type === "textarea" ? undefined : refs?.[p.type] ?? [],
    tab: "Anpassade egenskaper",
  }));
}

/** FK-15: läs jtId[tableId] och jtFilter[tableId][filter] ur adressen. */
function linkState(tableId?: string) {
  if (!tableId || typeof window === "undefined") return { id: null as string | null, filters: {} as Record<string, string[]> };
  const q = new URLSearchParams(window.location.search);
  const filters: Record<string, string[]> = {};
  const prefix = `jtFilter[${tableId}][`;
  q.forEach((v, k) => {
    if (k.startsWith(prefix) && k.endsWith("]")) {
      const f = k.slice(prefix.length, -1);
      filters[f] = [...(filters[f] ?? []), ...v.split(",").filter(Boolean)];
    }
  });
  return { id: q.get(`jtId[${tableId}]`), filters };
}

export function TableModule({ config: rawConfig, data, onDataChange, controllerRef, parentId }: Props) {
  const config = useMemo(() => rawConfig.customProperties?.length ? {
    ...rawConfig,
    columns: [...rawConfig.columns, ...customToColumns(rawConfig.customProperties, rawConfig.references)],
    formTabs: rawConfig.formTabs ? [...rawConfig.formTabs, "Anpassade egenskaper"] : rawConfig.formTabs,
    filters: [...rawConfig.filters, ...rawConfig.customProperties.filter((p) => p.filterable && p.type !== "textarea").map((p) => ({ key: p.key, label: p.label, mode: "multi" as const, options: p.type === "boolean" ? ["true", "false"] : rawConfig.references?.[p.type as keyof NonNullable<ModuleConfig["references"]>] }))],
  } : rawConfig, [rawConfig]);
  const link = useMemo(() => linkState(rawConfig.tableId), [rawConfig.tableId]);
  const [idFilter, setIdFilter] = useState<string | null>(link.id);
  const persist = config.persistState !== false;
  const [items, setItems] = useState<Item[]>(data);
  const [view, setView] = useState<ViewMode>(() => initialView(config));
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState<Record<string, string | string[]>>(() => {
    const out: Record<string, string | string[]> = {};
    for (const [k, v] of Object.entries(link.filters)) { const f = rawConfig.filters.find((x) => x.key === k); if (f) out[k] = f.mode === "multi" || f.mode === "tags" ? v : v[0]; }
    return out;
  });
  const [sort, setSort] = useState<SortState>({ key: null, dir: "asc" });
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(() => initialPageSize(config));
  useEffect(() => { if (persist) writeCookie("md_view", view); }, [view, persist]);
  useEffect(() => { if (persist) writeCookie("md_pageSize", String(pageSize)); }, [pageSize, persist]);
  const [selectedId, setSelectedId] = useState<string | null>(data[0]?.id ?? null);
  const [editing, setEditing] = useState<Item | null>(null);
  const [isNew, setIsNew] = useState(false);
  const [deleting, setDeleting] = useState<Item | null>(null);
  const [filterOpen, setFilterOpen] = useState(false);
  /** FK-33: per rad högst en öppen underordnad tabell; en ny ersätter den tidigare. */
  const [openChild, setOpenChild] = useState<Record<string, string>>({});

  // FK-11: 250 ms fördröjning, trimning, tillbaka till första sidan.
  useEffect(() => {
    const t = setTimeout(() => {
      const next = normalizeSearch(searchInput);
      setSearch((prev) => { if (prev !== next) setPage(1); return next; });
    }, SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(t);
  }, [searchInput]);

  const doneRef = useRef<(() => void)[]>([]);
  const loadDoneRef = useRef<(() => void)[]>([]);
  useEffect(() => { const d = doneRef.current; doneRef.current = []; d.forEach((f) => f()); });
  const itemsRef = useRef(items); itemsRef.current = items;
  useImperativeHandle(controllerRef, () => ({
    getItems: () => itemsRef.current,
    reload: (next, done) => {
      if (config.dataSource) { if (done) loadDoneRef.current.push(done); setReloadKey((k) => k + 1); return; }
      if (done) doneRef.current.push(done); setItems((p) => (next ? [...next] : [...p]));
    },
    updateItem: (id, patch, done) => { if (done) doneRef.current.push(done); setItems((p) => p.map((i) => (i.id === id ? { ...i, ...patch } : i))); },
    updateStatus: (id, st, done) => { if (done) doneRef.current.push(done); setItems((p) => p.map((i) => (i.id === id ? { ...i, ...st } : i))); },
  }), []);

  const first = useRef(true);
  useEffect(() => { if (first.current) { first.current = false; return; } onDataChange?.(items); }, [items]); // eslint-disable-line react-hooks/exhaustive-deps

  const perm = config.permissions;
  const canCreate = perm?.create !== false;
  const canUpdate = (it: Item) => it.access?.update !== false && it.accessFlags?.update !== false && (perm?.update?.(it) ?? true);
  const canDelete = (it: Item) => it.access?.delete !== false && it.accessFlags?.delete !== false && (perm?.delete?.(it) ?? true);

  const colLabel = (k: string) => config.columns.find((c) => c.key === k)?.label ?? k;
  const listCols = config.columns.filter((c) => !c.listHidden);
  const tableCols = config.tableColumns.filter((k) => !config.columns.find((c) => c.key === k)?.listHidden);
  const childSections = config.detailSections.filter((s) => s.type === "table");
  const ds = config.dataSource;
  const dsKey = ds ? JSON.stringify(ds) : "";
  const [total, setTotal] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const pendingDelete = useRef(new Set<string>());
  const reportError = (e: unknown) => {
    const err = e instanceof ApiError ? e : new ApiError(0, "Något gick fel.");
    if (err.status === 401) config.onUnauthorized?.();
    return err;
  };
  // API-läge: hämta från servern när sökning, filter, sortering eller sida ändras.
  useEffect(() => {
    if (!ds) return;
    let alive = true;
    setBusy(true);
    setLoadError(null);
    const f: Record<string, string | string[]> = config.reorder ? {} : { ...filters };
    if (idFilter) f.id = idFilter;
    if (ds.parentKey && parentId) f[ds.parentKey] = parentId;
    crud.list(ds, { search: idFilter || config.reorder ? "" : search, filters: f, page, perPage: pageSize, sort: config.reorder ? { key: null, dir: "asc" } : sort })
      .then((r) => {
        if (!alive) return;
        // FK-18: tom senare sida efter borttagning → visa föregående.
        if (r.items.length === 0 && page > 1 && (r.total ?? 0) > 0) { setPage(page - 1); return; }
        setItems(r.items);
        setTotal(r.total ?? (r.items.length < pageSize && page === 1 ? r.items.length : null));
        setSelectedId((cur) => (cur && r.items.some((i) => i.id === cur) ? cur : r.items[0]?.id ?? null));
      })
      .catch((e) => { if (alive) setLoadError(reportError(e).message); })
      .finally(() => { if (alive) { setBusy(false); const d = loadDoneRef.current; loadDoneRef.current = []; setTimeout(() => d.forEach((f) => f())); } });
    return () => { alive = false; };
  }, [dsKey, search, filters, page, pageSize, sort, idFilter, parentId, reloadKey]); // eslint-disable-line react-hooks/exhaustive-deps
  const reload = () => setReloadKey((k) => k + 1);

  const visible = useMemo(() => ds ? items : idFilter ? items.filter((i) => String(i.id) === idFilter) : config.reorder ? items : sortItems(filterItems(items, search, config.searchKeys, filters), sort), [ds, items, search, filters, sort, config.searchKeys, idFilter]);
  const local = paginate(visible, page, pageSize);
  const pageItems = ds ? items : local.pageItems;
  const totalPages = ds ? Math.max(1, Math.ceil((total ?? 0) / pageSize)) : local.totalPages;
  const current = ds ? page : local.current;
  const totalCount = ds ? total : visible.length;
  const range = pageRange(current, pageSize, totalCount ?? 0);
  const activeFilterCount = Object.values(filters).filter((v) => (Array.isArray(v) ? v.length : v)).length + (sort.key ? 1 : 0);
  const allValues = (key: string) => Array.from(new Set(items.flatMap((i) => (Array.isArray(i[key]) ? i[key] : i[key] != null ? [i[key]] : [])).map(String))).sort((a, b) => a.localeCompare(b, "sv"));
  const setFilter = (key: string, v: string | string[]) => { setFilters((p) => ({ ...p, [key]: v })); setPage(1); };
  const selected = items.find((i) => i.id === selectedId) ?? null;

  const renderValue = (key: string, value: any, item?: Item) => {
    const col = config.columns.find((c) => c.key === key);
    if (col?.render && item) return <span onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()}>{col.render(item)}</span>;
    const b = config.badges?.[key]?.[value];
    if (b) return <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${badgeTone[b]}`}>{value}</span>;
    const d = config.statusDots?.[key]?.[value];
    if (d) return <span className="inline-flex items-center gap-1.5"><span className={`h-2 w-2 rounded-full ${dotTone[d]}`} />{value}</span>;
    if (col?.type === "date") return <>{formatDate(value)}</>;
    if (col?.type === "datetime") return <>{formatDate(value, true)}</>;
    if (col?.type === "file") return <>{value?.name ?? ""}</>;
    if (col && (col.options || col.optionsFor)) {
      const opts = colOptions(col);
      const lbl = (v: any) => { const id = typeof v === "object" && v ? v.id : v; return opts.find((o) => o.value === String(id))?.label ?? String(id ?? ""); };
      return <>{Array.isArray(value) ? value.map(lbl).join(", ") : lbl(value)}</>;
    }
    return <>{Array.isArray(value) ? value.join(", ") : String(value ?? "")}</>;
  };

  // Massredigering och massradering (tabellvy)
  const [checked, setChecked] = useState<Set<string>>(new Set());
  useEffect(() => { setChecked((c) => { const n = new Set(Array.from(c).filter((id) => items.some((i) => i.id === id))); return n.size === c.size ? c : n; }); }, [items]);
  const toggleCheck = (id: string) => setChecked((c) => { const n = new Set(c); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const pageIds = (): string[] => pageItems.map((i) => i.id);
  const [bulkStep, setBulkStep] = useState<"fields" | "form" | null>(null);
  const [bulkFields, setBulkFields] = useState<string[]>([]);
  const [bulkDelete, setBulkDelete] = useState(false);
  const [bulkMsg, setBulkMsg] = useState<string | null>(null);
  const [bulkBusy, setBulkBusy] = useState(false);
  const checkedItems = items.filter((i) => checked.has(i.id));
  const bulkEditable = [
    ...config.columns.filter((c) => c.key !== "id" && c.key !== "createdAt" && c.key !== "updatedAt" && c.form !== "none" && c.form !== "create" && c.form !== "readonly-edit").map((c) => ({ key: c.key, label: c.label })),
    ...config.detailSections.filter((d) => d.type !== "table").map((d) => ({ key: d.key, label: d.label })),
  ];
  const bulkConfig: ModuleConfig = {
    ...config, formTabs: undefined, titleKey: bulkFields.includes(config.titleKey) ? config.titleKey : "__none__",
    columns: config.columns.filter((c) => bulkFields.includes(c.key)).map((c) => ({ ...c, form: "both" as const, tab: undefined })),
    detailSections: config.detailSections.filter((d) => bulkFields.includes(d.key)).map((d) => ({ ...d, tab: undefined })),
  };
  const bulkStart = (): Item => {
    // Startvärde: gemensamt värde om alla markerade har samma, annars tomt.
    const out: Item = { id: "__bulk__" };
    for (const k of bulkFields) {
      const vals = checkedItems.map((i) => JSON.stringify(i[k] ?? null));
      out[k] = vals.every((v) => v === vals[0]) ? checkedItems[0]?.[k] : Array.isArray(checkedItems[0]?.[k]) ? [] : "";
    }
    return out;
  };
  const saveBulk = async (draft: Item): Promise<{ fields?: Record<string, string>; message?: string } | null> => {
    const r = bulkPatch(items, Array.from(checked), bulkFields, draft, canUpdate);
    if (ds) {
      const failed: string[] = [];
      for (const id of r.allowedIds) {
        try { const saved = await crud.update(ds, id, r.patch); const prev = items.find((i) => i.id === id); if (prev) config.events?.onUpdated?.(saved, prev); }
        catch (e) { failed.push(reportError(e).message); }
      }
      reload();
      if (failed.length && failed.length === r.allowedIds.length) return { message: `Inget objekt kunde sparas: ${failed[0]}` };
      setBulkMsg(`${r.allowedIds.length - failed.length} objekt uppdaterades.${failed.length ? ` ${failed.length} misslyckades: ${failed[0]}` : ""}${r.skippedIds.length ? ` ${r.skippedIds.length} hoppades över (saknar behörighet).` : ""}`);
    } else {
      r.allowedIds.forEach((id) => { const prev = items.find((i) => i.id === id)!; config.events?.onUpdated?.({ ...prev, ...r.patch }, prev); });
      setItems(r.items);
      setBulkMsg(`${r.allowedIds.length} objekt uppdaterades.${r.skippedIds.length ? ` ${r.skippedIds.length} hoppades över (saknar behörighet).` : ""}`);
    }
    setBulkStep(null);
    return null;
  };
  const runBulkDelete = async () => {
    const targets = checkedItems.filter(canDelete);
    const skipped = checkedItems.length - targets.length;
    setBulkBusy(true);
    let ok = 0; const failed: string[] = [];
    for (const it of targets) {
      if (ds) {
        try { await crud.remove(ds, it.id); config.events?.onDeleted?.(it); ok++; } catch (e) { failed.push(reportError(e).message); }
      } else { config.events?.onDeleted?.(it); ok++; }
    }
    if (ds) reload(); else setItems((all) => all.filter((i) => !targets.some((t) => t.id === i.id)));
    setChecked(new Set());
    setBulkBusy(false);
    setBulkDelete(false);
    setBulkMsg(`${ok} objekt togs bort.${failed.length ? ` ${failed.length} misslyckades: ${failed[0]}` : ""}${skipped ? ` ${skipped} hoppades över (saknar behörighet).` : ""}`);
  };

  const startEdit = (item: Item, fresh = false) => { setEditing(structuredClone(item)); setIsNew(fresh); };
  const startNew = () => startEdit({ ...structuredClone(config.newItemDefaults), ...(ds?.parentKey && parentId ? { [ds.parentKey]: parentId } : {}), id: ds ? "" : crypto.randomUUID(), ...(ds ? {} : { createdAt: today(), updatedAt: today() }) }, true);
  /** Returnerar fältfel (422) eller ett allmänt fel; null vid lyckat sparande. */
  const save = async (item: Item): Promise<{ fields?: Record<string, string>; message?: string } | null> => {
    if (ds) {
      const prev = items.find((p) => p.id === item.id);
      const { id, ...payload } = item;
      try {
        const saved = isNew ? await crud.create(ds, payload) : await crud.update(ds, id, payload);
        if (isNew) config.events?.onCreated?.(saved); else if (prev) config.events?.onUpdated?.(saved, prev);
        setEditing(null);
        setSelectedId(saved.id);
        if (isNew) setPage(1);
        reload();
        return null;
      } catch (e) {
        const err = reportError(e);
        return { fields: err.status === 422 ? err.fieldErrors : undefined, message: err.message };
      }
    }
    const next = { ...item, updatedAt: today() };
    const prev = items.find((p) => p.id === next.id);
    setItems((all) => (isNew ? [next, ...all] : all.map((p) => (p.id === next.id ? next : p))));
    if (isNew) config.events?.onCreated?.(next); else if (prev) config.events?.onUpdated?.(next, prev);
    setSelectedId(next.id);
    setEditing(null);
    return null;
  };
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const remove = async (item: Item) => {
    if (ds) {
      if (pendingDelete.current.has(item.id)) return; // FK-28: inte flera gånger
      pendingDelete.current.add(item.id);
      try {
        await crud.remove(ds, item.id);
        config.events?.onDeleted?.(item);
        setDeleting(null);
        reload();
      } catch (e) {
        setDeleteError(reportError(e).message);
      } finally {
        pendingDelete.current.delete(item.id);
      }
      return;
    }
    config.events?.onDeleted?.(item);
    setItems((prev) => prev.filter((p) => p.id !== item.id));
    if (selectedId === item.id) setSelectedId(null);
    setDeleting(null);
  };
  const updateChildRows = (parentId: string, key: string, rows: Item[]) =>
    setItems((all) => all.map((p) => (p.id === parentId ? { ...p, [key]: rows } : p)));

  const inlineEditing = config.editMode === "inline" && editing;

  const actions = (item: Item) => (
    <div className="flex flex-wrap gap-1">
      {config.rowCommands?.filter((c) => c.visible?.(item) ?? true).map((c) => {
        const b = c.badge?.(item);
        return <button key={c.label} className={`${btnOutline} px-2 py-1 text-xs`} onClick={(e) => { e.stopPropagation(); runCommand(c, item); }}>{c.label}{b != null && b !== 0 && <span className="rounded-full bg-primary px-1.5 text-[10px] text-primary-foreground">{b}</span>}</button>;
      })}
      {canUpdate(item) && <button className={`${btnOutline} px-2`} aria-label="Redigera" onClick={(e) => { e.stopPropagation(); startEdit(item); }}><Pencil className="h-4 w-4" /></button>}
      {canDelete(item) && <button className={`${btnOutline} px-2 text-destructive`} aria-label="Ta bort" onClick={(e) => { e.stopPropagation(); setDeleting(item); }}><Trash2 className="h-4 w-4" /></button>}
    </div>
  );

  const childTable = (item: Item, s: DetailSection) =>
    s.childConfig ? (
      s.childConfig.dataSource
        ? <TableModule key={`${item.id}-${s.key}`} config={{ persistState: false, ...s.childConfig }} data={[]} parentId={item.id} />
        : <TableModule key={`${item.id}-${s.key}`} config={{ persistState: false, ...s.childConfig }} data={item[s.key] ?? []} onDataChange={(rows) => updateChildRows(item.id, s.key, rows)} />
    ) : <NestedTable section={s} rows={item[s.key] ?? []} />;

  const details = (item: Item) =>
    inlineEditing && editing!.id === item.id ? (
      <ItemForm tagSuggestions={allValues} config={config} item={editing!} isNew={false} onCancel={() => setEditing(null)} onSave={save} />
    ) : (
      <div className="space-y-4">
        {config.status?.(item)?.messages?.map((m, i) => (
          <p key={i} role="status" className={`rounded-md px-3 py-2 text-sm ${badgeTone[config.status!(item)!.tone]}`}>{m}</p>
        ))}
        <dl className="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
          {listCols.filter((c) => c.key !== config.titleKey && !(config.hiddenFields?.(item) ?? []).includes(c.key)).map((c) => (
            <div key={c.key}><dt className="text-xs uppercase tracking-wide text-muted-foreground">{c.label}</dt><dd className="text-sm">{renderValue(c.key, item[c.key], item)}</dd></div>
          ))}
        </dl>
        {config.detailSections.map((s) => (
          <section key={s.key}>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{s.label}</h4>
            {s.type === "textarea" && <p className="text-sm">{item[s.key] || "–"}</p>}
            {s.type === "properties" && (
              <table className="w-full text-sm"><tbody>
                {Object.entries(item[s.key] ?? {}).map(([k, v]) => (
                  <tr key={k} className="border-b last:border-0"><td className="py-1 pr-4 text-muted-foreground">{k}</td><td className="py-1">{String(v)}</td></tr>
                ))}
              </tbody></table>
            )}
            {s.type === "table" && childTable(item, s)}
            {s.type === "tags" && (
              <div className="flex flex-wrap gap-1">{(item[s.key] ?? []).map((t: string) => <span key={t} className="rounded bg-secondary px-2 py-0.5 text-xs">{t}</span>)}</div>
            )}
          </section>
        ))}
      </div>
    );

  const extraCols = childSections.length ? 1 : 0;
  const statusMark = (it: Item) => {
    const st = config.status?.(it);
    return st ? <span className="ml-2 inline-flex items-center gap-1 text-xs font-normal text-muted-foreground" title={st.label}><span className={`h-2 w-2 rounded-full ${dotTone[st.tone]}`} />{st.label}</span> : null;
  };
  const [cmdError, setCmdError] = useState<string | null>(null);
  const runCommand = async (c: NonNullable<ModuleConfig["rowCommands"]>[number], item: Item) => {
    if (c.serverAction && ds) {
      if (c.serverAction.confirm && !window.confirm(c.serverAction.confirm)) return;
      setCmdError(null);
      try { await crud.action(ds, item.id, c.serverAction.name, c.serverAction.method); reload(); }
      catch (e) { setCmdError(reportError(e).message); }
    }
    c.onClick?.(item);
  };
  // Reorder-läge: endast tabellvy, ingen sökning, filtrering eller sortering; dra och släpp rader.
  const reorderMode = !!config.reorder;
  const effView: ViewMode = reorderMode ? "table" : view;
  const canMove = reorderMode && !idFilter;
  const [dragId, setDragId] = useState<string | null>(null);
  const [dropIndex, setDropIndex] = useState<number | null>(null);
  const moveTo = (it: Item, toIndex: number) => {
    const r = moveItem(pageItems, it.id, toIndex);
    if (!r.moved) return;
    // Optimistisk ny ordning; servern får sista ordet vid omladdning.
    if (ds) setItems(r.list);
    else setItems((all) => {
      const others = all.filter((x) => !pageItems.some((p) => p.id === x.id));
      const start = all.findIndex((x) => x.id === pageItems[0]?.id);
      return [...others.slice(0, start), ...r.list, ...others.slice(start)];
    });
    config.reorder?.onMoved?.(it, r.previous);
    if (ds) {
      // after_id = posten som föregår; null = överst. Överst på en senare sida föregås av sista posten på sidan innan.
      const prevId = async () => {
        if (r.previous) return r.previous.id;
        if (current <= 1) return null;
        const prev = await crud.list(ds, { page: current - 1, perPage: pageSize, filters: ds.parentKey && parentId ? { [ds.parentKey]: parentId } : {} });
        return prev.items[prev.items.length - 1]?.id ?? null;
      };
      prevId()
        .then((after_id) => crud.action(ds, it.id, ds.reorderAction ?? "reorder", "POST", { after_id }))
        .then(reload, (e) => { setCmdError(reportError(e).message); reload(); });
    }
  };
  const move = (it: Item, dir: -1 | 1) => moveTo(it, pageItems.findIndex((x) => x.id === it.id) + dir);

  return (
    <div className="rounded-lg border bg-card shadow-sm">
      {/* Toolbar */}
      <div className="flex flex-wrap items-center gap-2 border-b p-3">
        <h2 className="mr-2 text-lg font-semibold">{config.listTitle} <span className="text-sm font-normal text-muted-foreground">({totalCount ?? items.length})</span></h2>
        {reorderMode ? <p className="flex-1 text-sm text-muted-foreground">Dra i <GripVertical className="inline h-4 w-4" /> för att ändra ordning.</p> : <>
        <div className="relative min-w-[200px] flex-1">
          <Search className="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-muted-foreground" />
          <input type="search" disabled={!!idFilter} aria-label="Sök" placeholder="Sök…" maxLength={SEARCH_MAX} value={searchInput} onChange={(e) => setSearchInput(e.target.value.slice(0, SEARCH_MAX))} className={`${inputCls} pl-8`} />
        </div>
        <button className={btnOutline} disabled={!!idFilter} onClick={() => setFilterOpen(true)}>
          <SlidersHorizontal className="h-4 w-4" />Filter och sortering
          {activeFilterCount > 0 && <span className="rounded-full bg-primary px-1.5 text-xs text-primary-foreground">{activeFilterCount}</span>}
        </button>
        <ToggleGroup.Root type="single" value={view} onValueChange={(v) => v && setView(v as ViewMode)} aria-label="Välj vy" className="inline-flex overflow-hidden rounded-md border">
          {([["master-detail", "Master/Detail", LayoutList], ["table", "Tabell", Table2], ["accordion", "Accordion", Rows3]] as const).map(([v, label, Icon]) => (
            <ToggleGroup.Item key={v} value={v} className="inline-flex items-center gap-1.5 border-r px-3 py-1.5 text-sm last:border-0 hover:bg-surface-hover data-[state=on]:bg-primary data-[state=on]:text-primary-foreground">
              <Icon className="h-4 w-4" /><span className="hidden sm:inline">{label}</span>
            </ToggleGroup.Item>
          ))}
        </ToggleGroup.Root>
        </>}
        {canCreate && <button className={btnPrimary} onClick={startNew}><Plus className="h-4 w-4" />Ny</button>}
      </div>

      {idFilter && (
        <div className="flex items-center gap-2 border-b bg-accent/50 px-3 py-1.5 text-sm text-accent-foreground">
          Visar endast post med ID <strong>{idFilter}</strong>
          <button aria-label="Ta bort ID-filter" className="ml-1 rounded p-0.5 hover:bg-accent" onClick={() => setIdFilter(null)}><X className="h-3.5 w-3.5" /></button>
        </div>
      )}
      {sort.key && (
        <div className="flex items-center gap-2 border-b bg-accent/50 px-3 py-1.5 text-sm text-accent-foreground">
          Sorterar: <strong>{colLabel(sort.key)} {sort.dir === "asc" ? "↑" : "↓"}</strong>
          <button aria-label="Ta bort sortering" className="ml-1 rounded p-0.5 hover:bg-accent" onClick={() => setSort({ key: null, dir: "asc" })}><X className="h-3.5 w-3.5" /></button>
        </div>
      )}

      {effView === "table" && checked.size > 0 && (
        <div className="flex flex-wrap items-center gap-2 border-b bg-accent/50 px-3 py-2 text-sm text-accent-foreground">
          <strong>{checked.size} markerade</strong>
          <button className={btnOutline} onClick={() => { setBulkFields([]); setBulkStep("fields"); }}><Pencil className="h-4 w-4" />Redigera markerade</button>
          <button className={`${btnOutline} text-destructive`} onClick={() => setBulkDelete(true)}><Trash2 className="h-4 w-4" />Ta bort markerade</button>
          <button className={`${btnOutline} ml-auto`} onClick={() => setChecked(new Set())}>Avmarkera alla</button>
        </div>
      )}
      {bulkMsg && (
        <div role="status" className="flex items-center gap-2 border-b px-3 py-2 text-sm">
          {bulkMsg}<button aria-label="Stäng meddelande" className="ml-auto rounded p-0.5 hover:bg-accent" onClick={() => setBulkMsg(null)}><X className="h-3.5 w-3.5" /></button>
        </div>
      )}
      {inlineEditing && isNew && <div className="border-b p-4"><ItemForm tagSuggestions={allValues} config={config} item={editing!} isNew onCancel={() => setEditing(null)} onSave={save} /></div>}

      {cmdError && <p role="alert" className="border-b px-3 py-2 text-sm text-destructive">{cmdError}</p>}
      {loadError && (
        <div role="alert" className="flex items-center justify-between gap-2 border-b bg-[hsl(var(--badge-danger))] px-3 py-2 text-sm text-[hsl(var(--badge-danger-foreground))]">
          {loadError}<button className={btnOutline} onClick={reload}>Försök igen</button>
        </div>
      )}
      {/* Views */}
      <div aria-busy={busy} className={`relative ${busy ? "pointer-events-none" : ""}`}>
      {busy && <div className="absolute inset-0 z-10 flex items-start justify-center bg-card/60 pt-6 text-sm text-muted-foreground" role="status">Laddar…</div>}
      {pageItems.length === 0 ? (
        <p className="p-8 text-center text-muted-foreground">{busy ? "" : "Inga träffar."}</p>
      ) : effView === "master-detail" ? (
        <div className="grid md:grid-cols-[minmax(240px,1fr)_2fr]">
          <ul className="border-b md:border-b-0 md:border-r">
            {pageItems.map((it) => (
              <li key={it.id}>
                <button onClick={() => setSelectedId(it.id)} className={`w-full border-b px-4 py-2.5 text-left hover:bg-surface-hover ${it.id === selectedId ? "border-l-4 border-l-primary bg-accent" : ""}`}>
                  <div className="font-medium">{it[config.titleKey]}{statusMark(it)}</div>
                  <div className="text-xs text-muted-foreground">{config.subtitleKeys.map((k) => it[k]).filter(Boolean).join(" · ")}</div>
                </button>
              </li>
            ))}
          </ul>
          <div className="min-w-0 p-4">
            {selected ? (
              <>
                <div className="mb-4 flex items-start justify-between gap-2"><h3 className="text-xl font-semibold">{selected[config.titleKey]}{statusMark(selected)}</h3>{actions(selected)}</div>
                {details(selected)}
              </>
            ) : <p className="text-muted-foreground">Välj ett objekt i listan.</p>}
          </div>
        </div>
      ) : effView === "table" ? (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-muted text-left"><tr>
              {canMove && <th className="w-8 px-2 py-2"><span className="sr-only">Flytta</span></th>}
              <th className="w-8 px-2 py-2">
                <Checkbox.Root aria-label="Markera alla på sidan" className={cbCls}
                  checked={pageItems.length > 0 && pageItems.every((i) => checked.has(i.id)) ? true : pageItems.some((i) => checked.has(i.id)) ? "indeterminate" : false}
                  onCheckedChange={(v) => setChecked((c) => { const n = new Set(c); pageIds().forEach((id) => (v === true ? n.add(id) : n.delete(id))); return n; })}>
                  <Checkbox.Indicator>{pageItems.every((i) => checked.has(i.id)) ? <Check className="h-3 w-3" /> : <span className="block h-0.5 w-2 bg-current" />}</Checkbox.Indicator>
                </Checkbox.Root>
              </th>
              {tableCols.map((k) => (
                <th key={k} className="px-3 py-2 font-medium">
                  {reorderMode ? colLabel(k) : (
                  <button className="inline-flex items-center gap-1" onClick={() => setSort((s) => ({ key: k, dir: s.key === k && s.dir === "asc" ? "desc" : "asc" }))}>
                    {colLabel(k)}{sort.key === k && (sort.dir === "asc" ? " ↑" : " ↓")}
                  </button>)}
                </th>
              ))}
              {extraCols > 0 && <th className="px-3 py-2 font-medium">Underlistor</th>}
              <th className="px-3 py-2" />
            </tr></thead>
            <tbody>
              {pageItems.map((it) => inlineEditing && editing!.id === it.id && !isNew ? (
                <tr key={it.id}><td colSpan={tableCols.length + 1 + extraCols + (canMove ? 1 : 0) + 1} className="p-4"><ItemForm tagSuggestions={allValues} config={config} item={editing!} isNew={false} onCancel={() => setEditing(null)} onSave={save} /></td></tr>
              ) : (
                <Fragment key={it.id}>
                  <tr
                    className={`border-t hover:bg-surface-hover ${dragId === it.id ? "opacity-40" : ""} ${dropIndex === pageItems.indexOf(it) && dragId && dragId !== it.id ? "outline outline-2 -outline-offset-2 outline-primary" : ""}`}
                    onDragOver={canMove && dragId ? (e) => { e.preventDefault(); e.dataTransfer.dropEffect = "move"; setDropIndex(pageItems.indexOf(it)); } : undefined}
                    onDrop={canMove ? (e) => { e.preventDefault(); const d = pageItems.find((x) => x.id === dragId); if (d) moveTo(d, pageItems.indexOf(it)); setDragId(null); setDropIndex(null); } : undefined}
                  >
                    <td className="px-2 py-2">
                      <Checkbox.Root aria-label={`Markera ${it[config.titleKey]}`} className={cbCls} checked={checked.has(it.id)} onCheckedChange={() => toggleCheck(it.id)}>
                        <Checkbox.Indicator><Check className="h-3 w-3" /></Checkbox.Indicator>
                      </Checkbox.Root>
                    </td>
                    {canMove && (
                      <td className="px-2 py-2">
                        {(config.reorder!.canReorder?.(it) ?? true) && (
                          <span draggable role="button" tabIndex={0} aria-label={`Flytta ${it[config.titleKey]} (dra, eller använd pil upp/ner)`} title="Dra för att flytta"
                            className="inline-flex cursor-grab rounded p-0.5 text-muted-foreground hover:bg-accent active:cursor-grabbing"
                            onDragStart={(e) => { setDragId(it.id); e.dataTransfer.effectAllowed = "move"; e.dataTransfer.setData("text/plain", it.id); }}
                            onDragEnd={() => { setDragId(null); setDropIndex(null); }}
                            onKeyDown={(e) => { if (e.key === "ArrowUp") { e.preventDefault(); move(it, -1); } if (e.key === "ArrowDown") { e.preventDefault(); move(it, 1); } }}>
                            <GripVertical className="h-4 w-4" />
                          </span>
                        )}
                      </td>
                    )}
                    {tableCols.map((k) => <td key={k} className="px-3 py-2">{renderValue(k, it[k], it)}</td>)}
                    {extraCols > 0 && (
                      <td className="px-3 py-2"><div className="flex flex-wrap gap-1">
                        {childSections.map((s) => {
                          const on = openChild[it.id] === s.key;
                          return (
                            <button key={s.key} aria-expanded={on} className={`${btnOutline} px-2 py-1 text-xs ${on ? "border-primary bg-accent" : ""}`}
                              onClick={() => setOpenChild((p) => { const n = { ...p }; on ? delete n[it.id] : (n[it.id] = s.key); return n; })}>
                              <ChevronRight className={`h-3.5 w-3.5 transition-transform ${on ? "rotate-90" : ""}`} />{s.label}{Array.isArray(it[s.key]) ? ` (${it[s.key].length})` : ""}
                            </button>
                          );
                        })}
                      </div></td>
                    )}
                    <td className="px-3 py-2"><div className="flex gap-1">
                      {actions(it)}</div></td>
                  </tr>
                  {openChild[it.id] && (() => {
                    const s = childSections.find((x) => x.key === openChild[it.id])!;
                    return (
                      <tr className="bg-background"><td colSpan={tableCols.length + 2 + extraCols + (canMove ? 1 : 0) + 1} className="py-3 pl-8 pr-3">
                        <div className="mb-2 flex items-center justify-between">
                          <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{s.label} för {it[config.titleKey]}</h4>
                          <button className={`${btnOutline} px-2 py-1 text-xs`} onClick={() => setOpenChild((p) => { const n = { ...p }; delete n[it.id]; return n; })}><X className="h-3.5 w-3.5" />Stäng</button>
                        </div>
                        {childTable(it, s)}
                      </td></tr>
                    );
                  })()}
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <Accordion.Root type="multiple">
          {pageItems.map((it) => (
            <Accordion.Item key={it.id} value={it.id} className="border-b">
              <Accordion.Header className="flex items-center">
                <Accordion.Trigger className="group flex flex-1 items-center gap-3 px-4 py-3 text-left hover:bg-surface-hover">
                  <ChevronRight className="h-4 w-4 transition-transform group-data-[state=open]:rotate-90" />
                  <div><div className="font-medium">{it[config.titleKey]}{statusMark(it)}</div><div className="text-xs text-muted-foreground">{config.subtitleKeys.map((k) => it[k]).filter(Boolean).join(" · ")}</div></div>
                </Accordion.Trigger>
                <div className="pr-3">{actions(it)}</div>
              </Accordion.Header>
              <Accordion.Content className="bg-background px-6 py-4">{details(it)}</Accordion.Content>
            </Accordion.Item>
          ))}
        </Accordion.Root>
      )}

      </div>
      {/* Pagination (FK-16): döljs om servern inte anger totalt antal. */}
      {totalCount != null && <div className="flex flex-wrap items-center justify-between gap-2 border-t px-3 py-2 text-sm">
        <span className="text-muted-foreground">Visar {range.from}–{range.to} av {range.total}</span>
        <div className="flex flex-wrap items-center gap-2">
          {(config.pageSizeOptions ?? DEFAULT_PAGE_SIZES).length > 0 && (
            <div className="flex items-center gap-2">
              <Label.Root className="text-muted-foreground">Objekt per sida</Label.Root>
              <RSelect label="Objekt per sida" value={String(pageSize)} onChange={(v) => { setPageSize(Number(v)); setPage(1); }} placeholder=""
                options={so(config.pageSizeOptions ?? DEFAULT_PAGE_SIZES)} />
            </div>
          )}
          <div className="flex items-center gap-1">
            <button className={`${btnOutline} px-2`} aria-label="Första sidan" disabled={current <= 1} onClick={() => setPage(1)}><ChevronsLeft className="h-4 w-4" /></button>
            <button className={`${btnOutline} px-2`} aria-label="Föregående sida" disabled={current <= 1} onClick={() => setPage(current - 1)}><ChevronLeft className="h-4 w-4" /></button>
            <span className="flex items-center gap-1 px-1 text-muted-foreground">Sida
              <span className="w-20"><RSelect label="Gå till sida" value={String(current)} onChange={(v) => setPage(Number(v))} placeholder=""
                options={so(Array.from({ length: totalPages }, (_, i) => i + 1))} /></span>
              av {totalPages}</span>
            <button className={`${btnOutline} px-2`} aria-label="Nästa sida" disabled={current >= totalPages} onClick={() => setPage(current + 1)}><ChevronRight className="h-4 w-4" /></button>
            <button className={`${btnOutline} px-2`} aria-label="Sista sidan" disabled={current >= totalPages} onClick={() => setPage(totalPages)}><ChevronsRight className="h-4 w-4" /></button>
          </div>
        </div>
      </div>}

      {/* Filter & sort dialog */}
      <Dialog.Root open={filterOpen} onOpenChange={setFilterOpen}>
        <Dialog.Portal>
          <Dialog.Overlay className={overlay} />
          <Dialog.Content className={dialogBox}>
            <Dialog.Title className="mb-4 text-lg font-semibold">Filter och sortering</Dialog.Title>
            <Dialog.Description className="sr-only">Välj sortering och filter för listan.</Dialog.Description>
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Sortering</h3>
            <div className="mb-5 grid grid-cols-2 gap-3">
              <div><Label.Root className="mb-1 block text-sm">Sortera på</Label.Root>
                <RSelect label="Sortera på" value={sort.key ?? NONE} onChange={(v) => setSort((s) => ({ ...s, key: v === NONE ? null : v }))} placeholder="Ingen"
                  options={[{ value: NONE, label: "Ingen" }, ...config.columns.filter((c) => c.sortable).map((c) => ({ value: c.key, label: c.label }))]} /></div>
              <div><Label.Root className="mb-1 block text-sm">Riktning</Label.Root>
                <RSelect label="Riktning" value={sort.dir} onChange={(v) => setSort((s) => ({ ...s, dir: v as "asc" | "desc" }))} placeholder=""
                  options={[{ value: "asc", label: "Stigande (A–Ö)" }, { value: "desc", label: "Fallande (Ö–A)" }]} /></div>
            </div>
            {config.filters.length > 0 && <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Filter</h3>}
            <div className="space-y-3">
              {config.filters.map((f) => (
                <div key={f.key}><Label.Root className="mb-1 block text-sm">{f.label}{f.mode === "multi" && <span className="ml-1 text-xs text-muted-foreground">(flerval)</span>}{f.mode === "tags" && <span className="ml-1 text-xs text-muted-foreground">(flerval, sök tagg)</span>}</Label.Root>
                  {f.mode === "multi" ? (
                    <MultiSelect label={f.label} options={f.options ?? allValues(f.key)} value={(filters[f.key] as string[]) ?? []} onChange={(v) => setFilter(f.key, v)} />
                  ) : f.mode === "tags" ? (
                    <TagSelect label={f.label} id={`filter-${f.key}`} allowCreate={false} suggestions={f.options ?? allValues(f.key)} value={(filters[f.key] as string[]) ?? []} onChange={(v) => setFilter(f.key, v)} />
                  ) : (
                    <RSelect label={f.label} value={(filters[f.key] as string) || ALL} placeholder="Alla" onChange={(v) => setFilter(f.key, v === ALL ? "" : v)}
                      options={[{ value: ALL, label: "Alla" }, ...so(f.options ?? allValues(f.key))]} />
                  )}</div>
              ))}
            </div>
            <div className="mt-6 flex justify-between">
              <button className={btnOutline} onClick={() => { setFilters({}); setSort({ key: null, dir: "asc" }); setPage(1); }}>Rensa alla</button>
              <Dialog.Close className={btnPrimary}>Klar</Dialog.Close>
            </div>
          </Dialog.Content>
        </Dialog.Portal>
      </Dialog.Root>

      {/* Edit popup */}
      <Dialog.Root open={config.editMode === "popup" && !!editing} onOpenChange={(o) => !o && setEditing(null)}>
        <Dialog.Portal>
          <Dialog.Overlay className={overlay} />
          <Dialog.Content
            className={dialogBox}
            // Skapa/redigera får inte tappas genom ett klick utanför popupen.
            onPointerDownOutside={(e) => e.preventDefault()}
            onInteractOutside={(e) => e.preventDefault()}
          >
            <Dialog.Title className="mb-4 text-lg font-semibold">{isNew ? "Skapa nytt objekt" : "Redigera objekt"}</Dialog.Title>
            <Dialog.Description className="sr-only">Fyll i fälten och spara.</Dialog.Description>
            {editing && <ItemForm tagSuggestions={allValues} config={config} item={editing} isNew={isNew} onCancel={() => setEditing(null)} onSave={save} />}
          </Dialog.Content>
        </Dialog.Portal>
      </Dialog.Root>

      {/* Bulk edit */}
      <Dialog.Root open={!!bulkStep} onOpenChange={(o) => !o && setBulkStep(null)}>
        <Dialog.Portal>
          <Dialog.Overlay className={overlay} />
          <Dialog.Content className={dialogBox} onPointerDownOutside={(e) => e.preventDefault()} onInteractOutside={(e) => e.preventDefault()}>
            <Dialog.Title className="mb-1 text-lg font-semibold">Redigera {checked.size} markerade</Dialog.Title>
            <Dialog.Description className="mb-4 text-sm text-muted-foreground">
              {bulkStep === "fields" ? "Välj vilka egenskaper du vill ändra." : "De nya värdena sätts på alla markerade objekt."}
              {checkedItems.some((i) => !canUpdate(i)) && ` ${checkedItems.filter((i) => !canUpdate(i)).length} av dem får inte ändras och hoppas över.`}
            </Dialog.Description>
            {bulkStep === "fields" ? (
              <>
                <div className="space-y-2">
                  {bulkEditable.map((f) => (
                    <label key={f.key} className="flex cursor-pointer items-center gap-2 text-sm">
                      <Checkbox.Root className={cbCls} checked={bulkFields.includes(f.key)} onCheckedChange={() => setBulkFields((b) => (b.includes(f.key) ? b.filter((x) => x !== f.key) : [...b, f.key]))}>
                        <Checkbox.Indicator><Check className="h-3 w-3" /></Checkbox.Indicator>
                      </Checkbox.Root>
                      {f.label}
                    </label>
                  ))}
                </div>
                <div className="mt-6 flex justify-end gap-2">
                  <button className={btnOutline} onClick={() => setBulkStep(null)}>Avbryt</button>
                  <button className={btnPrimary} disabled={!bulkFields.length} onClick={() => setBulkStep("form")}>Nästa</button>
                </div>
              </>
            ) : bulkStep === "form" ? (
              <ItemForm key={bulkFields.join(",")} tagSuggestions={allValues} config={bulkConfig} item={bulkStart()} isNew={false} onCancel={() => setBulkStep("fields")} onSave={saveBulk} />
            ) : null}
          </Dialog.Content>
        </Dialog.Portal>
      </Dialog.Root>

      {/* Bulk delete */}
      <AlertDialog.Root open={bulkDelete} onOpenChange={(o) => !o && !bulkBusy && setBulkDelete(false)}>
        <AlertDialog.Portal>
          <AlertDialog.Overlay className={overlay} />
          <AlertDialog.Content className={dialogBox}>
            <AlertDialog.Title className="text-lg font-semibold">Ta bort {checked.size} objekt?</AlertDialog.Title>
            <AlertDialog.Description className="mt-2 text-sm text-muted-foreground">
              De markerade objekten tas bort permanent.
              {checkedItems.some((i) => !canDelete(i)) && ` ${checkedItems.filter((i) => !canDelete(i)).length} av dem får inte tas bort och hoppas över.`}
            </AlertDialog.Description>
            <ul className="mt-3 max-h-40 list-disc overflow-y-auto pl-5 text-sm">{checkedItems.map((i) => <li key={i.id} className={canDelete(i) ? "" : "text-muted-foreground line-through"}>{i[config.titleKey]}</li>)}</ul>
            <div className="mt-6 flex justify-end gap-2">
              <AlertDialog.Cancel className={btnOutline} disabled={bulkBusy}>Avbryt</AlertDialog.Cancel>
              <AlertDialog.Action className={btnDanger} disabled={bulkBusy || !checkedItems.some(canDelete)} onClick={(e) => { e.preventDefault(); runBulkDelete(); }}>{bulkBusy ? "Tar bort…" : "Ta bort"}</AlertDialog.Action>
            </div>
          </AlertDialog.Content>
        </AlertDialog.Portal>
      </AlertDialog.Root>

      {/* Delete confirm */}
      <AlertDialog.Root open={!!deleting} onOpenChange={(o) => { if (!o) { setDeleting(null); setDeleteError(null); } }}>
        <AlertDialog.Portal>
          <AlertDialog.Overlay className={overlay} />
          <AlertDialog.Content className={dialogBox}>
            <AlertDialog.Title className="text-lg font-semibold">Ta bort objekt?</AlertDialog.Title>
            <AlertDialog.Description className="mt-2 text-sm text-muted-foreground">"{deleting?.[config.titleKey]}" tas bort permanent.</AlertDialog.Description>
            {deleteError && <p role="alert" className="mt-3 text-sm text-destructive">{deleteError}</p>}
            <div className="mt-6 flex justify-end gap-2">
              <AlertDialog.Cancel className={btnOutline}>Avbryt</AlertDialog.Cancel>
              <AlertDialog.Action className={btnDanger} onClick={(e) => { if (ds) e.preventDefault(); if (deleting) remove(deleting); }}>Ta bort</AlertDialog.Action>
            </div>
          </AlertDialog.Content>
        </AlertDialog.Portal>
      </AlertDialog.Root>
    </div>
  );
}

function today() { return new Date().toISOString().slice(0, 10); }

type FormEntry = { kind: "col"; col: ColumnConfig; readOnly: boolean } | { kind: "section"; section: DetailSection };

function ItemForm({ config, item, isNew, onSave, onCancel, tagSuggestions }: { tagSuggestions: (key: string) => string[]; config: ModuleConfig; item: Item; isNew: boolean; onSave: (i: Item) => Promise<{ fields?: Record<string, string>; message?: string } | null>; onCancel: () => void }) {
  const [saving, setSaving] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [draft, setDraft] = useState<Item>(item);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const tabs = config.formTabs?.length ? config.formTabs : null;
  const [tab, setTab] = useState(tabs?.[0] ?? "");

  const inForm = (c: ColumnConfig) => {
    if (c.key === "createdAt" || c.key === "updatedAt") return false;
    const f = c.form ?? "both";
    if (f === "none") return false;
    if (f === "create") return isNew;
    if (f === "edit" || f === "readonly-edit") return !isNew;
    return true;
  };
  const entries: FormEntry[] = [
    ...config.columns.filter(inForm).map((c) => ({ kind: "col" as const, col: c, readOnly: c.form === "readonly-edit" })),
    ...config.detailSections.filter((s) => !s.childConfig).map((s) => ({ kind: "section" as const, section: s })),
  ];
  const tabOf = (e: FormEntry) => {
    const t = e.kind === "col" ? e.col.tab : e.section.tab;
    return tabs && t && tabs.includes(t) ? t : tabs?.[0] ?? "";
  };
  const fs = config.formState?.(draft, { isNew }) ?? {};
  useEffect(() => {
    if (!fs.values) return;
    const diff = Object.entries(fs.values).filter(([k, v]) => draft[k] !== v);
    if (diff.length) setDraft((d) => ({ ...d, ...Object.fromEntries(diff) }));
  }); // eslint-disable-line react-hooks/exhaustive-deps
  const isHidden = (k: string) => fs.hidden?.includes(k);
  const isDisabled = (k: string) => fs.disabled?.includes(k);
  const rule = (c: ColumnConfig) => ({ ...c, required: c.required || c.key === config.titleKey || !!fs.required?.includes(c.key) });

  const set = (k: string, v: any) => {
    setErrors((e) => { if (!e[k]) return e; const n = { ...e }; delete n[k]; return n; });
    setDraft((d) => {
      let next = { ...d, [k]: v };
      // FK-26: beroende val — rensa värden som inte längre finns bland alternativen, i flera led.
      for (let pass = 0; pass < 5; pass++) {
        let changed = false;
        for (const c of config.columns) {
          if (!c.dependsOn || !c.optionsFor || next[c.key] == null || next[c.key] === "") continue;
          const allowed = colOptions(c, next).map((o) => o.value);
          const cur = next[c.key];
          if (Array.isArray(cur) ? cur.some((x) => !allowed.includes(x)) : !allowed.includes(String(cur))) {
            next = { ...next, [c.key]: Array.isArray(cur) ? cur.filter((x) => allowed.includes(x)) : "" };
            changed = true;
          }
        }
        if (!changed) break;
      }
      return next;
    });
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const errs: Record<string, string> = {};
    let firstBad: FormEntry | null = null;
    for (const en of entries) {
      if (en.kind !== "col" || en.readOnly || isHidden(en.col.key) || isDisabled(en.col.key)) continue;
      const msg = validateField(rule(en.col), draft[en.col.key]);
      if (msg) { errs[en.col.key] = msg; firstBad ??= en; }
    }
    setErrors(errs);
    if (firstBad && firstBad.kind === "col") {
      const key = firstBad.col.key;
      if (tabs) setTab(tabOf(firstBad));
      setTimeout(() => document.getElementById(`f-${key}`)?.focus(), 30);
      return;
    }
    if (saving) return;
    setSaving(true);
    setServerError(null);
    onSave(draft).then((res) => {
      setSaving(false);
      if (!res) return;
      // FK-23/40: visa serverns fel, behåll inmatade värden.
      if (res.fields && Object.keys(res.fields).length) {
        setErrors(res.fields);
        const bad = entries.find((en) => en.kind === "col" && res.fields![en.col.key]);
        if (bad && tabs) setTab(tabOf(bad));
      }
      setServerError(res.message ?? "Det gick inte att spara.");
    });
  };

  const field = (en: FormEntry) => {
    if (en.kind === "section") {
      const s = en.section;
      return (
        <div key={s.key}>
          <Label.Root htmlFor={`f-${s.key}`} className="mb-1 block text-sm">{s.label}{s.type === "tags" && " (sök eller skapa ny med Enter)"}{s.type === "properties" && " (en per rad: nyckel: värde)"}</Label.Root>
          {s.type === "textarea" && <textarea id={`f-${s.key}`} rows={3} className={inputCls} value={draft[s.key] ?? ""} onChange={(e) => set(s.key, e.target.value)} />}
          {s.type === "table" && <NestedTableEditor section={s} rows={draft[s.key] ?? []} onChange={(v) => set(s.key, v)} />}
          {s.type === "tags" && <TagSelect label={s.label} id={`f-${s.key}`} suggestions={tagSuggestions(s.key)} value={draft[s.key] ?? []} onChange={(v) => set(s.key, v)} />}
          {s.type === "properties" && (
            <textarea id={`f-${s.key}`} rows={4} className={`${inputCls} font-mono`} defaultValue={Object.entries(draft[s.key] ?? {}).map(([k, v]) => `${k}: ${v}`).join("\n")}
              onChange={(e) => set(s.key, Object.fromEntries(e.target.value.split("\n").map((l) => l.split(/:([\s\S]*)/).map((x) => x.trim())).filter(([k]) => k).map(([k, v]) => [k, v ?? ""])))} />
          )}
        </div>
      );
    }
    const c = en.col;
    if (isHidden(c.key)) return null;
    const id = `f-${c.key}`;
    const err = errors[c.key];
    const errId = `${id}-err`;
    const common = { id, "aria-invalid": err ? true : undefined, "aria-describedby": err ? errId : undefined, className: `${inputCls} ${err ? "border-destructive" : ""}` };
    const v = draft[c.key];
    const req = rule(c).required;
    let control: React.ReactNode;
    if (en.readOnly || isDisabled(c.key)) {
      control = <p className="rounded-md bg-muted px-3 py-1.5 text-sm">{String(v ?? "–")}</p>;
    } else if (c.type === "select") {
      const opts = colOptions(c, draft);
      const chosen = opts.find((o) => o.value === String(v ?? ""));
      control = (
        <>
          <RSelect id={id} label={c.label} invalid={!!err} value={v ? String(v) : ""} onChange={(x) => set(c.key, x)} placeholder={c.dependsOn && !opts.length ? "Välj först " + (config.columns.find((x) => x.key === c.dependsOn)?.label ?? "") : "Välj…"} options={opts} />
          {chosen?.description && <p className="mt-1 text-xs text-muted-foreground">{chosen.description}</p>}
        </>
      );
    } else if (c.type === "textarea") {
      control = <textarea {...common} rows={3} value={v ?? ""} onChange={(e) => set(c.key, e.target.value)} />;
    } else if (c.type === "number") {
      control = <input {...common} type="number" min={c.min} max={c.max} step={c.step} value={v ?? ""} onChange={(e) => set(c.key, e.target.value === "" ? "" : Number(e.target.value))} />;
    } else if (c.type === "date") {
      control = <input {...common} type="date" value={formatDate(v)} onChange={(e) => set(c.key, e.target.value)} />;
    } else if (c.type === "datetime") {
      control = <input {...common} type="datetime-local" value={v ? formatDate(v, true).replace(" ", "T") : ""} onChange={(e) => set(c.key, e.target.value)} />;
    } else if (c.type === "file") {
      control = (
        <>
          <input {...common} type="file" accept={c.accept} onChange={(e) => { const f = e.target.files?.[0]; set(c.key, f ? { name: f.name, size: f.size, type: f.type } : null); }} />
          {v?.name && <p className="mt-1 text-xs text-muted-foreground">Vald fil: {v.name}</p>}
        </>
      );
    } else {
      control = <input {...common} value={v ?? ""} onChange={(e) => set(c.key, e.target.value)} />;
    }
    return (
      <div key={c.key}>
        <Label.Root htmlFor={id} className="mb-1 block text-sm">{c.label}{req && !en.readOnly && <span className="text-destructive"> *</span>}</Label.Root>
        {control}
        {c.help && <p className="mt-1 text-xs text-muted-foreground">{c.help}</p>}
        {err && <p id={errId} role="alert" className="mt-1 text-xs text-destructive">{err}</p>}
      </div>
    );
  };

  const visibleIn = (t: string) => entries.filter((en) => tabOf(en) === t && !(en.kind === "col" && isHidden(en.col.key)));
  const tabHasError = (t: string) => entries.some((en) => en.kind === "col" && errors[en.col.key] && tabOf(en) === t);

  return (
    <form noValidate className="space-y-3" onSubmit={submit}>
      {tabs ? (
        <Tabs.Root value={tab} onValueChange={setTab}>
          <Tabs.List aria-label="Formulärflikar" className="mb-3 flex border-b">
            {tabs.map((t) => (
              <Tabs.Trigger key={t} value={t} className={`-mb-px inline-flex items-center gap-1.5 border-b-2 border-transparent px-3 py-1.5 text-sm data-[state=active]:border-primary data-[state=active]:font-medium ${tabHasError(t) ? "text-destructive" : ""}`}>
                {t}{tabHasError(t) && <span aria-label="innehåller fel" className="h-2 w-2 rounded-full bg-destructive" />}
              </Tabs.Trigger>
            ))}
          </Tabs.List>
          {tabs.map((t) => (
            <Tabs.Content key={t} value={t} className="space-y-3">{entries.filter((en) => tabOf(en) === t).map(field)}</Tabs.Content>
          ))}
        </Tabs.Root>
      ) : entries.map(field)}
      {serverError && <p role="alert" className="text-sm text-destructive">{serverError}</p>}
      {Object.keys(errors).length > 0 && !serverError && <p className="text-sm text-destructive">Rätta de markerade fälten innan du sparar.</p>}
      <div className="flex justify-end gap-2 pt-2">
        <button type="button" className={btnOutline} onClick={onCancel}>Avbryt</button>
        <button type="submit" disabled={saving} className={btnPrimary}>{saving ? "Sparar…" : "Spara"}</button>
      </div>
    </form>
  );
}
function NestedTable({ section, rows }: { section: { columns?: { key: string; label: string }[] }; rows: Record<string, any>[] }) {
  const cols = section.columns ?? [];
  if (!rows.length) return <p className="text-sm text-muted-foreground">Inga rader.</p>;
  return (
    <div className="overflow-x-auto rounded-md border">
      <table className="w-full text-sm">
        <thead className="bg-muted/60 text-left"><tr>{cols.map((c) => <th key={c.key} className="px-3 py-1.5 font-medium">{c.label}</th>)}</tr></thead>
        <tbody>{rows.map((r, i) => <tr key={i} className="border-t">{cols.map((c) => <td key={c.key} className="px-3 py-1.5">{String(r[c.key] ?? "")}</td>)}</tr>)}</tbody>
      </table>
    </div>
  );
}

function NestedTableEditor({ section, rows, onChange }: { section: { label: string; columns?: { key: string; label: string }[] }; rows: Record<string, any>[]; onChange: (r: Record<string, any>[]) => void }) {
  const cols = section.columns ?? [];
  const update = (i: number, k: string, v: string) => onChange(rows.map((r, j) => (j === i ? { ...r, [k]: v } : r)));
  return (
    <div className="space-y-2">
      {rows.length > 0 && (
        <table className="w-full text-sm">
          <thead className="text-left"><tr>{cols.map((c) => <th key={c.key} className="pb-1 pr-2 font-normal text-muted-foreground">{c.label}</th>)}<th /></tr></thead>
          <tbody>{rows.map((r, i) => (
            <tr key={i}>
              {cols.map((c) => <td key={c.key} className="pb-1 pr-2"><input aria-label={`${c.label} rad ${i + 1}`} className={inputCls} value={r[c.key] ?? ""} onChange={(e) => update(i, c.key, e.target.value)} /></td>)}
              <td className="pb-1"><button type="button" aria-label={`Ta bort rad ${i + 1}`} className={`${btnOutline} px-2 text-destructive`} onClick={() => onChange(rows.filter((_, j) => j !== i))}><Trash2 className="h-4 w-4" /></button></td>
            </tr>
          ))}</tbody>
        </table>
      )}
      <button type="button" className={btnOutline} onClick={() => onChange([...rows, Object.fromEntries(cols.map((c) => [c.key, ""]))])}><Plus className="h-4 w-4" />Lägg till rad</button>
    </div>
  );
}
