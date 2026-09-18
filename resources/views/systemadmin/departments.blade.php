@php if(Auth::user()->cannot('index', \App\Models\Department::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   const departmentParentOptions = @php echo json_encode(\App\Models\Department::orderBy('name')->get(['id', 'name', 'parent_department_id'])->map(function($department){
      return [
         'id' => $department->id,
         'name' => $department->name,
         'parent_department_id' => $department->parent_department_id,
      ];
   })->values()); @endphp;

   function getDepartmentParentSelectOptions(currentId)
   {
      var blockedIds = {};

      if(currentId)
      {
         blockedIds[currentId] = true;

         var changed = true;
         while(changed)
         {
            changed = false;

            departmentParentOptions.forEach(function(department){
               if(blockedIds[department.id])
                  return;

               if(blockedIds[department.parent_department_id])
               {
                  blockedIds[department.id] = true;
                  changed = true;
               }
            });
         }
      }

      var retval = [{ Value: null, DisplayText: '{{ __("None") }}' }];
      departmentParentOptions.forEach(function(department){
         if(!blockedIds[department.id])
            retval.push({ Value: department.id, DisplayText: department.name });
      });

      return retval;
   }

   $('#tableContainer').jtable({
      title: '{{ __("Departments") }}',
      paging: true,
      searchfield: true,
      tableId: 'departmentlist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Create department') }}',
      },
	  filter: {
@include('components.customproperty', ['classname' => 'App\Models\Department', 'showFilter' => true])
	  },
      actions: {
@if(Auth::user()->can('index', \App\Models\Department::class))         
         listAction: '/api/v1/items/Department',
@endif
@if(Auth::user()->can('create', \App\Models\Department::class))         
         createAction: '/api/v1/items/Department',
@endif
@if(Auth::user()->can('update', \App\Models\Department::class))         
         updateAction: '/api/v1/items/Department',
@endif
@if(Auth::user()->can('delete', \App\Models\Department::class))         
         deleteAction: '/api/v1/items/Department',
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
         site_id: {
            title: '{{ __("Site") }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col col-12 col-md-4',
            defaultValue: null,
            options: [
               { Value: null, DisplayText: '{{ __("None") }}' },
@foreach(\App\Models\Site::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{$obj->id}}, DisplayText: '{{ $obj->name }}' },
@endforeach
            ]
         },
         parent_department_id: {
            title: '{{ __("Parent department") }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col col-12 col-md-4',
            defaultValue: null,
            options: function(data){
               return getDepartmentParentSelectOptions(data.record ? data.record.id : null);
            }
         },
@if(!config('ledningssystemet.graph_departments_assignusers') && ("" != config('ledningssystemet.graph_groupsync_path', '')))
         external_provider_group_id: {
            title: '{{ config("ledningssystemet.graph_provider_name", __("External provider"))." ".__("group") }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col col-12 col-md-4',
            defaultValue: null,
            options: [
               { Value: null, DisplayText: '{{ __("None") }}' },
@foreach(\Illuminate\Support\Facades\DB::table('external_provider_groups')->orderBy('name')->get() as $obj)
               { Value: {{$obj->id}}, DisplayText: '{{ $obj->name }}' },
@endforeach
            ]
         },
@endif
@if(!config('ledningssystemet.graph_departments_assignusers') && ("" != config('ledningssystemet.graph_groupsync_path', '')))
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         department_users: {
            title: '{{ __('Users') }}',
            create: false,
            edit: true,
            list: true,
            multiple: true,
@if("" != config('ledningssystemet.graph_groupsync_path', ''))
            tooltip: '{{ __("This will be overwritten by the next synchronization with external provider if a group is selected above") }}',
@endif
            options: [
@foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach
            ]
         },
@endif
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         department_objects: {
            title: '{{ __('Objects owned by this department') }}',
            create: false,
            edit: false,
            list: true,
            display: function(data){
               var retval = $('<div />');
               
               if(data.record.processcount)
                  retval.append($('<div />').text(data.record.processcount+' {{ __("Processes") }}'));
              
               if(data.record.departmentriskcount)
                  retval.append($('<div />').text(data.record.departmentriskcount+' {{ __("Risks (including replaced risks)") }}'));
               
@if(!config('ledningssystemet.disable_finding'))
               if(data.record.departmentfindingcount)
                  retval.append($('<div />').text(data.record.departmentfindingcount+' {{ __("Findings") }}'));
@endif               
               return retval;
            }
         },
         @include('components.customproperty', ['classname' => 'App\Models\Department'])
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
@if(Auth::user()->can('index', \App\Models\Risk::class))         
         showRisks: showRisks('Department', $('#tableContainer')),
@endif
@if(Auth::user()->can('update', \App\Models\Department::class))         
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
                     
                     if(data.record.processcount)
                     {
                        $('<div />')
                           .addClass('form-group')
                           .appendTo(retval)
                           .append($('<label for="processes-department" class="form-label">{{ __("Processes") }} {{ __("department") }}</label>'))
                           .append($('<select id="processes-department" name="processes" class="form-select form-select-sm" required="true" />'));
                     }
                     if(data.record.departmentriskcount)
                     {
                        $('<div />')
                           .addClass('form-group')
                           .appendTo(retval)
                           .append($('<label for="risks-department" class="form-label">{{ __("Risks") }} {{ __("department") }}</label>'))
                           .append($('<select id="risks-department" name="risks" class="form-select form-select-sm" required="true" />'));
                     }
                     
                     if(data.record.departmentfindingcount)
                     {
                        $('<div />')
                           .addClass('form-group')
                           .appendTo(retval)
                           .append($('<label for="findings-department" class="form-label">{{ __("Findings") }} {{ __("department") }}</label>'))
                           .append($('<select id="findings-department" name="findings" class="form-select form-select-sm" required="true" />'));
                     }
                     
                     retval.find('select').each(function(){
@foreach(\App\Models\Department::orderBy('name')->get()->each->setAppends([]) as $obj)
                        $(this).append($('<option value="{{ $obj->id }}" />')
                           .text(@php echo(json_encode($obj->name)); @endphp)
                           .prop('selected', ({{ $obj->id }} == data.record.id)));
@endforeach                     
                        
                     });
                     
                     confirmDialog('{{ __("Re-assign objects") }}', retval, function(formdata){
                        ajaxPost('/api/v1/items/Department/'+data.record.id+'/reassign', formdata, function(){
                           $('#tableContainer').jtable('reload');
                        });
                     });
                  });
                  
               return retobj;
            }
         },      
@endif
      },
      recordUpdated: function(event, data){
        $(event.target).jtable('reloadRow', data.row);
      },
      rowLoaded: function(event, data){
         if(data.record.processcount ||
            data.record.departmentriskcount ||
            data.record.departmentfindingcount
         )
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"]').find('.jtable-delete-command').remove();
         else
            $(event.target).find('.jtable-data-row[data-record-key="'+data.record.id+'"]').find('.jtable-command-column[data-jtable-fieldname="reassign"]').remove();
      }
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer"></div>
   
@endsection
