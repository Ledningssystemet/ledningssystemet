@php if(Auth::user()->cannot('index', \App\Models\Risk::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>

$(function(){
   $('#tableContainer').jtable({
      paging: true,
      sorting: false,
      defaultSorting: 'name ASC',
      searchfield: true,
      tableId: 'riskregister',
      openChildAsAccordion: true,
      bootstrap: true,
      accordion: true,
      title: '{{ __("Risk register") }}',
      messages: {
         addNewRecord: '{{ __('Add new risk') }}',
      },
      filter: {
@php $tags = \App\Models\Risk::allUsedTags(); @endphp
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
            text: '{{ __("Department") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show mine') }}' },
               { value: -1, text: '{{ __('Show all') }}' },
@foreach(\App\Models\Department::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach               
            ]
         },
         context_type: {
            type: 'select',
            text: '{{ __("Context") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
               { value: 'Department', text: '{{ __("Departments") }}' },
               { value: 'Process', text: '{{ __("Processes") }}' },
               { value: 'Asset', text: '{{ __("Assets") }}' },
               { value: 'InformationType', text: '{{ __("Information types") }}' },
@if(!config('ledningssystemet.disable_supplier'))
               { value: 'Supplier', text: '{{ __("Suppliers") }}' },
@endif
               { value: 'ProcessActivity', text: '{{ __("Process activities") }}' },
               { value: 'Site', text: '{{ __("Sites") }}' },
               { value: 'Customer', text: '{{ __("Customers") }}' },
            ]
         },
         probability_id: {
            type: 'select',
            text: '{{ __("Probability") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
@foreach(\App\Models\ProbabilityLevel::orderBy('ordinal', 'desc')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach               
            ]
         },
         consequence_id: {
            type: 'select',
            text: '{{ __("Consequence") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
@foreach(\App\Models\ConsequenceLevel::orderBy('ordinal', 'desc')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach               
            ]
         },
         risk_level_id: {
            type: 'select',
            text: '{{ __("Risk level") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
@foreach(\App\Models\RiskLevel::orderBy('ordinal', 'desc')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach               
            ]
         },
         riskowner_id: {
            type: 'select',
            text: '{{ __("Risk owner") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
                  @foreach(\App\Models\User::leftJoin('risks', 'risks.riskowner_id', '=', 'users.id')->whereNotNull('risks.id')->select(['users.id', 'users.name'])->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showmyonly: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Only show risks where I am risk owner') }}',
         },
         showdraft: {
            type: 'checkbox',
            value: '1',
            checked: true,
            text: '{{ __('Show draft risks') }}',
         },
         showapproved: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show approved risks') }}',
         },
@include('components.customproperty', ['classname' => 'App\Models\Risk', 'showFilter' => true])
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Risk::class))         
         listAction: '/api/v1/items/Risk',
@endif         
@if(Auth::user()->can('create', \App\Models\Risk::class))         
         createAction: '/api/v1/items/Risk',
@endif         
@if(Auth::user()->can('update', \App\Models\Risk::class))         
         updateAction: '/api/v1/items/Risk',
@endif         
@if(Auth::user()->can('delete', \App\Models\Risk::class))         
         deleteAction: '/api/v1/items/Risk',
@endif         
      },
@include('assessment.risktablefields')      
      formCreated: function(event, data){
         if(data.record && data.record.partner_id)
         {
            $(data.form).find('*[name]').each(function(){
               switch($(this).prop('name'))
               {
                  case 'name':
                     $(this).closest('div.jtable-input-field-container [name]').prop('disabled', 1);
                     $(this).closest('div.jtable-input-field-container [name]').val(data.record.name_pretty);
                     
                     break;
                  case 'scenariodescription':
                     $(this).closest('div.jtable-input-field-container [name]').prop('disabled', 1);
                     $(this).closest('div.jtable-input-field-container [name]').val(data.record.scenariodescription_pretty);
                     break;
                  case 'context':
                     $(this).closest('div.jtable-input-field-container [name]').prop('disabled', 1);
                     break;
               }
            });
         }
         
         $(data.form).find('select#Edit-context').select2({dropdownParent: data.form});
      },
      recordUpdated: function(event, data) {
         $('#tableContainer').jtable('reloadRow', $(event.target).closest('.jtable-data-row'));
      },
      recordsLoaded: function(event, data){
         if(data.serverResponse.data)
         {
            data.serverResponse.data.forEach((obj) => {
               if(obj.assessed_at)
               {
                  $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-edit-command').remove();
@if(!auth()->user()->canAny(['riskadministrator.edit', 'superadmin.edit']))
                  $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-delete-command').remove();
@endif               
               }
            });
         }
         else
         {
            if(data.serverResponse.assessed_at)
            {
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .jtable-edit-command').remove();
@if(!auth()->user()->canAny(['riskadministrator.edit', 'superadmin.edit']))
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .jtable-delete-command').remove();
@endif
            }
         }
      },
   });
   $('#tableContainer').jtable('load');
});

</script>

<div class="d-flex flex-row flex-row-reverse mb-4 w-100">
@if(Auth::user()->canAny(['riskadministrator.edit']))
   <a class="btn btn-outline-primary btn-sm" href="/api/v1/ReportCentral/Risks/0">
      <span class="material-symbols-rounded">download</span>
      {{ __("Export as Excel") }}
   </a>
@endif
</div>

<div id="tableContainer"></div>

@endsection
