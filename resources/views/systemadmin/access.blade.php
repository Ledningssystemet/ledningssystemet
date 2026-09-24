@php if(Auth::user()->cannot('index', \App\Models\AccessGroup::class)) abort(403); @endphp
@extends('layouts.master')
@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Access groups") }}',
      paging: true,
      searchfield: true,
      tableId: 'accesstable',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Create access group') }}',
      },
      actions: {
@if(Auth::user()->can('index', 'App\\Models\\AccessGroup'))
         listAction: '/api/v1/items/AccessGroup',
@endif
@if(Auth::user()->can('create', 'App\\Models\\AccessGroup'))
         createAction: '/api/v1/items/AccessGroup',
@endif
@if(Auth::user()->can('update', 'App\\Models\\AccessGroup'))
         updateAction: '/api/v1/items/AccessGroup',
@endif         
@if(Auth::user()->can('delete', 'App\\Models\\AccessGroup'))
         deleteAction: '/api/v1/items/AccessGroup',
@endif         
      },
      fields: {
         id: {
            key: true,
            list: false,
            create: false,
            edit: false,
         },
         name: {
            title: '{{ __('Name') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            header: true,
            maxlength: 255,
         },
@if("" != config('ledningssystemet.graph_groupsync_path', ''))
         external_provider_group_id: {
            title: '{{ config("ledningssystemet.graph_provider_name", __("External provider"))." ".__("group") }}',
            create: true,
            edit: true,
            list: true,

            defaultValue: null,
            options: [
               { Value: null, DisplayText: '{{ __("None") }}' },
@foreach(\Illuminate\Support\Facades\DB::table('external_provider_groups')->orderBy('name')->get() as $obj)
               { Value: {{$obj->id}}, DisplayText: '{{ $obj->name }}' },
@endforeach
            ]
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },

@endif
         users: {
            title: '{{ __('Users') }}',
            create: true,
            edit: true,
            list: true,
            multiple: true,
@if("" != config('ledningssystemet.graph_groupsync_path', ''))
            tooltip: 'This will be overwritten by the next synchronization with external provider if a group is selected above',
@endif
            options: [
@foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach
            ]
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         risk_level_id: {
            title: '{{ __('Max risk acceptance level') }}',
            create: true,
            edit: true,
            list: true,
            defaultValue: null,
            options: [
               { Value: null, DisplayText: '{{ __("None") }}' },
@foreach(App\Models\RiskLevel::orderBy('ordinal')->get()->each->setAppends([]) as $obj)
               { Value: {{$obj ->id}}, DisplayText: '{{ $obj->name }}' },
@endforeach
            ]
         },
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         permission_ids: {
            title: '{{ __('Access rights') }}',
            create: true,
            edit: true,
            list: true,
            multiple: true,
            options: [
@php   
   $permissions = DB::table('permissions')
      ->where('guard_name', App\Models\AccessGroup::SHARED_GUARD)
      ->orderBy('name')
      ->pluck('name', 'id')
      ->toArray();
@endphp
@foreach($permissions as $permissionId => $permissionName)
               { Value: {{ $permissionId }}, DisplayText: @php echo(json_encode(__($permissionName))); @endphp },
@endforeach
            ]
         },
         hr3: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer"></div>
   
@endsection
