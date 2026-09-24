@php if(Auth::user()->cannot('index', \App\Models\Form::class)) abort(403); @endphp
@extends('layouts.master')
@section('container')

<script>
   var formTemplateContexts = {
   @foreach(App\Models\FormTemplate::orderBy('name')->select(['id', 'name', 'context'])->get()->each->setAppends([]) as $obj)
      {{ $obj->id }}: <?php echo(json_encode($obj->context)); ?>,
   @endforeach
   };


$(function(){
   $('#tableContainer').jtable({
      paging: true,
      sorting: false,
      defaultSorting: 'name ASC',
      searchfield: true,
      tableId: 'formstable',
      openChildAsAccordion: true,
      bootstrap: true,
      accordion: true,
      title: '{{ __("Forms") }}',
      messages: {
         addNewRecord: '{{ __('Add new form') }}',
      },
      filter: {
         responsible_user_id: {
            type: 'select',
            text: '{{ __("Responsible user") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
                  @foreach(\App\Models\User::leftJoin('incidents', 'incidents.responsible_user_id', '=', 'users.id')->whereNotNull('incidents.id')->select(['users.id', 'users.name'])->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         context_type: {
            type: 'select',
            text: '{{ __("Context") }}',
            default: '',
            options: [
               { value: '', text: '{{ __('Show all') }}' },
               { value: 'supplier', text: '{{ __('Supplier') }}' },
               { value: 'customer', text: '{{ __('Customer') }}' },
            ]
         },
         hideanswered: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Hide answered') }}',
         },
         hidearchived: {
            type: 'checkbox',
            value: '1',
            checked: true,
            text: '{{ __('Hide archived') }}',
         },
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\Form::class))
         listAction: '/api/v1/items/Form',
@endif         
@if(Auth::user()->can('create', \App\Models\Form::class) && \App\Models\FormTemplate::exists())
         createAction: '/api/v1/items/Form',
@endif         
@if(Auth::user()->can('update', \App\Models\Form::class))
         updateAction: '/api/v1/items/Form',
@endif
@if(Auth::user()->can('delete', \App\Models\Form::class))
         deleteAction: '/api/v1/items/Form',
@endif
      },
      fields: {
         id: {
            key: true,
            list: false,
            create: false,
            edit: false,
         },
         form_template_id: {
            title: '{{ __('Form template') }}',
            create: true,
            edit: false,
            list: true,
            required: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
                  @if(\App\Models\FormTemplate::where('context', 'supplier')->exists())
               {
                  Label: '{{ __("Supplier") }}', Children: [
                        @foreach (\App\Models\FormTemplate::where('context', 'supplier')->orderBy('name')->select(['id', 'name'])->get() as $template)
                     {
                        Value: '{{ $template->id }}', DisplayText: '{{ $template->name }}'
                     },
                     @endforeach
                  ]
               },
                  @endif
                  @if(\App\Models\FormTemplate::where('context', 'customer')->exists())
               {
                  Label: '{{ __("Customer") }}', Children: [
                        @foreach (\App\Models\FormTemplate::where('context', 'customer')->orderBy('name')->select(['id', 'name'])->get() as $template)
                     {
                        Value: '{{ $template->id }}', DisplayText: '{{ $template->name }}'
                     },
                     @endforeach
                  ]
               },
                  @endif
            ]

         },
         context_id: {
            title: '{{ __('Associated object') }}',
            create: true,
            edit: false,
            list: false,
            required: true,
            dependsOn: 'form_template_id',
            options: function (data) {
               let form_template_id = data && data.dependedValues && data.dependedValues.form_template_id ? data.dependedValues.form_template_id : null;
               if (form_template_id == null)
                  return [];

               let context_type = formTemplateContexts[form_template_id];
               let options = [{Value: null, DisplayText: '{{ __("None") }}'}];
               if (context_type == 'supplier') {
                  @foreach(App\Models\Supplier::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
                  options.push({Value: {{ $obj->id }}, DisplayText: '{{ $obj->name }}'});
                  @endforeach
               } else if (context_type == 'customer') {
                  @foreach(App\Models\Customer::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
                  options.push({Value: {{ $obj->id }}, DisplayText: '{{ $obj->name }}'});
                  @endforeach
               }
               return options;
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
            display: function (data) {
               let retval = $('<span />').text(data.record.name);
               if (data.record && data.record.context_object_name)
                  retval.append(' [' + data.record.context_object_name + ']');

               let statusBadgeColor = { bg: 'primary', text: 'white' };

               switch(data.record.state)
               {
                  case 'draft':
                     statusBadgeColor = { bg: 'primary', text: 'white' };
                     break;

                  case 'submitted':
                     statusBadgeColor = { bg: 'warning', text: 'dark' };
                     break;

                  case 'overdue':
                     statusBadgeColor = { bg: 'danger', text: 'white' };
                     break;

                  case 'answered':
                     statusBadgeColor = { bg: 'success', text: 'white' };
                     break;

                  case 'archived':
                     statusBadgeColor = { bg: 'secondary', text: 'white' };
                     break;
               }

               let statusBadge = $('<span />').addClass('badge mx-3 rounded-pill bg-'+statusBadgeColor.bg+' text-'+statusBadgeColor.text).text(data.record.state_pretty);

               retval = $('<div />').append(retval).append(statusBadge);
               return retval;
            }
         },
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            defaultValue: {{ auth()->user()->id }},
            listClass: 'd-inline-block col-12 col-md-8',
            options: [
               {Value: null, DisplayText: '{{ __("None assigned") }}'},
                  @foreach(App\Models\User::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               {
                  Value: {{$obj ->id}}, DisplayText: '{{ $obj->name }}'
               },
               @endforeach
            ]
         },
         due: {
            title: '{{ __('Due date') }}',
            create: true,
            edit: true,
            list: true,
            type: 'date',
            required: true,
            defaultValue: @php echo json_encode(date('Y-m-d', strtotime('+2 week'))); @endphp,
         },
         hr0: {
            title: '',
            create: false,
            edit: false,
            list: true,
            type: 'html',
            display: function(data){
               return '<hr />';
            }
         },
         showForm: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (sourcedata) {
               let skip = false;
               switch(sourcedata.record.state)
               {
                  case 'draft':
                  case 'answered':
                  case 'archived':
                     skip = false;
                     break;
                  default:
                     skip = true;
                     break;
               }

               if(skip)
                  return '';

               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Show form') }}')
                  .prepend($('<span>description</span>')
                     .addClass('material-symbols-rounded'));

               retobj.click(function () {
                  window.displayUserForm(sourcedata.record.id);
               });

               return retobj;
            }
         },
         relations: {
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
                  .text('{{ __('Relations') }}')
                  .prepend($('<span>group</span>')
                     .addClass('material-symbols-rounded'));

               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#relations-table').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }

                  var allowEdit = false;
                  switch(sourcedata.record.state)
                  {
                     case 'draft':
                     case 'submitted':
                     case 'overdue':
                     case 'answered':
                        allowEdit = true;
                        break;
                     case 'archived':
                        allowEdit = false;
                        break;
                  }

                  $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Relations") }}',
                        tableId: 'relations-table',
                        paging: false,
                        actions: {
                           listAction: '/api/v1/items/FormRelation?form_id='+sourcedata.record.id,
                           @if(Auth::user()->can('create', 'App\\Models\\Form'))
                           createAction: allowEdit && '/api/v1/items/FormRelation',
                           @endif
                           @if(Auth::user()->can('delete', 'App\\Models\\Form'))
                           deleteAction: allowEdit && '/api/v1/items/FormRelation',
                           @endif
                        },
                        fields: {
                           form_id: {
                              type: 'hidden',
                              defaultValue: sourcedata.record.id,
                           },
                           id: {
                              key: true,
                              list: false,
                           },
                           relation_true_id: {
                              list: true,
                              edit: false,
                              create: false,
                              listClass: 'd-none',
                              display: function(data) {
                                 return $('<input type="hidden" />').addClass('relation_true_id').val(data.record.relation_id);
                              }
                           },
                           relation_id: {
                              title: '{{ __('Relation') }}',
                              list: true,
                              create: true,
                              edit: true,
                              required: true,
                              options: function(data){
                                 data.clearCache();
                                 let options = [];
                                 let existingOptions = $(retobj).closest('.jtable-data-row').find('table#relations-table').find('input.relation_true_id').map(function(){return parseInt($(this).val());}).get();

                                 sourcedata.record.context_object_relations.forEach(function(relation){
                                    let skip = false;
                                    if(data.source !== 'list')
                                       skip = existingOptions.includes(relation.id);

                                    if(!skip)
                                       options.push({Value: relation.id, DisplayText: relation.name});
                                 });
                                 return options;
                              }
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
         actionbuttons: {
            sorting: false,
            edit: false,
            create: false,
            type: 'command',
            footer: true,
            display: function(data){
               let retval = $('<div class="mx-0"/>');
               if( (data.record.state == 'draft') || (data.record.state == 'answered') ) {
                  retval.append(
                     $('<button class="btn btn-outline-primary btn-sm mx-2" title="{{ __('Activate form') }}"><span class="material-symbols-rounded">play_circle</span>{{ __("Activate form") }}</button>')
                     .click(function(clickevent){
                        confirmDialog('{{ __("Activate form") }}', '{{ __("By activating the form, you will start the process for completion by the associated relation contacts that will be notified of this form") }}', function(){
                           ajaxPost('/api/v1/items/Form/'+data.record.id+'/activate', {}, function(){
                              $(clickevent.target).closest('.jtable-main-container').parent('div').jtable('reload');
                           });
                        });
                     })
                  );
               }

               if( (data.record.state == 'submitted') || (data.record.state == 'overdue') ) {
                  retval.append(
                     $('<button class="btn btn-outline-primary btn-sm" title="{{ __('Recall form') }}"><span class="material-symbols-rounded">stop_circle</span>{{ __("Recall form") }}</button>')
                     .click(function(clickevent){
                        confirmDialog('{{ __("Recall form") }}', '{{ __("By recalling the form, the relation contacts will no longer have access to the form") }}', function(){
                           ajaxPost('/api/v1/items/Form/'+data.record.id+'/recall', {}, function(){
                              $(clickevent.target).closest('.jtable-main-container').parent('div').jtable('reload');
                           });
                        });
                     })
                  );
               }

               if( (data.record.state == 'answered') ) {
                  retval.append(
                     $('<button class="btn btn-outline-primary btn-sm" title="{{ __('Archive form') }}"><span class="material-symbols-rounded">inventory_2</span>{{ __("Archive form") }}</button>')
                     .click(function(clickevent){
                        confirmDialog('{{ __("Archive form") }}', '{{ __("By archiving the form, it will be moved to the archive and no longer be editable") }}', function(){
                           ajaxPost('/api/v1/items/Form/'+data.record.id+'/archive', {}, function(){
                              $(clickevent.target).closest('.jtable-main-container').parent('div').jtable('reload');
                           });
                        });
                     })
                  );
               }

               return retval;
            }
         },

      },
      formCreated: function(event, formdata){
         if('create' != formdata.formType)
            return;

         formdata.form.find('select[name="form_template_id"]').on('change', function(){
            contextClass = formTemplateContexts[formdata.form.find('select[name="form_template_id"]').val()];
            if(contextClass !== null)
               formdata.form.find('select[name="context_id"]').closest('div.jtable-input-field-container').show();
            else
               formdata.form.find('select[name="context_id"]').closest('div.jtable-input-field-container').hide();
         }).change();
      }
   });
   $('#tableContainer').jtable('load');
});

</script>

<div id="tableContainer"></div>

@endsection
