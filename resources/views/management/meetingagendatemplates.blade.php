@php if(!Auth::user()->can('create', \Ledningssystemet\Ledningssystemet\Models\ProjectMeetingAgendaTemplate::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   var agendaTable = $('#tableContainer');
   $('#tableContainer').jtable({
      title: @json(__('Meeting agendas')),
      paging: true,
      sorting: false,
      searchfield: true,
      tableId: 'meetingagendatemplatelist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: @json(__('Add meeting agenda')),
      },
      actions: {
         listAction: '/api/v1/items/ProjectMeetingAgendaTemplate',
         createAction: '/api/v1/items/ProjectMeetingAgendaTemplate',
         updateAction: '/api/v1/items/ProjectMeetingAgendaTemplate',
         deleteAction: '/api/v1/items/ProjectMeetingAgendaTemplate',
      },
      fields: {
         id: { key: true, list: false, create: false, edit: false },
         name: {
            title: @json(__('Name')),
            required: true,
            header: true,
            maxlength: 255,
         },
         items: {
            title: '',
            type: 'command',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (agendaData) {
               var button = $('<button type="button" />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text(@json(__('Meeting agenda items')));
               button.on('click', function () {
                  agendaTable.jtable('openChildTable',
                     button.closest('.accordion-body').find('.accordion-footer'), {
                        title: @json(__('Meeting agenda items')),
                        messages: {
                           addNewRecord: @json(__('Add meeting agenda item')),
                        },
                        tableId: 'meetingagendaitemlist',
                        paging: false,
                        bootstrap: false,
                        accordion: false,
                        actions: {
                           listAction: '/api/v1/items/ProjectMeetingAgendaTemplateItem?project_meeting_agenda_template_id=' + agendaData.record.id,
                           createAction: '/api/v1/items/ProjectMeetingAgendaTemplateItem',
                           updateAction: '/api/v1/items/ProjectMeetingAgendaTemplateItem',
                           reorderAction: '/api/v1/items/ProjectMeetingAgendaTemplateItem',
                           deleteAction: '/api/v1/items/ProjectMeetingAgendaTemplateItem',
                        },
                        fields: {
                           id: { key: true, list: false, create: false, edit: false },
                           project_meeting_agenda_template_id: { type: 'hidden', defaultValue: agendaData.record.id },
                           name: { title: @json(__('Name')), required: true, maxlength: 255 },
                           description: { title: @json(__('Description')), type: 'textarea' },
                        },
                     }, function (data) { data.childTable.jtable('load'); });
               });
               return button;
            },
         },
      },
   });
   $('#tableContainer').jtable('load');
});
</script>

<div id="tableContainer"></div>
@endsection
