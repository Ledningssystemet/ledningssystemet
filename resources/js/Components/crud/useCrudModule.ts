import { useState, useCallback, useEffect, useRef } from "react";
import { CrudModuleConfig, CrudState, ViewMode } from "./types";
import { useTranslations } from "@/hooks/useTranslations";

const normalizeCrudItems = (payload: any): Record<string, any>[] => {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (Array.isArray(payload?.data)) {
    return payload.data;
  }

  if (payload !== null && payload !== undefined) {
    console.warn("Unexpected CRUD index payload shape", payload);
  }

  return [];
};

const resolveCrudTotal = (payload: any, items: Record<string, any>[]): number => {
  if (typeof payload?.meta?.total === "number") {
    return payload.meta.total;
  }

  if (typeof payload?.total === "number") {
    return payload.total;
  }

  return items.length;
};

const resolveCrudTotalPages = (payload: any): number => {
  if (typeof payload?.meta?.last_page === "number") {
    return payload.meta.last_page;
  }

  if (typeof payload?.last_page === "number") {
    return payload.last_page;
  }

  return 1;
};

const containsFile = (value: any): boolean => {
  if (typeof File !== "undefined" && value instanceof File) {
    return true;
  }

  if (Array.isArray(value)) {
    return value.some((item) => containsFile(item));
  }

  if (value && typeof value === "object") {
    return Object.values(value).some((item) => containsFile(item));
  }

  return false;
};

const appendFormDataValue = (formData: FormData, key: string, value: any): void => {
  if (value === undefined || value === null || value === "") {
    return;
  }

  if (typeof File !== "undefined" && value instanceof File) {
    formData.append(key, value);
    return;
  }

  if (Array.isArray(value)) {
    value.forEach((item) => {
      if (typeof File !== "undefined" && item instanceof File) {
        formData.append(`${key}[]`, item);
      } else if (item !== undefined && item !== null && item !== "") {
        formData.append(`${key}[]`, String(item));
      }
    });
    return;
  }

  if (typeof value === "boolean") {
    formData.append(key, value ? "1" : "0");
    return;
  }

  formData.append(key, String(value));
};

export const buildQueryString = (
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

  if (config.serializeFilters !== false) {
    const allFilters = {
      ...(config.fixedFilters || {}),
      ...state.filters,
    };

    Object.entries(allFilters).forEach(([key, value]) => {
      if (value !== undefined && value !== "" && value !== null) {
        if (Array.isArray(value) && value.length > 0) {
          params.set(`filter[${key}]`, value.join(","));
        } else if (!Array.isArray(value)) {
          params.set(`filter[${key}]`, String(value));
        }
      }
    });
  }

  if (config.customQueryParams) {
    const customParams = config.customQueryParams(state.filters) || {};
    Object.entries(customParams).forEach(([key, value]) => {
      if (value === undefined || value === null || value === "") {
        return;
      }

      params.set(key, String(value));
    });
  }

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
  const { t } = useTranslations();
  const fixedFiltersSignature = JSON.stringify(config.fixedFilters || {});
  const hasOrdinalField = config.fields.some((field) => field.key === "ordinal");
  const ordinalSortDirection = config.ordinalSortDirection ?? "asc";

  const [state, setState] = useState<CrudState>(() => ({
    items: [],
    selectedItems: new Set(),
    activeItem: null,
    search: "",
    filters: {},
    sort: hasOrdinalField ? "ordinal" : resolvePersistedSort(config),
    sortDirection: hasOrdinalField ? ordinalSortDirection : resolvePersistedSortDirection(config),
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
      const json = await res.json().catch(() => null);

      if (!res.ok) {
        console.error("Failed to load CRUD items", {
          apiUrl: config.apiUrl,
          status: res.status,
          payload: json,
        });
        setState((s) => ({
          ...s,
          items: [],
          activeItem: null,
          total: 0,
          totalPages: 1,
          loading: false,
        }));
        return;
      }

      const nextItems = normalizeCrudItems(json);

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
          total: resolveCrudTotal(json, nextItems),
          totalPages: resolveCrudTotalPages(json),
          loading: false,
        };
      });
    } catch (e: any) {
      if (e.name !== "AbortError") {
        setState((s) => ({ ...s, loading: false }));
      }
    }
  }, [config.apiUrl, fixedFiltersSignature, primaryKey, state.search, state.filters, state.sort, state.sortDirection, state.page, state.perPage]);

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
    if (hasOrdinalField) {
      setState((s) => ({ ...s, sort: "ordinal", sortDirection: ordinalSortDirection, page: 1 }));
      return;
    }

    setCookie(scopedCookieKey("crud_sort", config.apiUrl), sort);
    setState((s) => ({
      ...s,
      sort,
      sortDirection: s.sort === sort && s.sortDirection === "asc" ? "desc" : "asc",
      page: 1,
    }));
  }, [config.apiUrl, hasOrdinalField, ordinalSortDirection]);

  const setSortDirection = useCallback((sortDirection: "asc" | "desc") => {
    if (hasOrdinalField) {
      setState((s) => ({ ...s, sort: "ordinal", sortDirection: ordinalSortDirection, page: 1 }));
      return;
    }

    setCookie(scopedCookieKey("crud_sort_dir", config.apiUrl), sortDirection);
    setState((s) => ({ ...s, sortDirection, page: 1 }));
  }, [config.apiUrl, hasOrdinalField, ordinalSortDirection]);

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
      const payload = isNew
        ? { ...(config.createDefaults || {}), ...data }
        : data;
      const url = isNew ? config.apiUrl : `${config.apiUrl}/${id}`;
      const payloadHasFile = Object.values(payload).some((value) => containsFile(value));

      const requestInit: RequestInit = {
        method: isNew ? "POST" : "PUT",
        headers: {
          Accept: "application/json",
        },
      };

      if (payloadHasFile) {
        const formData = new FormData();
        Object.entries(payload).forEach(([key, value]) => appendFormDataValue(formData, key, value));

        if (!isNew) {
          // Keep route compatibility by spoofing PUT when multipart is used.
          formData.append("_method", "PUT");
          requestInit.method = "POST";
        }

        requestInit.body = formData;
      } else {
        requestInit.headers = {
          ...requestInit.headers,
          "Content-Type": "application/json",
        };
        requestInit.body = JSON.stringify(payload);
      }

      const res = await fetch(url, requestInit);

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || t("ui.crud.save_failed"));
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

        await config.onSaveSuccess?.(savedItem, { isNew, payload });
      }

      await fetchItems();
    },
    [config.apiUrl, config.createDefaults, primaryKey, fetchItems, config.onSaveSuccess, t]
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
        throw new Error(err.message || t("ui.crud.save_failed"));
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

        await config.onSaveSuccess?.(savedItem, { isNew: false, payload: updates });
      }

      await fetchItems();
    },
    [config.apiUrl, primaryKey, fetchItems, config.onSaveSuccess, t]
  );

  const deleteItem = useCallback(
    async (id: string | number) => {
      const res = await fetch(`${config.apiUrl}/${id}`, {
        method: "DELETE",
        headers: { Accept: "application/json" },
      });

      if (!res.ok) throw new Error(t("ui.crud.delete_failed"));
      await fetchItems();
    },
    [config.apiUrl, fetchItems, t]
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

  const reorderByOrdinal = useCallback(
    async (orderedIds: Array<string | number>) => {
      if (!hasOrdinalField || orderedIds.length === 0) {
        return;
      }

      const byId = new Map<string, Record<string, any>>(
        state.items.map((item) => [String(item[primaryKey]), item])
      );

      const updates: Array<{ id: string | number; payload: Record<string, any> }> = [];
      orderedIds.forEach((id, index) => {
        const item = byId.get(String(id));
        if (!item) {
          return;
        }

        const targetOrdinal = ordinalSortDirection === "desc"
          ? orderedIds.length - index
          : index + 1;

        updates.push({
          id,
          payload: {
            ...item,
            ordinal: targetOrdinal,
          },
        });
      });

      if (updates.length === 0) {
        return;
      }

      await Promise.all(
        updates.map(({ id, payload }) =>
          fetch(`${config.apiUrl}/${id}`, {
            method: "PATCH",
            headers: {
              "Content-Type": "application/json",
              Accept: "application/json",
            },
            body: JSON.stringify(payload),
          })
        )
      );

      await fetchItems();
    },
    [config.apiUrl, fetchItems, hasOrdinalField, ordinalSortDirection, primaryKey, state.items]
  );

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
    reorderByOrdinal,
    refetch: fetchItems,
    buildExportQueryString: useCallback(
      () => buildQueryString(config, { ...state, page: 1, perPage: 5000 }),
      // eslint-disable-next-line react-hooks/exhaustive-deps
      [config, state.search, state.filters, state.sort, state.sortDirection]
    ),
  };
}
