@php
abort_unless(auth()->user()->can('systemadministrator.edit'), 403);
$optionList = fn ($items) => $items
    ->map(fn ($item) => ['value' => (string) $item->id, 'label' => $item->name])
    ->values()->all();
$permissions = DB::table('permissions')
    ->where('guard_name', \Ledningssystemet\Ledningssystemet\Models\AccessGroup::SHARED_GUARD)
    ->orderBy('name')->pluck('name', 'id')->map(fn ($name) => __($name))->all();
asort($permissions);
$graphEnabled = (bool) config('ledningssystemet.graph_sync_enabled', false);
$labels = [
    'Access groups', 'Name', 'Users', 'Max risk acceptance level', 'Access rights',
    'None', 'This will be overwritten by the next synchronization with external provider if a group is selected above',
    'Title', 'Enabled', 'No', 'Yes', 'E-mail address', 'Last login', 'Manager',
    'Departments', 'Roles', 'Direct reports', 'Access', 'Organization', 'User',
    'Custom properties', 'Status', 'Deactivated', 'API tokens',
    'Changes were saved, but the page data could not be refreshed.',
];
$bootstrap = [
    'csrfToken' => csrf_token(),
    'urls' => [
       'crud' => '/api/v1/crud',
   ],
    'configuration' => [
        'staffEnabled' => \Ledningssystemet\Ledningssystemet\Support\Modules::active('staff'),
        'supplierEnabled' => \Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier'),
        'graphSyncEnabled' => $graphEnabled,
        'graphProviderName' => config('ledningssystemet.graph_provider_name', __('External provider group')),
        'graphDepartmentsAssignUsers' => (bool) config('ledningssystemet.graph_departments_assignusers'),
        'localUserManagement' => (bool) config('ledningssystemet.local_user_management'),
    ],
    'labels' => collect($labels)->mapWithKeys(fn ($label) => [$label => __($label)])->all(),
    'groups' => [
        'permissionOptions' => collect($permissions)
            ->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values()->all(),
        'externalProviderOptions' => $graphEnabled
            ? $optionList(DB::table('external_provider_groups')->orderBy('name')->get(['id', 'name']))
            : [],
        'notifications' => [
            'scopeOptions' => array_map(fn ($scope) => ['value' => $scope['key'], 'label' => $scope['text']], \Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::availableScopes()),
            'eventOptions' => array_map(fn ($event) => ['value' => $event['key'], 'label' => $event['text']], \Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::availableEvents()),
        ],
        'settings' => [
            'modules' => collect(\Ledningssystemet\Ledningssystemet\Support\Modules::LABELS)->map(fn ($label, $module) => [
                'key' => $module, 'label' => __($label),
                'enabled' => \Ledningssystemet\Ledningssystemet\Support\Modules::active($module),
            ])->values()->all(),
            'contact' => collect(\Ledningssystemet\Ledningssystemet\Support\SystemSettings::CONTACT_FIELDS)
                ->map(fn ($label, $key) => \Ledningssystemet\Ledningssystemet\Support\SystemSettings::value($key))->all(),
            'assessment' => collect(\Ledningssystemet\Ledningssystemet\Support\SystemSettings::ASSESSMENT_FIELDS)
                ->map(fn ($label, $key) => \Ledningssystemet\Ledningssystemet\Support\SystemSettings::integerValue($key))->all(),
            'contactFields' => collect(\Ledningssystemet\Ledningssystemet\Support\SystemSettings::CONTACT_FIELDS)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => __($label), 'email' => $key === 'data_privacy_contact_email'])->values()->all(),
            'assessmentFields' => collect(\Ledningssystemet\Ledningssystemet\Support\SystemSettings::ASSESSMENT_FIELDS)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => __($label)])->values()->all(),
        ],
    ],
];
@endphp
@extends('layouts.master')
@section('container')
@vite(['resources/js/apps/systemadmin/index.jsx'])
<systemadmin-app data-props="{{ json_encode($bootstrap) }}"></systemadmin-app>
@endsection

