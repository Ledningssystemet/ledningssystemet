import { useState, useCallback, useEffect, useRef } from "react";
import { CrudModuleConfig, CrudState, ViewMode } from "./types";

const buildQueryString = (
  config: CrudModuleConfig,
  state: Pick<CrudState, "search" | "filters" | "sort" | "sortDirection" | "page" | "perPage">
) => {
  const params = new URLSearchParams();
  const primaryKey = config.primaryKey || "id";
  const configuredSelectFields =
    config.selectFields?.length
      ? config.selectFields
      : config.fields.map((field) => field.key);

  if (configuredSelectFields.length) {
    const selectedFields = Array.from(
      new Set(configuredSelectFields.map((field) => field.trim()).filter((field) => field !== "").concat(primaryKey))
    );

    if (selectedFields.length > 0) {
      params.set("$select", selectedFields.join(","));
    }
  }

  if (state.search) {
    // GenericCrud expects top-level `search`
    params.set("search", state.search);
  }

  Object.entries(state.filters).forEach(([key, value]) => {
    if (value !== undefined && value !== "" && value !== null) {
      if (Array.isArray(value) && value.length > 0) {
        params.set(`filter[${key}]`, value.join(","));
      } else if (!Array.isArray(value)) {
        params.set(`filter[${key}]`, String(value));
      }
    }
  });

  if (state.sort) {
    params.set("sort", state.sortDirection === "desc" ? `-${state.sort}` : state.sort);
  } else if (config.defaultSort) {
    params.set("sort", config.defaultSort);
  }

  if (config.includes?.length) {
    params.set("include", config.includes.join(","));
  }

  params.set("paginate", "1");
  params.set("page", String(state.page));
  params.set("per_page", String(state.perPage));

  return params.toString();
};

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function setCookie(name: string, value: string) {
  document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`;
}

function toCookieScope(apiUrl: string): string {
  return apiUrl.replace(/[^a-zA-Z0-9_-]/g, "_");
}

function scopedCookieKey(baseKey: string, apiUrl: string): string {
  return `${baseKey}__${toCookieScope(apiUrl)}`;
}

function resolvePersistedSort(config: CrudModuleConfig): string {
  const scoped = getCookie(scopedCookieKey("crud_sort", config.apiUrl));
  const legacy = getCookie("crud_sort");
  const candidate = scoped ?? legacy ?? "";

  if (!candidate) {
    return "";
  }

  const sortableFields = new Set(
    config.fields
      .filter((field) => field.sortable !== false)
      .map((field) => field.key)
  );

  return sortableFields.has(candidate) ? candidate : "";
}

function resolvePersistedSortDirection(config: CrudModuleConfig): "asc" | "desc" {
  const scoped = getCookie(scopedCookieKey("crud_sort_dir", config.apiUrl));
  const legacy = getCookie("crud_sort_dir");
  const candidate = scoped ?? legacy;

  return candidate === "desc" ? "desc" : "asc";
}

function getPerPageFromCookie(fallback: number): number {
  const val = getCookie("crud_per_page");
  return val ? Number(val) : fallback;
}

export function useCrudModule(config: CrudModuleConfig) {
  const [state, setState] = useState<CrudState>(() => ({
    items: [],
    selectedItems: new Set(),
    activeItem: null,
    search: "",
    filters: {},
    sort: resolvePersistedSort(config),
    sortDirection: resolvePersistedSortDirection(config),
    page: 1,
    perPage: getPerPageFromCookie(config.perPage || 25),
    totalPages: 1,
    total: 0,
    loading: false,
    viewMode: (getCookie("crud_view_mode") as ViewMode) || "table",
  }));

  const abortRef = useRef<AbortController | null>(null);

  const primaryKey = config.primaryKey || "id";

  const fetchItems = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setState((s) => ({ ...s, loading: true }));

    try {
      const qs = buildQueryString(config, state);
      const res = await fetch(`${config.apiUrl}?${qs}`, {
        signal: controller.signal,
        headers: { Accept: "application/json" },
      });
      const json = await res.json();
      const nextItems = json.data || json;

      setState((s) => {
        const activeId = s.activeItem?.[primaryKey];
        const nextActiveItem =
          activeId === undefined || activeId === null
            ? s.activeItem
            : (nextItems.find((item: Record<string, any>) => item?.[primaryKey] === activeId) ?? null);

        return {
          ...s,
          items: nextItems,
          activeItem: nextActiveItem,
          total: json.meta?.total || json.total || nextItems.length,
          totalPages: json.meta?.last_page || json.last_page || 1,
          loading: false,
        };
      });
    } catch (e: any) {
      if (e.name !== "AbortError") {
        setState((s) => ({ ...s, loading: false }));
      }
    }
  }, [config.apiUrl, primaryKey, state.search, state.filters, state.sort, state.sortDirection, state.page, state.perPage]);

  useEffect(() => {
    fetchItems();
  }, [fetchItems]);

  const setSearch = useCallback((search: string) => {
    setState((s) => ({ ...s, search, page: 1 }));
  }, []);

  const setFilter = useCallback((key: string, value: any) => {
    setState((s) => ({ ...s, filters: { ...s.filters, [key]: value }, page: 1 }));
  }, []);

  const setSort = useCallback((sort: string) => {
    setCookie(scopedCookieKey("crud_sort", config.apiUrl), sort);
    setState((s) => ({
      ...s,
      sort,
      sortDirection: s.sort === sort && s.sortDirection === "asc" ? "desc" : "asc",
      page: 1,
    }));
  }, [config.apiUrl]);

  const setSortDirection = useCallback((sortDirection: "asc" | "desc") => {
    setCookie(scopedCookieKey("crud_sort_dir", config.apiUrl), sortDirection);
    setState((s) => ({ ...s, sortDirection, page: 1 }));
  }, [config.apiUrl]);

  const setPage = useCallback((page: number) => {
    setState((s) => ({ ...s, page }));
  }, []);

  const setPerPage = useCallback((perPage: number) => {
    setCookie("crud_per_page", String(perPage));
    setState((s) => ({ ...s, perPage, page: 1 }));
  }, []);

  const setViewMode = useCallback((viewMode: ViewMode) => {
    setCookie("crud_view_mode", viewMode);
    setState((s) => ({ ...s, viewMode }));
  }, []);

  const setActiveItem = useCallback((item: Record<string, any> | null) => {
    setState((s) => ({ ...s, activeItem: item }));
  }, []);

  const toggleSelect = useCallback(
    (id: string | number) => {
      setState((s) => {
        const next = new Set(s.selectedItems);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        return { ...s, selectedItems: next };
      });
    },
    []
  );

  const selectAll = useCallback(() => {
    setState((s) => {
      const allIds = s.items.map((i) => i[primaryKey]);
      const allSelected = allIds.every((id) => s.selectedItems.has(id));
      return {
        ...s,
        selectedItems: allSelected ? new Set() : new Set(allIds),
      };
    });
  }, [primaryKey]);

  const saveItem = useCallback(
    async (data: Record<string, any>) => {
      const id = data[primaryKey];
      const isNew = !id;
      const url = isNew ? config.apiUrl : `${config.apiUrl}/${id}`;
      const method = isNew ? "POST" : "PUT";

      const res = await fetch(url, {
        method,
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(data),
      });

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Save failed");
      }

      const savedItem = await res.json().catch(() => null);

      if (savedItem && typeof savedItem === "object") {
        setState((s) => {
          const savedId = savedItem[primaryKey];
          const isExisting = s.items.some((item) => item?.[primaryKey] === savedId);
          const nextItems = isExisting
            ? s.items.map((item) => (item?.[primaryKey] === savedId ? { ...item, ...savedItem } : item))
            : [savedItem, ...s.items];

          const activeId = s.activeItem?.[primaryKey];
          const nextActiveItem =
            activeId !== undefined && activeId === savedId
              ? { ...(s.activeItem || {}), ...savedItem }
              : s.activeItem;

          return {
            ...s,
            items: nextItems,
            activeItem: nextActiveItem,
          };
        });
      }

      await fetchItems();
    },
    [config.apiUrl, primaryKey, fetchItems]
  );

  const patchItem = useCallback(
    async (id: string | number, updates: Record<string, any>) => {
      const res = await fetch(`${config.apiUrl}/${id}`, {
        method: "PATCH",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(updates),
      });

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Save failed");
      }

      const savedItem = await res.json().catch(() => null);

      if (savedItem && typeof savedItem === "object") {
        setState((s) => {
          const savedId = savedItem[primaryKey] ?? id;
          const nextItems = s.items.map((item) =>
            item?.[primaryKey] === savedId ? { ...item, ...savedItem } : item
          );

          const activeId = s.activeItem?.[primaryKey];
          const nextActiveItem =
            activeId !== undefined && activeId === savedId
              ? { ...(s.activeItem || {}), ...savedItem }
              : s.activeItem;

          return {
            ...s,
            items: nextItems,
            activeItem: nextActiveItem,
          };
        });
      }

      await fetchItems();
    },
    [config.apiUrl, primaryKey, fetchItems]
  );

  const deleteItem = useCallback(
    async (id: string | number) => {
      const res = await fetch(`${config.apiUrl}/${id}`, {
        method: "DELETE",
        headers: { Accept: "application/json" },
      });

      if (!res.ok) throw new Error("Delete failed");
      await fetchItems();
    },
    [config.apiUrl, fetchItems]
  );

  const massUpdate = useCallback(
    async (updates: Record<string, any>) => {
      const ids = Array.from(state.selectedItems);
      await Promise.all(
        ids.map((id) =>
          fetch(`${config.apiUrl}/${id}`, {
            method: "PATCH",
            headers: {
              "Content-Type": "application/json",
              Accept: "application/json",
            },
            body: JSON.stringify(updates),
          })
        )
      );
      await fetchItems();
      setState((s) => ({ ...s, selectedItems: new Set() }));
    },
    [config.apiUrl, state.selectedItems, fetchItems]
  );

  const massDelete = useCallback(async () => {
    const ids = Array.from(state.selectedItems);
    await Promise.all(
      ids.map((id) =>
        fetch(`${config.apiUrl}/${id}`, {
          method: "DELETE",
          headers: { Accept: "application/json" },
        })
      )
    );
    await fetchItems();
    setState((s) => ({ ...s, selectedItems: new Set() }));
  }, [config.apiUrl, state.selectedItems, fetchItems]);

  return {
    state,
    setSearch,
    setFilter,
    setSort,
    setSortDirection,
    setPage,
    setPerPage,
    setViewMode,
    setActiveItem,
    toggleSelect,
    selectAll,
    clearSelection: useCallback(() => {
      setState((s) => ({ ...s, selectedItems: new Set() }));
    }, []),
    saveItem,
    patchItem,
    deleteItem,
    massUpdate,
    massDelete,
    refetch: fetchItems,
  };
}
