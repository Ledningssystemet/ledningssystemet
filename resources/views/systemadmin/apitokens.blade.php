@php
if (Auth::user()->cannot('index', \Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken::class)) abort(403);
$userOptions = \Ledningssystemet\Ledningssystemet\Models\User::orderBy('name')->get()
   ->each->setAppends([])
   ->map(fn ($user) => ['value' => (string) $user->id, 'label' => $user->name])
   ->values()
   ->all();
$props = [
   'page' => 'apitokens',
   'canCreate' => Auth::user()->can('create', \Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken::class),
   'userOptions' => $userOptions,
   'config' => [
      'listTitle' => __('API tokens'),
      'tableId' => 'tokenslist',
      'titleKey' => 'name',
      'subtitleKeys' => [],
      'defaultView' => 'table',
      'editMode' => 'inline',
      'perPage' => 10,
      'searchKeys' => ['name'],
      'dataSource' => [
         'baseUrl' => '/api/v1/crud',
         'model' => 'PersonalAccessToken',
         'sort' => ['name'],
         'headers' => ['X-CSRF-TOKEN' => csrf_token()],
      ],
      'permissions' => [
         'create' => false,
         'update' => Auth::user()->can('update', \Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken::class),
         'delete' => Auth::user()->can('delete', \Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken::class),
      ],
      'columns' => [
         ['key' => 'name', 'label' => __('Name'), 'form' => 'both', 'required' => true, 'maxLength' => 255, 'sortable' => true],
         ['key' => 'user_id', 'label' => __('User'), 'type' => 'select', 'form' => 'create', 'options' => $userOptions],
      ],
      'tableColumns' => ['name', 'user_id'],
      'detailSections' => [],
      'filters' => [],
      'newItemDefaults' => [],
   ],
];
@endphp
@extends('layouts.master')

@section('container')
@include('systemadmin.app')
@endsection
