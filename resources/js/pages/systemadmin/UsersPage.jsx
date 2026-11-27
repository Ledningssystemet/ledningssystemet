import React, { useMemo } from 'react';
import { useRouteLoaderData } from 'react-router-dom';
import { TableModule } from '../../components/table/TableModule';
import { t } from '../../lib/i18n';

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

function openNotificationSettings(user, props) {
    const $ = window.$;
    const title = t('E-mail notification settings');
    $.ajax({
        method: 'GET',
        url: `/systemadmin/users/${encodeURIComponent(user.id)}/notification-settings`,
        dataType: 'json',
        success: settings => {
            const content = $('<div class="container-fluid" />');
            const weekdays = $('<div class="row g-2" />');
            days.forEach(([name, label]) => {
                const inputId = `notification-${name}-${user.id}`;
                weekdays.append($('<div class="col-6 col-md-4" />').append(
                    $('<div class="form-check form-switch" />')
                        .append($('<input class="form-check-input" type="checkbox" />')
                            .attr({ id: inputId, name })
                            .prop('checked', settings[name] === true || settings[name] == 1))
                        .append($('<label class="form-check-label" />').attr('for', inputId).text(t(label)))
                ));
            });

            content.append($('<p class="mb-3" />').text(t('These settings adjust how often you want to get status overviews from the system')))
                .append(weekdays)
                .append($('<h5 class="mt-4" />').text(t('Notification channels')))
                .append($('<div id="userNotificationChannelsTable" />'));
            const status = $('<div class="text-danger mt-2" role="alert" />');
            content.append(status);
            let unmountChannels = null;
            const cleanup = () => {
                if (unmountChannels) {
                    unmountChannels();
                    unmountChannels = null;
                }
            };

            window.showDialog(title, content, {
                actionLabel: window.translateString('Save'),
                dismissLabel: window.translateString('Close'),
                modalClass: 'modal-xl',
                onAction: (data, dialog) => {
                    const preferences = {};
                    days.forEach(([name]) => {
                        preferences[name] = dialog.find(`input[name="${name}"]`).prop('checked') ? 1 : 0;
                    });
                    dialog.find('.action-button').prop('disabled', true);
                    $.ajax({
                        method: 'POST',
                        url: `/systemadmin/users/${encodeURIComponent(user.id)}/notification-settings`,
                        data: preferences,
                        dataType: 'json',
                        success: () => dialog.find('.dismiss-button').first().trigger('click'),
                        error: () => {
                            status.text(t('Unable to save notification settings.'));
                            dialog.find('.action-button').prop('disabled', false);
                        },
                    });
                    return true;
                },
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
                        permissions: props.channelPermissions,
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
        },
        error: () => window.showDialog(title, $('<p />').text(t('Unable to load notification settings.'))),
    });
}

function openReassignment(user, userOptions, supplierEnabled) {
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
        window.ajaxPost(`/api/v1/items/User/${encodeURIComponent(user.id)}/reassign`, formData, () => window.location.reload());
    });
}

export function Component() {
    const props = useRouteLoaderData('systemadmin-root');
    const config = useMemo(() => {
        const next = {
            ...props.config,
            columns: props.config.columns.map(column => ({ ...column })),
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
        next.permissions = {
            ...next.permissions,
            delete: next.permissions.delete ? user => !hasLinkedObjects(user) : false,
        };
        next.rowCommands = [
            ...(props.canUpdateUser ? [
                { label: t('Notification settings'), onClick: user => openNotificationSettings(user, props) },
                { label: t('Re-assign objects'), onClick: user => openReassignment(user, props.userOptions, props.supplierEnabled), visible: hasLinkedObjects },
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
    }, [props]);

    return <TableModule config={config} data={[]} />;
}
