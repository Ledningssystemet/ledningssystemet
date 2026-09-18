@php if(Auth::user()->cannot('index', \App\Models\Agreement::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Agreements") }}',
      paging: true,
      searchfield: true,
      tableId: 'agreementslist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Add agreement') }}',
      },
      filter: {
@php $tags = \App\Models\Agreement::allUsedTags(); @endphp
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
                  @foreach(\App\Models\User::leftJoin('agreements', 'agreements.responsible_user_id', '=', 'users.id')->whereNotNull('agreements.id')->select('users.*')->distinct()->orderBy('users.name')->get() as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showmyonly: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show only my agreements') }}',
         },
         hidechecked: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Hide items without issues') }}',
         },
         showarchived: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show archived agreements') }}',
         },
@include('components.customproperty', ['classname' => 'App\Models\Agreement', 'showFilter' => true])
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Agreement::class))
         listAction: '/api/v1/items/Agreement',
@endif         
@if(Auth::user()->can('create', \App\Models\Agreement::class))
         createAction: '/api/v1/items/Agreement',
@endif         
@if(Auth::user()->can('update', \App\Models\Agreement::class))
         updateAction: '/api/v1/items/Agreement',
@endif         
@if(Auth::user()->can('delete', \App\Models\Agreement::class))
         deleteAction: '/api/v1/items/Agreement',
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
         tags: showTags('Agreement', $('#tableContainer')),
         context: {
            title: '{{ __('Associated object') }}',
            list: true,
            edit: true,
            create: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
<?php
   // Customer
   echo("{ Label: '".__('Customer')."', Children: [\r\n");
   foreach(\App\Models\Customer::orderBy('name')->get()->each->setAppends([]) as $obj)
      echo("{ Value: 'Customer_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
   echo("]},\r\n");

   if(!config('ledningssystemet.disable_supplier'))
   {
      // Suppliers
      echo("{ Label: '".__('Supplier')."', Children: [\r\n");
      foreach(\App\Models\Supplier::orderBy('name')->get()->each->setAppends([]) as $obj)
         echo("{ Value: 'Supplier_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
      echo("]},\r\n");
   }
?>               
            ]
         },
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-6 col-md-4',
            defaultValue: {{ auth()->user()->id }},
            options: [
               { Value: null, DisplayText: '{{ __("None assigned") }}' },
@foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
@endforeach
            ],
         },
         hr0: {
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
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         startdate: {
            title: '{{ __("Start date") }}',
            type: 'date',
            list: true,
            edit: true,
            create: true,
            listClass: 'd-inline-block col-6 col-md-4',
         },
         reminderdate: {
            title: '{{ __("Reminder date") }}',
            type: 'date',
            list: true,
            edit: true,
            create: true,
            listClass: 'd-inline-block col-6 col-md-4',
         },
         enddate: {
            title: '{{ __("End date") }}',
            type: 'date',
            list: true,
            edit: true,
            create: true,
            listClass: 'd-inline-block col-6 col-md-4',
         },
@include('components.customproperty', ['classname' => 'App\Models\Agreement'])
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
         archive: {
            title: '',
            list: true,
            edit: false,
            create: false,
            footer: true,
            display: function (data) {
               var retval = $('<div />');

               if (!data.record.archived_at && (data.record.responsible_user_id == {{ auth()->user()->id }})) {
                  retval.append($('<button />')
                     .css({'margin-right': '10px'})
                     .addClass('btn btn-sm btn-outline-primary')
                     .text('{{ __("Archive agreement") }}')
                     .click(function () {
                        confirmDialog('{{ __("Archive agreement") }}', '{{ __("Are you sure that you want to archive this agreement?") }}', function () {
                           ajaxGet('/api/v1/items/Agreement/' + data.record.id+'/archive', function () {
                              $('#tableContainer').jtable('reload');
                           });
                        }, {warning: true});
                     })
                     .prepend($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('archive'))
                  );
               }

               return retval;
            }
         },
         files: showFiles('Agreement', $('#tableContainer'), '{{ csrf_token() }}'),
      },
      rowLoaded: function(event, data){
         if(!data.record.responsible_user_id)
            $('#jtable-body-agreementslist > .jtable-data-row[data-record-key="'+data.record.id+'"] div[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');
         
         if(!data.record.startdate)
            $('#jtable-body-agreementslist > .jtable-data-row[data-record-key="'+data.record.id+'"] div[data-jtable-fieldname="startdate"] .jtable-field-label').addClass('text-danger');
         
         if(!data.record.enddate)
            $('#jtable-body-agreementslist > .jtable-data-row[data-record-key="'+data.record.id+'"] div[data-jtable-fieldname="enddate"] .jtable-field-label').addClass('text-danger');
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>

<div id="tableContainer"></div>
   
@endsection
