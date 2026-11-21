@php
if (Auth::user()->cannot('index', \Ledningssystemet\Ledningssystemet\Models\User::class)) abort(403);

$optionList = fn ($items) => $items
   ->map(fn ($item) => ['value' => (string) $item->id, 'label' => $item->name])
   ->values()
   ->all();
$userOptions = $optionList(\Ledningssystemet\Ledningssystemet\Models\User::orderBy('name')->get()->each->setAppends([]));
$departmentOptions = $optionList(\Ledningssystemet\Ledningssystemet\Models\Department::orderBy('name')->get()->each->setAppends([]));
$roleOptions = $optionList(\Ledningssystemet\Ledningssystemet\Models\Role::orderBy('name')->get()->each->setAppends([]));
$accessGroupOptions = $optionList(\Ledningssystemet\Ledningssystemet\Models\AccessGroup::orderBy('name')->get()->each->setAppends([]));
$canCreateUser = config('ledningssystemet.local_user_management') && Auth::user()->can('create', \Ledningssystemet\Ledningssystemet\Models\User::class);
$canUpdateUser = config('ledningssystemet.local_user_management') && Auth::user()->can('update', \Ledningssystemet\Ledningssystemet\Models\User::class);
$customProperties = \Ledningssystemet\Ledningssystemet\Models\CustomProperty::where('context', \Ledningssystemet\Ledningssystemet\Models\User::class)->orderBy('ordinal')->get();
$customPropertyColumns = [];
$customPropertyFilters = [];

foreach ($customProperties as $property) {
   $type = match ($property->type) {
      'string' => 'text',
      'textarea' => 'textarea',
      default => 'select',
   };
   $options = collect(json_decode($property->getJtableOptions(), true) ?? [])
      ->filter(fn ($option) => isset($option['Value']))
      ->map(fn ($option) => [
         'value' => (string) $option['Value'],
         'label' => $option['DisplayText'],
         'disabled' => $option['Disabled'] ?? false,
      ])
      ->values()
      ->all();

   $customPropertyColumns[] = [
      'key' => 'customproperty_'.$property->id,
      'label' => __($property->name),
      'type' => $type,
      'options' => $options,
      'form' => $property->user_editable ? 'edit' : 'none',
      'required' => (bool) $property->required,
      'maxLength' => $type === 'text' ? 255 : null,
      'listHidden' => ! $property->display_on_card,
      'tab' => __('Custom properties'),
      'sortable' => false,
   ];

   if (! in_array($property->type, ['string', 'textarea'], true)) {
      $customPropertyFilters[] = [
         'key' => 'customproperty_'.$property->id,
         'label' => __($property->name),
         'mode' => 'single',
         'options' => $options,
      ];
   }
}

$tableColumns = [
   'name',
   'title',
   'enabled',
   'email',
   'last_login_at',
];
if (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) {
   $tableColumns[] = 'manager_user_id';
}
$tableColumns = [
   ...$tableColumns,
   'departments',
];
if (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) {
   $tableColumns[] = 'org_roles';
   $tableColumns[] = 'direct_reports';
}
$tableColumns = [
   ...$tableColumns,
   'access_groups',
   ...array_values(array_map(
      fn ($column) => $column['key'],
      $customPropertyColumns
   )),
];

$tableFilters = [
   [
      'key' => 'enabled',
      'label' => __('Status'),
      'mode' => 'single',
      'options' => [
         ['value' => '1', 'label' => __('Enabled')],
         ['value' => '0', 'label' => __('Deactivated')],
      ],
   ],
   ...$customPropertyFilters,
];

$formTabs = [__('User')];
if (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff') || ! config('ledningssystemet.graph_departments_assignusers')) {
   $formTabs[] = __('Organization');
}
$formTabs[] = __('Access');
if (array_filter($customPropertyColumns, fn ($column) => $column['form'] !== 'none')) {
   $formTabs[] = __('Custom properties');
}

$tableConfig = [
   'listTitle' => __('Users'),
   'tableId' => 'userslist',
   'titleKey' => 'name',
   'subtitleKeys' => ['email'],
   'defaultView' => 'masterDetail',
   'editMode' => 'popup',
   'perPage' => 10,
   'searchKeys' => ['name', 'email'],
   'dataSource' => [
      'baseUrl' => '/api/v1/crud',
      'model' => 'User',
      'allowedFilters' => array_values(array_map(fn ($filter) => $filter['key'], $tableFilters)),
      'sort' => ['name'],
      'headers' => ['X-CSRF-TOKEN' => csrf_token()],
   ],
   'permissions' => [
      'create' => $canCreateUser,
      'update' => $canUpdateUser,
      'delete' => Auth::user()->can('delete', \Ledningssystemet\Ledningssystemet\Models\User::class),
   ],
   'columns' => [
      ['key' => 'name', 'label' => __('Name'), 'form' => 'both', 'required' => true, 'maxLength' => 255, 'sortable' => true, 'tab' => __('User')],
      ['key' => 'title', 'label' => __('Title'), 'form' => 'both', 'maxLength' => 255, 'sortable' => true, 'tab' => __('User')],
      ['key' => 'enabled', 'label' => __('Enabled'), 'type' => 'select', 'form' => 'both', 'required' => true, 'options' => [
         ['value' => '0', 'label' => __('No')],
         ['value' => '1', 'label' => __('Yes')],
      ], 'sortable' => true, 'tab' => __('User')],
      ['key' => 'email', 'label' => __('E-mail address'), 'form' => 'both', 'required' => true, 'sortable' => true, 'tab' => __('User')],
      ['key' => 'last_login_at', 'label' => __('Last login'), 'type' => 'datetime', 'form' => 'none', 'sortable' => true, 'tab' => __('User')],
      ...(
         \Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')
            ? [
               ['key' => 'manager_user_id', 'label' => __('Manager'), 'type' => 'select', 'form' => 'both', 'options' => $userOptions, 'sortable' => false, 'tab' => __('Organization')],
            ]
            : []
      ),
      ['key' => 'departments', 'label' => __('Departments'), 'type' => 'select', 'multiple' => true, 'form' => config('ledningssystemet.graph_departments_assignusers') ? 'none' : 'both', 'options' => $departmentOptions, 'sortable' => false, 'tab' => __('Organization')],
      ...(
         \Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')
            ? [
               ['key' => 'org_roles', 'label' => __('Roles'), 'type' => 'select', 'multiple' => true, 'form' => 'both', 'options' => $roleOptions, 'sortable' => false, 'tab' => __('Organization')],
               ['key' => 'direct_reports', 'label' => __('Direct reports'), 'form' => 'none', 'sortable' => false, 'tab' => __('Organization')],
            ]
            : []
      ),
      ['key' => 'access_groups', 'label' => __('Access groups'), 'type' => 'select', 'multiple' => true, 'form' => 'both', 'options' => $accessGroupOptions, 'sortable' => false, 'tab' => __('Access')],
      ...$customPropertyColumns,
   ],
   'tableColumns' => $tableColumns,
   'defaultTableColumns' => array_values(array_intersect($tableColumns, ['name', 'title', 'email', 'manager_user_id'])),
   'detailSections' => [],
   'filters' => $tableFilters,
   'newItemDefaults' => [
      'enabled' => 1,
      'departments' => [],
      'org_roles' => [],
      'access_groups' => [],
   ],
   'formTabs' => $formTabs,
];

$notificationScopeOptions = array_map(
   fn ($scope) => ['value' => $scope['key'], 'label' => $scope['text']],
   \Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::availableScopes()
);
$notificationEventOptions = array_map(
   fn ($event) => ['value' => $event['key'], 'label' => $event['text']],
   \Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::availableEvents()
);
@endphp
@extends('layouts.master')

@section('container')
<script>
function openUserNotificationSettings(user) {
   var days = [
      { name: 'monday', label: @json(__('Monday')) },
      { name: 'tuesday', label: @json(__('Tuesday')) },
      { name: 'wednesday', label: @json(__('Wednesday')) },
      { name: 'thursday', label: @json(__('Thursday')) },
      { name: 'friday', label: @json(__('Friday')) },
      { name: 'saturday', label: @json(__('Saturday')) },
      { name: 'sunday', label: @json(__('Sunday')) },
   ];
   var title = @json(__('E-mail notification settings'));

   $.ajax({
      method: 'GET',
      url: '/systemadmin/users/' + encodeURIComponent(user.id) + '/notification-settings',
      dataType: 'json',
      success: function(settings) {
         var content = $('<div class="container-fluid" />');
         var weekdays = $('<div class="row g-2" />');

         days.forEach(function(day) {
            var inputId = 'notification-' + day.name + '-' + user.id;
            weekdays.append(
               $('<div class="col-6 col-md-4" />').append(
                  $('<div class="form-check form-switch" />')
                     .append($('<input class="form-check-input" type="checkbox" />')
                        .attr({ id: inputId, name: day.name })
                        .prop('checked', settings[day.name] === true || settings[day.name] == 1))
                     .append($('<label class="form-check-label" />')
                        .attr('for', inputId)
                        .text(day.label))
               )
            );
         });

         content
            .append($('<p class="mb-3" />').text(@json(__('These settings adjust how often you want to get status overviews from the system'))))
            .append(weekdays)
            .append($('<h5 class="mt-4" />').text(@json(__('Notification channels'))))
            .append($('<div id="userNotificationChannelsTable" />'));

         var status = $('<div class="text-danger mt-2" role="alert" />');
         content.append(status);
         var unmountChannels = null;
         var cleanupChannels = function() {
            if (unmountChannels) {
               unmountChannels();
               unmountChannels = null;
            }
         };

         showDialog(title, content, {
            actionLabel: translateString('Save'),
            dismissLabel: translateString('Close'),
            modalClass: 'modal-xl',
            onAction: function(data, dialogDiv) {
               var preferences = {};
               days.forEach(function(day) {
                  preferences[day.name] = dialogDiv.find('input[name="' + day.name + '"]').prop('checked') ? 1 : 0;
               });

               dialogDiv.find('.action-button').prop('disabled', true);
               $.ajax({
                  method: 'POST',
                  url: '/systemadmin/users/' + encodeURIComponent(user.id) + '/notification-settings',
                  data: preferences,
                  dataType: 'json',
                  success: function() {
                     dialogDiv.find('.dismiss-button').first().trigger('click');
                  },
                  error: function() {
                     status.text(@json(__('Unable to save notification settings.')));
                     dialogDiv.find('.action-button').prop('disabled', false);
                  },
               });

               return true;
            },
            formCreated: function(dialogDiv) {
               dialogDiv.find('.modal-dialog').addClass('modal-xl');
               dialogDiv.one('hidden.bs.modal', cleanupChannels);

               var channelConfig = {
                  listTitle: @json(__('Notification channels')),
                  tableId: 'usernotificationchannels-' + user.id,
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
                     params: { user_id: String(user.id) },
                     allowedFilters: ['user_id'],
                     sort: ['name'],
                     headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
                  },
                  permissions: {
                     create: @json(Auth::user()->can('create', \Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::class)),
                     update: @json(Auth::user()->can('update', \Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::class)),
                     delete: @json(Auth::user()->can('delete', \Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::class)),
                  },
                  columns: [
                     { key: 'name', label: @json(__('Name')), required: true, maxLength: 255, sortable: true },
                     {
                        key: 'ignoremine',
                        label: @json(__('Notify me when I am doing the changes')),
                        type: 'select',
                        options: [
                           { value: '1', label: @json(__('No')) },
                           { value: '0', label: @json(__('Yes')) },
                        ],
                     },
                     { key: 'channelscopes', label: @json(__('Object scopes')), type: 'select', multiple: true, options: @json($notificationScopeOptions), sortable: false },
                     { key: 'channelevents', label: @json(__('Events')), type: 'select', multiple: true, options: @json($notificationEventOptions), sortable: false },
                  ],
                  tableColumns: ['name', 'ignoremine', 'channelscopes', 'channelevents'],
                  detailSections: [],
                  filters: [],
                  newItemDefaults: { user_id: String(user.id), ignoremine: 1, channelscopes: [], channelevents: [] },
               };

               unmountChannels = window.mountTableModule(
                  dialogDiv.find('#userNotificationChannelsTable').get(0),
                  { config: channelConfig, data: [] }
               );
            },
            afterClose: cleanupChannels,
         });
      },
      error: function() {
         showDialog(title, $('<p />').text(@json(__('Unable to load notification settings.'))));
      },
   });
}

function openUserReassignmentDialog(user) {
   var reassignable = [
      { count: 'activitiescount', key: 'activities', label: @json(__('Activities')) },
      { count: 'activityflowcount', key: 'activity_flows', label: @json(__('Activity Flows')) },
      { count: 'agreementcount', key: 'agreements', label: @json(__('Agreements')) },
      { count: 'assetscount', key: 'assets', label: @json(__('Assets')) },
      { count: 'controlscount', key: 'controls', label: @json(__('Controls')) },
      { count: 'control_actionscount', key: 'control_actions', label: @json(__('Control actions')) },
      { count: 'customercount', key: 'customers', label: @json(__('Customers')) },
      { count: 'findingscount', key: 'findings', label: @json(__('Findings')) },
      { count: 'formcount', key: 'forms', label: @json(__('Forms')) },
      { count: 'incidentscount', key: 'incidents', label: @json(__('Incidents')) },
      { count: 'information_typescount', key: 'information_types', label: @json(__('Information types')) },
      { count: 'objectivescount', key: 'objectives', label: @json(__('Objectives')) },
      { count: 'processescount', key: 'processes', label: @json(__('Processes')) },
      { count: 'process_performance_metricscount', key: 'process_performance_metrics', label: @json(__('Process performance metrics')) },
      { count: 'projectcount', key: 'projects', label: @json(__('Projects')) },
      { count: 'projectactivitycount', key: 'project_activities', label: @json(__('Project activities')) },
      { count: 'riskscount', key: 'risks', label: @json(__('Risks')) },
@if(\Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier'))
      { count: 'supplierscount', key: 'suppliers', label: @json(__('Suppliers')) },
@endif
   ];
   var content = $('<div class="container-fluid" />');

   reassignable.filter(function(entry) {
      return Number(user[entry.count]) > 0;
   }).forEach(function(entry) {
      var id = 'reassign-' + entry.key + '-' + user.id;
      var select = $('<select class="form-select form-select-sm" required />').attr({ id: id, name: entry.key });
      @json($userOptions).forEach(function(option) {
         select.append($('<option />').val(option.value).text(option.label).prop('selected', option.value === String(user.id)));
      });
      content.append(
         $('<div class="mb-3" />')
            .append($('<label class="form-label" />').attr('for', id).text(entry.label))
            .append(select)
      );
   });

   confirmDialog(@json(__('Re-assign objects')), content, function(formdata) {
      ajaxPost('/api/v1/items/User/' + encodeURIComponent(user.id) + '/reassign', formdata, function() {
         window.location.reload();
      });
   });
}

$(function() {
   var tableConfig = @json($tableConfig);
   var optionLabels = function(options) {
      return new Map(options.map(function(option) {
         return [String(option.value), option.label];
      }));
   };
   var relationText = function(value, options) {
      var labels = optionLabels(options);
      var values = Array.isArray(value) ? value : [];
      return values.map(function(item) {
         var id = item && typeof item === 'object' ? item.id : item;
         return labels.get(String(id)) || (item && typeof item === 'object' ? item.name : String(item));
      }).filter(Boolean).join(', ');
   };
   var linkedObjectCounts = [
      'activitiescount', 'assetscount', 'controlscount', 'control_actionscount', 'findingscount',
      'incidentscount', 'information_typescount', 'objectivescount', 'processescount',
      'process_performance_metricscount', 'riskscount', 'supplierscount',
   ];
   var hasLinkedObjects = function(user) {
      return Boolean(user.external_id) || linkedObjectCounts.some(function(key) { return Number(user[key]) > 0; });
   };

   tableConfig.columns.find(function(column) { return column.key === 'departments'; }).render = function(user) {
      return relationText(user.departments, @json($departmentOptions));
   };
@if(\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff'))
   tableConfig.columns.find(function(column) { return column.key === 'org_roles'; }).render = function(user) {
      return relationText(user.org_roles, @json($roleOptions));
   };
   tableConfig.columns.find(function(column) { return column.key === 'direct_reports'; }).render = function(user) {
      return Array.isArray(user.direct_reports) ? user.direct_reports.map(function(item) { return item.name; }).join(', ') : '';
   };
@endif
   tableConfig.columns.find(function(column) { return column.key === 'access_groups'; }).render = function(user) {
      return relationText(user.access_groups, @json($accessGroupOptions));
   };

   tableConfig.permissions.delete = tableConfig.permissions.delete
      ? function(user) { return !hasLinkedObjects(user); }
      : false;

   tableConfig.rowCommands = [
@if(Auth::user()->can('update', \Ledningssystemet\Ledningssystemet\Models\User::class))
      { label: @json(__('Notification settings')), onClick: openUserNotificationSettings },
      { label: @json(__('Re-assign objects')), onClick: openUserReassignmentDialog, visible: hasLinkedObjects },
@endif
      {
         label: @json(__('Send password reset link')),
         onClick: function(user) {
            confirmDialog(@json(__('Send password reset link')), @json(__('Are you sure?')), function() {
               ajaxPost('/api/v1/items/User/' + encodeURIComponent(user.id) + '/resetpasssword', {}, function() {
                  showDialog(@json(__('Send password reset link')), @json(__('A password reset link has been sent to the user')));
               });
            });
         },
      },
   ];

   window.mountTableModule(document.getElementById('tableContainer'), { config: tableConfig, data: [] });
});
</script>
<div id="tableContainer"></div>
@endsection
