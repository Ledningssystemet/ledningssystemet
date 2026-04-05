import { useCallback, useEffect, useState } from 'react';
import axios, { AxiosError } from 'axios';
import { CrudRecord, CrudTableState, CrudFilter, CrudSort, CrudIndexResponse, BulkEditPayload, ApiErrorResponse } from '@/types/crud';

interface UseCrudTableOptions {
  resource: string;
  paginate?: boolean;
  perPage?: number;
  debounceMs?: number;
}

export function useCrudTable(options: UseCrudTableOptions) {
  const { resource, paginate = false, perPage = 25, debounceMs = 300 } = options;

  const [state, setState] = useState<CrudTableState>({
    data: [],
    loading: true,
    error: null,
    selectedRows: new Set(),
    filters: [],
    search: '',
    sort: null,
    pagination: {
      page: 1,
      perPage,
      total: 0,
    },
  });

  const [searchTimeout, setSearchTimeout] = useState<ReturnType<typeof setTimeout> | null>(null);

  /**
   * Fetch data from API
   */
  const fetchData = useCallback(async () => {
    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const params = new URLSearchParams();

      // Add search parameter
      if (state.search) {
        params.append('search', state.search);
      }

      // Add filters
      state.filters.forEach(filter => {
        params.append(filter.field, String(filter.value));
      });

      // Add sort
      if (state.sort) {
        params.append('sort', state.sort.direction === 'desc' ? `-${state.sort.field}` : state.sort.field);
      }

      // Add pagination
      if (paginate) {
        params.append('paginate', 'true');
        params.append('per_page', String(state.pagination.perPage));
        params.append('page', String(state.pagination.page));
      }

      const response = await axios.get<CrudIndexResponse>(`/api/crud/${resource}`, {
        params: Object.fromEntries(params),
      });

      setState(prev => ({
        ...prev,
        data: response.data.data || [],
        pagination: response.data.meta ? {
          page: response.data.meta.current_page,
          perPage: response.data.meta.per_page,
          total: response.data.meta.total,
        } : prev.pagination,
        loading: false,
      }));
    } catch (error) {
      const apiError = error as AxiosError<ApiErrorResponse>;
      setState(prev => ({
        ...prev,
        error: apiError.response?.data?.message || 'Failed to fetch data',
        loading: false,
      }));
    }
  }, [resource, state.search, state.filters, state.sort, state.pagination.page, state.pagination.perPage, paginate]);

  /**
   * Fetch data on mount and when filters/search/sort change
   */
  useEffect(() => {
    fetchData();
  }, [fetchData]);

  /**
   * Set filters and reset to page 1
   */
  const setFilters = useCallback((filters: CrudFilter[]) => {
    setState(prev => ({
      ...prev,
      filters,
      pagination: { ...prev.pagination, page: 1 },
    }));
  }, []);

  /**
   * Set search with debounce
   */
  const setSearch = useCallback((search: string) => {
    setState(prev => ({
      ...prev,
      search,
      pagination: { ...prev.pagination, page: 1 },
    }));

    if (searchTimeout) {
      clearTimeout(searchTimeout);
    }

    const timeout = setTimeout(() => {
      // The useEffect will be triggered by search state change
    }, debounceMs);

    setSearchTimeout(timeout);
  }, [debounceMs, searchTimeout]);

  /**
   * Set sort
   */
  const setSort = useCallback((sort: CrudSort | null) => {
    setState(prev => ({
      ...prev,
      sort,
      pagination: { ...prev.pagination, page: 1 },
    }));
  }, []);

  /**
   * Set page
   */
  const setPage = useCallback((page: number) => {
    setState(prev => ({
      ...prev,
      pagination: { ...prev.pagination, page },
    }));
  }, []);

  /**
   * Toggle row selection
   */
  const toggleSelectRow = useCallback((id: string | number) => {
    setState(prev => {
      const selectedRows = new Set(prev.selectedRows);
      if (selectedRows.has(id)) {
        selectedRows.delete(id);
      } else {
        selectedRows.add(id);
      }
      return { ...prev, selectedRows };
    });
  }, []);

  /**
   * Select/deselect all rows
   */
  const selectAll = useCallback((selected: boolean) => {
    setState(prev => ({
      ...prev,
      selectedRows: selected ? new Set(prev.data.map(r => r.id)) : new Set(),
    }));
  }, []);

  /**
   * Clear all selections
   */
  const clearSelection = useCallback(() => {
    setState(prev => ({
      ...prev,
      selectedRows: new Set(),
    }));
  }, []);

  /**
   * Create a new record
   */
  const createRecord = useCallback(async (data: Omit<CrudRecord, 'id'>) => {
    try {
      const response = await axios.post<CrudRecord>(`/api/crud/${resource}`, data);
      setState(prev => ({
        ...prev,
        data: [response.data, ...prev.data],
      }));
      return response.data;
    } catch (error) {
      throw (error as AxiosError<ApiErrorResponse>);
    }
  }, [resource]);

  /**
   * Update a record
   */
  const updateRecord = useCallback(async (id: string | number, data: Partial<CrudRecord>) => {
    try {
      const response = await axios.patch<CrudRecord>(`/api/crud/${resource}/${id}`, data);
      setState(prev => ({
        ...prev,
        data: prev.data.map(record => record.id === id ? response.data : record),
      }));
      return response.data;
    } catch (error) {
      throw (error as AxiosError<ApiErrorResponse>);
    }
  }, [resource]);

  /**
   * Delete a record
   */
  const deleteRecord = useCallback(async (id: string | number) => {
    try {
      await axios.delete(`/api/crud/${resource}/${id}`);
      setState(prev => ({
        ...prev,
        data: prev.data.filter(record => record.id !== id),
        selectedRows: new Set(Array.from(prev.selectedRows).filter(rid => rid !== id)),
      }));
    } catch (error) {
      throw (error as AxiosError<ApiErrorResponse>);
    }
  }, [resource]);

  /**
   * Bulk update records
   */
  const bulkUpdate = useCallback(async (payload: BulkEditPayload) => {
    const updatePromises = payload.ids.map(id =>
      updateRecord(id, payload.data)
    );

    try {
      await Promise.all(updatePromises);
      setState(prev => ({
        ...prev,
        selectedRows: new Set(),
      }));
    } catch (error) {
      throw error;
    }
  }, [updateRecord]);

  /**
   * Refresh data
   */
  const refresh = useCallback(() => {
    fetchData();
  }, [fetchData]);

  return {
    state,
    setFilters,
    setSearch,
    setSort,
    setPage,
    toggleSelectRow,
    selectAll,
    clearSelection,
    createRecord,
    updateRecord,
    deleteRecord,
    bulkUpdate,
    refresh,
  };
}

