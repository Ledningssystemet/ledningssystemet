@php if(Auth::user()->cannot('index', \App\Models\ActivityFlowTemplate::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Activity flow templates") }}',
      paging: true,
      sorting: false,
      defaultSorting: 'name ASC',
      searchfield: true,
      tableId: 'activityflowtemplatelist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Add activity flow template') }}',
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\ActivityFlowTemplate::class))         
         listAction: '/api/v1/items/ActivityFlowTemplate',
@endif
@if(Auth::user()->can('create', \App\Models\ActivityFlowTemplate::class))         
         createAction: '/api/v1/items/ActivityFlowTemplate',
@endif
@if(Auth::user()->can('update', \App\Models\ActivityFlowTemplate::class))         
         updateAction: '/api/v1/items/ActivityFlowTemplate',
@endif
@if(Auth::user()->can('delete', \App\Models\ActivityFlowTemplate::class))         
         deleteAction: '/api/v1/items/ActivityFlowTemplate',
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
            header: true,
            required: true,
            maxlength: 255,
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
                     .text('{{ __("This template is provided by") }} '+data.record.partner_name);
               else
                  return '';
            },
         },
         description: {
            title: '{{ __('Notes') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            width: '40%',
         },
         hr0: {
            title: '',
            create: false,
            edit: false,
            list: true,
            display: function(data){
               return $('<hr />');
            }
         },
         user_instantiatable: {
            title: '{{ __('Allow user instantiation') }}',
            create: true,
            edit: true,
            list: true,
            defaultValue: 0,
            options: [
               { Value: 0, DisplayText: '{{ __("No") }}' },
               { Value: 1, DisplayText: '{{ __("Yes") }}' },
            ]
         },
         hr1: {
            title: '',
            create: false,
            edit: false,
            list: true,
            display: function(data){
               return $('<hr />');
            }
         },
         activity_flow_template_items: {
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
                  .text('{{ __('Show items') }}')
                  .prepend($('<span>checklist</span>')
                     .addClass('material-symbols-rounded'));
                   
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#activityflowtemplateitemslist').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                 $('#tableContainer').jtable('openChildTable',
                  retobj.closest('.accordion-body').find('.accordion-footer'), 
                  {
                        title: '{{ __("Items") }}',
                        tableId: 'activityflowtemplateitemslist',
                        messages: {
                           addNewRecord: '{{ __('Add item') }}',
                        },
                        actions: {
                           listAction: '/api/v1/items/ActivityFlowTemplateItem?activity_flow_template_id='+reqsourcedata.record.id,
                           createAction: reqsourcedata.record.partner_id ? null : '/api/v1/items/ActivityFlowTemplateItem',
                           updateAction: reqsourcedata.record.partner_id ? null : '/api/v1/items/ActivityFlowTemplateItem',
                           reorderAction: reqsourcedata.record.partner_id ? null : '/api/v1/items/ActivityFlowTemplateItem',
                           deleteAction: reqsourcedata.record.partner_id ? null : '/api/v1/items/ActivityFlowTemplateItem',
                        },
                        fields: {
                           activity_flow_template_id: {
                              type: 'hidden',
                              defaultValue: reqsourcedata.record.id,
                           },
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
                              width: '35%',
                           },
                           type: {
                              title: '{{ __("Item type") }}',
                              create: true,
                              edit: false,
                              list: false,
                              defaultValue: 'item',
                              options: [
                                 { Value: 'item', DisplayText: '{{ __("Activity") }}' },
                                 { Value: 'header', DisplayText: '{{ __("Header") }}' },
                              ]
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                              width: '35%',
                           },
                           waitforpreceeding: {
                              title: '{{ __("Wait for previous activity") }}',
                              create: true,
                              edit: true,
                              list: true,
                              defaultValue: 0,
                              options: [
                                 { Value: 0, DisplayText: '{{ __("No") }}' },
                                 { Value: 1, DisplayText: '{{ __("Yes") }}' },
                              ]
                           },
                           dueoffsetdays: {
                              title: '{{ __("Due days") }}',
                              type: 'number',
                              min: 0,
                              max: 365,
                              create: true,
                              edit: true,
                              list: true,
                              defaultValue: 14,
                              tooltip: '{{ __("If this activity is created when the previous activity is finished, the offset is based on preceeding activity finish day") }}',
                           },
                        },
                        recordsLoaded: function(event, data) {
                           Object.values(data.serverResponse).forEach((obj) => {
                              if('header' == obj.type) {
                                 $(event.target).find('.jtable-data-row[data-record-key="'+obj.id+'"] td:nth-child(2)').remove();
                                 $(event.target).find('.jtable-data-row[data-record-key="'+obj.id+'"] td:nth-child(2)').remove();
                                 $(event.target).find('.jtable-data-row[data-record-key="'+obj.id+'"] td:nth-child(2)').remove();
                                 $(event.target).find('.jtable-data-row[data-record-key="'+obj.id+'"]').css({'background-color': 'var(--bs-gray-800)', 'color': 'white'});
                                 $(event.target).find('.jtable-data-row[data-record-key="'+obj.id+'"] .material-symbols-rounded').css({'color': 'white'});
                                 $(event.target).find('.jtable-data-row[data-record-key="'+obj.id+'"] td:first-child').attr('colspan', '4').css({'font-weight':'500', 'font-size': '1.2em', 'font-style': 'italic'});
                              }
                           });
                        },
                        recordAdded: function(event, data) {
                           $(event.target).jtable('reload');
                        },
                        formCreated: function(event, data) {
                           if(data.record && data.record.type && ("header" == data.record.type))
                           {
                              $(data.form).find('#Edit-description').closest('.jtable-input-field-container').hide();
                              $(data.form).find('#Edit-waitforpreceeding').closest('.jtable-input-field-container').hide();
                              $(data.form).find('#Edit-dueoffsetdays').closest('.jtable-input-field-container').hide();
                           }
                           else
                           {
                              $(data.form).find('#Edit-type').on('change', function(){
                                 if('header' == $(this).val())
                                 {
                                    $(data.form).find('#Edit-description').closest('.jtable-input-field-container').hide();
                                    $(data.form).find('#Edit-waitforpreceeding').closest('.jtable-input-field-container').hide();
                                    $(data.form).find('#Edit-dueoffsetdays').closest('.jtable-input-field-container').hide();
                                 }
                                 else
                                 {
                                    $(data.form).find('#Edit-description').closest('.jtable-input-field-container').show();
                                    $(data.form).find('#Edit-waitforpreceeding').closest('.jtable-input-field-container').show();
                                    $(data.form).find('#Edit-dueoffsetdays').closest('.jtable-input-field-container').show();
                                 }
                              });
                           }
                        }
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         }
      },
      recordsLoaded: function(event, data){
         if(data.serverResponse.data)
         {
            data.serverResponse.data.forEach((obj) => {
               if(obj.partner_id)
               {
                  $(event.target).find('[data-record-key="'+obj.id+'"]').find('.jtable-delete-command').remove();
                  $(event.target).find('[data-record-key="'+obj.id+'"]').find('.jtable-edit-command').remove();
               }
            });
         }
         else
         {
            if(data.serverResponse.partner_id){
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"]').find('.jtable-delete-command').remove();
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"]').find('.jtable-edit-command').remove();
            }
         }
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer" ></div>
   
@endsection
