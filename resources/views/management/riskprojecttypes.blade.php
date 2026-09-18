@php if(!Auth::user()->canAny(['managementtools.edit'])) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>

$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Risk project types") }}',
      bootstrap: true,
      accordion: true,
      searchfield: true,
      messages: {
         addNewRecord: '{{ __('Add new type') }}',
      },
      actions: {
         listAction: '/api/v1/items/RiskProjectType',
         createAction: '/api/v1/items/RiskProjectType',
         updateAction: '/api/v1/items/RiskProjectType',
         deleteAction: '/api/v1/items/RiskProjectType',
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
         partnerinfo: {
            list: true,
            create: false,
            edit: false,
            display: function(data){
               if(data.record.partner_id)
                  return $('<div />')
                     .css({'font-size': '10pt', 'font-style': 'italic'})
                     .text('{{ __("This risk project type is provided by") }} '+data.record.partner_name);
               else
                  return '';
            },
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
                           listAction: '/api/v1/items/RiskProjectTypeRiskTemplate?risk_project_type_id='+sourcedata.record.id,
                           updateAction: (sourcedata.record.partner_id) ? null : '/api/v1/items/RiskProjectTypeRiskTemplate',
                           createAction: (sourcedata.record.partner_id) ? null : '/api/v1/items/RiskProjectTypeRiskTemplate',
                           deleteAction: (sourcedata.record.partner_id) ? null : '/api/v1/items/RiskProjectTypeRiskTemplate',
                        },
                        fields: {
                           risk_project_type_id: {
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
                  @foreach(App\Models\ProbabilityLevel::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
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
                  @foreach(App\Models\ConsequenceLevel::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                                 { Value: {{$obj->id}}, DisplayText: <?php echo(json_encode($obj->name)); ?> },
                  @endforeach
                              ]
                           },
                           hr2: {
                              list: true,
                              edit: false,
                              create: false,
                              display: function(data){
                                 return $('<hr />');
                              }
                           },
                           controls: {
                              title: '{{ __('Controls') }}',
                              create: false,
                              edit: true,
                              list: true,
                              multiple: true,
                              options: [
                                    @foreach(App\Models\Control::orderBy('name')->get()->each->setAppends([]) as $obj)
                                 { Value: {{ $obj->id }}, DisplayText: <?php echo(json_encode($obj->name.($obj->not_applicable_at ? " [".__("Not applicable")."]" : ""))); ?>, Tooltip: <?php echo(json_encode($obj->description)); ?> },
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
      recordsLoaded: function(event, data){
         data.serverResponse.data.forEach((obj) => {
            if(obj.partner_id)
            {
               $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-edit-command').remove();
               $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-delete-command').remove();
            }
         });
      },
   });
   $('#tableContainer').jtable('load');          
});
</script>

<div id="tableContainer"></div>
@endsection
