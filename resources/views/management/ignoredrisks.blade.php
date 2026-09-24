@php if(Auth::user()->cannot('index', \App\Models\IgnoredRisk::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')
<style>
   .jtable-search-field {
      justify-content: right;
   }
</style>
<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Ignored risks") }}',
      paging: true,
      searchfield: true,
      tableId: 'ignoredriskstable',
      actions: {
@if(Auth::user()->can('index', \App\Models\IgnoredRisk::class))
         listAction: '/api/v1/items/IgnoredRisk',
@endif
@if(Auth::user()->can('delete', \App\Models\IgnoredRisk::class))
         deleteAction: '/api/v1/items/IgnoredRisk',
@endif
      },
      fields: {
         id: {
            key: true,
            list: false,
            create: false,
            edit: false,
         },
         object: {
            title: '{{ __('Context') }}',
            list: true,
            display: function(data) {
               if(!data.record.object || !data.record.object.type)
                  return '';

               return data.record.object.type + (data.record.object.name ? (' ' + data.record.object.name) : '');
            },
         },
         name: {
            title: '{{ __('Name') }}',
            list: true,
         },
         scenariodescription: {
            title: '{{ __('Scenario description') }}',
            list: true,
         },
         creationinfo: {
            title: '{{ __('Ignored') }}',
            list: true,
         },
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div class="alert alert-info">
   <strong>{{ __("Note") }}:</strong> {{ __("If you delete an ignored risk, it will be re-generated if the associated object still exist and the partner risk template is applicable for the object.") }}
</div>
<div id="tableContainer"></div>
   
@endsection
