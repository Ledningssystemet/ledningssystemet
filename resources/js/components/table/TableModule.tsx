import { useEffect, useImperativeHandle, useMemo, useRef, useState, type MutableRefObject } from "react";
import { Pencil, Trash2, X } from "lucide-react";
import type { ColumnConfig, DetailSection, Item, ModuleConfig, ModuleController, SortState, ViewMode } from "./types";
import { bulkPatch, filterItems, moveItem, formatDate, normalizeSearch, pageRange, paginate, SEARCH_DEBOUNCE_MS, SEARCH_MAX, sortItems } from "./logic";
import { ApiError, crud } from "./api";
import React from "react";
import { ItemForm } from "./forms/ItemForm";
import { NestedTable } from "./components/NestedTable";
import { TableDialogs } from "./dialogs";
import { btnOutline } from "./helpers/styles";
import { TablePagination } from "./components/TablePagination";
import { TableToolbar } from "./components/TableToolbar";
import { AccordionView } from "./components/AccordionView";
import { DataTableView } from "./components/DataTableView";
import { ItemDetails } from "./components/ItemDetails";
import { MasterDetailView } from "./components/MasterDetailView";
import { RowActions } from "./components/RowActions";
import { DEFAULT_PAGE_SIZES, initialPageSize, initialView, writeCookie } from "./helpers/persistence";
import { colOptions, customToColumns, linkState } from "./helpers/tableConfig";
import { badgeTone, dotTone } from "./helpers/tones";
// @ts-ignore
import "./table.css";
// @ts-ignore
import "./table.utilities.css";

interface Props {
  config: ModuleConfig;
  data: Item[];
  onDataChange?: (data: Item[]) => void;
  /** FK-32: styr tabellen utifrån. */
  controllerRef?: MutableRefObject<ModuleController | null>;
  /** Förälderns ID för underordnad tabell i API-läge (FK-34). */
  parentId?: string;
}

function today() { return new Date().toISOString().slice(0, 10); }

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
  const canUpdate = (it: Item) => it.access?.update !== false && it.accessFlags?.update !== false && (typeof perm?.update === "function" ? perm.update(it) : perm?.update !== false);
  const canDelete = (it: Item) => it.access?.delete !== false && it.accessFlags?.delete !== false && (typeof perm?.delete === "function" ? perm.delete(it) : perm?.delete !== false);

  const colLabel = (k: string) => config.columns.find((c) => c.key === k)?.label ?? k;
  const listCols = config.columns.filter((c) => !c.listHidden);
  const tableCols = config.tableColumns.filter((k) => !config.columns.find((c) => c.key === k)?.listHidden);
  const childSections = config.detailSections.filter((s) => s.type === "table");
  const ds = config.dataSource;
  const dsKey = ds ? JSON.stringify(ds) : "";
  const searchKeysKey = JSON.stringify(config.searchKeys);
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
    crud.list(ds, { search: idFilter || config.reorder ? "" : search, searchKeys: config.searchKeys ?? [], filters: f, page, perPage: pageSize, sort: config.reorder ? { key: null, dir: "asc" } : sort })
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
  }, [dsKey, search, searchKeysKey, filters, page, pageSize, sort, idFilter, parentId, reloadKey]); // eslint-disable-line react-hooks/exhaustive-deps
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
  const [bulkStep, setBulkStep] = useState<"fields" | "form" | null>(null);
  const [bulkFields, setBulkFields] = useState<string[]>([]);
  const [bulkDelete, setBulkDelete] = useState(false);
  const [bulkMsg, setBulkMsg] = useState<string | null>(null);
  const [bulkBusy, setBulkBusy] = useState(false);
  const checkedItems = items.filter((i) => checked.has(i.id));
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
        if (isNew && config.retainCreatedItem) {
          setItems((current) => [saved, ...current.filter((existing) => existing.id !== saved.id)].slice(0, pageSize));
          setTotal((current) => current === null ? null : current + 1);
        } else {
          if (isNew) setPage(1);
          reload();
        }
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
    <RowActions
      config={config}
      item={item}
      canUpdate={canUpdate(item)}
      canDelete={canDelete(item)}
      onRunCommand={runCommand}
      onEdit={startEdit}
      onDelete={setDeleting}
    />
  );

  const childTable = (item: Item, s: DetailSection) =>
    s.childConfig ? (
      s.childConfig.dataSource
        ? <TableModule key={`${item.id}-${s.key}`} config={{ persistState: false, ...s.childConfig }} data={[]} parentId={item.id} />
        : <TableModule key={`${item.id}-${s.key}`} config={{ persistState: false, ...s.childConfig }} data={item[s.key] ?? []} onDataChange={(rows) => updateChildRows(item.id, s.key, rows)} />
    ) : <NestedTable section={s} rows={item[s.key] ?? []} />;

  const details = (item: Item) => (
    <ItemDetails
      config={config}
      item={item}
      listColumns={listCols}
      editing={editing}
      inlineEditing={!!inlineEditing}
      tagSuggestions={allValues}
      renderValue={renderValue}
      renderChildTable={childTable}
      onCancelEdit={() => setEditing(null)}
      onSave={save}
    />
  );

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
    <div className="ls-table">
    <div className="rounded-lg border bg-card shadow-sm">
      <TableToolbar
        title={config.listTitle}
        count={totalCount ?? items.length}
        reorderMode={reorderMode}
        idFilter={idFilter}
        searchInput={searchInput}
        onSearchChange={setSearchInput}
        searchMax={SEARCH_MAX}
        activeFilterCount={activeFilterCount}
        view={view}
        onViewChange={setView}
        canCreate={canCreate}
        onCreate={startNew}
        onOpenFilters={() => setFilterOpen(true)}
      />

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
        <MasterDetailView
          items={pageItems}
          config={config}
          selected={selected}
          selectedId={selectedId}
          onSelect={setSelectedId}
          renderStatus={statusMark}
          renderActions={actions}
          renderDetails={details}
        />
      ) : effView === "table" ? (
        <DataTableView
          items={pageItems}
          config={config}
          columns={tableCols}
          childSections={childSections}
          canMove={canMove}
          isReorderMode={reorderMode}
          checked={checked}
          onToggle={toggleCheck}
          onTogglePage={(checkedOnPage) => setChecked((currentChecked) => {
            const next = new Set(currentChecked);
            pageItems.forEach((item) => checkedOnPage ? next.add(item.id) : next.delete(item.id));
            return next;
          })}
          sort={sort}
          onSortChange={setSort}
          editing={editing}
          isInlineEditing={!!inlineEditing}
          isNew={isNew}
          tagSuggestions={allValues}
          onCancelEdit={() => setEditing(null)}
          onSave={save}
          renderValue={renderValue}
          renderActions={actions}
          onMove={moveTo}
          onMoveBy={move}
          dragId={dragId}
          setDragId={setDragId}
          dropIndex={dropIndex}
          setDropIndex={setDropIndex}
          openChild={openChild}
          onToggleChild={(itemId, sectionKey) => setOpenChild((current) => {
            const next = { ...current };
            if (next[itemId] === sectionKey) delete next[itemId];
            else next[itemId] = sectionKey;
            return next;
          })}
          onCloseChild={(itemId) => setOpenChild((current) => {
            const next = { ...current };
            delete next[itemId];
            return next;
          })}
          renderChildTable={childTable}
        />
      ) : (
        <AccordionView items={pageItems} config={config} renderStatus={statusMark} renderActions={actions} renderDetails={details} />
      )}

      </div>
      {/* Pagination (FK-16): döljs om servern inte anger totalt antal. */}
      {totalCount != null && (
        <TablePagination
          range={range}
          page={current}
          pageSize={pageSize}
          totalPages={totalPages}
          pageSizeOptions={config.pageSizeOptions ?? DEFAULT_PAGE_SIZES}
          onPageChange={setPage}
          onPageSizeChange={(size) => { setPageSize(size); setPage(1); }}
        />
      )}

      <TableDialogs
        config={config}
        filters={filters}
        setFilters={setFilters}
        setFilter={setFilter}
        allValues={allValues}
        sort={sort}
        setSort={setSort}
        setPage={setPage}
        filterOpen={filterOpen}
        setFilterOpen={setFilterOpen}
        editing={editing}
        setEditing={setEditing}
        isNew={isNew}
        save={save}
        bulkStep={bulkStep}
        setBulkStep={setBulkStep}
        checkedCount={checked.size}
        checkedItems={checkedItems}
        canUpdate={canUpdate}
        bulkFields={bulkFields}
        setBulkFields={setBulkFields}
        bulkConfig={bulkConfig}
        bulkStart={bulkStart}
        saveBulk={saveBulk}
        bulkDelete={bulkDelete}
        setBulkDelete={setBulkDelete}
        bulkBusy={bulkBusy}
        runBulkDelete={runBulkDelete}
        deleting={deleting}
        setDeleting={setDeleting}
        deleteError={deleteError}
        setDeleteError={setDeleteError}
        canDelete={canDelete}
        remove={remove}
      />

    </div>
    </div>
  );
}
