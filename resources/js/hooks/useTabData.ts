import { useState, useCallback, useEffect } from 'react';

export interface TabDataState<T> {
    data: T | null;
    loading: boolean;
    error: string | null;
}

/**
 * Hook for lazy-loading tab data. Fetches from `url` when it changes from null to a value,
 * or when `reload` is called. Re-resets when `url` changes to null.
 *
 * @example
 * const { data, loading, error } = useTabData<Employee>(
 *     activeTab === 'general' && employeeId ? `/api/employees/${employeeId}` : null
 * );
 */
export function useTabData<T>(url: string | null): TabDataState<T> & { reload: () => void } {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetch_ = useCallback(async (fetchUrl: string) => {
        setLoading(true);
        setError(null);
        try {
            const res = await fetch(fetchUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            setData((await res.json()) as T);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Error loading data');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!url) {
            setData(null);
            setError(null);
            return;
        }
        void fetch_(url);
    }, [url, fetch_]);

    const reload = useCallback(() => {
        if (url) void fetch_(url);
    }, [url, fetch_]);

    return { data, loading, error, reload };
}

