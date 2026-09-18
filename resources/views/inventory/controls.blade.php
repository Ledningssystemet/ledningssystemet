@php if(Auth::user()->cannot('index', \App\Models\Control::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Controls") }}',
      paging: true,
      searchfield: true,
      tableId: 'controlstable',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Add new control') }}',
      },
      filter: {
@php $tags = \App\Models\Control::allUsedTags(); @endphp
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
                  @foreach(\App\Models\User::leftJoin('controls', 'controls.responsible_user_id', '=', 'users.id')->whereNotNull('controls.id')->select('users.*')->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showmyonly: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show only my controls') }}',
         },
         hidechecked: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Hide items without issues') }}',
         },
         shownotapplicable: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show not applicable') }}',
         },
@include('components.customproperty', ['classname' => 'App\Models\Control', 'showFilter' => true])
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Control::class))
         listAction: '/api/v1/items/Control',
@endif         
@if(Auth::user()->can('create', \App\Models\Control::class))
         createAction: '/api/v1/items/Control',
@endif         
@if(Auth::user()->can('update', \App\Models\Control::class))
         updateAction: '/api/v1/items/Control',
@endif         
@if(Auth::user()->can('delete', \App\Models\Control::class))
         deleteAction: '/api/v1/items/Control',
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
         tags: showTags('Control', $('#tableContainer')),
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            create: true,
            edit: true,
            list: true,

            required: true,
            listClass: 'd-inline-block col-6 col-md-8',
            defaultValue: {{ auth()->user()->id }},
            options: [
               { Value: null, DisplayText: '{{ __("None assigned") }}' },
@foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
@endforeach
            ],
         },
         partnerinfo: {
            list: true,
            create: false,
            edit: false,
            listClass: 'd-inline-block col-6 col-md-4',
            display: function(data){
               if(data.record.partner_id)
                  return $('<div />')
                     .css({'font-size': '10pt', 'font-style': 'italic'})
                     .text('{{ __("This control is provided by") }} '+data.record.partner_name);
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
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         statusdescription: {
            title: '{{ __('Status description') }}',
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
         requirements: {
            title: '{{ __('Governed requirements') }}',
            create: false,
            edit: true,
            list: true,

            multiple: true,
            listClass: 'd-inline-block col-12 col-md-6',
            options: [
@foreach(App\Models\RequirementSource::whereNull('not_applicable_at')->orderBy('name')->get()->each->setAppends([]) as $reqsource)
               { Label: '{{ $reqsource->reference." ".$reqsource->name }}', Children: [
   @foreach($reqsource->int_requirements()->where('requirements.applicable', 1)->orderBy('requirements.ordinal')->get()->each->setAppends([]) as $obj)
                  { Value: {{$obj->id}}, DisplayText: '{{ $reqsource->reference." ".$obj->reference." ".$obj->name}}', Tooltip: @php echo(json_encode($obj->description)); @endphp },
   @endforeach
               ]},
@endforeach
            ],
         },
         risks: {
            title: '{{ __('Related risks') }}',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col-12 col-md-6',
            display: function(data){
               var retval = $('<div />');
               if(data.record.risks)
               {
                  data.record.risks.forEach((obj) => {
                     if(!obj.finished_at)
                     {
                        retval.append($('<div />')
                           .append($('<span />')
                              .addClass('text-secondary')
                              .css({'cursor': 'pointer'})
                              .text('RISK-'+obj.id + ' ' + obj.name + ' ('+obj.status.text+(obj.riskowner ? ', {{ __("Risk owner") }} '+obj.riskowner: '')+')'))
                           .click(function(){
                              window.open('/assessment/riskregister?jtId[riskregister]='+obj.id, 'target=_blank');
                           }));
                     }
                  });
               }
               return retval;
            },
         },
         @include('components.customproperty', ['classname' => 'App\Models\Control'])
         hr2: {
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
                     ajaxPost('/api/v1/ai/control', { control_id: $(this).closest('.jtable-data-row').attr('data-record-key') }, function(aidata, textStatus, jqXHR){
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
@if(Auth::user()->can('update', \App\Models\Control::class))
         notapplicable: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (data) {
               if(data.record.partner_id && !data.record.not_applicable_at)
               {
                  return $('<button />')
                     .addClass('btn btn-warning btn-sm')
                     .css({'margin-left': '10px'})
                     .text('{{ __('Not applicable') }}')
                     .prepend($('<span>visibility_off</span>')
                        .addClass('material-symbols-rounded'))
                     .click(function(clickevent){
                        confirmDialog('{{ __("Not applicable") }}', '{{ __("By marking this control as not applicable, you certify that this is not relevant to our business") }}', function(){
                           ajaxPost('/api/v1/items/Control/'+data.record.id+'/notapplicable', {}, function(){
                              $('#tableContainer').jtable('reload');
                           });
                        });
                     });
               }
               else if(data.record.partner_id && data.record.not_applicable_at)
               {
                  return $('<button />')
                     .addClass('btn btn-warning btn-sm')
                     .css({'margin-left': '10px'})
                     .text('{{ __('Applicable') }}')
                     .prepend($('<span>visibility</span>')
                        .addClass('material-symbols-rounded'))
                     .click(function(clickevent){
                        confirmDialog('{{ __("Applicable") }}', '{{ __("By marking this control as applicable, you override a prior decision that the control is not relevant to our business") }}', function(){
                           ajaxPost('/api/v1/items/Control/'+data.record.id+'/applicable', {}, function(){
                              $('#tableContainer').jtable('reload');
                           });
                        });
                     });
               }


               return '';

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
               var actions = {listAction: '/api/v1/items/ControlAction?showhandled=1&control=' + sourcedata.record.id};

@if(Auth::user()->can('update', \App\Models\ControlAction::class))
               if (!sourcedata.record.finished_at) {
                  actions.createAction = '/api/v1/items/ControlAction';
                  actions.updateAction = '/api/v1/items/ControlAction';
                  actions.deleteAction = '/api/v1/items/ControlAction';
               }
@endif
               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Actions') }}')
                  .prepend($('<span>event_list</span>')
                     .addClass('material-symbols-rounded' + (sourcedata.record.pending_action_count ? ' text-danger' : '')));


               retobj.click(function () {
                  if (retobj.closest('.accordion-body').find('#actionstable').length) {
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
                           control_id: {
                              type: 'hidden',
                              defaultValue: sourcedata.record.id,
                           },
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
                              display: function (data) {
                                 var status = '';
                                 if (data.record.finished_at)
                                    status = '{{ __("Finished") }}';
                                 else
                                    status = '{{ __("Planned") }}';

                                 return $('<span />')
                                    .addClass('badge rounded-pill bg-info')
                                    .text(status);
                              }
                           },
                           responsible_id: {
                              title: '{{ __('Responsible') }}',
                              width: '15%',
                              list: true,
                              edit: true,
                              create: true,
                              options: [
                                    @foreach(\App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
                                 {
                                    Value: {{ $obj->id }}, DisplayText: '{{ $obj->name }}'
                                 },
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
                        rowLoaded: function (event, data) {
                           if(data.record.finished_at)
                              $(event.target).find('[data-record-key="'+data.record.id+'"] .jtable-edit-command').empty();

                           if(data.record.access && data.record.access.read) {
                              $(event.target).find('[data-record-key="' + data.record.id + '"]')
                                 .css({'cursor': 'pointer'})
                                 .on('click', function () {
                                    location.href = '/assessment/controlactions?jtId[controlactionstable]=' + data.record.id;
                                 });
                           }
                        },
                     }, function (data) {
                        data.childTable.jtable('load');
                     }
                  );
               });

               return retobj;
            }
         },
         showHistory: showHistoryField('Control', $('#tableContainer')),
         showMessages: showMessagesField('Control', $('#tableContainer')),    
      },
      formCreated: function(event, data){
         if(data.record && data.record.partner_id)
         {
            $(data.form).find('*[name]').each(function(){
               switch($(this).prop('name'))
               {
                  case 'description':
                     $(this).prop('disabled', true);
                     break;
                  case 'responsible_user_id':
                  case 'statusdescription':
                  case 'requirements[]':
                     break;
                  default:
                     $(this).closest('div.jtable-input-field-container').remove();
                     break;
               }
            });
            $(data.form).find('.jtable-input-field-container:has(textarea[name="description"]) .jtable-input-label').text('{{ __("Partner proposed governance") }}');
         }
      },
      rowLoaded: function (event, data) {
         if(data.record.partner_id)
            $(event.target).find('[data-record-key="'+data.record.id+'"] div[data-jtable-fieldname="description"] .jtable-field-label').text('{{ __("Partner proposed governance") }}');
      },
      recordsLoaded: function(event, data){
         if(data.serverResponse.data)
         {
            data.serverResponse.data.forEach((obj) => {
               if(obj.partner_id)
                  $(event.target).find('[data-record-key="'+obj.id+'"]').find('.jtable-delete-command').remove();
               
               if(!obj.responsible_user_id)
                  $('#jtable-body-controlstable > .jtable-data-row[data-record-key="'+obj.id+'"] div[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');
            });
         }
         else
         {
            if(data.serverResponse.partner_id)
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"]').find('.jtable-delete-command').remove();
            
            if(!data.serverResponse.responsible_user_id)
               $('#jtable-body-controlstable > .jtable-data-row[data-record-key="'+data.serverResponse.id+'"] div[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');
         }
     },
   });
   $('#tableContainer').jtable('load');
});
</script>
<div class="d-flex flex-row flex-row-reverse mb-4 w-100">
   <a class="btn btn-outline-primary btn-sm" href="/api/v1/ReportCentral/Controls/0">
      <span class="material-symbols-rounded">download</span>
      {{ __("Export as Excel") }}
   </a>
</div>
<div id="tableContainer"></div>
   
@endsection
