@php
if (Auth::user()->cannot('index', \Ledningssystemet\Ledningssystemet\Models\AccessGroup::class)) abort(403);
$optionList = fn ($items) => $items
   ->map(fn ($item) => ['value' => (string) $item->id, 'label' => $item->name])
   ->values()
   ->all();
$userOptions = $optionList(\Ledningssystemet\Ledningssystemet\Models\User::orderBy('name')->get()->each->setAppends([]));
$riskLevelOptions = [
   ['value' => '', 'label' => __('None')],
   ...$optionList(\Ledningssystemet\Ledningssystemet\Models\RiskLevel::orderBy('ordinal')->get()->each->setAppends([])),
];
$permissions = DB::table('permissions')
   ->where('guard_name', \Ledningssystemet\Ledningssystemet\Models\AccessGroup::SHARED_GUARD)
   ->orderBy('name')
   ->pluck('name', 'id')
   ->map(fn ($name) => __($name))
   ->toArray();
asort($permissions);
$permissionOptions = collect($permissions)
   ->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])
   ->values()
   ->all();
$tableColumns = ['name', 'users', 'risk_level_id', 'permission_ids'];
$columns = [
   ['key' => 'name', 'label' => __('Name'), 'form' => 'both', 'required' => true, 'maxLength' => 255, 'sortable' => true],
   ['key' => 'users', 'label' => __('Users'), 'type' => 'select', 'multiple' => true, 'form' => 'both', 'options' => $userOptions, 'sortable' => false],
   ['key' => 'risk_level_id', 'label' => __('Max risk acceptance level'), 'type' => 'select', 'form' => 'both', 'options' => $riskLevelOptions, 'sortable' => false],
   ['key' => 'permission_ids', 'label' => __('Access rights'), 'type' => 'select', 'multiple' => true, 'form' => 'both', 'options' => $permissionOptions, 'sortable' => false],
];
if (config('ledningssystemet.graph_sync_enabled', false)) {
   $externalProviderOptions = [
      ['value' => '', 'label' => __('None')],
      ...DB::table('external_provider_groups')
         ->orderBy('name')
         ->get()
         ->map(fn ($group) => ['value' => (string) $group->id, 'label' => $group->name])
         ->values()
         ->all(),
   ];
   $tableColumns[] = 'external_provider_group_id';
   $columns[] = [
      'key' => 'external_provider_group_id',
      'label' => config('ledningssystemet.graph_provider_name', __('External provider group')),
      'type' => 'select',
      'form' => 'both',
      'options' => $externalProviderOptions,
      'sortable' => false,
      'help' => __('This will be overwritten by the next synchronization with external provider if a group is selected above'),
   ];
}
$props = [
   'page' => 'access',
   'config' => [
      'listTitle' => __('Access groups'),
      'tableId' => 'accesstable',
      'titleKey' => 'name',
      'subtitleKeys' => [],
      'defaultView' => 'masterDetail',
      'editMode' => 'popup',
      'perPage' => 10,
      'searchKeys' => ['name'],
      'dataSource' => [
         'baseUrl' => '/api/v1/crud',
         'model' => 'AccessGroup',
         'fields' => ['access', 'users', 'permission_ids', 'risk_level_id', 'external_provider_group_id'],
         'sort' => ['name'],
         'headers' => ['X-CSRF-TOKEN' => csrf_token()],
      ],
      'permissions' => [
         'create' => Auth::user()->can('create', \Ledningssystemet\Ledningssystemet\Models\AccessGroup::class),
         'update' => Auth::user()->can('update', \Ledningssystemet\Ledningssystemet\Models\AccessGroup::class),
         'delete' => Auth::user()->can('delete', \Ledningssystemet\Ledningssystemet\Models\AccessGroup::class),
      ],
      'columns' => $columns,
      'tableColumns' => $tableColumns,
      'defaultTableColumns' => array_values(array_intersect($tableColumns, ['name', 'users', 'permission_ids'])),
      'detailSections' => [],
      'filters' => [],
      'newItemDefaults' => ['users' => [], 'permission_ids' => [], 'risk_level_id' => '', 'external_provider_group_id' => ''],
   ],
];
@endphp
@extends('layouts.master')

@section('container')
@include('systemadmin.app')
@endsection
