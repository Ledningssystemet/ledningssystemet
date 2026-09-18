@php if(Auth::user()->cannot('index', \App\Models\ControlAction::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<style>
   #tableContainer > .jtable-main-container > .jtable-title > .jtable-filter > .jtable-filter-group:first-child{
      width: 100%;
   }
   #tableContainer > .jtable-main-container > .jtable-title .jtable-filter .form-check-input {
      margin-left: -2em;
   }
   #tableContainer > .jtable-main-container > .jtable-title > .jtable-filter > .jtable-filter-group:first-child select {
      width: auto;
      margin: 0;
   }
</style>
<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Actions") }}',
      paging: true,
      searchfield: true,
      tableId: 'controlactionstable',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Add new action') }}',
      },
      filter: {
@php $tags = \App\Models\ControlAction::allUsedTags(); @endphp
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
         control: {
            type: 'select',
            text: '{{ __("Control") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
@foreach(\App\Models\Control::orderBy('name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: @php echo(json_encode($obj->name.($obj->not_applicable_at ? " [".__("Not applicable")."]" : ""))); @endphp },
@endforeach               
            ]
         },
         responsible_id: {
            type: 'select',
            text: '{{ __("Responsible user") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
                  @foreach(\App\Models\User::leftJoin('control_actions', 'control_actions.responsible_id', '=', 'users.id')->whereNotNull('control_actions.id')->select(['users.id', 'users.name'])->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showhandled: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show handled actions') }}',
         },
         showmyonly: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show only my actions') }}',
         },
      },
      actions: {
@if(Auth::user()->can('index', App\Models\ControlAction::class))         
         listAction: '/api/v1/items/ControlAction',
@endif         
@if(Auth::user()->can('create', App\Models\ControlAction::class))         
         createAction: '/api/v1/items/ControlAction',
@endif         
@if(Auth::user()->can('update', App\Models\ControlAction::class))         
         updateAction: '/api/v1/items/ControlAction',
@endif         
@if(Auth::user()->can('delete', App\Models\ControlAction::class))         
         deleteAction: '/api/v1/items/ControlAction',
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
         tags: showTags('ControlAction', $('#tableContainer')),
         control_id: {
            title: '{{ __('Control') }}',
            width: '15%',
            list: true,
            edit: true,
            create: true,
            required: true,

            listClass: 'd-inline-block col-4',
            options:[
@foreach(App\Models\Control::orderBy('name')->select(['id', 'name', 'not_applicable_at'])->get()->each->setAppends([]) as $obj)
{ Value: {{ $obj->id }}, DisplayText: '{{ $obj->name }}{{ $obj->not_applicable_at ? " [".__("Not applicable")."]" : "" }}'}, 
@endforeach
            ]
         },
         created_by_name: {
            title: '{{ __('Created by') }}',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col-4',
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         origin: {
            title: '{{ __('Origin') }}',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col-12',
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
         responsible_id: {
            title: '{{ __('Responsible') }}',
            width: '15%',
            list: true,
            edit: true,
            create: true,

            listClass: 'd-inline-block col-4',
            options: [
@foreach(\App\Models\User::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: '{{ $obj->name }}'},
@endforeach                                 
            ],
            required: true,
         },
         due: {
            title: '{{ __('Due') }}',
            type: 'date',
            width: '10%',
            defaultValue: '{{ date("Y-m-d", strtotime("+3 MONTHS")) }}',
            list: true,
            edit: true,
            create: true,
            required: true,
            listClass: 'd-inline-block col-4',
         },
         hr2: {
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
            required: true,
            listClass: 'd-inline-block col-12 col-md-8',
         },
         estimated_cost: {
            title: '{{ __('Estimated cost') }}',
            type: 'number',
            min: 0,
            step: 1,
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data){
               if(!data.record.estimated_cost)
                  return '-';

               // Format as currency
               return new Intl.NumberFormat('sv-SE', {
                  style: 'currency',
                  currency: 'SEK',
                  minimumFractionDigits: 0,
                  maximumFractionDigits: 0
               }).format(data.record.estimated_cost);
            }
         },
         hr3: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
@if(Auth::user()->can('index', App\Models\ControlAction::class))         
         commands: {
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function(data){
               if(({{ auth()->user()->id }} == data.record.responsible_id) &&
                  !data.record.finished_at)
               {
                  return $('<button />')
                     .addClass('btn btn-outline-primary btn-sm')
                     .text('{{ __('Finish') }}')
                     .prepend($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('check'))
                     .click(function(){ 
                        confirmDialog('{{__("Finish") }}', '{{__("By marking this action as finished, you state that the planned actions have been performed")}}', function(){
                           ajaxPatch('/api/v1/items/ControlAction/'+data.record.id, { finished_at: 1 }, function(){
                              $('#tableContainer').jtable('reload');
                           });                        
                        }, { warning: true });
                     });
               }
               else
               {
                  return $('<span />');
               }
            }
         },
@endif         
         showHistory: showHistoryField('ControlAction', $('#tableContainer')),          
         showMessages: showMessagesField('ControlAction', $('#tableContainer')),    
      },
      formCreated: function(event, data){
      },
      recordsLoaded: function(event, data){
         if(data.serverResponse.data)
         {
            data.serverResponse.data.forEach((obj) => {
               if(obj.finished_at)
               {
                  $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-edit-command').remove();
                  $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-delete-command').remove();
               }
            });
         }
         else
         {
            if(data.serverResponse.finished_at)
            {
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .jtable-edit-command').remove();
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .jtable-delete-command').remove();
            }
         }
      },
   });
   $('#tableContainer').jtable('load');
});
</script>
<div id="tableContainer"></div>
   
@endsection
