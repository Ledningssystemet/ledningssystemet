@php if(Auth::user()->cannot('index', \App\Models\FormTemplate::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Form templates") }}',
      paging: true,
      sorting: false,
      defaultSorting: 'name ASC',
      searchfield: true,
      tableId: 'formtemplatelist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Add form template') }}',
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\FormTemplate::class))
         listAction: '/api/v1/items/FormTemplate',
@endif
@if(Auth::user()->can('create', \App\Models\FormTemplate::class))
         createAction: '/api/v1/items/FormTemplate',
@endif
@if(Auth::user()->can('update', \App\Models\FormTemplate::class))
         updateAction: '/api/v1/items/FormTemplate',
@endif
@if(Auth::user()->can('delete', \App\Models\FormTemplate::class))
         deleteAction: '/api/v1/items/FormTemplate',
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
            header: true,
            required: true,
            maxlength: 255,
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
         },
         context: {
            title: '{{ __('Context') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            options: [
               { "DisplayText": "{{ __('Customer') }}", "Value": "customer" },
               { "DisplayText": "{{ __('Supplier') }}", "Value": "supplier" }
            ]
         },
         edittemplate: {
            sorting: false,
            edit: false,
            create: false,
            type: 'command',
            footer: true,
            display: function(data){
               return '<a class="btn btn-outline-primary btn-sm" href="/management/formtemplate/'+data.record.id+'" title="{{ __('Edit template') }}"><span class="material-symbols-rounded">draw</span>{{ __("Edit template") }}</a>';
            }
         },
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer" ></div>
   
@endsection
