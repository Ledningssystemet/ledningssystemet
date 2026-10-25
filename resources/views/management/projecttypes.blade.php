@php if(!Auth::user()->canAny(['managementtools.edit'])) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>

$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Project types") }}',
      bootstrap: true,
      accordion: true,
      searchfield: true,
      messages: {
         addNewRecord: '{{ __('Add new type') }}',
      },
      actions: {
         listAction: '/api/v1/items/ProjectType',
         createAction: '/api/v1/items/ProjectType',
         updateAction: '/api/v1/items/ProjectType',
         deleteAction: '/api/v1/items/ProjectType',
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
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
         },
         risktemplates: {
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
                  .text('{{ __('Risk templates') }}')
                  .prepend($('<span>warning</span>')
                     .addClass('material-symbols-rounded'));
                  
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#risktemplatestable').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                  $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Risk templates") }}',
                        tableId: 'risktemplatestable',
                        paging: false,
                        bootstrap: true,
                        accordion: false,
                        actions: {
                           listAction: '/api/v1/items/ProjectTypeRiskTemplate?project_type_id='+sourcedata.record.id,
                           updateAction: '/api/v1/items/ProjectTypeRiskTemplate',
                           createAction: '/api/v1/items/ProjectTypeRiskTemplate',
                           deleteAction: '/api/v1/items/ProjectTypeRiskTemplate',
                        },
                        fields: {
                           project_type_id: {
                              type: 'hidden',
                              create: true,
                              defaultValue: sourcedata.record.id,
                           },
                           id: {
                              key: true,
                              list: false,
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
                           scenariodescription: {
                              title: '{{ __('Risk scenario') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                              listClass: 'd-inline-block col col-12 col-md-6',
                           },
                           consequencedescription: {
                              title: '{{ __('Consequence description') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                              listClass: 'd-inline-block col col-12 col-md-6',
                           },
                           hr0: {
                              list: true,
                              edit: false,
                              create: false,
                              display: function(data){
                                 return $('<hr />'); 
                              }
                           },
                           probability_id: {
                              title: '{{ __('Probability') }}',
                              create: true,
                              edit: true,
                              list: true,
                              listClass: 'd-inline-block col col-12 col-md-6',
                              options: [
                                 { Value: null, DisplayText: '{{ __("Not assessed") }}' },
                  @foreach(Ledningssystemet\Ledningssystemet\Models\ProbabilityLevel::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                                 { Value: {{$obj->id}}, DisplayText: <?php echo(json_encode($obj->name)); ?> },
                  @endforeach
                              ]
                           },
                           consequence_id: {
                              title: '{{ __('Consequence') }}',
                              create: true,
                              edit: true,
                              list: true,
                              listClass: 'd-inline-block col col-12 col-md-6',
                              options: [
                                 { Value: null, DisplayText: '{{ __("Not assessed") }}' },
                  @foreach(Ledningssystemet\Ledningssystemet\Models\ConsequenceLevel::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                                 { Value: {{$obj->id}}, DisplayText: <?php echo(json_encode($obj->name)); ?> },
                  @endforeach
                              ]
                           },
                        },
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         },      
      },
   });
   $('#tableContainer').jtable('load');          
});
</script>

<div id="tableContainer"></div>
@endsection
