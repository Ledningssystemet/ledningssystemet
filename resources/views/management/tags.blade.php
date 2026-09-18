@php if(Auth::user()->cannot('index', \App\Models\Tag::class)) abort(403); @endphp
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
      title: '{{ __("Tag collection") }}',
      paging: true,
      searchfield: true,
      tableId: 'tagstable',
      messages: {
         addNewRecord: '{{ __('Create tag') }}',
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Tag::class))         
         listAction: '/api/v1/items/Tag',
@endif
@if(Auth::user()->can('index', \App\Models\Tag::class))         
         createAction: '/api/v1/items/Tag',
@endif
@if(Auth::user()->can('index', \App\Models\Tag::class))         
         updateAction: '/api/v1/items/Tag',
@endif
@if(Auth::user()->can('index', \App\Models\Tag::class))         
         deleteAction: '/api/v1/items/Tag',
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
         },
         usageinformation: {
            type: 'textarea',
            title: '{{ __('Usage information') }}',
            create: false,
            edit: false,
            list: true,
         },
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer"></div>
   
@endsection
