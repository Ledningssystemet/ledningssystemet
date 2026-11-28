import React, { createContext, useCallback, useContext, useMemo, useSyncExternalStore } from 'react';
import { t } from '../../lib/i18n';
import { ApiError } from '../../components/table/helpers/api';

const Context = createContext(null);

export function HydrationProvider({ store, csrfToken, children }) {
    const snapshot = useSyncExternalStore(store.subscribe, store.getSnapshot);
    const value = useMemo(() => ({
        ...snapshot, csrfToken, refresh: store.refresh,
        updateSettings: store.updateSettings, updateModule: store.updateModule,
    }), [snapshot, csrfToken, store]);

    return <Context.Provider value={value}>
        {snapshot.error && <div className="alert alert-danger" role="alert">
            <p>{snapshot.data?.labels['Changes were saved, but the page data could not be refreshed.']
                ?? t('Changes were saved, but the page data could not be refreshed.')}
                {snapshot.error instanceof ApiError && ` (${snapshot.error.status})`}</p>
            <p>{snapshot.error instanceof Error ? snapshot.error.message : t('An unexpected error occurred.')}</p>
            <button type="button" className="btn btn-outline-primary" disabled={snapshot.refreshing} onClick={() => void store.retry()}>
                {t('Retry')}
            </button>
        </div>}
        {children}
    </Context.Provider>;
}

export function useSystemAdmin() {
    const context = useContext(Context);
    if (!context) throw new Error('System administration hydration provider is missing.');
    return context;
}

export function useSystemAdminLabels() {
    const { data } = useSystemAdmin();
    return useCallback(key => data.labels[key] ?? t(key), [data.labels]);
}

export function mutationEvents(refresh, groups) {
    const changed = () => { void refresh(groups); };
    return { onCreated: changed, onUpdated: changed, onDeleted: changed };
}
