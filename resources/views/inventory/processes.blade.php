@php if(Auth::user()->cannot('index', \App\Models\Process::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

   <script>
      $(function () {
         $('#tableContainer').jtable({
            title: '{{ __("Processes") }}',
            paging: true,
            searchfield: true,
            tableId: 'processeslist',
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
                     'department_id',
                     'isstartprocess',
                  ]
               },
                  @if(!config('ledningssystemet.disable_gdpr'))
               {
                  'name': '{{ __("Data privacy") }}',
                  'fields': [
                     'dataprocessor',
                     'legal_basises',
                     'legalbasisdescription',
                     'thirdcountrytransferdescription',
                     'thirdcountrytransferprotectiondescription',
                     'securitymeasuredescription',
                     'data_processor_processing_activities',
                  ]
               }
               @endif
            ],
            messages: {
               addNewRecord: '{{ __('Add process') }}',
            },
            filter: {
               @php $tags = \App\Models\Process::allUsedTags(); @endphp
                  @if(0 < count($tags))
               tag_id: {
                  type: 'select',
                  text: '{{ __("Tag") }}',
                  default: 0,
                  options: [
                     {value: 0, text: '{{ __('Show all') }}'},
                        @foreach($tags as $obj)
                     {
                        value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
                     @endforeach
                  ]
               },
               @endif
               department_id: {
                  type: 'select',
                  text: '{{ __("Department") }}',
                  default: -1,
                  options: [
                     {value: 0, text: '{{ __('Show mine') }}'},
                     {value: -1, text: '{{ __('Show all') }}'},
                        @foreach(\App\Models\Department::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
                     @endforeach
                  ]
               },
               responsible_user_id: {
                  type: 'select',
                  text: '{{ __("Responsible user") }}',
                  default: 0,
                  options: [
                     {value: 0, text: '{{ __('Show all') }}'},
                        @foreach(\App\Models\User::leftJoin('processes', 'processes.responsible_user_id', '=', 'users.id')->whereNotNull('processes.id')->select('users.*')->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
                     {
                        value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
                     @endforeach
                  ]
               },
               showmyonly: {
                  type: 'checkbox',
                  value: '1',
                  checked: false,
                  text: '{{ __('Show only my processes') }}',
               },
               hidechecked: {
                  type: 'checkbox',
                  value: '1',
                  checked: false,
                  text: '{{ __('Hide items without issues') }}',
               },
               @include('components.customproperty', ['classname' => 'App\Models\Process', 'showFilter' => true])
            },
            actions: {
               @if(Auth::user()->can('index', 'App\\Models\\Process'))
               listAction: '/api/v1/items/Process',
               @endif
                  @if(Auth::user()->can('create', 'App\\Models\\Process'))
               createAction: '/api/v1/items/Process',
               @endif
                  @if(Auth::user()->can('update', 'App\\Models\\Process'))
               updateAction: '/api/v1/items/Process',
               @endif
                  @if(Auth::user()->can('delete', 'App\\Models\\Process'))
               deleteAction: '/api/v1/items/Process',
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
               tags: showTags('Process', $('#tableContainer')),
               responsible_user_id: {
                  title: '{{ __('Responsible') }}',
                  create: true,
                  edit: true,
                  list: true,
                  listClass: 'd-inline-block col-6 col-md-4',
                  defaultValue: {{ auth()->user()->id }},
                  options: [
                     {Value: null, DisplayText: '{{ __("None assigned") }}'},
                        @foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
                     @endforeach
                  ],
               },
               department_id: {
                  title: '{{ __('Department') }}',
                  create: true,
                  edit: true,
                  list: true,

                  defaultValue: <?php $userdeps = auth()->user()->int_departments; echo((0 == count($userdeps)) ? '-1' : $userdeps[0]->id); ?>,
                  listClass: 'd-inline-block col-6 col-md-4',
                  options: [
                        @foreach(App\Models\Department::orderBy('name')->get()->each->setAppends([]) as $department)
                     {
                        Value: {{$department->id}}, DisplayText: '{{ $department->name }}'
                     },
                     @endforeach
                  ]
               },
               description: {
                  title: '{{ __('Description') }}',
                  type: 'textarea',
                  create: true,
                  edit: true,
                  list: true,
                  required: false,
               },
               hr0: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     return $('<hr />');
                  }
               },
               isstartprocess: {
                  title: '{{ __('Is start process?') }}',
                  list: false,
                  create: true,
                  edit: true,
                  options: {
                     0: '{{ __("No") }}',
                     1: '{{ __("Yes") }}',
                  }
               },
               @if(!config('ledningssystemet.disable_gdpr'))
               dataprocessor: {
                  title: '{{ __('Is data processor for customers') }}',
                  list: false,
                  create: true,
                  edit: true,
                  options: {
                     0: '{{ __("No") }}',
                     1: '{{ __("Yes") }}',
                  }
               },
               legal_basises: {
                  title: '{{ __('Legal basises') }}',
                  list: false,
                  create: true,
                  edit: true,
                  multiple: true,
                  options: [
                        @foreach(App\Models\LegalBasis::orderBy('name')->get()->each->setAppends([]) as $legalBasis)
                     {
                        Value: {{ $legalBasis->id }}, DisplayText: @php echo(json_encode($legalBasis->name)); @endphp },
                     @endforeach
                  ]
               },
               legalbasisdescription: {
                  title: '{{ __('Legal basis explanation') }}',
                  type: 'textarea',
                  list: false,
                  create: true,
                  edit: true,
               },
               thirdcountrytransferdescription: {
                  title: '{{ __('Third country transfers') }}',
                  type: 'textarea',
                  list: false,
                  create: true,
                  edit: true,
               },
               thirdcountrytransferprotectiondescription: {
                  title: '{{ __('Protection of third country transfers') }}',
                  type: 'textarea',
                  list: false,
                  create: false,
                  edit: true,
               },
               securitymeasuredescription: {
                  title: '{{ __('Technical and organisational security measures') }}',
                  type: 'textarea',
                  list: false,
                  create: true,
                  edit: true,
               },
               data_processor_processing_activities: {
                  title: '{{ __('Data processor processing activities') }}',
                  type: 'textarea',
                  list: false,
                  create: true,
                  edit: true,
               },
               @endif
               linkingprocesses: {
                  title: '{{ __("Processes linking to this") }}',
                  type: 'textarea',
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     var retobj = $('<span />')
                        .addClass('jtable-cell-content');

                     Object.values(data.record.process_linkingprocesses).forEach((key) => {
                        retobj.append($('<span />')
                           .css('display', 'block')
                           .text(key.name)
                        );
                     });
                     return retobj;
                  }
               },
               hr1: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     return $('<hr />');
                  }
               },
               informationtypeslist: {
                  title: '{{ __('Information types') }}',
                  list: true,
                  edit: false,
                  create: false,
                  type: 'textarea',
                  display: function (data) {
                     var retobj = $('<span/>')
                        .addClass('jtable-cell-content');

                     Object.values(data.record.process_informationtypes).forEach(val => {
                        retobj.append($('<a  />')
                           .attr('href', '/inventory/informationtypes?jtId[informationtypelist]=' + val.id)
                           .addClass('d-block')
                           .text(val.name)
                        );
                     });
                     return retobj;
                  }
               },
               hr2: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     return $('<hr />');
                  }
               },
               assetslist: {
                  title: '{{ __('Assets') }}',
                  list: true,
                  edit: false,
                  create: false,
                  type: 'textarea',
                  display: function (data) {
                     var retobj = $('<span/>')
                        .addClass('jtable-cell-content');

                     Object.values(data.record.process_assets).forEach(val => {
                        retobj.append($('<a  />')
                           .attr('href', '/inventory/assets?jtId[assetslist]=' + val.id)
                           .addClass('d-block')
                           .text(val.name)
                        );
                     });

                     return retobj;
                  }
               },
               @include('components.customproperty', ['classname' => 'App\Models\Process'])
               hr3: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     return $('<hr />');
                  }
               },
               processactivities: {
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
                        .text('{{ __('Tasks') }}')
                        .prepend($('<span>event_list</span>')
                           .addClass('material-symbols-rounded'));

                     retobj.click(function () {
                        if (retobj.closest('.accordion-body').find('#activities-table').length) {
                           $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                           return;
                        }

                        $('#tableContainer').jtable('openChildTable',
                           retobj.closest('.accordion-body').find('.accordion-footer'),
                           {
                              title: '{{__("Tasks") }}',
                              tableId: 'activities-table',
                              paging: false,
                              actions: {
                                 listAction: '/api/v1/items/ProcessActivity?process_id=' + sourcedata.record.id,
                                 @if(Gate::allows('update', 'App\\Models\\ProcessActivity'))
                                 updateAction: '/api/v1/items/ProcessActivity',
                                 @endif
                              },
                              fields: {
                                 process_id: {
                                    type: 'hidden',
                                    defaultValue: sourcedata.record.id,
                                 },
                                 id: {
                                    key: true,
                                    list: false,
                                 },
                                 name: {
                                    title: '{{ __('Name') }}',
                                    width: '15%',
                                    list: true,
                                 },
                                 description: {
                                    title: '{{ __('Description') }}',
                                    width: '25%',
                                    list: true,
                                    edit: true,
                                    type: 'textarea',
                                 },
                                 @if(!config('ledningssystemet.disable_staff'))
                                 accountable_role_id: {
                                    title: '{{ __('Accountable role') }}',
                                    width: '20%',
                                    list: true,
                                    edit: true,
                                    options: [
                                       {Value: null, DisplayText: '{{ __("None") }}'},
                                          @foreach(\App\Models\Role::orderBy('name')->get()->each->setAppends([]) as $role)
                                       {
                                          Value: {{ $role->id }},
                                          DisplayText: @php echo(json_encode($role->name)); @endphp },
                                       @endforeach
                                    ],
                                    display: function (data) {
                                       var retobj = $('<span></span>')
                                          .text(data.record.accountable_role_name);

                                       if (!data.record.accountable_role_id)
                                          retobj.addClass('not-applicable badge rounded-pill text-bg-warning w-100').text('?');

                                       return retobj;
                                    }
                                 },
                                 responsible_role_id: {
                                    title: '{{ __('Responsible role') }}',
                                    width: '20%',
                                    list: true,
                                    edit: true,
                                    options: [
                                       {Value: null, DisplayText: '{{ __("None") }}'},
                                          @foreach(\App\Models\Role::orderBy('name')->get()->each->setAppends([]) as $role)
                                       {
                                          Value: {{ $role->id }},
                                          DisplayText: @php echo(json_encode($role->name)); @endphp },
                                       @endforeach
                                    ],
                                    display: function (data) {
                                       var retobj = $('<span></span>')
                                          .text(data.record.responsible_role_name);

                                       if (!data.record.responsible_role_id && !data.record.process_activity_suppliers.length)
                                          retobj.addClass('not-applicable badge rounded-pill text-bg-warning w-100').text('?');

                                       return retobj;
                                    }
                                 },
                                 @endif
                                    @if(!config('ledningssystemet.disable_supplier'))
                                 process_activity_suppliers: {
                                    title: '{{ __('Outsourcing supplier(s)') }}',
                                    width: '20%',
                                    list: true,
                                    edit: true,
                                    create: false,
                                    multiple: true,
                                    options: [
                                          @foreach(\App\Models\Supplier::orderBy('name')->get()->each->setAppends([]) as $supplier)
                                       {
                                          Value: {{ $supplier->id }},
                                          DisplayText: @php echo(json_encode($supplier->name)); @endphp },
                                       @endforeach
                                    ],
                                    display: function (data) {
                                       var textcontent = '';

                                       Object.values(data.record.process_activity_suppliers).forEach(obj => {
                                          textcontent += (('' != textcontent) ? "\r\n" : "") + obj.name;
                                       });

                                       var retobj = $('<span></span>')
                                          .text(textcontent);

                                       if (!data.record.responsible_role_id && !data.record.process_activity_suppliers.length)
                                          retobj.addClass('not-applicable badge rounded-pill text-bg-warning w-100').text('?');

                                       return retobj;
                                    }
                                 },
                                 @endif
                                 @include('components.customproperty', ['classname' => 'App\Models\ProcessActivity'])
                              },
                              recordUpdated: function (event, data) {
                                 $(event.target).jtable('reload');
                                 $('#tableContainer').jtable('reloadRow',
                                    $('#jtable-body-processeslist > .jtable-data-row[data-record-key="' + sourcedata.record.id + '"]')
                                 );
                              }
                           }, function (data) {
                              data.childTable.jtable('load');
                           }
                        );
                     });

                     return retobj;
                  }
               },
               @if(Gate::allows('update', 'App\\Models\\Process'))
               editprocess: {
                  sorting: false,
                  edit: false,
                  create: false,
                  type: 'command',
                  footer: true,
                  display: function (data) {
                     return '<a class="btn btn-outline-primary btn-sm" href="processedit/' + data.record.id + '" title="{{ __('Edit process chart') }}"><span class="material-symbols-rounded">draw</span>{{ __("Edit process chart") }}</a>';
                  }
               },
               @endif
               showHistory: showHistoryField('Process', $('#tableContainer')),
               showMessages: showMessagesField('Process', $('#tableContainer')),
               @if(Gate::allows('index', 'App\\Models\\Risk'))
               showRisks: showRisks('Process', $('#tableContainer')),
               @endif
                  @if(Gate::allows('index', 'App\\Models\\Finding'))
               showFindings: showFindings('Process', $('#tableContainer')),
               @endif
               processhrefs: {
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
                        .text('{{ __('Links') }}')
                        .prepend($('<span>link</span>')
                           .addClass('material-symbols-rounded'));

                     retobj.click(function () {
                        if (retobj.closest('.accordion-body').find('#links-table').length) {
                           $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                           return;
                        }

                        $('#tableContainer').jtable('openChildTable',
                           retobj.closest('.accordion-body').find('.accordion-footer'),
                           {
                              title: '{{__("Links") }}',
                              tableId: 'links-table',
                              paging: false,
                              actions: {
                                 @if(Auth::user()->can('index', 'App\\Models\\ProcessHref'))
                                 listAction: '/api/v1/items/ProcessHref?process_id=' + sourcedata.record.id,
                                 @endif
                                    @if(Auth::user()->can('create', 'App\\Models\\ProcessHref'))
                                 createAction: '/api/v1/items/ProcessHref',
                                 @endif
                                    @if(Auth::user()->can('update', 'App\\Models\\ProcessHref'))
                                 updateAction: '/api/v1/items/ProcessHref',
                                 @endif
                                    @if(Auth::user()->can('delete', 'App\\Models\\ProcessHref'))
                                 deleteAction: '/api/v1/items/ProcessHref',
                                 @endif
                              },
                              fields: {
                                 process_id: {
                                    type: 'hidden',
                                    defaultValue: sourcedata.record.id,
                                 },
                                 id: {
                                    key: true,
                                    list: false,
                                 },
                                 name: {
                                    title: '{{ __('Name') }}',
                                    width: '20%',
                                    required: true,
                                    maxlength: 255,
                                    list: true,
                                    create: true,
                                    edit: true,
                                 },
                                 description: {
                                    title: '{{ __('Description') }}',
                                    width: '30%',
                                    type: 'textarea',
                                    list: true,
                                    create: true,
                                    edit: true,
                                 },
                                 url: {
                                    title: '{{ __('URL') }}',
                                    width: '30%',
                                    required: true,
                                    maxlength: 255,
                                    list: true,
                                    create: true,
                                    edit: true,
                                 },
                                 blank: {
                                    title: '{{ __('Target') }}',
                                    list: true,
                                    create: true,
                                    edit: true,
                                    options: [
                                       {Value: 0, DisplayText: '{{ __("Same window") }}'},
                                       {Value: 1, DisplayText: '{{ __("New window") }}'},
                                    ],
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
            recordUpdated: function (event, data) {
               $(event.target).jtable('reloadRow', data.row);
            },
            rowLoaded: function (event, data) {
               if (!data.record.responsible_user_id)
                  $('#jtable-body-processeslist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');

               if (data.record.unclassifiedcount || data.record.undecidedcount)
                  $('#jtable-body-processeslist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="processactivities"] .material-symbols-rounded').addClass('text-danger');

            },
         });
         $('#tableContainer').jtable('load');
      });
   </script>
   <div id="tableContainer"></div>

@endsection
