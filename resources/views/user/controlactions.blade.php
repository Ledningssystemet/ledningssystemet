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
               { value: {{ $obj->id }}, text: @php echo(json_encode($obj->name)); @endphp },
@endforeach               
            ]
         },
         showhandled: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show handled actions') }}',
         },
      },
      actions: {
         listAction: '/api/v1/items/ControlAction?showmyonly=1',
         updateAction: '/api/v1/items/ControlAction',
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
@foreach(App\Models\Control::orderBy('name')->get()->each->setAppends([]) as $obj)
{ Value: {{ $obj->id }}, DisplayText: '{{ $obj->name}}'}, 
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
         due: {
            title: '{{ __('Due') }}',
            type: 'date',
            width: '10%',
            defaultValue: '{{ date("Y-m-d", strtotime("+3 MONTHS")) }}',
            list: true,
            edit: true,
            required: true,
            listClass: 'd-inline-block col-4',
         },
         origin: {
            title: '{{ __('Origin') }}',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col-8',
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
            required: true,
         },
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
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
                        confirmDialog('{{__("Finish") }}', '{{__("By marking this control as finish, you state that the planned actions have been performed")}}', function(){
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
         showHistory: showHistoryField('ControlAction', $('#tableContainer')),          
         showMessages: showMessagesField('ControlAction', $('#tableContainer')),    
      },
   });
   $('#tableContainer').jtable('load');
});
</script>
<div id="tableContainer"></div>
   
@endsection
