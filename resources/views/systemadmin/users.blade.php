@php if(Auth::user()->cannot('index', \Ledningssystemet\Ledningssystemet\Models\User::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
function openUserNotificationSettings(user) {
   var days = [
      { name: 'monday', label: @json(__('Monday')) },
      { name: 'tuesday', label: @json(__('Tuesday')) },
      { name: 'wednesday', label: @json(__('Wednesday')) },
      { name: 'thursday', label: @json(__('Thursday')) },
      { name: 'friday', label: @json(__('Friday')) },
      { name: 'saturday', label: @json(__('Saturday')) },
      { name: 'sunday', label: @json(__('Sunday')) },
   ];
   var title = @json(__('E-mail notification settings'));

   $.ajax({
      method: 'GET',
      url: '/systemadmin/users/' + user.id + '/notification-settings',
      dataType: 'json',
      success: function(settings) {
         var content = $('<div class="container-fluid" />');
         var weekdays = $('<div class="row g-2" />');

         days.forEach(function(day) {
            var inputId = 'notification-' + day.name + '-' + user.id;
            weekdays.append(
               $('<div class="col-6 col-md-4" />').append(
                  $('<div class="form-check form-switch" />')
                     .append($('<input class="form-check-input" type="checkbox" />')
                        .attr({ id: inputId, name: day.name })
                     .prop('checked', settings[day.name] === true || settings[day.name] == 1))
                     .append($('<label class="form-check-label" />')
                        .attr('for', inputId)
                        .text(day.label))
               )
            );
         });

         content
            .append($('<p class="mb-3" />').text(@json(__('These settings adjust how often you want to get status overviews from the system'))))
            .append(weekdays)
            .append($('<h5 class="mt-4" />').text(@json(__('Notification channels'))))
            .append($('<div id="userNotificationChannelsTable" />'));

         var status = $('<div class="text-danger mt-2" role="alert" />');
         content.append(status);

         showDialog(title, content, {
            actionLabel: translateString('Save'),
            dismissLabel: translateString('Close'),
            modalClass: 'modal-xl',
            onAction: function(data, dialogDiv) {
               var preferences = {};
               days.forEach(function(day) {
                  preferences[day.name] = dialogDiv.find('input[name="' + day.name + '"]').prop('checked') ? 1 : 0;
               });

               dialogDiv.find('.action-button').prop('disabled', true);
               $.ajax({
                  method: 'POST',
                  url: '/systemadmin/users/' + user.id + '/notification-settings',
                  data: preferences,
                  dataType: 'json',
                  success: function() {
                     dialogDiv.find('.dismiss-button').first().trigger('click');
                  },
                  error: function() {
                     status.text(@json(__('Unable to save notification settings.')));
                     dialogDiv.find('.action-button').prop('disabled', false);
                  },
               });

               return true;
            },
            formCreated: function(dialogDiv) {
               dialogDiv.find('.modal-dialog').addClass('modal-xl');

               var channelsTable = dialogDiv.find('#userNotificationChannelsTable');
               channelsTable.jtable({
                  title: @json(__('Notification channels')),
                  tableId: 'usernotificationchannels',
                  bootstrap: true,
                  accordion: true,
                  messages: {
                     addNewRecord: @json(__('Add new channel')),
                  },
                  actions: {
                     listAction: '/api/v1/items/UserNotificationChannel?user_id=' + user.id,
                     createAction: '/api/v1/items/UserNotificationChannel?user_id=' + user.id,
                     updateAction: '/api/v1/items/UserNotificationChannel',
                     deleteAction: '/api/v1/items/UserNotificationChannel',
                  },
                  fields: {
                     id: {
                        key: true,
                        list: false,
                        create: false,
                        edit: false,
                     },
                     name: {
                        title: @json(__('Name')),
                        create: true,
                        edit: true,
                        list: true,
                        required: true,
                        maxlength: 255,
                        header: true,
                     },
                     ignoremine: {
                        title: @json(__('Notify me when I am doing the changes')),
                        create: true,
                        edit: true,
                        list: true,
                        defaultValue: true,
                        options: [
                           { Value: 1, DisplayText: @json(__('No')) },
                           { Value: 0, DisplayText: @json(__('Yes')) },
                        ],
                     },
                     channelscopes: {
                        title: @json(__('Object scopes')),
                        create: true,
                        edit: true,
                        list: true,
                        multiple: true,
                        options: [
@foreach(\Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::availableScopes() as $obj)
                           { Value: @json($obj['key']), DisplayText: @json($obj['text']) },
@endforeach
                        ],
                     },
                     channelevents: {
                        title: @json(__('Events')),
                        create: true,
                        edit: true,
                        list: true,
                        multiple: true,
                        options: [
@foreach(\Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel::availableEvents() as $obj)
                           { Value: @json($obj['key']), DisplayText: @json($obj['text']) },
@endforeach
                        ],
                     },
                  },
               });
               channelsTable.jtable('load');
            },
         });
      },
      error: function() {
         showDialog(title, $('<p />').text(@json(__('Unable to load notification settings.'))));
      },
   });
}

$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Users") }}',
      paging: true,
      searchfield: true,
      tableId: 'userslist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Create user') }}',
         search: '{{ __('Search by name or department') }}',
      },
	  filter: {
        hide_enabled: {
           type: 'checkbox',
           value: '1',
           checked: false,
           text: '{{ __('Hide actived users') }}',
        },
        hide_disabled: {
           type: 'checkbox',
           value: '1',
           checked: false,
           text: '{{ __('Hide deactivated users') }}',
        },
@include('components.customproperty', ['classname' => 'Ledningssystemet\Ledningssystemet\Models\User', 'showFilter' => true])
	  },
      actions: {
@if(Auth::user()->can('index', \Ledningssystemet\Ledningssystemet\Models\User::class))
         listAction: '/api/v1/items/User',
@endif
@if(Auth::user()->can('delete', \Ledningssystemet\Ledningssystemet\Models\User::class))
         deleteAction: '/api/v1/items/User',
@endif
@if(config('ledningssystemet.local_user_management'))
@if(Auth::user()->can('create', \Ledningssystemet\Ledningssystemet\Models\User::class))
         createAction: '/api/v1/items/User',
@endif
@if(Auth::user()->can('update', \Ledningssystemet\Ledningssystemet\Models\User::class))
         updateAction: '/api/v1/items/User',
@endif
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
         title: {
            title: '{{ __('Title') }}',
            create: true,
            edit: true,
            list: true,
            maxlength: 255,
            listClass: 'd-inline-block col-6 col-md-4',
         },
         enabled: {
            title: '{{ __('Enabled') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            defaultValue: 1,
            options: {0: '{{ __("No") }}', 1: '{{ __("Yes") }}'},
            listClass: 'd-inline-block col-6 col-md-4',
         },
@if(\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff'))
         manager_user_id: {
            title: '{{ __('Manager') }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-6 col-md-4',
            options: [
               { Value: null, DisplayText: '{{ __("None") }}' },
@foreach(\Ledningssystemet\Ledningssystemet\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach            
            ]
         },
@endif
         email: {
            title: '{{ __('E-mail address') }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            required: true,
         },
         last_login_at: {
            title: '{{ __('Last login') }}',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         departments: {
            title: '{{ __('Departments') }}',
            create: @php echo(config('ledningssystemet.graph_departments_assignusers') ? "false" : "true"); @endphp,
            edit: @php echo(config('ledningssystemet.graph_departments_assignusers') ? "false" : "true"); @endphp,
            list: true,
            multiple: true,
            options: [
@foreach(Ledningssystemet\Ledningssystemet\Models\Department::orderBy('name')->get()->each->setAppends([]) as $obj)
   { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach
            ]
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
@if(\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff'))
         org_roles: {
            title: '{{ __('Roles') }}',
            create: true,
            edit: true,
            list: true,
            multiple: true,
            options: [
@foreach(Ledningssystemet\Ledningssystemet\Models\Role::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
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
@endif
         access_groups: {
            title: '{{ __('Access groups') }}',
            create: true,
            edit: true,
            list: true,
            multiple: true,
            options: [
@foreach(Ledningssystemet\Ledningssystemet\Models\AccessGroup::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach
            ]
         },
@if(\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff'))
         hr3: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         directreports: {
            title: '{{ __('Direct reports') }}',
            create: false,
            edit: false,
            list: true,
            display: function(data) {
               var retval = $('<div />');
               Object.values(data.record.direct_reports).forEach((obj) => {
                  retval.append($('<div />').text(obj.name));
               });
               return retval;
            }
         },
@endif
   @include('components.customproperty', ['classname' => 'Ledningssystemet\Ledningssystemet\Models\User'])

         hr4: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
@if(Auth::user()->can('update', \Ledningssystemet\Ledningssystemet\Models\User::class))
         notifications: {
            title: '',
            list: true,
            edit: false,
            create: false,
            type: 'command',
            footer: true,
            display: function(data) {
               return $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .css({cursor: 'pointer'})
                  .text('{{ __('Notification settings') }}')
                  .append($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('notifications'))
                  .on('click', function() {
                     openUserNotificationSettings(data.record);
                  });
            },
         },
         reassign: {
            edit: false,
            create: false,
            type: 'command',
            footer: true,
            display: function(data){
               var retobj= $('<a />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .css({cursor: 'pointer'})
                  .text('{{__('Re-assign objects'); }}')
                  .append($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('alt_route'))
                  .on('click', function(){
                     var retval = $('<div />');
                     
                     if(data.record.activitiescount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="activities-user" class="form-label">{{ __("Activities") }}</label>'))
                           .append($('<select id="activities-user" name="activities" class="form-select form-select-sm" required="true" />'));
                     }


                     if(data.record.assetscount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="assets-user" class="form-label">{{ __("Assets") }}</label>'))
                           .append($('<select id="assets-user" name="assets" class="form-select form-select-sm" required="true" />'));
                     }


                     if(data.record.controlscount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="controls-user" class="form-label">{{ __("Controls") }}</label>'))
                           .append($('<select id="controls-user" name="controls" class="form-select form-select-sm" required="true" />'));
                     }


                     if(data.record.control_actionscount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="control_actions-user" class="form-label">{{ __("Control actions") }}</label>'))
                           .append($('<select id="control_actions-user" name="control_actions" class="form-select form-select-sm" required="true" />'));
                     }

                     if(data.record.incidentscount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="incidents-user" class="form-label">{{ __("Incidents") }}</label>'))
                           .append($('<select id="incidents-user" name="incidents" class="form-select form-select-sm" required="true" />'));
                     }


                     if(data.record.information_typescount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="information_types-user" class="form-label">{{ __("Information types") }}</label>'))
                           .append($('<select id="information_types-user" name="information_types" class="form-select form-select-sm" required="true" />'));
                     }

                     if(data.record.objectivescount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="objectives-user" class="form-label">{{ __("Objectives") }}</label>'))
                           .append($('<select id="objectives-user" name="objectives" class="form-select form-select-sm" required="true" />'));
                     }


                     if(data.record.processescount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="processes-user" class="form-label">{{ __("Processes") }}</label>'))
                           .append($('<select id="processes-user" name="processes" class="form-select form-select-sm" required="true" />'));
                     }

                     if(data.record.process_performance_metricscount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="process_performance_metrics-user" class="form-label">{{ __("Process performance metrics") }}</label>'))
                           .append($('<select id="process_performance_metrics-user" name="process_performance_metrics" class="form-select form-select-sm" required="true" />'));
                     }


                     if(data.record.riskscount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="risks-user" class="form-label">{{ __("Risks") }}</label>'))
                           .append($('<select id="risks-user" name="risks" class="form-select form-select-sm" required="true" />'));
                     }

@if(\Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier'))
                     if(data.record.supplierscount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="suppliers-user" class="form-label">{{ __("Suppliers") }}</label>'))
                           .append($('<select id="suppliers-user" name="suppliers" class="form-select form-select-sm" required="true" />'));
                     }
@endif
                        
                     retval.find('select').each(function(){
@foreach(\Ledningssystemet\Ledningssystemet\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
                        $(this).append($('<option value="{{ $obj->id }}" />')
                           .text(@php echo(json_encode($obj->name)); @endphp)
                           .prop('selected', ({{ $obj->id }} == data.record.id)));
@endforeach                     
                     });
                     
                     confirmDialog('{{ __("Re-assign objects") }}', retval, function(formdata){
                        ajaxPost('/api/v1/items/User/'+data.record.id+'/reassign', formdata, function(){
                           $('#tableContainer').jtable('reload');
                        });
                     });
                  });
                  
               return retobj;
            },
         },   
@endif         
         passwordreset: {
            list: true,
            edit: false,
            create: false,
            type: 'command',
            footer: true,
            display: function(data){
               var retobj= $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{__('Send password reset link'); }}')
                  .append($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('lock_reset'))
                  .on('click', function(){
                     confirmDialog('{{ __("Send password reset link") }}', '{{__("Are you sure?") }}', function(){
                        ajaxPost('/api/v1/items/User/'+data.record.id+'/resetpasssword', {}, function(){
                           showDialog('{{ __("Send password reset link") }}', '{{ __("A password reset link has been sent to the user") }}');
                        });
                     });
                  });
               return retobj;
            }
         }
      },
      rowLoaded: function(event, data){
         if(data.record.activitiescount ||
            data.record.assetscount ||
            data.record.controlscount ||
            data.record.control_actionscount ||
            data.record.findingscount ||
            data.record.incidentscount ||
            data.record.information_typescount ||
            data.record.objectivescount ||
            data.record.processescount ||
            data.record.process_performance_metricscount ||
            data.record.riskscount ||
            data.record.supplierscount ||
            data.record.external_id
         )
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"]').find('.jtable-delete-command').remove();
         else
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"]').find('.jtable-command-column[data-jtable-fieldname="reassign"]').remove();
      },
      recordsLoaded: function(event, data) {
         if(data.serverResponse.data)
         {
            data.serverResponse.data.forEach((obj) => {
               if(obj.external_id)
               {
                  // Remove delete button
                  $('[data-record-key="'+obj.id+'"]').find('.jtable-delete-command').remove();
                  
               }
            });
         }
         else
         {
            if(data.serverResponse.external_id)
            {
               // Remove delete button
               $('[data-record-key="'+data.serverResponse.id+'"]').find('.jtable-delete-command').remove();
               
            }
         }
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer"></div>
   
@endsection
