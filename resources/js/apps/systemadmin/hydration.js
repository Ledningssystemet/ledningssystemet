import { ApiError, buildListUrl, parseList } from '../../components/table/helpers/api';
import { t } from '../../lib/i18n';

const sources = {
    userOptions: { model: 'User', fields: ['id', 'name'], sort: ['name'] },
    departmentOptions: { model: 'Department', fields: ['id', 'name'], sort: ['name'] },
    roleOptions: { model: 'Role', fields: ['id', 'name'], sort: ['name'] },
    accessGroupOptions: { model: 'AccessGroup', fields: ['id', 'name'], sort: ['name'] },
    riskLevelOptions: { model: 'RiskLevel', fields: ['id', 'name'], sort: ['ordinal'] },
    customProperties: {
        model: 'CustomProperty',
        fields: ['id', 'translated_name', 'type', 'user_editable', 'required', 'display_on_card', 'select_options'],
        sort: ['ordinal'],
        params: { 'filter[context]': 'Ledningssystemet\\Ledningssystemet\\Models\\User' },
    },
};

export function createHydrationStore(bootstrap) {
    let snapshot = { data: null, error: null, refreshing: false };
    let queue = Promise.resolve();
    let revision = 0;
    const listeners = new Set();
    const dirty = new Set();
    let initial = {
        urls: bootstrap.urls,
        configuration: bootstrap.configuration,
        labels: bootstrap.labels,
        groups: bootstrap.groups,
    };
    const publish = patch => {
        snapshot = { ...snapshot, ...patch };
        listeners.forEach(listener => listener());
    };

    async function readGroup(key, signal) {
        const source = { baseUrl: initial.urls.crud, ...sources[key] };
        const response = await fetch(buildListUrl(source), {
            signal,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new ApiError(response.status, `${source.model}: ${response.statusText}`);
        const { items, lastPage, total } = parseList(await response.json());
        const invalid = () => { throw new ApiError(0, `${source.model}: ${t('The server returned an unexpected response.')}`); };
        if (lastPage !== null || total !== null) invalid();
        if (items.some(item => !item.id || ['undefined', 'null'].includes(item.id))) invalid();
        if (key === 'customProperties') {
            const booleans = [true, false, 0, 1, '0', '1'];
            if (items.some(property => typeof property.translated_name !== 'string'
                || typeof property.type !== 'string' || !Array.isArray(property.select_options)
                || !booleans.includes(property.user_editable) || !booleans.includes(property.required)
                || !booleans.includes(property.display_on_card))) invalid();
            return items.map(property => ({
                id: property.id,
                name: property.translated_name,
                type: property.type,
                userEditable: Boolean(Number(property.user_editable)),
                required: Boolean(Number(property.required)),
                displayOnCard: Boolean(Number(property.display_on_card)),
                options: property.select_options,
            }));
        }
        if (items.some(item => typeof item.name !== 'string')) invalid();
        return items.map(item => ({ value: item.id, label: item.name }));
    }

    function enqueue(groups, signal, afterMutation) {
        if (groups !== undefined && (!Array.isArray(groups) || groups.some(group => typeof group !== 'string' || !Object.hasOwn(sources, group)))) {
            throw new Error('Unknown system administration reference group.');
        }
        if (afterMutation) {
            revision++;
            (groups ?? Object.keys(sources)).forEach(group => dirty.add(group));
        }
        // Serialize refresh batches; mutations invalidate older in-flight snapshots.
        const work = queue.then(async () => {
            signal?.throwIfAborted();
            publish({ refreshing: true });
            const startedAt = revision;
            const enabled = Object.keys(sources).filter(key => key !== 'roleOptions' || initial.configuration.staffEnabled);
            const selected = groups === undefined || !snapshot.data
                ? enabled
                : [...new Set([...groups, ...dirty])].filter(key => enabled.includes(key));
            try {
                const values = await Promise.all(selected.map(async key => [key, await readGroup(key, signal)]));
                signal?.throwIfAborted();
                if (revision !== startedAt) return null;
                const data = {
                    ...initial,
                    groups: { ...initial.groups, ...snapshot.data?.groups, ...Object.fromEntries(values) },
                };
                if (!initial.configuration.staffEnabled) delete data.groups.roleOptions;
                dirty.clear();
                publish({ data, error: null });
                return data;
            } catch (error) {
                if (signal?.aborted) throw error;
                if (!afterMutation) throw error;
                publish({
                    error,
                    ...(error instanceof ApiError && [401, 403, 419].includes(error.status) ? { data: null } : {}),
                });
                return null;
            } finally {
                publish({ refreshing: false });
            }
        });
        queue = work.catch(() => {});
        return work;
    }

    function updateSettings(result) {
        const settings = { ...initial.groups.settings, contact: result.contact, assessment: result.assessment };
        initial = { ...initial, groups: { ...initial.groups, settings } };
        if (snapshot.data) publish({ data: { ...snapshot.data, groups: { ...snapshot.data.groups, settings } } });
    }

    function updateModule({ module, enabled }) {
        const settings = {
            ...initial.groups.settings,
            modules: initial.groups.settings.modules.map(item => item.key === module ? { ...item, enabled } : item),
        };
        const configuration = {
            ...initial.configuration,
            ...(module === 'staff' ? { staffEnabled: enabled } : {}),
            ...(module === 'supplier' ? { supplierEnabled: enabled } : {}),
        };
        initial = { ...initial, configuration, groups: { ...initial.groups, settings } };
        if (snapshot.data) {
            publish({ data: { ...snapshot.data, groups: { ...snapshot.data.groups, settings } } });
        }
        return enqueue(undefined, undefined, true);
    }

    return {
        getSnapshot: () => snapshot,
        subscribe: listener => {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        load: signal => enqueue(undefined, signal, false),
        refresh: groups => enqueue(groups, undefined, true),
        retry: () => enqueue(undefined, undefined, true),
        updateSettings,
        updateModule,
    };
}
