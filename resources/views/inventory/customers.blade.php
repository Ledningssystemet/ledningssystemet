@php if(Auth::user()->cannot('index', \App\Models\Customer::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Customers") }}',
      paging: true,
      searchfield: true,
      tableId: 'customerslist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Create customer') }}',
      },
      fieldtabs: [
         {
            'name': '{{ __("General") }}',
            'fields': [
               'id',
               'name',
               'legal_reg',
               'description',
               'responsible_user_id',
               'external_customer_id',
            ]
         },
            @if(!config('ledningssystemet.disable_gdpr'))
         {
            'name': '{{ __("Data privacy") }}',
            'fields': [
               'dpo_name',
               'dpo_email',
               'processes',
            ]
         },
         @endif
      ],
      filter: {
@php $tags = \App\Models\Customer::allUsedTags(); @endphp
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
         responsible_user_id: {
            type: 'select',
            text: '{{ __("Responsible user") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
                  @foreach(\App\Models\User::leftJoin('customers', 'customers.responsible_user_id', '=', 'users.id')->whereNotNull('customers.id')->select('users.*')->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showmyonly: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show only my customers') }}',
         },
         hidechecked: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Hide items without issues') }}',
         },
@include('components.customproperty', ['classname' => 'App\Models\Customer', 'showFilter' => true])
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Customer::class))
         listAction: '/api/v1/items/Customer',
@endif         
@if(Auth::user()->can('create', \App\Models\Customer::class))
         createAction: '/api/v1/items/Customer',
@endif         
@if(Auth::user()->can('update', \App\Models\Customer::class))
         updateAction: '/api/v1/items/Customer',
@endif         
@if(Auth::user()->can('delete', \App\Models\Customer::class))
         deleteAction: '/api/v1/items/Customer',
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
         tags: showTags('Customer', $('#tableContainer')),
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
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         legal_reg: {
            title: '{{ __('Legal registration number') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            maxlength: 255,
            listClass: 'd-inline-block col-12 col-md-4',
         },
         ext_id: {
            title: '{{ __('External customer id') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            maxlength: 255,
            listClass: 'd-inline-block col-12 col-md-8',
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
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
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         processes: {
            title: '{{ __('Data processor processes') }}',
            list: true,
            edit: true,
            create: true,
            multiple: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
@foreach(App\Models\Process::where('dataprocessor', 1)->orderBy('name')->get()->each->setAppends([]) as $process)
               { Value: {{ $process->id }}, DisplayText: @php echo(json_encode($process->name)); @endphp },
@endforeach
            ]
         },
         dpo_name: {
            title: '{{ __('Data protection contact name') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            maxlength: 255,
            listClass: 'd-inline-block col-12 col-md-4',
         },
         dpo_email: {
            title: '{{ __('Data protection contact email') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            maxlength: 255,
            listClass: 'd-inline-block col-12 col-md-4',
            input: function(data){
               return $('<input type="email" name="dpo_email" maxlength="255" class="form-control" />')
                  .val((data.record && data.record.dpo_email) ? data.record.dpo_email : '');
            }
         },
@endif
@include('components.customproperty', ['classname' => 'App\Models\Customer'])
         hr3: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         showHistory: showHistoryField('Customer', $('#tableContainer')),          
         showMessages: showMessagesField('Customer', $('#tableContainer')),    
@if(Auth::user()->can('index', \App\Models\Risk::class))
         showRisks: showRisks('Customer', $('#tableContainer')),
@endif
@if(Auth::user()->can('index', \App\Models\Finding::class))
         showFindings: showFindings('Customer', $('#tableContainer')),
@endif
@if(Auth::user()->can('update', \App\Models\Customer::class))
         files: showFiles('Customer', $('#tableContainer'), '{{ csrf_token() }}'),          
@else
         files: showFiles('Customer', $('#tableContainer')),          
@endif
         forms: showForms('Customer', $('#tableContainer')),
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
                           listAction: '/api/v1/items/Agreement?customer_id='+sourcedata.record.id,
@if(Auth::user()->can('update', \App\Models\Agreement::class))
                           createAction: '/api/v1/items/Agreement?customer_id='+sourcedata.record.id,
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
                           listAction: '/api/v1/items/Relation?relation_type=Customer&relation_id='+sourcedata.record.id,
                           @if(Auth::user()->can('update', \App\Models\Customer::class))
                           createAction: '/api/v1/items/Relation?relation_type=Customer&relation_id='+sourcedata.record.id,
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
            $('#jtable-body-customerslist > .jtable-data-row[data-record-key="'+data.record.id+'"] div[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>

<div id="tableContainer"></div>
   
@endsection
