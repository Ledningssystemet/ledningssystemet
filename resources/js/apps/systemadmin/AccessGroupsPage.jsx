import React, { useMemo } from 'react';
import { TableModule } from '../../components/table/TableModule';
import { mutationEvents, useSystemAdmin, useSystemAdminLabels } from './HydrationProvider';

export function Component() {
    const { data, csrfToken, refresh } = useSystemAdmin();
    const label = useSystemAdminLabels();
    const config = useMemo(() => {
        const { groups, configuration, urls } = data;
        const columns = [
            { key: 'name', label: label('Name'), form: 'both', required: true, maxLength: 255, sortable: true },
            { key: 'users', label: label('Users'), type: 'select', multiple: true, form: 'both', options: groups.userOptions, sortable: false },
            { key: 'risk_level_id', label: label('Max risk acceptance level'), type: 'select', form: 'both', options: [{ value: '', label: label('None') }, ...groups.riskLevelOptions], sortable: false },
            { key: 'permission_ids', label: label('Access rights'), type: 'select', multiple: true, form: 'both', options: groups.permissionOptions, sortable: false },
        ];
        const tableColumns = ['name', 'users', 'risk_level_id', 'permission_ids'];
        if (configuration.graphSyncEnabled) {
            tableColumns.push('external_provider_group_id');
            columns.push({
                key: 'external_provider_group_id',
                label: configuration.graphProviderName,
                type: 'select',
                form: 'both',
                options: [{ value: '', label: label('None') }, ...groups.externalProviderOptions],
                sortable: false,
                help: label('This will be overwritten by the next synchronization with external provider if a group is selected above'),
            });
        }
        return {
            listTitle: label('Access groups'),
            tableId: 'accesstable',
            titleKey: 'name',
            subtitleKeys: [],
            defaultView: 'master-detail',
            editMode: 'popup',
            perPage: 10,
            searchKeys: ['name'],
            dataSource: {
                baseUrl: urls.crud,
                model: 'AccessGroup',
                fields: ['access', 'users', 'permission_ids', 'risk_level_id', 'external_provider_group_id'],
                sort: ['name'],
                headers: { 'X-CSRF-TOKEN': csrfToken },
            },
            columns,
            tableColumns,
            defaultTableColumns: ['name', 'users', 'permission_ids'],
            detailSections: [],
            filters: [],
            newItemDefaults: { users: [], permission_ids: [], risk_level_id: '', external_provider_group_id: '' },
            events: mutationEvents(refresh, ['accessGroupOptions', 'userOptions', 'customProperties']),
        };
    }, [data, csrfToken, refresh, label]);
    return <TableModule config={config} data={[]} />;
}
