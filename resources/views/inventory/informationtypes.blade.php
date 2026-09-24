@php if(Auth::user()->cannot('index', \App\Models\InformationType::class)) abort(403); @endphp
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
            title: '{{ __("Information types") }}',
            paging: true,
            sorting: false,
            defaultSorting: 'name ASC',
            searchfield: true,
            tableId: 'informationtypelist',
            fieldtabs: [
               {
                  'name': '{{ __("General") }}',
                  'fields': [
                     'id',
                     'name',
                     'description',
                     'responsible_user_id',
                  ]
               },
               {
                  'name': '{{ __("Information security") }}',
                  'fields': [
                     'confidentiality_class_id',
                     'integrity_class_id',
                     'availability_class_id',
                     'retention',
                  ]
               },
                  @if(!config('ledningssystemet.disable_gdpr'))
               {
                  'name': '{{ __("Data privacy") }}',
                  'fields': [
                     'data_subject_categories',
                     'data_categories',
                     'recipient_categories',
                     'piidescription',

                  ]
               }
                  @endif
                  @if(!config('ledningssystemet.disable_archival'))
               {
                  'name': '{{ __("Archival") }}',
                  'fields': [
                     'confidentiality_ground_id',
                     'diary_id',
                     'sortinginformation',
                     'archivingdescription',
                     'archiveshippingtime',
                     'archivemedia',
                  ]
               }
               @endif
            ],
            bootstrap: true,
            accordion: true,
            messages: {
               addNewRecord: '{{ __('Create information type') }}',
            },
            filter: {
               @php $tags = \App\Models\InformationType::allUsedTags(); @endphp
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
                        @foreach(\App\Models\User::leftJoin('information_types', 'information_types.responsible_user_id', '=', 'users.id')->whereNotNull('information_types.id')->select('users.*')->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
                     {
                        value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
                     @endforeach
                  ]
               },
               showmyonly: {
                  type: 'checkbox',
                  value: '1',
                  checked: false,
                  text: '{{ __('Show only my information types') }}',
               },
               hidechecked: {
                  type: 'checkbox',
                  value: '1',
                  checked: false,
                  text: '{{ __('Hide items without issues') }}',
               },
               @include('components.customproperty', ['classname' => 'App\Models\InformationType', 'showFilter' => true])
            },
            actions: {
               @if(request()->user()->can('index', \App\Models\InformationType::class))
               listAction: '/api/v1/items/InformationType',
               @endif
                  @if(request()->user()->can('create', \App\Models\InformationType::class))
               createAction: '/api/v1/items/InformationType',
               @endif
                  @if(request()->user()->can('update', \App\Models\InformationType::class))
               updateAction: '/api/v1/items/InformationType',
               @endif
                  @if(request()->user()->can('delete', \App\Models\InformationType::class))
               deleteAction: '/api/v1/items/InformationType',
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
               tags: showTags('InformationType', $('#tableContainer')),
               responsible_user_id: {
                  title: '{{ __('Responsible') }}',
                  create: true,
                  edit: true,
                  list: true,
                  listClass: 'd-inline-block col-6 col-md-8',
                  defaultValue: {{ auth()->user()->id }},
                  options: [
                     {Value: null, DisplayText: '{{ __("None assigned") }}'},
                        @foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
                     @endforeach
                  ],
               },
               confidentiality_class_id: {
                  title: '{{ __('Confidentiality') }}',
                  create: true,
                  edit: true,
                  list: true,
                  listClass: 'd-inline-block col-12 col-md-4',
                  options: [
                     {Value: null, DisplayText: '{{ __("Not classified") }}'},
                        @foreach(App\Models\ConfidentialityClass::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
                     @endforeach
                  ],
               },
               integrity_class_id: {
                  title: '{{ __('Integrity') }}',
                  create: true,
                  edit: true,
                  list: true,
                  listClass: 'd-inline-block col-12 col-md-4',
                  options: [
                     {Value: null, DisplayText: '{{ __("Not classified") }}'},
                        @foreach(App\Models\IntegrityClass::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
                     @endforeach
                  ],
               },
               availability_class_id: {
                  title: '{{ __('Availability') }}',
                  create: true,
                  edit: true,
                  list: true,
                  listClass: 'd-inline-block col-12 col-md-4',
                  options: [
                     {Value: null, DisplayText: '{{ __("Not classified") }}'},
                        @foreach(App\Models\AvailabilityClass::orderBy('ordinal', 'desc')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
                     @endforeach
                  ],
               },
               retention: {
                  title: '{{ __('Retention time (months)') }}',
                  create: true,
                  edit: true,
                  list: true,
                  type: 'number',
                  listClass: 'd-inline-block col-12 col-md-4',
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
               hr1: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     return $('<hr />');
                  }
               },
               processlist: {
                  title: '{{ __('Processes') }}',
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     var retobj = $('<span></span>');
                     Object.values(data.record.processes).forEach(val => {
                        retobj.append($('<a  />')
                           .attr('href', '/inventory/processes?jtId[processeslist]=' + val.id)
                           .addClass('d-block')
                           .text(val.name)
                        );
                     });
                     return retobj;
                  }
               },
               @if(!config('ledningssystemet.disable_gdpr'))
               data_subject_categories: {
                  title: '{{ __('Data subject categories') }}',
                  list: false,
                  edit: true,
                  create: true,
                  multiple: true,
                  options: [
                        @foreach(App\Models\SubjectCategory::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ],
               },
               data_categories: {
                  title: '{{ __('Data categories') }}',
                  list: false,
                  edit: true,
                  create: true,
                  multiple: true,
                  options: [
                        @foreach(App\Models\DataCategory::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ],
               },
               recipient_categories: {
                  title: '{{ __('Recipient categories') }}',
                  list: false,
                  edit: true,
                  create: true,
                  multiple: true,
                  options: [
                        @foreach(App\Models\RecipientCategory::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ],
               },
               piidescription: {
                  title: '{{ __('Data handling clarification') }}',
                  type: 'textarea',
                  list: false,
                  edit: true,
                  create: true,
               },
               @endif
               hr2: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     return $('<hr />');
                  }
               },
               assets: {
                  title: '{{ __('Assets') }}',
                  list: true,
                  edit: true,
                  create: false,
                  multiple: true,
                  options: [
                        @foreach(App\Models\Asset::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
                     @endforeach
                  ],
                  display: function (data) {
                     var retobj = $('<span></span>');
                     Object.values(data.record.assets).forEach(val => {
                        retobj.append($('<a  />')
                           .attr('href', '/inventory/assets?jtId[assetslist]=' + val.id)
                           .addClass('d-block')
                           .text(val.name)
                        );
                     });
                     return retobj;
                  }
               },
               @if(!config('ledningssystemet.disable_archival'))
               hr3: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     return $('<hr />');
                  }
               },
               confidentiality_ground_id: {
                  title: '{{ __('Confidentiality ground') }}',
                  list: true,
                  edit: true,
                  create: false,
                  listClass: 'd-inline-block col-12 col-md-3',
                  options: [
                     {Value: null, DisplayText: '{{ __("None") }}'},
                        @foreach(App\Models\ConfidentialityGround::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
                     @endforeach
                  ],
               },
               diary_id: {
                  title: '{{ __('Diary') }}',
                  list: true,
                  edit: true,
                  create: true,
                  listClass: 'd-inline-block col-12 col-md-3',
                  options: [
                     {Value: null, DisplayText: '{{ __("Not recorded") }}'},
                        @foreach(App\Models\Diary::orderBy('name')->get()->each->setAppends([]) as $obj)
                     {
                        Value: {{$obj->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
                     @endforeach
                  ],
               },
               sortinginformation: {
                  title: '{{ __('Sorting details') }}',
                  list: true,
                  edit: true,
                  create: true,
                  listClass: 'd-inline-block col-12 col-md-3',
               },
               archivingdescription: {
                  title: '{{ __('Archiving and record keeping details') }}',
                  type: 'textarea',
                  list: true,
                  edit: true,
                  create: true,
                  listClass: 'd-inline-block col-12 col-md-3',
               },
               archiveshippingtime: {
                  title: '{{ __('Years until shipping to central archive') }}',
                  type: 'number',
                  list: true,
                  edit: true,
                  create: true,
                  listClass: 'd-inline-block col-12 col-md-3',
               },
               archivemedia: {
                  title: '{{ __('Archiving media') }}',
                  list: true,
                  edit: true,
                  create: true,
                  listClass: 'd-inline-block col-12 col-md-3',
               },
               @endif
                  @include('components.customproperty', ['classname' => 'App\Models\InformationType'])
               hr4: {
                  list: true,
                  edit: false,
                  create: false,
                  display: function (data) {
                     return $('<hr />');
                  }
               },
               showHistory: showHistoryField('InformationType', $('#tableContainer')),
               showMessages: showMessagesField('InformationType', $('#tableContainer')),
               @if(request()->user()->can('index', \App\Models\Risk::class))
               showRisks: showRisks('InformationType', $('#tableContainer')),
               @endif
            },
            recordUpdated: function (event, data) {
               $(event.target).jtable('reloadRow', data.row);
            },
            rowLoaded: function (event, data) {
               if (!data.record.responsible_user_id)
                  $('#jtable-body-informationtypelist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');

               if (data.record.processes.length)
                  $('#jtable-body-informationtypelist > .jtable-data-row[data-record-key="' + data.record.id + '"] > .accordion-collapse > .accordion-body > .accordion-footer > .jtable-delete-command').remove();

               if (!data.record.confidentiality_class_id)
                  $('#jtable-body-informationtypelist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="confidentiality_class_id"] .jtable-field-label').addClass('text-danger');

               if (!data.record.integrity_class_id)
                  $('#jtable-body-informationtypelist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="integrity_class_id"] .jtable-field-label').addClass('text-danger');

               if (!data.record.availability_class_id)
                  $('#jtable-body-informationtypelist > .jtable-data-row[data-record-key="' + data.record.id + '"] div[data-jtable-fieldname="availability_class_id"] .jtable-field-label').addClass('text-danger');
            },
         });
         $('#tableContainer').jtable('load');
      });
   </script>
   <div class="d-flex flex-row flex-row-reverse mb-4 w-100">
      <a class="btn btn-outline-primary btn-sm" href="/api/v1/ReportCentral/InformationTypes/0">
         <span class="material-symbols-rounded">download</span>
         {{ __("Export as Excel") }}
      </a>
   </div>
   <div id="tableContainer"></div>

@endsection
