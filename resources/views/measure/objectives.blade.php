@php if(Auth::user()->cannot('index', \App\Models\Objective::class)) abort(403); @endphp
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
      title: '{{ __("Objectives") }}',
      paging: true,
      searchfield: true,
      tableId: 'objectiveslist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Create objective') }}',
      },
      filter: {
@php $tags = \App\Models\Objective::allUsedTags(); @endphp
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
                  @foreach(\App\Models\User::leftJoin('objectives', 'objectives.responsible_user_id', '=', 'users.id')->whereNotNull('objectives.id')->select('users.*')->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showmyonly: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show only my objectives') }}',
         },
         showarchived: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show archived objectives') }}',
         },
@include('components.customproperty', ['classname' => 'App\Models\Objective', 'showFilter' => true])
      },
      actions: {
@if(Auth::user()->can('index', 'App\\Models\\Objective'))
         listAction: '/api/v1/items/Objective',
@endif
@if(Auth::user()->can('create', 'App\\Models\\Objective'))
         createAction: '/api/v1/items/Objective',
@endif
@if(Auth::user()->can('update', 'App\\Models\\Objective'))
         updateAction: '/api/v1/items/Objective',
@endif
@if(Auth::user()->can('delete', 'App\\Models\\Objective'))
         deleteAction: '/api/v1/items/Objective',
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
         tags: showTags('Objective', $('#tableContainer')),
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            create: true,
            edit: true,
            list: true,
            required: true,

            listClass: 'd-inline-block col-12 col-md-4',
            defaultValue: {{ auth()->user()->id }},
            options: [
               { Value: null, DisplayText: '{{ __("None assigned") }}' },
@foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
@endforeach
            ],
         },
         due: {
            title: '{{ __('Due') }}',
            type: 'date',
            create: true,
            edit: true,
            list: true,
            defaultValue: '{{ date("Y"); }}-12-31',
            listClass: 'd-inline-block col-12 col-md-4',
            required: true,
         },
         department_id: {
            title: '{{ __('Department') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            listClass: 'd-inline-block col-12 col-md-4',
            defaultValue: {{ auth()->user()->id }},
            options: [
               { Value: null, DisplayText: '{{ __("None") }}' },
                  @foreach(App\Models\Department::orderBy('name')->get()->each->setAppends([]) as $obj)
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
            placeholder: '{{ __("The description should describe not only what the objective is about, but also how it is related to strategic company objectives and expectations from interested parties") }}',
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
         action_plan: {
            title: '{{ __('Summary action plan') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
            placeholder: '{{ __("The description is supposed to outline the plans on how to achieve the objective, summarizing the detailed actions") }}',
         },
         @include('components.customproperty', ['classname' => 'App\Models\Objective'])
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
                     .text('{{ __("Archive objective") }}')
                     .click(function () {
                        confirmDialog('{{ __("Archive objective") }}', '{{ __("Are you sure that you want to archive this objective?") }}', function () {
                           ajaxGet('/api/v1/items/Objective/' + data.record.id+'/archive', function () {
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
         metrics: {
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
                  .text('{{ __('Process metrics') }}')
                  .prepend($('<span>trending_up</span>')
                     .addClass('material-symbols-rounded'));
                  
                  
               if(sourcedata.record.metricscount)
                  retobj.find('.material-symbols-rounded').css({'color': 'var(--bs-danger)'});
               
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#metrics-table').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                   $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Process metrics") }}',
                        tableId: 'metrics-table',
                        paging: true,
                        actions: {
                           listAction: '/api/v1/items/ObjectiveProcessPerformanceMetric?objective_id='+sourcedata.record.id,
                           updateAction: '/api/v1/items/ObjectiveProcessPerformanceMetric',
                           createAction: '/api/v1/items/ObjectiveProcessPerformanceMetric',
                           deleteAction: '/api/v1/items/ObjectiveProcessPerformanceMetric',
                        },
                        fields: {
                           objective_id: {
                              type: 'hidden',
                              create: true,
                              defaultValue: sourcedata.record.id,
                           },
                           id: {
                              key: true,
                              list: false,
                           },
                           processes: {
                              title: '{{ __("Processes") }}',
                              list: true,
                              edit: false,
                              create: false,
                              width: '30%',
                              display: function(data) {
                                 var retval = $('<div />');
                                 Object.values(data.record.processes).forEach((obj) => {
                                    retval.append($('<div />').text(obj.name));
                                 });
                                 
                                 return retval;
                              }
                           },
                           name: {
                              title: '{{ __("Name") }}',
                              list: true,
                              edit: false,
                              create: false,
                              width: '25%',
                           },
                           process_performance_metric_id: {
                              title: '{{ __("Process metric") }}',
                              type: 'select',
                              list: false,
                              edit: true,
                              create: true,

                              options: [
@foreach(\App\Models\ProcessPerformanceMetric::where('quantitative', true)->orderBy('name')->get()->each->setAppends([]) as $obj)
                                 { Value: {{ $obj->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach                           
                              ],
                           },
                           targetvalue: {
                              title: '{{ __("Target value") }}',
                              type: 'number',
                              list: true,
                              edit: true,
                              create: true,
                              step: 1,
                              min: 0,
                              max: 0,
                              defaultValue: 0,
                              width: '15%',
                              display: function(data)
                              {
                                 return data.record.targetvalue + (data.record.unit ? ' ' + data.record.unit : '');
                              }
                           },
                           acceptablevalue: {
                              title: '{{ __("Acceptable value") }}',
                              type: 'number',
                              list: true,
                              edit: true,
                              create: true,
                              step: 1,
                              min: 0,
                              max: 0,
                              defaultValue: 0,
                              width: '15%',
                              display: function(data)
                              {
                                 return data.record.acceptablevalue + (data.record.unit ? ' ' + data.record.unit : '');
                              }
                           },
                           latest_value: {
                              title: '{{ __("Latest value") }}',
                              list: true,
                              edit: false,
                              create: false,
                              width: '15%',
                              display: function(data)
                              {
                                 if(null === data.record.latest_value)
                                    return '';
                                 
                                 return data.record.latest_value + (data.record.unit ? ' ' + data.record.unit : '');
                              }
                           },
                        },
                        formCreated: function(event, data){
                           if($(data.form).find('#Edit-id').val())
                              $(data.form).find('#Edit-process_performance_metric_id').prop('disabled', true);
                           
                           $(data.form).find('#Edit-process_performance_metric_id').on('change', function(){
                              var selectedId = $(this).val();
                              if(selectedId)
                              {
                                 var min = null;
                                 var max = null;
                                 var step = null;
                                 
                                 switch(parseInt(selectedId))
                                 {
@foreach(\App\Models\ProcessPerformanceMetric::get() as $obj)
                                    case {{ $obj->id }}:
                                       min = {{ $obj->minvalue / (10 ** $obj->precision)}};
                                       max = {{ $obj->maxvalue / (10 ** $obj->precision)}};
                                       step = {{ 10 ** -$obj->precision }};
                                       break;
@endforeach
                                    default:
                                       return;
                                 }
                                 
                                 $(data.form).find('#Edit-targetvalue')
                                    .attr('min', min)
                                    .attr('max', max)
                                    .attr('step', step);
                                    
                                 $(data.form).find('#Edit-acceptablevalue')
                                    .attr('min', min)
                                    .attr('max', max)
                                    .attr('step', step);
                              }
                              
                           })
                           .trigger('change');
                        }
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         }, 
         actions: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (sourcedata) {
               var actions = { listAction: '/api/v1/items/ControlAction?objective_id='+sourcedata.record.id };
               
@if(Auth::user()->can('update', \App\Models\Objective::class))         
               if(!sourcedata.record.finished_at)
               {
                  actions.createAction = '/api/v1/items/ControlAction?objective_id='+sourcedata.record.id;
                  actions.updateAction = '/api/v1/items/ControlAction?objective_id='+sourcedata.record.id;
                  actions.deleteAction = '/api/v1/items/ControlAction?objective_id='+sourcedata.record.id;
               }
@endif
               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Actions') }}')
                  .prepend($('<span>event_list</span>')
                     .addClass('material-symbols-rounded' + (sourcedata.record.controlactionscount ? ' text-danger' : '')));

               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#actionstable').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }


                  $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{ __("Actions") }}',
                        tableId: 'actionstable',
                        actions: actions,
                        paging: true,
                        messages: {
                           addNewRecord: '{{ __('Add new action') }}',
                        },
                        fields: {
                           id: {
                              key: true,
                              list: false,
                              edit: false,
                              create: false,
                           },
                           status: {
                              title: '{{ __("Status") }}',
                              list: true,
                              edit: false,
                              create: false,
                              width: '1%',
                              display: function(data){
                                 var status = '';
                                 if(data.record.finished_at)
                                    status = '{{ __("Finished") }}';
                                 else
                                    status = '{{ __("Planned") }}';
                                    
                                 return $('<span />')
                                       .addClass('badge rounded-pill bg-info')
                                       .text(status);
                              }
                           },
                           existing_control_action_id: {
                              title: '{{ __('Existing control actions') }}',
                              list: false,
                              edit: false,
                              create: true,
                              options:[
                                 { Value: null, DisplayText: '-- {{ __("Add new action") }} --' },
                                    @foreach(App\Models\ControlAction::whereNull('finished_at')->orderBy('name')->get()->each->setAppends([]) as $obj)
                                 { Value: {{ $obj->id }}, DisplayText: '{{ $obj->name}}'},
                                 @endforeach

                              ]
                           },
                           control_id: {
                              title: '{{ __('Control') }}',
                              width: '15%',
                              list: true,
                              edit: true,
                              create: true,

                              required: true,
                              options:[
@foreach(App\Models\Control::whereNull('not_applicable_at')->orderBy('name')->get()->each->setAppends([]) as $obj)
   { Value: {{ $obj->id }}, DisplayText: '{{ $obj->name}}'}, 
@endforeach
                              
                              ]
                           },
                           responsible_id: {
                              title: '{{ __('Responsible') }}',
                              width: '15%',
                              list: true,
                              edit: true,

                              create: true,
                              options: [
@foreach(\App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
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
                           },
                           name: {
                              title: '{{ __('Name') }}',
                              width: '25%',
                              list: true,
                              edit: true,
                              create: true,
                              required: true,
                              maxlength: 255,
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              type: 'textarea',
                              width: '40%',
                              list: true,
                              edit: true,
                              create: true,
                              required: true,
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
                        },
                        formCreated: function(event, data){
                           if(data.record)
                              return;

                           // Save the required-attributes for all fields
                           $(data.form).find('[name][required]').each(function(){
                              $(this).data('is-required', true);
                           });

                           // Create a on-change event for the existing_control_action_id field
                           $(data.form).find('#Edit-existing_control_action_id').change(function(){
                              if($(this).val())
                              {
                                 $(data.form).find('div.jtable-input-field-container').hide();
                                 $(data.form).find('div.jtable-input-field-container:has(#Edit-existing_control_action_id)').show();
                                 $(data.form).find('[required]').attr('required', false);
                              }
                              else {
                                 $(data.form).find('div.jtable-input-field-container').show();
                                 $(data.form).find('[name]').each(function(){
                                    if($(this).data('is-required'))
                                       $(this).attr('required', true);
                                 });
                              }
                           }).trigger('change');
                        }
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         },  
         showHistory: showHistoryField('Objective', $('#tableContainer')),          
         showMessages: showMessagesField('Objective', $('#tableContainer')),    
      },
      rowLoaded: function(event, data){
         if(!data.record.responsible_user_id)
            $('#jtable-body-objectiveslist > .jtable-data-row[data-record-key="'+data.record.id+'"] div[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer"></div>
   
@endsection
