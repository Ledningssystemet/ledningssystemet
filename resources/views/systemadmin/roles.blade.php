@php if(Auth::user()->cannot('index', \App\Models\Role::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Roles") }}',
      paging: true,
      searchfield: true,
      tableId: 'roleslist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Create role') }}',
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Role::class))         
         listAction: '/api/v1/items/Role',
@endif
@if(Auth::user()->can('create', \App\Models\Role::class))         
         createAction: '/api/v1/items/Role',
@endif
@if(Auth::user()->can('update', \App\Models\Role::class))         
         updateAction: '/api/v1/items/Role',
@endif
@if(Auth::user()->can('delete', \App\Models\Role::class))         
         deleteAction: '/api/v1/items/Role',
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
         role_users: {
            title: '{{ __('Users') }}',
            create: false,
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
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
         },
         hr2: {
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
