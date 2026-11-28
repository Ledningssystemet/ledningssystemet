import React, { useMemo, useRef } from 'react';
import { TableModule } from '../../components/table/TableModule';
import { t } from '../../lib/i18n';
import { mutationEvents, useSystemAdmin, useSystemAdminLabels } from './HydrationProvider';

const days = [
    ['monday', 'Monday'],
    ['tuesday', 'Tuesday'],
    ['wednesday', 'Wednesday'],
    ['thursday', 'Thursday'],
    ['friday', 'Friday'],
    ['saturday', 'Saturday'],
    ['sunday', 'Sunday'],
];

const linkedObjectCounts = [
    'activitiescount', 'assetscount', 'controlscount', 'control_actionscount', 'findingscount',
    'incidentscount', 'information_typescount', 'objectivescount', 'processescount',
    'process_performance_metricscount', 'riskscount', 'supplierscount',
];

function hasLinkedObjects(user) {
    return Boolean(user.external_id) || linkedObjectCounts.some(key => Number(user[key]) > 0);
}

function relationText(value, options) {
    const labels = new Map(options.map(option => [String(option.value), option.label]));
    return (Array.isArray(value) ? value : []).map(item => {
        const id = item && typeof item === 'object' ? item.id : item;
        return labels.get(String(id)) || (item && typeof item === 'object' ? item.name : String(item));
    }).filter(Boolean).join(', ');
}

function openNotificationChannels(user, props) {
    const $ = window.$;
    const content = $('<div class="container-fluid" />')
        .append($('<div id="userNotificationChannelsTable" />'));
    let unmountChannels = null;
    const cleanup = () => {
        if (unmountChannels) {
            unmountChannels();
            unmountChannels = null;
        }
    };

    window.showDialog(t('Notification channels'), content, {
        dismissLabel: window.translateString('Close'),
        modalClass: 'modal-xl',
        formCreated: dialog => {
            dialog.find('.modal-dialog').addClass('modal-xl');
            dialog.one('hidden.bs.modal', cleanup);
            const channelConfig = {
                listTitle: t('Notification channels'),
                tableId: `usernotificationchannels-${user.id}`,
                titleKey: 'name',
                subtitleKeys: [],
                defaultView: 'table',
                viewModes: { masterDetail: false, table: true, list: false },
                editMode: 'inline',
                perPage: 10,
                searchKeys: ['name'],
                dataSource: {
                    baseUrl: '/api/v1/crud',
                    model: 'UserNotificationChannel',
                    fields: ['access'],
                    params: { user_id: String(user.id) },
                    allowedFilters: ['user_id'],
                    sort: ['name'],
                    headers: { 'X-CSRF-TOKEN': props.csrfToken },
                },
                columns: [
                    { key: 'name', label: t('Name'), required: true, maxLength: 255, sortable: true },
                    {
                        key: 'ignoremine',
                        label: t('Notify me when I am doing the changes'),
                        type: 'select',
                        options: [{ value: '1', label: t('No') }, { value: '0', label: t('Yes') }],
                    },
                    { key: 'channelscopes', label: t('Object scopes'), type: 'select', multiple: true, options: props.notificationScopeOptions, sortable: false },
                    { key: 'channelevents', label: t('Events'), type: 'select', multiple: true, options: props.notificationEventOptions, sortable: false },
                ],
                tableColumns: ['name', 'ignoremine', 'channelscopes', 'channelevents'],
                detailSections: [],
                filters: [],
                newItemDefaults: { user_id: String(user.id), ignoremine: 1, channelscopes: [], channelevents: [] },
            };
            unmountChannels = window.mountTableModule(
                dialog.find('#userNotificationChannelsTable').get(0),
                { config: channelConfig, data: [] }
            );
        },
        afterClose: cleanup,
    });
}

function openReassignment(user, userOptions, supplierEnabled, onChanged) {
    const entries = [
        ['activitiescount', 'activities', 'Activities'],
        ['activityflowcount', 'activity_flows', 'Activity Flows'],
        ['agreementcount', 'agreements', 'Agreements'],
        ['assetscount', 'assets', 'Assets'],
        ['controlscount', 'controls', 'Controls'],
        ['control_actionscount', 'control_actions', 'Control actions'],
        ['customercount', 'customers', 'Customers'],
        ['findingscount', 'findings', 'Findings'],
        ['formcount', 'forms', 'Forms'],
        ['incidentscount', 'incidents', 'Incidents'],
        ['information_typescount', 'information_types', 'Information types'],
        ['objectivescount', 'objectives', 'Objectives'],
        ['processescount', 'processes', 'Processes'],
        ['process_performance_metricscount', 'process_performance_metrics', 'Process performance metrics'],
        ['projectcount', 'projects', 'Projects'],
        ['projectactivitycount', 'project_activities', 'Project activities'],
        ['riskscount', 'risks', 'Risks'],
        ['supplierscount', 'suppliers', 'Suppliers'],
    ].filter(([, key]) => key !== 'suppliers' || supplierEnabled)
        .filter(([count]) => Number(user[count]) > 0);
    const $ = window.$;
    const content = $('<div class="container-fluid" />');
    entries.forEach(([, key, label]) => {
        const id = `reassign-${key}-${user.id}`;
        const select = $('<select class="form-select form-select-sm" required />').attr({ id, name: key });
        userOptions.forEach(option => select.append(
            $('<option />').val(option.value).text(option.label).prop('selected', option.value === String(user.id))
        ));
        content.append($('<div class="mb-3" />')
            .append($('<label class="form-label" />').attr('for', id).text(t(label)))
            .append(select));
    });
    window.confirmDialog(t('Re-assign objects'), content, formData => {
        window.ajaxPost(`/api/v1/items/User/${encodeURIComponent(user.id)}/reassign`, formData, onChanged);
    });
}

export function Component() {
    const { data, csrfToken, refresh } = useSystemAdmin();
    const label = useSystemAdminLabels();
    const controllerRef = useRef(null);
    const props = {
        ...data.groups,
        ...data.configuration,
        csrfToken,
        notificationScopeOptions: data.groups.notifications.scopeOptions,
        notificationEventOptions: data.groups.notifications.eventOptions,
        onChanged: () => {
            controllerRef.current?.reload();
            void refresh(['userOptions', 'customProperties']);
        },
    };
    const config = useMemo(() => {
        const staff = props.staffEnabled;
        const customColumns = props.customProperties.map(property => {
            const type = property.type === 'string' ? 'text' : property.type === 'textarea' ? 'textarea' : 'select';
            return {
                key: `customproperty_${property.id}`,
                label: property.name,
                type,
                options: property.options,
                form: property.userEditable ? 'edit' : 'none',
                required: property.required,
                maxLength: type === 'text' ? 255 : undefined,
                listHidden: !property.displayOnCard,
                tab: label('Custom properties'),
                sortable: false,
            };
        });
        const statusEmailDayOptions = days.map(([value, dayLabel]) => ({ value, label: label(dayLabel) }));
        const filters = [
            { key: 'enabled', label: label('Status'), mode: 'single', options: [{ value: '1', label: label('Enabled') }, { value: '0', label: label('Deactivated') }] },
            ...customColumns.filter(column => column.type === 'select').map(column => ({
                key: column.key, label: column.label, mode: 'single', options: column.options,
            })),
        ];
        const tableColumns = [
            'name', 'title', 'enabled', 'email', 'last_login_at',
            ...(staff ? ['manager_user_id'] : []),
            'departments',
            ...(staff ? ['org_roles', 'direct_reports'] : []),
            'access_groups',
            'status_email_days',
            ...customColumns.map(column => column.key),
        ];
        const columns = [
            { key: 'name', label: label('Name'), form: 'both', required: true, maxLength: 255, sortable: true, tab: label('User') },
            { key: 'title', label: label('Title'), form: 'both', maxLength: 255, sortable: true, tab: label('User') },
            { key: 'enabled', label: label('Enabled'), type: 'select', form: 'both', required: true, options: [{ value: '0', label: label('No') }, { value: '1', label: label('Yes') }], sortable: true, tab: label('User') },
            { key: 'email', label: label('E-mail address'), form: 'both', required: true, sortable: true, tab: label('User') },
            { key: 'last_login_at', label: label('Last login'), type: 'datetime', form: 'none', sortable: true, tab: label('User') },
            { key: 'status_email_days', label: label('Status overview days'), type: 'select', multiple: true, form: 'both', options: statusEmailDayOptions, help: label('These settings adjust how often you want to get status overviews from the system'), sortable: false, tab: label('User') },
            ...(staff ? [{ key: 'manager_user_id', label: label('Manager'), type: 'select', form: 'both', options: props.userOptions, sortable: false, tab: label('Organization') }] : []),
            { key: 'departments', label: label('Departments'), type: 'select', multiple: true, form: props.graphDepartmentsAssignUsers ? 'none' : 'both', options: props.departmentOptions, sortable: false, tab: label('Organization') },
            ...(staff ? [
                { key: 'org_roles', label: label('Roles'), type: 'select', multiple: true, form: 'both', options: props.roleOptions, sortable: false, tab: label('Organization') },
                { key: 'direct_reports', label: label('Direct reports'), form: 'none', sortable: false, tab: label('Organization') },
            ] : []),
            { key: 'access_groups', label: label('Access groups'), type: 'select', multiple: true, form: 'both', options: props.accessGroupOptions, sortable: false, tab: label('Access') },
            ...customColumns,
        ];
        const next = {
            listTitle: label('Users'),
            tableId: 'userslist',
            titleKey: 'name',
            subtitleKeys: ['email'],
            defaultView: 'master-detail',
            editMode: 'popup',
            perPage: 10,
            searchKeys: ['name', 'email'],
            dataSource: {
                baseUrl: data.urls.crud,
                model: 'User',
                fields: [
                    'access', 'external_id', 'activitiescount', 'activityflowcount', 'agreementcount',
                    'assetscount', 'controlscount', 'control_actionscount', 'customercount',
                    'findingscount', 'formcount', 'incidentscount', 'information_typescount',
                    'objectivescount', 'processescount', 'process_performance_metricscount',
                    'projectcount', 'projectactivitycount', 'riskscount', 'supplierscount',
                ],
                allowedFilters: filters.map(filter => filter.key),
                sort: ['name'],
                headers: { 'X-CSRF-TOKEN': csrfToken },
            },
            permissions: { create: props.localUserManagement, update: props.localUserManagement },
            columns,
            tableColumns,
            defaultTableColumns: tableColumns.filter(key => ['name', 'title', 'email', 'manager_user_id'].includes(key)),
            detailSections: [],
            filters,
            newItemDefaults: { enabled: 1, departments: [], org_roles: [], access_groups: [], status_email_days: ['monday'] },
            formTabs: [
                label('User'),
                ...(staff || !props.graphDepartmentsAssignUsers ? [label('Organization')] : []),
                label('Access'),
                ...(customColumns.some(column => column.form !== 'none') ? [label('Custom properties')] : []),
            ],
            events: mutationEvents(refresh, ['userOptions', 'customProperties']),
        };
        const column = key => next.columns.find(item => item.key === key);
        column('departments').render = user => relationText(user.departments, props.departmentOptions);
        if (props.staffEnabled) {
            column('org_roles').render = user => relationText(user.org_roles, props.roleOptions);
            column('direct_reports').render = user => Array.isArray(user.direct_reports)
                ? user.direct_reports.map(item => item.name).join(', ')
                : '';
        }
        column('access_groups').render = user => relationText(user.access_groups, props.accessGroupOptions);
        column('status_email_days').render = user => relationText(user.status_email_days, statusEmailDayOptions);
        next.permissions = {
            ...next.permissions,
            delete: user => !hasLinkedObjects(user),
        };
        next.rowCommands = [
            ...(props.localUserManagement ? [
                { label: t('Notification channels'), onClick: user => openNotificationChannels(user, props) },
                { label: t('Re-assign objects'), onClick: user => openReassignment(user, props.userOptions, props.supplierEnabled, props.onChanged), visible: hasLinkedObjects },
            ] : []),
            {
                label: t('Send password reset link'),
                onClick: user => window.confirmDialog(t('Send password reset link'), t('Are you sure?'), () => {
                    window.ajaxPost(`/api/v1/items/User/${encodeURIComponent(user.id)}/resetpasssword`, {}, () => {
                        window.showDialog(t('Send password reset link'), t('A password reset link has been sent to the user'));
                    });
                }),
            },
        ];
        return next;
    }, [data, csrfToken, refresh, label]);

    return <TableModule config={config} data={[]} controllerRef={controllerRef} />;
}
