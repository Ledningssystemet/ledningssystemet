@php if(Auth::user()->cannot('index', \App\Models\Finding::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Findings") }}',
      paging: true,
      searchfield: true,
      tableId: 'findingstable',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Add new finding') }}',
      },
      filter: {
@php $tags = \App\Models\Finding::allUsedTags(); @endphp
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
         department_id: {
            type: 'select',
            default: 0,
            text: '{{ __("Department") }}',
            options: [
               { value: 0, text: '{{ __('Show mine') }}' },
               { value: -1, text: '{{ __('Show all') }}' },
@foreach(\App\Models\Department::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: '{{ $obj->name }}' },
@endforeach               
            ]
         },
         showunhandled: {
            type: 'checkbox',
            value: '1',
            checked: true,
            text: '{{ __('Show unhandled findings') }}',
         },
         showhandled: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show handled findings') }}',
         },
         isnc: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show non-conformities only') }}',
         },
@include('components.customproperty', ['classname' => 'App\Models\Finding', 'showFilter' => true])
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Finding::class))         
         listAction: '/api/v1/items/Finding',
@endif
@if(Auth::user()->can('create', \App\Models\Finding::class))         
         createAction: '/api/v1/items/Finding',
@endif
@if(Auth::user()->can('update', \App\Models\Finding::class))         
         updateAction: '/api/v1/items/Finding',
@endif
@if(Auth::user()->can('delete', \App\Models\Finding::class))         
         deleteAction: '/api/v1/items/Finding',
@endif
      },
      fields: {
         name: {
            title: '{{ __('Name') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            header: true,
            maxlength: 255,
         },
         tags: showTags('Finding', $('#tableContainer')),
         id: {
            title: '{{ __('ID') }}',
            key: true,
            list: true,
            create: false,
            edit: false,
            listClass: 'd-inline-block col-6 col-md-4',
            display: function(data){
               return '{{ __("FINDING") }}-'+data.record.id;
            }
         },
         created_at: {
            title: '{{ __('Created') }}',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            display(data) {
               var date = new Date(data.record.created_at);
               return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            }
         },
         nonconformity: {
            title: '{{ __('Nonconformity?') }}',
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
         context: {
            title: '{{ __('Associated object') }}',
            list: true,
            edit: true,
            create: true,

            listClass: 'd-inline-block col-6 col-md-4',
            options: [
               { Label: '{{ __("No object association") }}', Children: [{Value: null, DisplayText: '{{ __("None") }}' }]},
<?php
   // Processes
   echo("{ Label: '".__('Process')."', Children: [\r\n");
   foreach(\App\Models\Process::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
      echo("{ Value: 'Process_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
   echo("]},\r\n");

   // Assets
   echo("{ Label: '".__('Asset')."', Children: [\r\n");
   foreach(\App\Models\Asset::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
      echo("{ Value: 'Asset_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
   echo("]},\r\n");
   
if(!config('ledningssystemet.disable_supplier'))
{
   // Suppliers
   echo("{ Label: '".__('Supplier')."', Children: [\r\n");
   foreach(\App\Models\Supplier::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
      echo("{ Value: 'Supplier_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
   echo("]},\r\n");
}
?>               
            ]
         },
         department_id: {
            title: '{{ __('Department') }}',
            create: true,
            edit: true,
            list: true,

            defaultValue: <?php $userdeps = auth()->user()->int_departments; echo((0 == count($userdeps)) ? '-1' : $userdeps[0]->id); ?>,
            listClass: 'd-inline-block col-6 col-md-4',
            options: [
@foreach(App\Models\Department::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { Value: {{$obj->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach
            ]
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
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         consequence: {
            title: '{{ __('Consequence') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         rootcause: {
            title: '{{ __('Root cause analysis') }}',
            type: 'textarea',
            create: false,
            edit: true,
            list: true,
         },
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         immediateaction: {
            title: '{{ __('Immediate action') }}',
            type: 'textarea',
            create: false,
            edit: true,
            list: true,
         },
         hr3: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         preventativeaction: {
            title: '{{ __('Preventative action') }}',
            type: 'textarea',
            create: false,
            edit: true,
            list: true,
         },
         @if(\App\Models\Site::count())
         hr4: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
         distribution_analysis: {
            title: '{{ __('Distribution analysis') }}',
            type: 'textarea',
            create: false,
            edit: true,
            list: true,
            placeholder: '{{ __("Assess to what extent this observation applies to different parts (e.g. sites) of the organization") }}',
         },
         @endif
         @include('components.customproperty', ['classname' => 'App\Models\Finding'])
         hr5: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
@if(auth()->user()->can('useai'))
         ai_agent: {
            title: '',
            type: 'textarea',
            create: false,
            edit: false,
            list: true,
            footer: true,
            display: function(data) {
               var retobj = $('<div />');
               retobj.append($('<button />')
                  .addClass('btn btn-primary btn-sm text-light')
                  .text('{{ __('Get AI opinion') }}')
                  .prepend($('<span>support_agent</span>')
                     .addClass('material-symbols-rounded'))
                  .click(function(){
                     var aiFeedbackContainer = $(this).siblings('.ai-agent-feedback');
                     ajaxPost('/api/v1/ai/finding', { finding_id: $(this).closest('.jtable-data-row').attr('data-record-key') }, function(aidata, textStatus, jqXHR){
                        if(null == aidata.retval)
                           aiFeedbackContainer.text('{{ __("No response was received from AI agent") }}');
                        else
                           aiFeedbackContainer.text(aidata.retval);

                        aiFeedbackContainer.show();
                     });

                     return false;
                  }));

               retobj.append($('<div />')
                  .addClass('ai-agent-feedback')
                  .css({ 'display': 'none' }));

               return retobj;
            }
         },
@endif
@if(Auth::user()->can('update', \App\Models\Finding::class))
         commands: {
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function(data){
               if(!data.record.finished_at)
               {
                  return $('<button />')
                     .addClass('btn btn-outline-primary btn-sm')
                     .text('{{ __('Handled') }}')
                     .prepend($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('check'))
                     .click(function(){ 
                        confirmDialog('{{ __("Finding handled") }}', '{{ __("When the finding is marked as handleded, no further activities can be planned and the action cannot be made undone") }}', function(){
                           ajaxPatch('/api/v1/items/Finding/'+data.record.id,{ finished_at: 1}, function(){
                              $('#tableContainer').jtable('reload');
                           });                        
                        }, { warning: true });
                     });
               }
               else
               {
@if(Auth::user()->can('managementtools.edit'))
                  return $('<button />')
                     .addClass('btn btn-outline-primary btn-sm')
                     .text('{{ __('Reopen finding') }}')
                     .prepend($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('check'))
                     .click(function(){
                        confirmDialog('{{ __("Reopen finding") }}', '{{ __("By reopening the finding, it will be sent back to unhandled mode") }}', function(){
                           ajaxGet('/api/v1/items/Finding/'+data.record.id+'/reopen', function(){
                              $('#tableContainer').jtable('reload');
                           });
                        }, { warning: true });
                     });

@else
                  return $('<span />');
@endif
               }
            }
         },   
@endif         
         actions: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (sourcedata) {
               var actions = { listAction: '/api/v1/items/ControlAction?showhandled=1&finding='+sourcedata.record.id };
               
@if(Auth::user()->can('update', \App\Models\Finding::class))         
               if(!sourcedata.record.finished_at)
               {
                  actions.createAction = '/api/v1/items/ControlAction?finding_id='+sourcedata.record.id;
                  actions.updateAction = '/api/v1/items/ControlAction?finding_id='+sourcedata.record.id;
                  actions.deleteAction = '/api/v1/items/ControlAction?finding_id='+sourcedata.record.id;
               }
@endif
               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Actions') }}')
                  .prepend($('<span>event_list</span>')
                     .addClass('material-symbols-rounded' + (sourcedata.record.controlactioncount ? ' text-danger' : '')));
                  
                  
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
@foreach(App\Models\ControlAction::whereNull('finished_at')->orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
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
         showHistory: showHistoryField('Finding', $('#tableContainer')),          
         showMessages: showMessagesField('Finding', $('#tableContainer')),    
      },
      formCreated: function(event, data){
         if(data.record && !data.record.nonconformity)
         {
            $(data.form[0]).find('#Edit-consequence').closest('div.jtable-input-field-container').empty();
            $(data.form[0]).find('#Edit-rootcause').closest('div.jtable-input-field-container').empty();
            $(data.form[0]).find('#Edit-preventativeaction').closest('div.jtable-input-field-container').empty();
         }
         
         $(data.form).find('select#Edit-context').select2({dropdownParent: data.form});
      },
      rowLoaded: function(event, data){
         if(data.record.finished_at)
         {
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"] .jtable-edit-command').remove();
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"] .jtable-delete-command').remove();
         }
         if(!data.record.nonconformity)
         {
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"] .jtable-field[data-jtable-fieldname="hr1"]').remove();
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"] .jtable-field[data-jtable-fieldname="hr2"]').remove();
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"] .jtable-field[data-jtable-fieldname="hr3"]').remove();
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"] .jtable-field[data-jtable-fieldname="rootcause"]').remove();
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"] .jtable-field[data-jtable-fieldname="preventativeaction"]').remove();
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"] .jtable-field[data-jtable-fieldname="consequence"]').remove();
         }
      },
      recordUpdated: function(event, data){
         $('#tableContainer').jtable('reloadRowStatus', $(event.target).closest('.jtable-data-row'));
      }
   });
   $('#tableContainer').jtable('load');
});
</script>
<div id="tableContainer" class="cardlayout"></div>
   
@endsection
