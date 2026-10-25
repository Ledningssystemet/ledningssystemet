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
         meetingagendas: {
            title: '',
            type: 'command',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (sourcedata) {
               var agendaTable;
               var button = $('<button type="button" />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text(@json(__('Standard agendas')));
               button.on('click', function () {
                  $('#tableContainer').jtable('openChildTable',
                     button.closest('.accordion-body').find('.accordion-footer'), {
                        title: @json(__('Standard agendas')),
                        tableId: 'meetingagendastable',
                        paging: false,
                        bootstrap: true,
                        accordion: true,
                        actions: {
                           listAction: '/api/v1/items/ProjectMeetingAgendaTemplate?project_type_id=' + sourcedata.record.id,
                           createAction: '/api/v1/items/ProjectMeetingAgendaTemplate',
                           updateAction: '/api/v1/items/ProjectMeetingAgendaTemplate',
                           deleteAction: '/api/v1/items/ProjectMeetingAgendaTemplate',
                        },
                        fields: {
                           id: { key: true, list: false, create: false, edit: false },
                           project_type_id: { type: 'hidden', defaultValue: sourcedata.record.id },
                           name: { title: @json(__('Name')), required: true, header: true, maxlength: 255 },
                           items: {
                              title: '', type: 'command', create: false, edit: false, sorting: false, footer: true,
                              display: function (agendaData) {
                                 var itemsButton = $('<button type="button" />').addClass('btn btn-outline-primary btn-sm').text(@json(__('Standard agenda items')));
                                 itemsButton.on('click', function () {
                                    agendaTable.jtable('openChildTable',
                                       itemsButton.closest('.accordion-body').find('.accordion-footer'), {
                                          title: @json(__('Standard agenda items')),
                                          paging: false, bootstrap: true, accordion: false,
                                          actions: {
                                             listAction: '/api/v1/items/ProjectTypeMeetingAgendaItem?agenda_template_id=' + agendaData.record.id,
                                             createAction: '/api/v1/items/ProjectTypeMeetingAgendaItem',
                                             updateAction: '/api/v1/items/ProjectTypeMeetingAgendaItem',
                                             deleteAction: '/api/v1/items/ProjectTypeMeetingAgendaItem',
                                          },
                                          fields: {
                                             id: { key: true, list: false, create: false, edit: false },
                                             agenda_template_id: { type: 'hidden', defaultValue: agendaData.record.id },
                                             ordinal: { title: @json(__('Order')), required: true, defaultValue: 0 },
                                             name: { title: @json(__('Name')), required: true, maxlength: 255 },
                                             description: { title: @json(__('Description')), type: 'textarea' },
                                          },
                                       }, function (data) { data.childTable.jtable('load'); });
                                 });
                                 return itemsButton;
                              },
                           },
                        },
                     }, function (data) { agendaTable = data.childTable; data.childTable.jtable('load'); });
               });
               return button;
            },
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
                              create: true,
                              edit: true,
                              list: true,
                              multiple: true,
                              options: [
                                    @foreach(Ledningssystemet\Ledningssystemet\Models\Control::orderBy('name')->get()->each->setAppends([]) as $obj)
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
   });
   $('#tableContainer').jtable('load');          
});
</script>

<div id="tableContainer"></div>
@endsection
