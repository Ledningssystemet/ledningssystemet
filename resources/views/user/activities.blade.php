@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '&nbsp;',
      paging: true,
      searchfield: true,
      tableId: 'activitiestable',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Add new activity') }}',
      },
      filter: {
         showcompleted: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show completed activities') }}',
         },
      },
      actions: {
         listAction: '/api/v1/items/Activity?showmyonly=1',
         createAction: '/api/v1/items/Activity',
         updateAction: '/api/v1/items/Activity',
         deleteAction: '/api/v1/items/Activity',
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
            listClass: 'd-inline-block col-10',
            required: true,
            maxlength: 255,
            header: true,
            display: function(data){
               let retval = $('<span />')
                  .css({'display': 'flex', 'align-items': 'center'})
                  .append($('<span />')
                     .text(data.record.name));

               if(data.record.activity_flow_id) {
                  retval
                     .append($('<span />')
                        .addClass('badge pill rounded-pill bg-primary')
                        .css( { 'margin-left': '1rem', 'font-size': '0.8rem', 'font-weight': 600 })
                        .text(data.record.activity_flow_name));
               }

               return retval;
            }
         },
         responsible_user_id: {
            title: '{{ __('Responsible user') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            listClass: 'd-inline-block col-4',
            defaultValue: {{ auth()->user()->id  }},
            options: [
@foreach(App\Models\User::where('enabled', true)->get()->each->setAppends([]) as $obj)
               { Value: {{$obj->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach
            ]
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
            listClass: 'd-inline-block col-4',
         },
         activity_flow_name: {
            title: '{{ __('Activity flow') }}',
            list: true,
            edit: false,
            create: false,
            listClass: 'd-inline-block col-4',
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
         intervalnum: {
            title: '{{ __('Interval') }}',
            type: 'number',
            width: '10%',
            min: 1,
            max: 999,
            defaultValue: 1,
            step: 1,
            list: true,
            edit: true,
            create: true,
            display: function(data){
               if(data.record.intervaltype)
               {
                  return $('<span />')
                     .text('{{ __("Every") }} '+data.record.intervalnum+' '+data.record.intervaltypetext);
               }
               else
                  return $('<span />')
                     .text('{{ __("Not recurring") }}');
            }
         },
         intervaltype: {
            title: '{{ __('Interval type') }}',
            width: '10%',
            list: false,
            edit: true,
            create: true,
            options: [
               { Value: null, DisplayText: '{{__("No recurring actitivity") }}' },
               { Value: 'DAYS', DisplayText: '{{ __("Days") }}' },
               { Value: 'MONTHS', DisplayText: '{{ __("Months") }}' },
               { Value: 'YEARS', DisplayText: '{{ __("Years") }}' },
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
         commands: {
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function(data){
               if(!data.record.completed_at)
               {
                  return $('<button />')
                     .addClass('btn btn-outline-primary btn-sm completedbutton')
                     .text('{{ __('Completed') }}')
                     .prepend($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('check'))
                     .click(function(){ userFinishActivity(data.record.id, function(){
                        $('#tableContainer').jtable('reload');
                     }); });
               }
               else
               {
                  return $('<span />');
               }
            }
         },   
         showHistory: showHistoryField('Activity', $('#tableContainer')),          
         showMessages: showMessagesField('Activity', $('#tableContainer')),  
      },
      formCreated: function(event, data){
      },
      recordsLoaded: function(event, data){
         if(data.serverResponse.data)
         {
            data.serverResponse.data.forEach((obj) => {
               
               if(!obj.responsible_user_id)
               {
                  $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-field-text[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');
                  $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-edit-command .material-symbols-rounded').addClass('text-danger');
                  $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-delete-command').remove();
                  $(event.target).find('[data-record-key="'+obj.id+'"] .completedbutton').closest('.jtable-cell-content').remove();
               }
               
               if(obj.responsible_user_id && (obj.responsible_user_id != {{ auth()->user()->id }}))
               {
                  $(event.target).find('[data-record-key="'+obj.id+'"] .completedbutton').closest('.jtable-cell-content').remove();
               }
               
               if(obj.activity_flow_id)
               {
                  $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-delete-command').remove();
               }
            });
         }
         else
         {
            if(!data.serverResponse.responsible_user_id)
            {
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .jtable-field-text[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .jtable-edit-command .material-symbols-rounded').addClass('text-danger');
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .jtable-delete-command').remove();
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .completedbutton').closest('.jtable-cell-content').remove();
            }

            if(data.serverResponse.responsible_user_id && (data.serverResponse.responsible_user_id != {{ auth()->user()->id }}))
            {
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .completedbutton').closest('.jtable-cell-content').remove();
            }
            
            if(data.serverResponse.activity_flow_id)
            {
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .jtable-delete-command').remove();
            }
         }
      },
   });
   $('#tableContainer').jtable('load');

   $('#activityflowtable').jtable({
      title: '{{ __("Activity flows") }}',
      paging: true,
      sorting: false,
      defaultSorting: 'name ASC',
      searchfield: true,
      tableId: 'activityflowstable',
      bootstrap: true,
      accordion: true,
      filter: {
         hidecompleted: {
            type: 'checkbox',
            value: '1',
            checked: true,
            text: '{{ __('Hide completed') }}',
         },
      },
      actions: {
         listAction: '/api/v1/items/ActivityFlow?showmyonly=1',
         deleteAction: '/api/v1/items/ActivityFlow',
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
         },
         activity_flow_template_id: {
            title: '{{ __('Activity flow template') }}',
            list: true,
            listClass: 'd-inline-block col col-12 col-md-4',
            options: [
@foreach(\App\Models\ActivityFlowTemplate::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach            
            ]
         },
         responsible_user_id: {
            title: '{{ __('Responsible user') }}',
            list: true,
            listClass: 'd-inline-block col col-12 col-md-4',
            options: [
@foreach(\App\Models\User::where('enabled', true)->orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach            
            ]
         },
         started_at: {
            title: '{{ __('Started') }}',
            list: true,
            listClass: 'd-inline-block col col-12 col-md-4',
         },
         hr0: {
            title: '',
            list: true,
            display: function(data){
               return $('<hr />');
            }
         },
         description: {
            title: '{{ __('Notes') }}',
         },
         hr1: {
            title: '',
            list: true,
            display: function(data){
               return $('<hr />');
            }
         },
         activities: {
            title: '{{ __('Activities') }}',
            list: true,
            display: function(data) {
               var retval = $('<div />');
               
               Object.values(data.record.activities).forEach((obj) => {
                  retval.append($('<div />')
                     .addClass('activityflowitem')
                     .addClass(obj.status)
                     .text(obj.name+', '+translateString('responsible user')+': '+obj.responsible + '('+(obj.completed_at ? translateString('Finished') : obj.due)+')'));
               });
               
               return retval;
            }
         },
         hr2: {
            title: '',
            list: true,
            display: function(data){
               return $('<hr />');
            }
         },
      },
      recordsLoaded: function(event, data){
      },
   });
   $('#activityflowtable').jtable('load');         
});
</script>
<ul class="nav nav-tabs" id="pageTabs" role="tablist" style="margin-bottom: 20px;">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="activitytab" data-bs-toggle="tab" data-bs-target="#activitytabcontent" type="button" role="tab" aria-controls="activitytab" aria-selected="true">{{ __("Activities") }}</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="activityflowtab" data-bs-toggle="tab" data-bs-target="#activityflowtabcontents" type="button" role="tab" aria-controls="activityflowtab" aria-selected="true">{{ __("Activity flows") }}</button>
  </li>
</ul>
<div class="tab-content" id="tabContents">
  <div class="tab-pane fade show active" id="activitytabcontent" role="tabpanel" aria-labelledby="activitytab">
      <div id="tableContainer"></div>
  </div>
  <div class="tab-pane fade show" id="activityflowtabcontents" role="tabpanel" aria-labelledby="activityflowtab">
@if(\App\Models\ActivityFlowTemplate::where('user_instantiatable', true)->exists())
   <div class="dropdown">
     <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
     {{ __("Start new flow") }}
     </button>
     <ul class="dropdown-menu" style="border-radius: 5px;">
@foreach(\App\Models\ActivityFlowTemplate::where('user_instantiatable', true)->orderBy('name')->get()->each->setAppends([]) as $obj)
       <li><a class="dropdown-item" href="/management/activityflow?activity_flow_template_id={{ $obj->id }}">{{ $obj->name }}</a></li>
@endforeach
     </ul>
   </div>  
@endif
      <div id="activityflowtable" style="margin-top: 20px;"></div>
  </div>
</div>
@endsection
