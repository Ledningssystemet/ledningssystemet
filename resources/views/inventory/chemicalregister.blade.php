@php if(Auth::user()->cannot('index', \App\Models\Chemical::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')
<style type="text/css">
   span.badge {
      margin: 0 3px 0 3px;
   }

</style>
<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Chemical register") }}',
      paging: true,
      sorting: false,
      defaultSorting: 'name ASC',
      searchfield: true,
      tableId: 'chemicalslist',
      bootstrap: true,
      accordion: true,
      filter: {
         @php $tags = \App\Models\Chemical::allUsedTags(); @endphp
            @if(0 < count($tags))
         tag_id: {
            type: 'select',
            text: '{{ __("Tag") }}',
            default: 0,
            options: [
               {value: 0, text: '{{ __('Show all') }}'},
                  @foreach($tags as $obj)
               {
                  value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         @endif
@include('components.customproperty', ['classname' => 'App\Models\Chemical', 'showFilter' => true])
      },
      messages: {
         addNewRecord: '{{ __('Create chemical') }}',
      },
      actions: {
@if(request()->user()->can('index', \App\Models\Chemical::class))         
         listAction: '/api/v1/items/Chemical',
@endif         
@if(request()->user()->can('create', \App\Models\Chemical::class))         
         createAction: '/api/v1/items/Chemical',
@endif         
@if(request()->user()->can('update', \App\Models\Chemical::class))         
         updateAction: '/api/v1/items/Chemical',
@endif         
@if(request()->user()->can('delete', \App\Models\Chemical::class))         
         deleteAction: '/api/v1/items/Chemical',
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
         },
         tags: showTags('Chemical', $('#tableContainer')),
         manufacturer: {
            title: '{{ __('Manufacturer') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            listClass: 'd-inline-block col-4',
         },
         ohs_danger_properties: {
            title: '{{ __('Danger properties') }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-8',
            display: function(data) {
               var retval = $('<div />');
@foreach(array_keys(App\Models\Chemical::dangerProperties()) as $objkey)
               if(data.record.danger.includes(@php echo(json_encode($objkey)); @endphp))
               {
                  retval.append($('<div />')
                     .addClass('d-inline-block px-1 py-1')
                     .append($('<div />')
                        .attr('title', @php echo(json_encode(App\Models\Chemical::dangerProperties()[$objkey]['text'])); @endphp)
                        .append(@php echo(json_encode(App\Models\Chemical::dangerProperties()[$objkey]['svg'])); @endphp)
                     )
                  );
               }
@endforeach    
               return retval;
            },
            input: function(data) {
               var retval = $('<div />');
@foreach(array_keys(App\Models\Chemical::dangerProperties()) as $objkey)
               retval.append($('<div />')
                  .addClass('d-inline-block col-12 col-md-4 mb-1')
                  .append($('<input />')
                     .addClass('form-check-input')
                     .attr('type', 'checkbox')
                     .attr('id', 'ohs_danger_properties_{{ $objkey }}')
                     .attr('name', 'danger[]')
                     .attr('checked', (data.record && data.record.danger && Array.isArray(data.record.danger) && data.record.danger.includes(@php echo(json_encode($objkey)); @endphp)))
                     .attr('value', @php echo(json_encode($objkey)); @endphp)
                     .css({'position': 'relative', 'top': '10px' })
                  )
                  .append($('<label />')
                     .addClass('form-check-label')
                     .attr('for', 'ohs_danger_properties_{{ $objkey }}')
                     .attr('title', @php echo(json_encode(App\Models\Chemical::dangerProperties()[$objkey]['text'])); @endphp)
                     .append(@php echo(json_encode(App\Models\Chemical::dangerProperties()[$objkey]['svg'])); @endphp)
                  )
               );
@endforeach    
               return retval;
            }
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
            listClass: 'd-inline-block col-12 col-md-4',
         },
         usagedescription: {
            title: '{{ __('Usage') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
            tooltip: '{{ __("Describe how the chemical is used (purposes), where and by whom") }}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         storagedescription: {
            title: '{{ __('Storage') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
            tooltip: '{{ __("Describe how and where the chemical is safely stored") }}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         consumptiondescription: {
            title: '{{ __('Annual consumption') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
            tooltip: '{{ __("Describe the consumption pattern") }}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         riskdescription: {
            title: '{{ __('Risk description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
            tooltip: '{{ __("Describe the risk assessment being performed for this chemical") }}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         handlingguidance: {
            title: '{{ __('Handling guidance') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
            tooltip: '{{ __("Describe any specific guidance for handling") }}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         sdbfile: {
            title: '{{ __("Safety Datasheet") }}',
            type: 'file',
            accept: 'application/pdf',
            list: false,
            edit: true,
            create: true,
         },
         @include('components.customproperty', ['classname' => 'App\Models\Chemical'])
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         download: {
            title: '',
            create: false,
            edit: false,
            list: true,
            width: '1%',
            footer: true,
            display: function(data){
               
               if(!data.record.sdbfilename)
                  return '';
               
               return $('<a />')
                  .attr('href', '/api/v1/items/Chemical/'+data.record.id+'/download')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __("Download safety datasheet") }}')
                  .prepend($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('download')
                  );
            }
         },         
         showHistory: showHistoryField('Chemical', $('#tableContainer')),          
         showMessages: showMessagesField('Chemical', $('#tableContainer')),    
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div class="d-flex flex-row flex-row-reverse mb-4 w-100">
</div>
<?php
$lastupdatedchem = \App\Models\Chemical::orderBy('updated_at', 'desc')->select('updated_at')->first();
if(null !== $lastupdatedchem)
   echo('<div style="color: var(--bs-gray-500); font-style: italic; margin-bottom: 20px;">'.__("Updated").': '.date("Y-m-d", strtotime($lastupdatedchem->updated_at)).'</div>');

?>
<div id="tableContainer"></div>
   
@endsection
