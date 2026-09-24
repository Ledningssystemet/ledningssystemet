@php if(Auth::user()->cannot('index', \App\Models\Qualification::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Qualifications") }}',
      paging: true,
      searchfield: true,
      tableId: 'qualificationstable',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Add new qualification') }}',
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Qualification::class))         
         listAction: '/api/v1/items/Qualification',
@endif      
@if(Auth::user()->can('create', \App\Models\Qualification::class))         
         createAction: '/api/v1/items/Qualification',
@endif      
@if(Auth::user()->can('update', \App\Models\Qualification::class))         
         updateAction: '/api/v1/items/Qualification',
@endif      
@if(Auth::user()->can('delete', \App\Models\Qualification::class))         
         deleteAction: '/api/v1/items/Qualification',
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
            maxlength: 255,
            header: true,
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
         expires: {
            title: '{{ __('Expires') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
               { Value: 0, DisplayText: '{{ __("No") }}' },
               { Value: 1, DisplayText: '{{ __("Yes") }}' },
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
         users: {
            title: '{{ __("Employees") }}',
            list: true,
            edit: false,
            create: false,
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.users).forEach((obj) => {
                  retval.append( $('<div />')
                     .text(obj.name+', {{ __("Performed") }}: '+(obj.finished_at ? obj.finished_at : '{{ __("Never") }}')+(obj.expires_at ? ', {{ __("Expires") }}: '+obj.expires_at : '')+', Planned: '+(obj.planned_at ? obj.planned_at : '{{ __("Not planned") }}'))
                  );
               });
               
               return retval;
            }
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         roles: {
            title: '{{ __("Roles") }}',
            list: true,
            edit: false,
            create: false,
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.roles).forEach((obj) => {
                  retval.append( $('<div />')
                     .text(obj.name+', {{ __("Mandatory") }}: '+(obj.mandatory ? '{{ __("Yes") }}' : '{{ __("No") }}'))
                  );
               });
               
               return retval;
            }
         },
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         
         showHistory: showHistoryField('Qualification', $('#tableContainer')),          
         showMessages: showMessagesField('Qualification', $('#tableContainer')),  
      },
   });
   $('#tableContainer').jtable('load');
});
</script>
<div id="tableContainer"></div>
   
@endsection
