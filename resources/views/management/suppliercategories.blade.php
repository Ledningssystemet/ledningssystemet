@php if(config('ledningssystemet.disable_supplier')) abort(404); @endphp
@php if(!Auth::user()->canAny(['managementtools.edit'])) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>

$(function(){
   $('#supplierCategoryContainer').jtable({
      title: '{{ __("Supplier categories") }}',
      bootstrap: true,
      accordion: true,
      searchfield: true,
      messages: {
         addNewRecord: '{{ __('Add new supplier category') }}',
      },
      actions: {
         listAction: '/api/v1/items/SupplierCategory',
         createAction: '/api/v1/items/SupplierCategory',
         updateAction: '/api/v1/items/SupplierCategory',
         deleteAction: '/api/v1/items/SupplierCategory',
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
         require_assessment: {
            title: '{{ __('Require assessment of existing suppliers') }}',
            create: true,
            edit: false,
            list: false,
            defaultValue: 0,
            options: [
               { Value: 0, DisplayText: '{{ __("No") }}' },
               { Value: 1, DisplayText: '{{ __("Yes") }}' },
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
         reassessment: {
            title: '{{ __('Reassessment interval') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            defaultValue: 'never',
            options: [
@foreach(array_keys(\Ledningssystemet\Ledningssystemet\Models\SupplierCategory::getIntervals()) as $objkey)
               { Value: '{{ $objkey }}', DisplayText: '{{ \Ledningssystemet\Ledningssystemet\Models\SupplierCategory::getIntervals()[$objkey]["text"] }}' },
@endforeach
            ]
         },
         requirements: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (sourcedata) {
               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Requirements') }}')
                  .prepend($('<span>checklist</span>')
                     .addClass('material-symbols-rounded'));
                  
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#supplierrequirementstable').length)
                  {
                     $('#supplierCategoryContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                  $('#supplierCategoryContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Requirements") }}',
                        tableId: 'supplierrequirementstable',
                        paging: false,
                        actions: {
                           listAction: '/api/v1/items/SupplierRequirement?supplier_category_id='+sourcedata.record.id,
                           updateAction: '/api/v1/items/SupplierRequirement',
                           createAction: '/api/v1/items/SupplierRequirement',
                           deleteAction: '/api/v1/items/SupplierRequirement',
                        },
                        fields: {
                           supplier_category_id: {
                              type: 'hidden',
                              create: true,
                              defaultValue: sourcedata.record.id,
                           },
                           id: {
                              key: true,
                              list: false,
                           },
                           name: {
                              title: '{{ __('Name') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                              maxlength: 255,
                              width: '30%',
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                              width: '55%',
                              required: true,
                           },
                           reassessment: {
                              title: '{{ __('Included in reassessment') }}',
                              create: true,
                              edit: true,
                              list: true,
                              width: '15%',
                              required: true,
                              defaultValue: 0,
                              options: [
                                 { Value: 0, DisplayText: '{{ __("No") }}' },
                                 { Value: 1, DisplayText: '{{ __("Yes") }}' },
                              ]
                           },
                        },
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         },      
      },
   });
   $('#supplierCategoryContainer').jtable('load');          
});
</script>

<div id="supplierCategoryContainer"></div>
@endsection
