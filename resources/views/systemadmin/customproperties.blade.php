@php if(auth()->user()->cannot('index', \App\Models\CustomProperty::class)) abort(403); @endphp
<?php
   $contexts = [];
   foreach(App\Models\CustomProperty::getContexts() as $context) {
      // Strip off the "App\Models\" prefix for easier handling in the view
      $contexts[] = substr($context, strlen("App\\Models\\"));
   }
?>
@extends('layouts.master')
@section('container')

<script>
$(function(){

@foreach($contexts as $context)
   $('#tableContainer_{{ $context }}').jtable({
      title: '',
      tableId: 'custompropertieslist',
      messages: {
         addNewRecord: '{{ __('Add new custom property') }}',
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\CustomProperty::class))
         listAction: '/api/v1/items/CustomProperty?context={{ urlencode('App\\Models\\'.$context) }}',
@endif
@if(Auth::user()->can('create', \App\Models\CustomProperty::class))
         createAction: '/api/v1/items/CustomProperty?context={{ urlencode('App\\Models\\'.$context) }}',
@endif
@if(Auth::user()->can('update', \App\Models\CustomProperty::class))
         updateAction: '/api/v1/items/CustomProperty',
@endif
@if(Auth::user()->can('delete', \App\Models\CustomProperty::class))
         deleteAction: '/api/v1/items/CustomProperty',
@endif
@if(Auth::user()->can('update', \App\Models\CustomProperty::class))
         reorderAction: '/api/v1/items/CustomProperty',
@endif
      },
      fields: {
         id: {
            title: 'ID',
            key: true,
            list: true,
            create: false,
            edit: false,
            display: function(data){
               return 'customproperty_'+data.record.id;
            }
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
         type: {
            title: '{{ __('Type') }}',
            create: true,
            edit: false,
            list: true,
            required: true,
            options: [
               { Value: 'string', DisplayText: '{{ __('String (max 255 characters)') }}' },
               { Value: 'textarea', DisplayText: '{{ __('Multiline text') }}' },
               { Value: 'boolean', DisplayText: '{{ __('Yes/No') }}' },
               { Value: 'user', DisplayText: '{{ __('User') }}' },
               { Value: 'department', DisplayText: '{{ __('Department') }}' },
               { Value: 'supplier', DisplayText: '{{ __('Supplier') }}' },
               { Value: 'customer', DisplayText: '{{ __('Customer') }}' },
               { Value: 'asset', DisplayText: '{{ __('Asset') }}' },
               { Value: 'process', DisplayText: '{{ __('Process') }}' },
            ]
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
         },
         display_on_card: {
            title: '{{ __('Display on card') }}',
            create: true,
            edit: true,
            list: true,
            defaultValue: 1,
            options: [
               { Value: 0, DisplayText: '{{ __("No") }}' },
               { Value: 1, DisplayText: '{{ __("Yes") }}' },
            ]
         },
         user_editable: {
            title: '{{ __('User editable') }}',
            create: true,
            edit: true,
            list: true,
            defaultValue: 1,
            options: [
               { Value: 0, DisplayText: '{{ __("No") }}' },
               { Value: 1, DisplayText: '{{ __("Yes") }}' },
            ]
         },
         required: {
            title: '{{ __('Required') }}',
            create: true,
            edit: true,
            list: true,
            defaultValue: 0,
            options: [
               { Value: 0, DisplayText: '{{ __("No") }}' },
               { Value: 1, DisplayText: '{{ __("Yes") }}' },
            ]
         },
      },
   });
   $('#tableContainer_{{ $context }}').jtable('load');
@endforeach
});
</script>

<ul class="nav nav-tabs" id="pageTabs" role="tablist">
<?php $idx = 0; ?>
@foreach($contexts as $context)
<?php $classname = "App\\Models\\" . $context; ?>
   <li class="nav-item" role="presentation">
      <button class="nav-link@php echo(($idx++ == 0) ? " active" : ""); @endphp" id="tab_{{ $context }}" data-bs-toggle="tab" data-bs-target="#tab_{{ $context }}_content" type="button" role="tab" aria-controls="tab_{{ $context }}_content" aria-selected="true">{{ $classname::getPrettyName(true) }}</button>
   </li>
@endforeach
</ul>

<div class="tab-content" id="tabContents">
<?php $idx = 0; ?>
@foreach($contexts as $context)
   <div class="tab-pane fade show@php echo(($idx++ == 0) ? " active" : ""); @endphp" id="tab_{{ $context }}_content" role="tabpanel" aria-labelledby="tab_{{ $context }}">
      <div id="tableContainer_{{ $context }}"></div>
   </div>
@endforeach
</div>


@endsection
