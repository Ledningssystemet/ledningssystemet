@php if(Auth::user()->cannot('index', \App\Models\Incident::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   
   $('#tableContainer').jtable({
      title: '{{ __("Incidents") }}',
      paging: true,
      searchfield: true,
      tableId: 'incidentstable',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('New incident') }}',
      },
      filter: {
         responsible_user_id: {
            type: 'select',
            text: '{{ __("Responsible user") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
                  @foreach(\App\Models\User::leftJoin('incidents', 'incidents.responsible_user_id', '=', 'users.id')->whereNotNull('incidents.id')->select(['users.id', 'users.name'])->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showongoing: {
            type: 'checkbox',
            value: '1',
            checked: true,
            text: '{{ __('Show ongoing incidents') }}',
         },
         showfinished: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show finished incidents') }}',
         },
@include('components.customproperty', ['classname' => 'App\Models\Incident', 'showFilter' => true])
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Incident::class))         
         listAction: '/api/v1/items/Incident',
@endif         
@if(Auth::user()->can('create', \App\Models\Incident::class))         
         createAction: '/api/v1/items/Incident',
@endif         
@if(Auth::user()->can('update', \App\Models\Incident::class))         
         updateAction: '/api/v1/items/Incident',
@endif         
@if(Auth::user()->can('delete', \App\Models\Incident::class))         
         deleteAction: '/api/v1/items/Incident',
@endif         
      },
      fields: {
         name: {
            title: '{{ __('Name') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            maxlength: 255,
            header: true,
         },
         id: {
            title: '{{ __('ID') }}',
            key: true,
            list: true,
            create: false,
            edit: false,
            listClass: 'd-inline-block col-6 col-md-4',
            display: function(data){
               return 'INCIDENT-'+data.record.id;
            }
         },
         started_at: {
            title: '{{ __('Incident started') }}',
            type: 'datetime',
            create: true,
            edit: true,
            list: true,
            required: true,
            listClass: 'd-inline-block col-6 col-md-4',
            defaultValue: '{{ date("Y-m-d H:i") }}',
         },
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            create: true,
            edit: true,
            list: true,
            required: true,

            defaultValue: {{ auth()->user()->id }},
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
               { Value: null, DisplayText: '{{ __("None assigned") }}' },
@foreach(App\Models\User::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { Value: {{$obj ->id}}, DisplayText: '{{ $obj->name }}' },
@endforeach
            ]
         },
         eventdescription: {
            title: '{{ __('Event description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         participants: {
            title: '{{ __('Involved participants') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         retrospective: {
            title: '{{ __('Retrospective / Lessons learned') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
         },
         @include('components.customproperty', ['classname' => 'App\Models\Incident'])
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
@if(Auth::user()->can('update', \App\Models\Incident::class))         
         commands: {
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function(data){
               if(!data.record.finished_at)
               {
                  return $('<button />')
                     .addClass('btn btn-sm btn-outline-primary')
                     .text('{{ __('Incident handling finished') }}')
                     .prepend($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('check'))
                     .click(function(){ 
                        confirmDialog('{{ __("Finish incident") }}', '{{ __("By finishing this incident, you agree that the incident is fully handled. This action cannot be undone and you cannot re-open the incident") }}', function(){
                           ajaxPatch('/api/v1/items/Incident/'+data.record.id, {finished_at: 1 }, function(){
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
@endif         
         incidentlogs: {
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
                  .text('{{ __('Logs') }}')
                  .prepend($('<span>notes</span>')
                     .addClass('material-symbols-rounded' + (sourcedata.record.logcount ? ' text-danger' : '')));
                  
               var actions = {};
               
               actions.listAction ='/api/v1/items/IncidentLog?incident_id='+sourcedata.record.id;
@if(Auth::user()->can('update', \App\Models\Incident::class))         
               if(!sourcedata.record.finished_at)
               {
                  actions.createAction = '/api/v1/items/IncidentLog';
                  actions.updateAction = '/api/v1/items/IncidentLog';
                  actions.deleteAction = '/api/v1/items/IncidentLog';
               }
@endif               
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#logstable').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                   $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{ __("Logs") }}',
                        actions: actions,
                        tableId: 'logstable',
                        messages: {
                           addNewRecord: '{{ __('Add new log entry') }}',
                        },
                        fields: {
                           incident_id: {
                              type: 'hidden',
                              defaultValue: sourcedata.record.id,
                           },
                           id: {
                              key: true,
                              list: false,
                              edit: false,
                              create: false,
                           },
                           start_at: {
                              title: '{{ __('Timestamp') }}',
                              type: 'datetime',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                              defaultValue: '{{ date("Y-m-d H:i") }}',
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                           },
                        },
                        recordUpdated: function(event, data){
                           console.log(data);
                           $('#jtable-body-incidentstable > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"] .jtable-child-table-container').jtable('reload');

                        },
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         },
         showHistory: showHistoryField('Incident', $('#tableContainer')),          
         actions: {
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
                  .text('{{ __('Actions') }}')
                  .prepend($('<span></span>')
                     .addClass('material-symbols-rounded'+ (sourcedata.record.actioncount ? ' text-danger' : ''))
                     .text('event_list'));
                  
               var actions = { listAction: '/api/v1/items/ControlAction?incident='+sourcedata.record.id };
               
@if(Auth::user()->can('update', \App\Models\Incident::class))         
               if(!sourcedata.record.finished_at)
               {
                  actions.createAction = '/api/v1/items/ControlAction?incident_id='+sourcedata.record.id;
                  actions.updateAction = '/api/v1/items/ControlAction?incident_id='+sourcedata.record.id;
                  actions.deleteAction = '/api/v1/items/ControlAction?incident_id='+sourcedata.record.id;
               }
@endif                  
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
                        actions: actions,
                        tableId: 'actionstable',
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
@foreach(App\Models\Control::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
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
@foreach(\App\Models\User::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
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
      },
   });
   $('#tableContainer').jtable('load');
});

</script>

<div id="tableContainer"></div>
   
@endsection
