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
$customPropertyColumns = [];
$customPropertyFilters = [];

foreach (\Ledningssystemet\Ledningssystemet\Models\CustomProperty::where('context', \Ledningssystemet\Ledningssystemet\Models\User::class)->orderBy('ordinal')->get() as $property) {
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

$staffEnabled = \Ledningssystemet\Ledningssystemet\Support\Modules::active('staff');
$tableColumns = ['name', 'title', 'enabled', 'email', 'last_login_at'];
if ($staffEnabled) $tableColumns[] = 'manager_user_id';
$tableColumns[] = 'departments';
if ($staffEnabled) $tableColumns = [...$tableColumns, 'org_roles', 'direct_reports'];
$tableColumns = [
   ...$tableColumns,
   'access_groups',
   ...array_map(fn ($column) => $column['key'], $customPropertyColumns),
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
if ($staffEnabled || ! config('ledningssystemet.graph_departments_assignusers')) $formTabs[] = __('Organization');
$formTabs[] = __('Access');
if (array_filter($customPropertyColumns, fn ($column) => $column['form'] !== 'none')) $formTabs[] = __('Custom properties');

$columns = [
   ['key' => 'name', 'label' => __('Name'), 'form' => 'both', 'required' => true, 'maxLength' => 255, 'sortable' => true, 'tab' => __('User')],
   ['key' => 'title', 'label' => __('Title'), 'form' => 'both', 'maxLength' => 255, 'sortable' => true, 'tab' => __('User')],
   ['key' => 'enabled', 'label' => __('Enabled'), 'type' => 'select', 'form' => 'both', 'required' => true, 'options' => [
      ['value' => '0', 'label' => __('No')],
      ['value' => '1', 'label' => __('Yes')],
   ], 'sortable' => true, 'tab' => __('User')],
   ['key' => 'email', 'label' => __('E-mail address'), 'form' => 'both', 'required' => true, 'sortable' => true, 'tab' => __('User')],
   ['key' => 'last_login_at', 'label' => __('Last login'), 'type' => 'datetime', 'form' => 'none', 'sortable' => true, 'tab' => __('User')],
];
if ($staffEnabled) {
   $columns[] = ['key' => 'manager_user_id', 'label' => __('Manager'), 'type' => 'select', 'form' => 'both', 'options' => $userOptions, 'sortable' => false, 'tab' => __('Organization')];
}
$columns[] = ['key' => 'departments', 'label' => __('Departments'), 'type' => 'select', 'multiple' => true, 'form' => config('ledningssystemet.graph_departments_assignusers') ? 'none' : 'both', 'options' => $departmentOptions, 'sortable' => false, 'tab' => __('Organization')];
if ($staffEnabled) {
   $columns[] = ['key' => 'org_roles', 'label' => __('Roles'), 'type' => 'select', 'multiple' => true, 'form' => 'both', 'options' => $roleOptions, 'sortable' => false, 'tab' => __('Organization')];
   $columns[] = ['key' => 'direct_reports', 'label' => __('Direct reports'), 'form' => 'none', 'sortable' => false, 'tab' => __('Organization')];
}
$columns[] = ['key' => 'access_groups', 'label' => __('Access groups'), 'type' => 'select', 'multiple' => true, 'form' => 'both', 'options' => $accessGroupOptions, 'sortable' => false, 'tab' => __('Access')];
$columns = [...$columns, ...$customPropertyColumns];

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
      'fields' => [
         'access', 'external_id', 'activitiescount', 'activityflowcount', 'agreementcount',
         'assetscount', 'controlscount', 'control_actionscount', 'customercount',
         'findingscount', 'formcount', 'incidentscount', 'information_typescount',
         'objectivescount', 'processescount', 'process_performance_metricscount',
         'projectcount', 'projectactivitycount', 'riskscount', 'supplierscount',
      ],
      'allowedFilters' => array_column($tableFilters, 'key'),
      'sort' => ['name'],
      'headers' => ['X-CSRF-TOKEN' => csrf_token()],
   ],
   'permissions' => [
      'create' => $canCreateUser,
      'update' => $canUpdateUser,
      'delete' => Auth::user()->can('delete', \Ledningssystemet\Ledningssystemet\Models\User::class),
   ],
   'columns' => $columns,
   'tableColumns' => $tableColumns,
   'defaultTableColumns' => array_values(array_intersect($tableColumns, ['name', 'title', 'email', 'manager_user_id'])),
   'detailSections' => [],
   'filters' => $tableFilters,
   'newItemDefaults' => ['enabled' => 1, 'departments' => [], 'org_roles' => [], 'access_groups' => []],
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
$props = [
   'page' => 'users',
   'config' => $tableConfig,
   'csrfToken' => csrf_token(),
   'userOptions' => $userOptions,
   'departmentOptions' => $departmentOptions,
   'roleOptions' => $roleOptions,
   'accessGroupOptions' => $accessGroupOptions,
   'staffEnabled' => $staffEnabled,
   'supplierEnabled' => \Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier'),
   'canUpdateUser' => $canUpdateUser,
   'channelPermissions' => [
      'create' => Auth::user()->can('create', \Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::class),
      'update' => Auth::user()->can('update', \Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::class),
      'delete' => Auth::user()->can('delete', \Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::class),
   ],
   'notificationScopeOptions' => $notificationScopeOptions,
   'notificationEventOptions' => $notificationEventOptions,
];
@endphp
@extends('layouts.master')

@section('container')
@include('systemadmin.app')
@endsection
