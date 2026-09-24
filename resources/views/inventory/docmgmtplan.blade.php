@php if(config('ledningssystemet.disable_archival') || config('ledningssystemet.disable_archival')) abort(404); @endphp
@php if(!request()->user()->canAny(['docmgmtplan.read', 'docmgmtplan.edit'])) abort(403);  @endphp
@extends('layouts.master')

@section('container')
<style>
</style>
<script>
   const confidentialityGrounds = {
@foreach (\App\Models\ConfidentialityGround::all() as $obj)
      {{ $obj->id }}: <?php echo(json_encode($obj->name)); ?>,
@endforeach
   };

   const diaries = {
@foreach (\App\Models\Diary::all() as $obj)
           {{ $obj->id }}: <?php echo(json_encode($obj->name)); ?>,
@endforeach
   };

$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Document management plan") }}',
      tableId: 'docmgmtplan',
      bootstrap: true,
      accordion: true,
      searchfield: true,
      filter: {
         department_id: {
            type: 'select',
            text: '{{ __("Department") }}',
            default: 0,
            options: [
               { value: null, text: '{{ __('Show all') }}' },
@foreach(\App\Models\Department::orderBy('name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach               
            ]
         },
         'confidentiality_ground_id': {
            type: 'select',
            text: '{{ __("Confidentiality ground") }}',
            default: 0,
            options: [
               { value: null, text: '{{ __('Show all') }}' },
@foreach(\App\Models\ConfidentialityGround::whereIn('id',\App\Models\InformationType::pluck('confidentiality_ground_id')->unique())->orderBy('name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach
            ]
         },
         'diary_id': {
            type: 'select',
            text: '{{ __("Diary") }}',
            default: 0,
            options: [
               { value: null, text: '{{ __('Show all') }}' },
@foreach(\App\Models\Diary::whereIn('id',\App\Models\InformationType::pluck('diary_id')->unique())->orderBy('name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach
            ]
         }
      },
      actions: {
         listAction: '/api/v1/documentManagementPlan',
      },
      fields: {
         id: {
            key: true,
            list: false,
         },
         name: {
            title: '',
            header: true,
         },
         description: {
            title: '{{ __("Description") }}',
            listClass: 'd-inline-block col col-12',
         },
         hr0: {
            title: '',
            display: function(data){
               return '<hr />';
            }
         },
         departments: {
            title: '{{ __("Departments") }}',
            listClass: 'd-inline-block col col-12 col-md-4',
            display: function(data){
               var retval = $('<div />');

               data.record.departments.forEach(function(departments){
                  retval.append($('<div />').text(departments.name));
               });

               return retval;
            }
         },
         responsible: {
            title: '{{ __("Responsible") }}',
            listClass: 'd-inline-block col col-12 col-md-4',
         },
         assets: {
            title: '{{ __("Storage location") }}',
            listClass: 'd-inline-block col col-12 col-md-4',
            display: function(data){
               var retval = $('<div />');

               data.record.assets.forEach(function(asset){
                  retval.append($('<div />').text(asset.name));
               });

               return retval;
            }
         },
         hr1: {
            title: '',
            display: function(data){
               return '<hr />';
            }
         },
         retention: {
            title: '{{ __("Retention time") }}',
            listClass: 'd-inline-block col col-12 col-md-4',
         },
         confidentiality_ground: {
            title: '{{ __("Confidentiality ground") }}',
            listClass: 'd-inline-block col col-12 col-md-4',
         },
         diary: {
            title: '{{ __("Diary") }}',
            listClass: 'd-inline-block col col-12 col-md-4',
         },
         hr2: {
            title: '',
            display: function(data){
               return '<hr />';
            }
         },
         sortinginformation: {
            title: '{{ __("Sorting details") }}',
            listClass: 'd-inline-block col col-12 col-md-4',
         },
         archiveshippingtime: {
            title: '{{ __("Years until shipping to central archive") }}',
            listClass: 'd-inline-block col col-12 col-md-4',
         },
         archivemedia: {
            title: '{{ __("Archiving media") }}',
            listClass: 'd-inline-block col col-12 col-md-4',
         },
         hr3: {
            title: '',
            display: function(data){
               return '<hr />';
            }
         },
         archivingdescription: {
            title: '{{ __("Archiving and record keeping details") }}',
            listClass: 'd-inline-block col col-12',
         },
      },
   });
   $('#tableContainer').jtable('load');
});
</script>

<div class="d-flex flex-row flex-row-reverse mb-4 w-100">
   <a class="btn btn-outline-primary btn-sm" href="/api/v1/ReportCentral/DocumentManagementPlan/0">
      <span class="material-symbols-rounded">download</span>
      {{ __("Export as Excel") }}
   </a>
</div>
<div id="tableContainer"></div>

   
@endsection
