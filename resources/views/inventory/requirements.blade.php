@php if(Auth::user()->cannot('index', \App\Models\RequirementSource::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')
<style>
.jtable-data-row.needsapproval
{
   background-color: var(--bs-warning-bg-subtle);
}

.jtable-data-row.needsapproval:hover
{
   background-color: var(--bs-warning-bg-subtle);
}
</style>
<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Requirement sources") }}',
      paging: 'auto',
      sorting: false,
      defaultSorting: 'name ASC',
      searchfield: true,
      tableId: 'requirementslist',
      bootstrap: true,
      accordion:true,
      messages: {
         addNewRecord: '{{ __('Add requirement source') }}',
      },
      filter: {
@php $tags = \App\Models\RequirementSource::allUsedTags(); @endphp
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
                  @foreach(\App\Models\User::leftJoin('requirement_sources', 'requirement_sources.responsible_user_id', '=', 'users.id')->whereNotNull('requirement_sources.id')->select('users.*')->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showmyonly: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show only my requirement sources') }}',
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
@include('components.customproperty', ['classname' => 'App\Models\RequirementSource', 'showFilter' => true])
      },
      actions: {
@if(Auth::user()->can('index', 'App\\Models\\RequirementSource'))
         listAction: '/api/v1/items/RequirementSource',
@endif
@if(Auth::user()->can('create', 'App\\Models\\RequirementSource'))
         createAction: '/api/v1/items/RequirementSource',
@endif
@if(Auth::user()->can('update', 'App\\Models\\RequirementSource'))
         updateAction: '/api/v1/items/RequirementSource',
@endif
@if(Auth::user()->can('delete', 'App\\Models\\RequirementSource'))
         deleteAction: '/api/v1/items/RequirementSource',
@endif
      },
      fields: {
         id: {
            key: true,
            list: false,
            create: false,
            edit: false,
         },
         reference: {
            title: '{{ __('Reference') }}',
            create: true,
            edit: true,
            list: true,
            header: true,
            required: true,
            maxlength: 20,
            display: function (data) {
               return data.record.reference + ' ' + data.record.name;
            }
         },
         tags: showTags('RequirementSource', $('#tableContainer')),
         partnerinfo: {
            list: true,
            create: false,
            edit: false,
            listClass: 'd-inline-block col col-12 col-md-4',
            display: function (data) {
               if (data.record.partner_id)
                  return $('<div />')
                     .css({'font-size': '10pt', 'font-style': 'italic'})
                     .text('{{ __("This requirement source is provided by") }} ' + data.record.partner_name);
               else
                  return '';
            },
         },
         exportsoa: {
            title: '',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col col-12 col-md-4',
            display: function (data) {
               if(data.record.partner_id && data.record.not_applicable_at)
                  return '';

               return $('<a />')
                  .addClass('btn btn-sm btn-outline-primary')
                  .text('{{ __("Generate Statement of Applicability") }}')
                  .prepend($('<span></span>')
                     .addClass('material-symbols-rounded')
                     .text('table'))
                  .attr('href', '/api/v1/ReportCentral/StatementOfApplicability/' + data.record.id)
            }
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function (data) {
               return $('<hr />');
            }
         },
         name: {
            title: '{{ __('Name') }}',
            create: true,
            edit: true,
            list: false,
            required: true,
            maxlength: 100,
            width: '30%',
         },
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            listClass: 'd-inline-block col-12',
            defaultValue: {{ auth()->user()->id }},
            options: [
               {Value: null, DisplayText: '{{ __("None assigned") }}'},
                  @foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               {
                  Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
               @endforeach
            ],
         },
         max_sanction_fee: {
            title: '{{ __('Maximum estimated sanction cost') }}',
            type: 'number',
            min: 0,
            step: 1,
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data){
               if(!data.record.max_sanction_fee)
                  return '-';

               // Format as currency
               return new Intl.NumberFormat('sv-SE', {
                  style: 'currency',
                  currency: 'SEK',
                  minimumFractionDigits: 0,
                  maximumFractionDigits: 0
               }).format(data.record.max_sanction_fee);
            }
         },
         description: {
            title: '{{ __('Notes') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            width: '40%',
         },
         @include('components.customproperty', ['classname' => 'App\Models\RequirementSource'])
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function (data) {
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
                     ajaxPost('/api/v1/ai/requirementsource', { requirement_source_id: $(this).closest('.jtable-data-row').attr('data-record-key') }, function(aidata, textStatus, jqXHR){
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
@if(auth()->user()->can('update', 'App\\Models\\RequirementSource'))
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
                        confirmDialog('{{ __("Not applicable") }}', '{{ __("By marking this requirement source as not applicable, you certify that the requirements within are not relevant to our business") }}', function(){
                           ajaxPost('/api/v1/items/RequirementSource/'+data.record.id+'/notapplicable', {}, function(){
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
                        confirmDialog('{{ __("Applicable") }}', '{{ __("By marking this requirement source as applicable, you override a prior decision that the requirements within are not relevant to our business") }}', function(){
                           ajaxPost('/api/v1/items/RequirementSource/'+data.record.id+'/applicable', {}, function(){
                              $('#tableContainer').jtable('reload');
                           });
                        });
                     });
               }


               return '';

            }
         },
@endif
         requirements: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (reqsourcedata) {
               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Show requirements') }}')
                  .prepend($('<span>zoom_in</span>')
                     .addClass('material-symbols-rounded'));

               if (reqsourcedata.record.applicabilitymissingcount)
                  retobj.find('.material-symbols-rounded').css({'color': 'var(--bs-danger)'});

               retobj.click(function () {
                  if (retobj.closest('.accordion-body').find('#requirementsTable').length) {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }

                  $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{ __("Requirements") }}',
                        tableId: 'requirementsTable',
                        messages: {
                           addNewRecord: '{{ __('Add requirement') }}',
                        },
                        actions: {
                           @if(Auth::user()->can('index', 'App\\Models\\Requirement'))
                           listAction: '/api/v1/items/Requirement?requirement_source_id=' + reqsourcedata.record.id,
                           @endif
                              @if(Auth::user()->can('create', 'App\\Models\\Requirement'))
                           createAction: (reqsourcedata.record.not_applicable_at || reqsourcedata.record.partner_id) ? null : '/api/v1/items/Requirement',
                           @endif
                              @if(Auth::user()->can('update', 'App\\Models\\Requirement'))
                           updateAction: reqsourcedata.record.not_applicable_at ? null : '/api/v1/items/Requirement',
                           reorderAction: (reqsourcedata.record.not_applicable_at || reqsourcedata.record.partner_id) ? null : '/api/v1/items/Requirement',
                           @endif
                              @if(Auth::user()->can('delete', 'App\\Models\\Requirement'))
                           deleteAction: reqsourcedata.record.not_applicable_at ? null : '/api/v1/items/Requirement',
                           @endif
                        },
                        fields: {
                           requirement_source_id: {
                              type: 'hidden',
                              defaultValue: reqsourcedata.record.id,
                           },
                           id: {
                              key: true,
                              list: false,
                              create: false,
                              edit: false,
                           },
                           reference: {
                              title: '{{ __('Reference') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                              maxlength: 20,
                              width: '10%',
                           },
                           name: {
                              title: '{{ __('Name') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                              maxlength: 100,
                              width: '20%',
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                              width: '35%',
                           },
                           applicable: {
                              title: '{{ __('Applicable') }}',
                              listClass: 'applicable',
                              create: true,
                              edit: true,
                              list: true,
                              listClass: 'align-center',
                              width: '5%',
                              defaultValue: 1,
                              options: {
                                 0: '{{ __('No') }}',
                                 1: '{{ __('Yes') }}',
                              },
                              display: function (data) {
                                 var retobj = $('<span></span>')
                                    .text(data.record.applicable ? '{{ __('Yes') }}' : '{{ __('No') }}');

                                 if (null == data.record.applicable)
                                    retobj.addClass('not-applicable badge rounded-pill text-bg-warning w-100').text('?');

                                 return retobj;
                              }
                           },
                           governance: {
                              title: '{{ __('Governance information') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                              width: '35%',
                           },
                           controls: {
                              title: '{{ __('Governing controls') }}',
                              create: false,
                              edit: true,
                              list: true,
                              multiple: true,
                              options: [
                                    @foreach(App\Models\Control::whereNull('not_applicable_at')->orderBy('name')->get()->each->setAppends([]) as $obj)
                                 {
                                    Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
                                 @endforeach
                              ]
                           },
                        },
                        formCreated: function (event, data) {
                           if (reqsourcedata.record.partner_id) {
                              $(data.form).find('*[name]').each(function () {
                                 switch ($(this).prop('name')) {
                                    case 'applicable':
                                    case 'governance':
                                    case 'controls[]':
                                       break;
                                    default:
                                       $(this).closest('div.jtable-input-field-container').remove();
                                       break;
                                 }
                              });
                           }
                        },
                        rowLoaded: function (event, data) {
                           if(reqsourcedata.record.partner_id && reqsourcedata.record.not_applicable_at)
                              return;

                           if(data.record.needsapproval && ({{ auth()->user()->id }} == reqsourcedata.record.responsible_user_id))
                           {
                             $(event.target).find('.jtable-data-row[data-record-key="' + data.record.id + '"]').addClass('needsapproval');
                           }
                        },
                        recordsLoaded: function (event, data) {
                           if (reqsourcedata.record.partner_id) {
                              $(event.target).find('.jtable-delete-command').remove();
                           }

                           if(reqsourcedata.record.partner_id && reqsourcedata.record.not_applicable_at)
                              return;

                           $(event.target).closest('.jtable-data-row').find('[data-jtable-fieldname="requirements"] .material-symbols-rounded').css({'color': $(event.target).find('.not-applicable').length ? 'var(--bs-danger)' : 'inherit'});
                        },
                        recordDeleted: function (event, data) {
                           $(event.target).closest('.jtable-child-table-container').jtable('reload');
                        },
                        recordUpdated: function (event, data) {
                           $(event.target).closest('.jtable-child-table-container').jtable('reload');
                        },
                        recordAdded: function (event, data) {
                           $(event.target).closest('.jtable-child-table-container').jtable('reload');
                        }

                     }, function (data) {
                        data.childTable.jtable('load');
                     }
                  );
               });

               return retobj;
            }
         },
         approve: {
            type: 'command',
            title: '',
            footer: true,
            edit: false,
            create: false,
            display: function (data) {
               var retval = $('<span />');
               if (!data.record.not_applicable_at && data.record.needsapproval && ({{ auth()->user()->id }} == data.record.responsible_user_id)) {
                  retval.append($('<button />')
                     .addClass('btn btn-primary btn-sm text-light')
                     .text('{{ __('Approve') }}')
                     .prepend($('<span>check</span>')
                        .addClass('material-symbols-rounded'))
                     .click(function (clickevent) {
                        confirmDialog('{{  __("Approve requirement source") }}', '{{ __("By approving the requirement source, you certify that according to your best knowledge the applicable requirements have been identified, that description and our described controls are relevant and accurate") }}', function () {
                           ajaxPost('/api/v1/items/RequirementSource/' + data.record.id + '/approve', {}, function () {
                              $(clickevent.target).closest('.jtable-main-container').parent('div').jtable('reload');
                           });
                        });
                     }));
               }
               return retval;
            },
         },
         showHistory: showHistoryField('RequirementSource', $('#tableContainer')),
         showMessages: showMessagesField('RequirementSource', $('#tableContainer')),
      },
      formCreated: function(event, data){
         if(data.record && data.record.partner_id)
         {
            $(data.form).find('*[name]').each(function(){
               switch($(this).prop('name'))
               {
                  case 'responsible_user_id':
                  case 'max_sanction_fee':
                     break;
                  default:
                     if(!$(this).prop('name').startsWith('customproperty_'))
                        $(this).closest('div.jtable-input-field-container').remove();
                     break;
               }
            });
         }
      },
      recordUpdated: function(event, data){
         $('#tableContainer').jtable('reloadRow', 
            $('#jtable-body-requirementslist > .jtable-data-row[data-record-key="'+data.record.id+'"]')
         );
      },
      rowLoaded: function(event, data){
         if(data.record.partner_id)
            $('#jtable-body-requirementslist > .jtable-data-row[data-record-key="'+data.record.id+'"] .jtable-delete-command').remove();
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>

<div id="tableContainer"></div>
   
@endsection
