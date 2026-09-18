@php if(Auth::user()->cannot('index', \App\Models\User::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
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
@include('components.customproperty', ['classname' => 'App\Models\User', 'showFilter' => true])
	  },
      actions: {
@if(Auth::user()->can('index', \App\Models\User::class))
         listAction: '/api/v1/items/User',
@endif
@if(Auth::user()->can('delete', \App\Models\User::class))
         deleteAction: '/api/v1/items/User',
@endif
@if(config('ledningssystemet.local_user_management'))
@if(Auth::user()->can('create', \App\Models\User::class))
         createAction: '/api/v1/items/User',
@endif
@if(Auth::user()->can('update', \App\Models\User::class))
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
@if(!config('ledningssystemet.disable_staff'))
         manager_user_id: {
            title: '{{ __('Manager') }}',
            create: false,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-6 col-md-4',
            options: [
               { Value: null, DisplayText: '{{ __("None") }}' },
@foreach(\App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
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
            create: false,
            edit: @php echo(config('ledningssystemet.graph_departments_assignusers') ? "false" : "true"); @endphp,
            list: true,
            multiple: true,
            options: [
@foreach(App\Models\Department::orderBy('name')->get()->each->setAppends([]) as $obj)
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
@if(!config('ledningssystemet.disable_staff'))
         roles: {
            title: '{{ __('Roles') }}',
            create: false,
            edit: true,
            list: true,
            multiple: true,
            options: [
@foreach(App\Models\Role::orderBy('name')->get()->each->setAppends([]) as $obj)
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
         accessgroups: {
            title: '{{ __('Access groups') }}',
            create: false,
            edit: true,
            list: true,
            multiple: true,
            options: [
@foreach(App\Models\AccessGroup::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach
            ]
         },
@if(!config('ledningssystemet.disable_staff'))
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
   @include('components.customproperty', ['classname' => 'App\Models\User'])

         hr4: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
@if(Auth::user()->can('update', \App\Models\User::class))
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

@if(!config('ledningssystemet.disable_supplier'))
                     if(data.record.supplierscount)
                     {
                        $('<div />')
                           .appendTo(retval)
                           .append($('<label for="suppliers-user" class="form-label">{{ __("Suppliers") }}</label>'))
                           .append($('<select id="suppliers-user" name="suppliers" class="form-select form-select-sm" required="true" />'));
                     }
@endif
                        
                     retval.find('select').each(function(){
@foreach(\App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
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
      formCreated: function(event, data) {
         if(!data || !data.record || !data.record.external_id || !data.form)
            return;
         
         $(data.form).find('[name]').each(function(){
            switch($(this).prop('name')){
               case 'manager_user_id':
               case 'departments[]':
               case 'roles[]':
               case 'accessgroups[]':
                  break;
               default:
                  $(this).closest('.jtable-input-field-container').remove();               
            }
         });
      }
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer"></div>
   
@endsection
