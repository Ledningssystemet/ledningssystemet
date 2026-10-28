@php if(Auth::user()->cannot('index', \Ledningssystemet\Ledningssystemet\Models\Site::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Sites") }}',
      paging: true,
      searchfield: true,
      tableId: 'siteslist',
      bootstrap: true,
      accordion: true,
      filter: {
@php $tags = \Ledningssystemet\Ledningssystemet\Models\Site::allUsedTags(); @endphp
@if(0 < count($tags))
         tag_id: {
            type: 'select',
            text: '{{ __("Tag") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
@foreach($tags as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach               
            ]
         },
@endif
         responsible_user_id: {
            type: 'select',
            text: '{{ __("Responsible user") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
                  @foreach(\Ledningssystemet\Ledningssystemet\Models\User::leftJoin('sites', 'sites.responsible_user_id', '=', 'users.id')->whereNotNull('sites.id')->select('users.*')->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         hidechecked: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Hide items without issues') }}',
         },
@include('components.customproperty', ['classname' => 'Ledningssystemet\Ledningssystemet\Models\Site', 'showFilter' => true])
      },
      messages: {
         addNewRecord: '{{ __('Create site') }}',
      },
      actions: {
@if(Auth::user()->can('index', \Ledningssystemet\Ledningssystemet\Models\Site::class))
         listAction: '/api/v1/items/Site',
@endif
@if(Auth::user()->can('create', \Ledningssystemet\Ledningssystemet\Models\Site::class))
         createAction: '/api/v1/items/Site',
@endif
@if(Auth::user()->can('update', \Ledningssystemet\Ledningssystemet\Models\Site::class))
         updateAction: '/api/v1/items/Site',
@endif
@if(Auth::user()->can('delete', \Ledningssystemet\Ledningssystemet\Models\Site::class))
         deleteAction: '/api/v1/items/Site',
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
         tags: showTags('Site', $('#tableContainer')),
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            create: true,
            edit: true,
            list: true,

            required: true,
            listClass: 'd-inline-block col-6 col-md-3',
            defaultValue: {{ auth()->user()->id }},
            options: [
               { Value: null, DisplayText: '{{ __("None assigned") }}' },
@foreach(Ledningssystemet\Ledningssystemet\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
@endforeach
            ],
         },
@if(config('ledningssystemet.graph_sync_enabled', false))
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
            listClass: 'd-inline-block col-12 col-md-4',
@if(config('ledningssystemet.graph_sync_enabled', false))
            tooltip: '{{ __("This will be overwritten by the next synchronization with external provider if a group is selected above") }}',
@endif
            options: [
@foreach(Ledningssystemet\Ledningssystemet\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach
            ]
         },
         departments: {
            title: '{{ __('Departments') }}',
            create: true,
            edit: true,
            list: true,
            multiple: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
@foreach(Ledningssystemet\Ledningssystemet\Models\Department::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach
            ]
         },
         assets: {
            title: '{{ __('Assets') }}',
            create: true,
            edit: true,
            list: true,
            multiple: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
@foreach(Ledningssystemet\Ledningssystemet\Models\Asset::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach
            ]
         },
         @include('components.customproperty', ['classname' => 'Ledningssystemet\Ledningssystemet\Models\Site'])
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
@if(Auth::user()->can('index', \Ledningssystemet\Ledningssystemet\Models\Site::class))
         showRisks: showRisks('Site', $('#tableContainer')),
@endif
         showHistory: showHistoryField('Site', $('#tableContainer')),          
         showMessages: showMessagesField('Site', $('#tableContainer')),    
      },
      recordUpdated: function(event, data){
        $(event.target).jtable('reloadRow', data.row);
      },
      rowLoaded: function(event, data){
      }
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer"></div>
   
@endsection
