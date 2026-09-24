@php if(Auth::user()->cannot('index', \App\Models\Supplier::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   const supplierCategoryOptions = @php echo(json_encode(\App\Models\SupplierCategory::orderBy('name')->get()->map(function($category){
      return [
         'id' => $category->id,
         'name' => $category->name,
         'description' => $category->description,
      ];
   }))); @endphp;

   function showSupplierCategoriesDialog(record)
   {
      var dialogContent = $('<div />');

      supplierCategoryOptions.forEach(function(category){
         var currentValue = '';
         Object.values(record.supplier_categories || []).forEach(function(existingCategory){
            if(existingCategory.id == category.id)
               currentValue = (null === existingCategory.applicable) ? '' : (existingCategory.applicable ? '1' : '0');
         });

         $('<div class="mb-3" />')
            .append($('<label class="form-label" />')
               .attr('for', 'supplier-category-' + category.id)
               .prop('title', category.description ? category.description : '')
               .text(category.name))
            .append($('<select class="form-select form-select-sm" />')
               .attr('id', 'supplier-category-' + category.id)
               .attr('name', 'supplier_category_assessments[' + category.id + ']')
               .append($('<option value="" />').text('{{ __("Not decided") }}'))
               .append($('<option value="1" />').text('{{ __("Yes") }}'))
               .append($('<option value="0" />').text('{{ __("No") }}'))
               .val(currentValue))
            .appendTo(dialogContent);
      });

      showDialog('{{ __('Supplier categories') }}' + (record.name ? ' - ' + record.name : ''), dialogContent, {
         dismissLabel: translateString('Cancel'),
         actionLabel: translateString('Save'),
         onAction: function(formData){
            var payload = { supplier_category_assessments: {} };

            formData.forEach(function(item){
               var matches = item.name.match(/^supplier_category_assessments\[(\d+)\]$/);
               if(matches)
                  payload.supplier_category_assessments[matches[1]] = ('' === item.value) ? null : ('1' == item.value);
            });

            ajaxPatch('/api/v1/items/Supplier/' + record.id, payload, function(){
               $('#tableContainer').jtable('reloadRow',
                  $('#jtable-body-supplierslist > .jtable-data-row[data-record-key="' + record.id + '"]')
               );
            });
         },
         closeOnAction: true,
      });
   }

   $('#tableContainer').jtable({
      title: '{{ __("Suppliers") }}',
      paging: true,
      searchfield: true,
      tableId: 'supplierslist',
      bootstrap: true,
      accordion: true,
      fieldtabs: [
         {
            'name': '{{ __("General") }}',
            'fields': [
               'id',
               'name',
               'description',
               'responsible_user_id',
               'external_supplier_id',
            ]
         },
            @if(!config('ledningssystemet.disable_gdpr'))
         {
            'name': '{{ __("Data privacy") }}',
            'fields': [
               'dataprocessor',
               'processoragreementdescription',
            ]
         },
         @endif
      ],

      messages: {
         addNewRecord: '{{ __('Create supplier') }}',
      },
      filter: {
@php $tags = \App\Models\Supplier::allUsedTags(); @endphp
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
@php $categories = \App\Models\SupplierCategory::orderBy('name')->get(); @endphp
@if(0 < count($categories))
         supplier_category_id: {
            type: 'select',
            text: '{{ __("Supplier category") }}',
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
                  @foreach($categories as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
@endif
         responsible_user_id: {
            type: 'select',
            text: '{{ __("Responsible user") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
                  @foreach(\App\Models\User::leftJoin('suppliers', 'suppliers.responsible_user_id', '=', 'users.id')->whereNotNull('suppliers.id')->select('users.*')->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showmyonly: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show only my suppliers') }}',
         },
         hidechecked: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Hide items without issues') }}',
         },
@include('components.customproperty', ['classname' => 'App\Models\Supplier', 'showFilter' => true])
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Supplier::class))
         listAction: '/api/v1/items/Supplier',
@endif         
@if(Auth::user()->can('create', \App\Models\Supplier::class))
         createAction: '/api/v1/items/Supplier',
@endif         
@if(Auth::user()->can('update', \App\Models\Supplier::class))
         updateAction: '/api/v1/items/Supplier',
@endif         
@if(Auth::user()->can('delete', \App\Models\Supplier::class))
         deleteAction: '/api/v1/items/Supplier',
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
         tags: showTags('Supplier', $('#tableContainer')),
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            create: true,
            edit: true,
            list: true,

            required: true,
            listClass: 'd-inline-block col-6 col-md-3',
            defaultValue: {{ auth()->user()->id }},
            options: [
               { Value: null, DisplayText: '{{ __("None assigned") }}' },
@foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
@endforeach
            ],
         },
         external_supplier_id: {
            title: '{{ __('External supplier id') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            maxlength: 255,
            listClass: 'd-inline-block col-6 col-md-3',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
         },
@if(!config('ledningssystemet.disable_gdpr'))
         dataprocessor: {
            title: '{{ __('Is data processor') }}',
            create: true,
            edit: true,
            list: true,
            options: {
               0: '{{ __("No") }}',
               1: '{{ __("Yes") }}',
            }
         },
         processoragreementdescription: {
            title: '{{ __('Data processor agreement description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
         },
@endif
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         activities: {
            title: '{{ __('Performed tasks') }}',
            list: true,
            edit: false,
            create: false,
            listClass: 'activities',
            type: 'textarea',
            display: function(data){
               var retobj = $('<span></span>');
               if(data.record.processactivities)
               {
                  for(var i = 0; i < data.record.processactivities.length; i++)
                     retobj.append($('<div />').text(data.record.processactivities[i].name));
               }
               return retobj;
            }
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         assets: {
            title: '{{ __('Provider of assets') }}',
            create: false,
            edit: false,
            list: true,
            type: 'textarea',
            display: function(data){
               var retobj = $('<span></span>');
               if(data.record.assets)
               {
                  for(var i = 0; i < data.record.assets.length; i++)
                     retobj.append($('<div />').text(data.record.assets[i].name));
               }
               return retobj;
            }
         },
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         supplier_categories: {
            title: '{{ __('Supplier categories') }}',
            create: false,
            edit: false,
            list: true,
            type: 'textarea',
            display: function(data){
               var retobj = $('<span></span>');
               if(data.record.supplier_categories)
               {
                  Object.values(data.record.supplier_categories).forEach((category) => {
                     if(category.applicable)
                        retobj.append($('<div />').text(category.name));
                  });
               }
               return retobj;
            }
         },
         @include('components.customproperty', ['classname' => 'App\Models\Supplier'])
         hr3: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         showHistory: showHistoryField('Supplier', $('#tableContainer')),          
         showMessages: showMessagesField('Supplier', $('#tableContainer')),
@if(Gate::allows('index', 'App\\Models\\Risk'))
         showRisks: showRisks('Supplier', $('#tableContainer'),  @if(auth()->user()->can('useai')) true @else false @endif,@if(auth()->user()->can('delete', 'App\\Models\\Risk')) true @else false @endif ),
@endif
@if(Auth::user()->can('index', \App\Models\Finding::class))
         showFindings: showFindings('Supplier', $('#tableContainer')),
@endif
@if(Auth::user()->can('update', \App\Models\Supplier::class))
         suppliercategories: {
            sorting: false,
            edit: false,
            create: false,
            type: 'command',
            footer: true,
            display: function(data){
               var hasUndecidedCategories = false;
               Object.values(data.record.supplier_categories || []).forEach(function(category){
                 if(null === category.applicable)
                    hasUndecidedCategories = true;
               });

               var retobj= $('<a />')
                 .addClass('btn btn-outline-primary btn-sm')
                 .css({cursor: 'pointer'})
                 .text('{{ __('Supplier categories') }}')
                 .append($('<span />')
                    .addClass('material-symbols-rounded' + (hasUndecidedCategories ? ' text-danger' : ''))
                     .text('edit'))
                 .on('click', function(){
                    showSupplierCategoriesDialog(data.record);
                 });
                   
               return retobj;
            }
         },
@endif
         supplier_requirements: {
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
                  .text('{{ __('Evaluation') }}')
                  .prepend($('<span>checklist</span>')
                     .addClass('material-symbols-rounded'));
                     
                  if(sourcedata.record.status && sourcedata.record.status.area && ('evaluation' == sourcedata.record.status.area))
                     retobj.find('span').addClass('text-danger');
                  
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#supplierrequirementstable').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  var supplier_id = sourcedata.record.id;
                  
                  $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Evaluation") }}',
                        tableId: 'supplierrequirementstable',
                        paging: false,
                        bootstrap: true,
                        actions: {
                           listAction: '/api/v1/items/Supplier/'+sourcedata.record.id+'/evaluation',
@if(Auth::user()->can('update', \App\Models\Supplier::class))
                           updateAction: '/api/v1/items/Supplier/'+sourcedata.record.id+'/evaluation',
@endif
                        },
                        messages: {
                           editRecord: '{{ __('Update evaluation') }}',
                        },
                        fields: {
                           name: {
                              title: '{{ __('Name') }}',
                              edit: false,
                              list: true,
                              header: true,
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              edit: true,
                              list: true,
                              listClass: 'd-inline-block col-12 col-md-8',
                              input: function(data){
                                 return $('<div />')
                                    .addClass('jtable-input-label-description')
                                    .text(data.record.description);
                              }
                           },
                           reassessment: {
                              title: '{{ __('Required for reassessment') }}',
                              edit: false,
                              list: true,
                              listClass: 'd-inline-block col-12 col-md-4',
                              options: [
                                 { Value: 0, DisplayText: '{{ __("No") }}' },
                                 { Value: 1, DisplayText: '{{ __("Yes") }}' },
                              ]
                           },
                           hr0: {
                              list: true,
                              edit: false,
                              display: function(data){
                                 return $('<hr />'); 
                              }
                           },
                           evaluated_at: {
                              title: '{{ __("Assessed") }}',
                              list: true,
                              edit: false,
                              listClass: 'd-inline-block col-12 col-md-4',
                              display: function(data)
                              {
                                 var retval = $('<div />')
                                    .text(data.record.evaluated_at ? data.record.evaluated_at + ' {{ __("by") }} ' + data.record.evaluated_by_name : '{{ __("Never")}}');
                                    
                                 if(!data.record.evaluated_at)
                                    retval.addClass('text-danger');
                                   
                                 return retval;
                              }
                           },
                           satisfactory: {
                              title: '{{ __("Fulfills expectations") }}',
                              list: true,
                              edit: true,
                              defaultValue: 1,
                              listClass: 'd-inline-block col-12 col-md-4',
                              options: [
                                 { Value: 0, DisplayText: '{{ __("No") }}' },
                                 { Value: 1, DisplayText: '{{ __("Yes") }}' },
                              ],
                              display: function(data)
                              {
                                 var retval = $('<div />');
                                 
                                 if(null !== data.record.satisfactory)
                                 {
                                    retval.text(data.record.satisfactory ? '{{ __("Yes") }}' : '{{ __("No") }}');
                                    if(!data.record.satisfactory)
                                       retval.addClass('text-warning');
                                 }
                                 
                                 return retval;
                              }
                           },
                           note: {
                              title: '{{ __("Notes") }}',
                              type: 'textarea',
                              list: true,
                              edit: true,
                              listClass: 'd-inline-block col-12 col-md-4',
                           },
                           supplier_id: {
                              edit: true,
                              input: function(data)
                              {
                                 return $('<input type="hidden" name="supplier_id" value="'+supplier_id+'" />');
                              }
                           },
                           requirement_id: {
                              edit: true,
                              input: function(data)
                              {
                                 return $('<input type="hidden" name="requirement_id" value="'+data.record.id+'" />');
                              }
                           },
                        },
                        recordUpdated: function(event, data) {
                           $(event.target).jtable('reload', function(){
                              $('#tableContainer').jtable('reloadProperty', $(event.target).closest('.jtable-data-row'), 'supplier_requirements');                              
                              $('#tableContainer').jtable('reloadRowStatus', $(event.target).closest('.jtable-data-row'));                              
                           });
                        }
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         },             
@if(Auth::user()->can('update', \App\Models\Supplier::class))
         files: showFiles('Supplier', $('#tableContainer'), '{{ csrf_token() }}'),          
@else
         files: showFiles('Supplier', $('#tableContainer')),          
@endif
         forms: showForms('Supplier', $('#tableContainer')),

@if(Auth::user()->can('index', \App\Models\Agreement::class))
         agreements: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            list: true,
            edit: false,
            create: false,
            footer: true,
            display: function (sourcedata) {
               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Agreements') }}')
                  .prepend($('<span>handshake</span>')
                     .addClass('material-symbols-rounded'));
                     
               if(0 < sourcedata.record.agreements.length)
                  retobj.find('.material-symbols-rounded').addClass('text-danger');
      
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#agreementstable').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{ __("Agreements") }}',
                        actions: {
                           listAction: '/api/v1/items/Agreement?supplier_id='+sourcedata.record.id,
@if(Auth::user()->can('update', \App\Models\Agreement::class))
                           createAction: '/api/v1/items/Agreement?supplier_id='+sourcedata.record.id,
                           updateAction: '/api/v1/items/Agreement',
                           deleteAction: '/api/v1/items/Agreement',
@endif
                        },
                        tableId: 'agreementstable',
                        bootstrap: true,
                        accordion: true,
                        fields: {
                           id: {
                              key: true,
                              list: false,
                              edit: false,
                              create: false,
                           },
                           name: {
                              title: '{{ __("Name") }}',
                              list: true,
                              edit: true,
                              create: true,
                              header: true,
                           },
                           description: {
                              title: '{{ __("Description") }}',
                              type: 'textarea',
                              list: true,
                              edit: true,
                              create: true,
                           },
                           responsible_user_id: {
                              title: '{{ __("Responsible") }}',
                              create: true,
                              edit: true,
                              list: true,
                              create: true,
                              required: true,
                              listClass: 'd-inline-block col-6 col-md-3',
                              defaultValue: sourcedata.record.responsible_user_id,
                              options: [
                                 { Value: null, DisplayText: '{{ __("None assigned") }}' },
@foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
                                 { Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
@endforeach
                              ],
                           },
                           startdate: {
                              title: '{{ __("Start date") }}',
                              type: 'date',
                              list: true,
                              edit: true,
                              create: true,
                           },
                           reminderdate: {
                              title: '{{ __("Reminder date") }}',
                              type: 'date',
                              list: true,
                              edit: true,
                              create: true,
                           },
                           enddate: {
                              title: '{{ __("End date") }}',
                              type: 'date',
                              list: true,
                              edit: true,
                              create: true,
                           },
                           files: showFiles('Agreement', $('#tableContainer'), '{{ csrf_token() }}'),          
                        },
                     },function (data) {
                         data.childTable.jtable('load');
                     }
                  );
               });
               
               return retobj;
            },
         },
@endif
         relations: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            list: true,
            edit: false,
            create: false,
            footer: true,
            display: function (sourcedata) {
               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Relations') }}')
                  .prepend($('<span>group</span>')
                     .addClass('material-symbols-rounded'));

               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#relationstable').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }

                  $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{ __("Relations") }}',
                        actions: {
                           listAction: '/api/v1/items/Relation?relation_type=Supplier&relation_id='+sourcedata.record.id,
                           @if(Auth::user()->can('update', \App\Models\Supplier::class))
                           createAction: '/api/v1/items/Relation?relation_type=Supplier&relation_id='+sourcedata.record.id,
                           updateAction: '/api/v1/items/Relation',
                           deleteAction: '/api/v1/items/Relation',
                           @endif
                        },
                        tableId: 'relationstable',
                        bootstrap: true,
                        accordion: false,
                        fields: {
                           id: {
                              key: true,
                              list: false,
                              edit: false,
                              create: false,
                           },
                           name: {
                              title: '{{ __("Name") }}',
                              list: true,
                              edit: true,
                              create: true,
                              header: true,
                              required: true,
                           },
                           description: {
                              title: '{{ __("Description") }}',
                              type: 'textarea',
                              list: true,
                              edit: true,
                              create: true,
                           },
                           email: {
                              title: '{{ __("Email") }}',
                              list: true,
                              edit: true,
                              create: true,
                              required: true,
                           },
                           phone: {
                              title: '{{ __("Phone") }}',
                              list: true,
                              edit: true,
                              create: true,
                           },
                        },
                     },function (data) {
                        data.childTable.jtable('load');
                     }
                  );
               });

               return retobj;
            },
         },
      },
      recordUpdated: function(event, data){
        $(event.target).jtable('reloadRow', data.row);
      },
      rowLoaded: function(event, data){
         if(!data.record.responsible_user_id)
            $('#jtable-body-supplierslist > .jtable-data-row[data-record-key="'+data.record.id+'"] div[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');

         Object.values(data.record.supplier_categories).forEach((category) => {
            if(null === category.applicable)
               $('#jtable-body-supplierslist > .jtable-data-row[data-record-key="'+data.record.id+'"] div[data-jtable-fieldname="supplier_categories"] .jtable-field-label').addClass('text-danger');
         });
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>

<div class="d-flex flex-row flex-row-reverse mb-4 w-100">
   <a class="btn btn-outline-primary btn-sm" href="/api/v1/ReportCentral/Suppliers/0">
      <span class="material-symbols-rounded">download</span>
      {{ __("Export as Excel") }}
   </a>
</div>
<div id="tableContainer"></div>
   
@endsection
