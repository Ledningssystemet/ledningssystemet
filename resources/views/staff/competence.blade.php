@php if(Auth::user()->cannot('index', \App\Models\Competence::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Competences") }}',
      paging: true,
      searchfield: true,
      tableId: 'competencestable',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Add new competence') }}',
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Competence::class))         
         listAction: '/api/v1/items/Competence',
@endif      
@if(Auth::user()->can('create', \App\Models\Competence::class))         
         createAction: '/api/v1/items/Competence',
@endif      
@if(Auth::user()->can('update', \App\Models\Competence::class))         
         updateAction: '/api/v1/items/Competence',
@endif      
@if(Auth::user()->can('delete', \App\Models\Competence::class))         
         deleteAction: '/api/v1/items/Competence',
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
            listClass: 'd-inline-block col-12 col-md-12',
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
            title: '{{ __("Evaluated employees") }}',
            list: true,
            edit: false,
            create: false,
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.users).forEach((obj) => {
                  retval.append( $('<div />')
                     .text(obj.name));
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
                     .text(obj.name));
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
         competencelevels: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (sourcedata) {
               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Competence levels') }}')
                  .prepend($('<span>stairs_2</span>')
                     .addClass('material-symbols-rounded'));
                  
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#competencesstable').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                   $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Competence levels") }}',
                        tableId: 'competencesstable',
                        actions: {
@if(Auth::user()->can('index', \App\Models\CompetenceLevel::class))         
                           listAction: '/api/v1/items/CompetenceLevel?competence_id='+sourcedata.record.id,
@endif         
@if(Auth::user()->can('create', \App\Models\CompetenceLevel::class))         
                           createAction:  '/api/v1/items/CompetenceLevel',
@endif         
@if(Auth::user()->can('update', \App\Models\CompetenceLevel::class))         
                           updateAction:  '/api/v1/items/CompetenceLevel',
@endif         
@if(Auth::user()->can('delete', \App\Models\CompetenceLevel::class))         
                           deleteAction:  '/api/v1/items/CompetenceLevel',
@endif         
@if(Auth::user()->can('update', \App\Models\CompetenceLevel::class))         
                           reorderAction:  '/api/v1/items/CompetenceLevel',
@endif         
                        },
                        messages: {
                           addNewRecord: '{{ __('Add competence level') }}',
                        },
                        fields: {
                           competence_id: {
                              type: 'hidden',
                              defaultValue: sourcedata.record.id,
                              create: true,
                              edit: true,
                           },
                           id: {
                              key: true,
                              list: false,
                              edit: false,
                              create: false,
                           },
                           name: {
                              title: '{{ __('Name') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                              maxlength: 255,
                              width: '40%',
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                           },
                        },
                        formCreated: function(event, data){
                        },
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         },         
         showHistory: showHistoryField('Competence', $('#tableContainer')),          
         showMessages: showMessagesField('Competence', $('#tableContainer')),  
      },
   });
   $('#tableContainer').jtable('load');
});
</script>
<div id="tableContainer"></div>
   
@endsection
