@php if(Auth::user()->cannot('index', \App\Models\Asset::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')
   <style type="text/css">
      span.badge {
         margin: 0 3px 0 3px;
      }

   </style>
   <script>
      $(function () {
         $('#tableContainer').jtable({
            title: '{{ __("Assets") }}',
            paging: true,
            searchfield: true,
            tableId: 'assetslist',
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
                     'site_id',
                     'supplier_id',
                  ]
               },
               {
                  'name': '{{ __("Information security") }}',
                  'fields': [
                     'confidentiality_class_id',
                     'integrity_class_id',
                     'availability_class_id',
                     'mtd',
                     'rpo'
                  ]
               },
            ],
            messages: {
               addNewRecord: '{{ __('Create asset') }}',
            },
            filter: {
               @php $tags = \App\Models\Asset::allUsedTags(); @endphp
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
               process_id: {
                  type: 'select',
                  text: '{{ __("Process") }}',
                  default: 0,
                  options: [
                     {value: 0, text: '{{ __('Show all') }}'},
                        @foreach(App\Models\Process::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        value: {{ $obj->id }}, text: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ]
               },
               @if(\App\Models\Site::count() > 0)
               site_id: {
                  type: 'select',
                  text: '{{ __("Site") }}',
                  default: 0,
                  options: [
                     {value: 0, text: '{{ __('Show all') }}'},
                        @foreach(\App\Models\Site::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
                     @endforeach
                  ]
               },
               @endif
               confidentiality_class_id: {
                  type: 'select',
                  text: '{{ __("Confidentiality class") }}',
                  default: 0,
                  options: [
                     {value: 0, text: '{{ __('Show all') }}'},
                        @foreach(App\Models\ConfidentialityClass::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                     {
                        value: {{ $obj->id }}, text: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ]
               },
               integrity_class_id: {
                  type: 'select',
                  text: '{{ __("Integrity class") }}',
                  default: 0,
                  options: [
                     {value: 0, text: '{{ __('Show all') }}'},
                        @foreach(App\Models\IntegrityClass::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                     {
                        value: {{ $obj->id }}, text: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ]
               },
               availability_class_id: {
                  type: 'select',
                  text: '{{ __("Availability class") }}',
                  default: 0,
                  options: [
                     {value: 0, text: '{{ __('Show all') }}'},
                        @foreach(App\Models\AvailabilityClass::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                     {
                        value: {{ $obj->id }}, text: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ]
               },
               responsible_user_id: {
                  type: 'select',
                  text: '{{ __("Responsible user") }}',
                  default: 0,
                  options: [
                     {value: 0, text: '{{ __('Show all') }}'},
                        @foreach(\App\Models\User::leftJoin('assets', 'assets.responsible_user_id', '=', 'users.id')->whereNotNull('assets.id')->select('users.*')->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
                     {
                        value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
                     @endforeach
                  ]
               },
               showmyonly: {
                  type: 'checkbox',
                  value: '1',
                  checked: false,
                  text: '{{ __('Show only my assets') }}',
               },
               hidechecked: {
                  type: 'checkbox',
                  value: '1',
                  checked: false,
                  text: '{{ __('Hide items without issues') }}',
               },
               @include('components.customproperty', ['classname' => 'App\Models\Asset', 'showFilter' => true])
            },
            actions: {
               @if(Auth::user()->can('index', 'App\\Models\\Asset'))
               listAction: '/api/v1/items/Asset',
               @endif
                  @if(Auth::user()->can('create', 'App\\Models\\Asset'))
               createAction: '/api/v1/items/Asset',
               @endif
                  @if(Auth::user()->can('update', 'App\\Models\\Asset'))
               updateAction: '/api/v1/items/Asset',
               @endif
                  @if(Auth::user()->can('delete', 'App\\Models\\Asset'))
               deleteAction: '/api/v1/items/Asset',
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
               },
               tags: showTags('Asset', $('#tableContainer')),
               responsible_user_id: {
                  title: '{{ __('Responsible') }}',
                  create: true,
                  edit: true,
                  list: true,
                  required: true,
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
               site_id: {
                  title: '{{ __("Site") }}',
                  create: true,
                  edit: true,
                  list: true,
                  listClass: 'd-inline-block col col-12 col-md-4',
                  defaultValue: null,
                  options: [
                     {Value: null, DisplayText: '{{ __("None") }}'},
                        @foreach(\App\Models\Site::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj->id}}, DisplayText: '{{ $obj->name }}'
                     },
                     @endforeach
                  ]
               },
               confidentiality_class_calculated_id: {
                  title: '{{ __('Confidentiality') }}',
                  create: false,
                  edit: false,
                  list: true,
                  listClass: 'd-inline-block col-12 col-md-4',
                  options: [
                     {Value: null, DisplayText: '{{ __("Not classified") }}'},
                        @foreach(App\Models\ConfidentialityClass::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
                     @endforeach
                  ],
               },
               integrity_class_calculated_id: {
                  title: '{{ __('Integrity') }}',
                  create: false,
                  edit: false,
                  list: true,
                  listClass: 'd-inline-block col-12 col-md-4',
                  options: [
                     {Value: null, DisplayText: '{{ __("Not classified") }}'},
                        @foreach(App\Models\IntegrityClass::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
                     @endforeach
                  ],
               },
               availability_class_calculated_id: {
                  title: '{{ __('Availability') }}',
                  create: false,
                  edit: false,
                  list: true,
                  listClass: 'd-inline-block col-12 col-md-4',
                  options: [
                     {Value: null, DisplayText: '{{ __("Not classified") }}'},
                        @foreach(App\Models\AvailabilityClass::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
                     @endforeach
                  ],
               },
               confidentiality_class_id: {
                  title: '{{ __('Confidentiality') }}',
                  create: true,
                  edit: true,
                  list: false,
                  listClass: 'd-inline-block col-12 col-md-4',
                  options: [
                     {Value: null, DisplayText: '{{ __("Inherit") }}'},
                        @foreach(App\Models\ConfidentialityClass::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ],
               },
               integrity_class_id: {
                  title: '{{ __('Integrity') }}',
                  create: true,
                  edit: true,
                  list: false,
                  listClass: 'd-inline-block col-12 col-md-4',
                  options: [
                     {Value: null, DisplayText: '{{ __("Inherit") }}'},
                        @foreach(App\Models\IntegrityClass::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ],
               },
               availability_class_id: {
                  title: '{{ __('Availability') }}',
                  create: true,
                  edit: true,
                  list: false,
                  listClass: 'd-inline-block col-12 col-md-4',
                  options: [
                     {Value: null, DisplayText: '{{ __("Inherit") }}'},
                        @foreach(App\Models\AvailabilityClass::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ],
               },

               hr0: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
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
               mtd: {
                  title: '{{ __('Maximum Tolerable Downtime (hours)') }}',
                  type: 'number',
                  create: true,
                  edit: true,
                  list: false,
               },
               rpo: {
                  title: '{{ __('Recovery Point Objective (hours)') }}',
                  type: 'number',
                  create: true,
                  edit: true,
                  list: false,
               },
               hr1: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     return $('<hr />');
                  }
               },
               informationtypes: {
                  title: '{{ __('Informationtypes') }}',
                  list: true,
                  edit: false,
                  create: false,
                  multiple: true,
                  listClass: 'informationtypes d-inline-block col-12 col-md-6',
                  options: [
                        @foreach(\App\Models\InformationType::orderBy('name')->get()->each->setAppends([]) as $informationType)
                     {
                        Value: {{ $informationType->id }},
                        DisplayText: @php echo(json_encode($informationType->name)); @endphp },
                     @endforeach
                  ],
                  display: function (data) {
                     var retobj = $('<span></span>');
                     if (data.record.informationtypes) {
                        for (var i = 0; i < data.record.informationtypes.length; i++)
                           retobj.append($('<a  />')
                              .attr('href', '/inventory/informationtypes?jtId[informationtypelist]=' + data.record.informationtypes[i].id)
                              .addClass('d-block')
                              .text(data.record.informationtypes[i].name)
                           );
                     }
                     return retobj;
                  }
               },
               processes: {
                  title: '{{ __('Processes') }}',
                  list: true,
                  edit: false,
                  create: false,
                  listClass: 'processes d-inline-block col-12 col-md-6',
                  type: 'textarea',
                  display: function (data) {
                     var retobj = $('<span></span>');
                     if (data.record.processes) {
                        for (var i = 0; i < data.record.processes.length; i++)
                           retobj.append($('<a  />')
                              .attr('href', '/inventory/processes?jtId[processeslist]=' + data.record.processes[i].id)
                              .addClass('d-block')
                              .text(data.record.processes[i].name)
                           );
                     }
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
               @if(!config('ledningssystemet.disable_supplier'))
               supplier_id: {
                  title: '{{ __('Supplier') }}',
                  list: true,
                  edit: true,
                  create: true,
                  listClass: 'suppliers d-inline-block col-12 col-md-6',
                  options: [
                     {Value: null, DisplayText: '{{ __('None') }}'},
                        @foreach(\App\Models\Supplier::orderBy('name')->get()->each->setAppends([]) as $supplier)
                     {
                        Value: {{ $supplier->id }}, DisplayText: @php echo(json_encode($supplier->name)); @endphp },
                     @endforeach
                  ]
               },
               @endif
               dependants: {
                  title: '{{ __('Supported assets') }}',
                  list: true,
                  edit: false,
                  create: false,
                  listClass: 'dependants d-inline-block col-12 col-md-6',
                  type: 'textarea',
                  display: function (data) {
                     var retobj = $('<span></span>');
                     if (data.record.dependant_assets) {
                        for (var i = 0; i < data.record.dependant_assets.length; i++)
                           retobj.append($('<span  />')
                              .addClass('d-block')
                              .text(data.record.dependant_assets[i].name)
                           );
                     }
                     return retobj;
                  }
               },
               @include('components.customproperty', ['classname' => 'App\Models\Asset'])
               hr3: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     return $('<hr />');
                  }
               },
               depgraph: {
                  sorting: false,
                  edit: false,
                  create: false,
                  type: 'command',
                  footer: true,
                  display: function (data) {
                     var retobj = $('<a />')
                        .addClass('btn btn-outline-primary btn-sm')
                        .css({cursor: 'pointer'})
                        .text('{{ __('Show relations') }}')
                        .append($('<span />')
                           .addClass('material-symbols-rounded')
                           .text('family_history'))
                        .on('click', function () {
                           ajaxGet('/api/v1/items/Asset/' + data.record.id + '/dependencyGraph', function (data) {
                              showDialog('{{ __("Asset relations") }}',
                                 $('<div />').html(data),
                                 {modalClass: 'modal-xl'}
                              );
                           });
                        });

                     return retobj;
                  }
               },

               showHistory: showHistoryField('Asset', $('#tableContainer')),
               showMessages: showMessagesField('Asset', $('#tableContainer')),
               @if(Gate::allows('index', 'App\\Models\\Risk'))
               showRisks: showRisks('Asset', $('#tableContainer'), @if(auth()->user()->can('useai')) true
               @else false @endif,
               @if(auth()->user()->can('delete', 'App\\Models\\Risk')) true @else false @endif ),
               @endif
                  @if(Gate::allows('index', 'App\\Models\\Finding'))
               showFindings: showFindings('Asset', $('#tableContainer')),
               @endif
               supportingassets: {
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
                        .text('{{ __('Supporting assets') }}')
                        .prepend($('<span>merge</span>')
                           .addClass('material-symbols-rounded'));

                     retobj.click(function () {
                        if (retobj.closest('.accordion-body').find('#supportingassets-table').length) {
                           $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                           return;
                        }

                        $('#tableContainer').jtable('openChildTable',
                           retobj.closest('.accordion-body').find('.accordion-footer'),
                           {
                              title: '{{__("Supporting assets") }}',
                              tableId: 'supportingassets-table',
                              paging: false,
                              actions: {
                                 listAction: '/api/v1/items/Asset/' + sourcedata.record.id + '/dependantsList',
                                 @if(Gate::allows('update', 'App\\Models\\Asset'))
                                 createAction: '/api/v1/items/Asset/' + sourcedata.record.id + '/dependantsUpdate',
                                 @endif
                              },
                              fields: {
                                 dependant_asset_id: {
                                    type: 'hidden',
                                    defaultValue: sourcedata.record.id,
                                 },
                                 id: {
                                    key: true,
                                    list: false,
                                 },
                                 depending_asset_id: {
                                    title: '{{ __('Supporting asset') }}',
                                    width: '20%',
                                    list: true,
                                    create: true,
                                    edit: true,
                                    required: true,
                                    options: {
                                    @foreach(\App\Models\Asset::orderBy('name')->get()->each->setAppends([]) as $obj)
                                    @php echo($obj->id . ': ' . json_encode($obj->name)); @endphp,
                                    @endforeach
                                 }
                              },
                              description: {
                                 title: '{{ __('Description') }}',
                                 width: '35%',
                                 list: true,
                              },
                              inherit_confidentiality: {
                                 title: '{{ __('Inherit confidentiality') }}',
                                 width: '15%',
                                 defaultValue: 0,
                                 list: true,
                                 options: {
                                    0: '{{ __("No") }}',
                                    1: '{{ __("Yes") }}',
                                 }
                              },
                              inherit_integrity: {
                                 title: '{{ __('Inherit integrity') }}',
                                 width: '15%',
                                 defaultValue: 0,
                                 list: true,
                                 options: {
                                    0: '{{ __("No") }}',
                                    1: '{{ __("Yes") }}',
                                 }
                              },
                              inherit_availability: {
                                 title: '{{ __('Inherit availability') }}',
                                 width: '15%',
                                 defaultValue: 0,
                                 list: true,
                                 options: {
                                    0: '{{ __("No") }}',
                                    1: '{{ __("Yes") }}',
                                 }
                              },
                              @if(Gate::allows('update', 'App\\Models\\Asset'))
                              deleteRow: {
                                 title: '',
                                 width: '1%',
                                 sorting: false,
                                 edit: false,
                                 create: false,
                                 type: 'command',
                                 display: function (data) {
                                    var retobj = $('<button />')
                                       .addClass('')
                                       .css({cursor: 'pointer', border: 'none', background: 'inherit'})
                                       .attr('title', '{{ __("Delete") }}')
                                       .append($('<span />')
                                          .addClass('material-symbols-rounded')
                                          .css({'font-size': '16pt'})
                                          .text('delete'))
                                       .on('click', function () {
                                          confirmDialog('{{ __("Are you sure that you want to delete this record?") }}', '{{ __("This record will be permanently removed") }}', function () {
                                                ajaxCall({
                                                   url: '/api/v1/items/Asset/' + sourcedata.record.id + '/dependantsDelete?id=' + data.record.id,
                                                   method: 'DELETE',
                                                   success: function () {
                                                      $('#jtable-body-assetslist>.jtable-data-row[data-record-key="' + sourcedata.record.id + '"] #supportingassets-table').closest('.jtable-child-table-container').jtable('reload');
                                                   },
                                                })
                                             },
                                             {
                                                warning: true,
                                             }
                                          );
                                       });
                                    return retobj;
                                 }
                              },
                              @endif
                           },
                           formCreated
                     :

                        function (event, data) {
                           $(data.form[0]).find('select#Edit-depending_asset_id option[value="' + sourcedata.record.id + '"]').remove();
                        }

                     ,
                        recordAdded: function (event, data) {
                           $('#jtable-body-assetslist>.jtable-data-row[data-record-key="' + sourcedata.record.id + '"] #supportingassets-table').closest('.jtable-child-table-container').jtable('reload');
                        }
                     ,
                     },

                        function (data) {
                           data.childTable.jtable('load');
                        }

                     )
                        ;
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
                  $('#jtable-body-assetslist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');

               if (data.record.informationtypes.length)
                  $('#jtable-body-assetslist > .jtable-data-row[data-record-key="' + data.record.id + '"] > .accordion-collapse > .accordion-body > .accordion-footer > .jtable-delete-command').remove();

               if (!data.record.confidentiality_class_calculated_id)
                  $('#jtable-body-assetslist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="confidentiality_class_calculated_id"] .jtable-field-label').addClass('text-danger');

               if (!data.record.integrity_class_calculated_id)
                  $('#jtable-body-assetslist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="integrity_class_calculated_id"] .jtable-field-label').addClass('text-danger');

               if (!data.record.availability_class_calculated_id)
                  $('#jtable-body-assetslist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="availability_class_calculated_id"] .jtable-field-label').addClass('text-danger');

               if (data.record.confidentiality_class_calculated_id && !data.record.confidentiality_class_id)
                  $('#jtable-body-assetslist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="confidentiality_class_calculated_id"] .jtable-display-select-option')
                     .css({'text-decoration': 'underline dotted', 'cursor': 'pointer'})
                     .click(function () {
                        ajaxGet('/api/v1/items/Asset/' + data.record.id + '/dependencyGraph?aspect=confidentiality', function (data) {
                           showDialog('{{ __("Classification heritage") }}',
                              $('<div />').html(data),
                              {modalClass: 'modal-xl'}
                           );
                        });
                     });

               if (data.record.integrity_class_calculated_id && !data.record.integrity_class_id)
                  $('#jtable-body-assetslist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="integrity_class_calculated_id"] .jtable-display-select-option')
                     .css({'text-decoration': 'underline dotted', 'cursor': 'pointer'})
                     .click(function () {
                        ajaxGet('/api/v1/items/Asset/' + data.record.id + '/dependencyGraph?aspect=integrity', function (data) {
                           showDialog('{{ __("Integrity heritage") }}',
                              $('<div />').html(data),
                              {modalClass: 'modal-xl'}
                           );
                        });
                     });

               if (data.record.availability_class_calculated_id && !data.record.availability_class_id)
                  $('#jtable-body-assetslist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="availability_class_calculated_id"] .jtable-display-select-option')
                     .css({'text-decoration': 'underline dotted', 'cursor': 'pointer'})
                     .click(function () {
                        ajaxGet('/api/v1/items/Asset/' + data.record.id + '/dependencyGraph?aspect=availability', function (data) {
                           showDialog('{{ __("Availability heritage") }}',
                              $('<div />').html(data),
                              {modalClass: 'modal-xl'}
                           );
                        });
                     });
            },
         });
         $('#tableContainer').jtable('load');
      });
   </script>

   <div class="d-flex flex-row flex-row-reverse mb-4 w-100">
      <a class="btn btn-outline-primary btn-sm" href="/api/v1/ReportCentral/Assets/0">
         <span class="material-symbols-rounded">download</span>
         {{ __("Export as Excel") }}
      </a>
   </div>
   <div id="tableContainer"></div>

@endsection
