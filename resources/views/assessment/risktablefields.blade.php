      fields: {
         name_pretty: {
            title: '{{ __('Name') }}',
            create: false,
            edit: false,
            list: true,
            header: true,
            display: function(data){
               var retval = $('<span />').text(data.record.name_pretty);
               if(data.record.risk_level)
               {
                  retval.append($('<span></span>')
                     .addClass('badge rounded-pill')
                     .css({'background-color': '#'+data.record.risk_level.color+'bb',
                     'margin-left': '10px'})
                     .text(data.record.risk_level.name)
                  );
               }

               return retval;
            },
         },
         name: {
            title: '{{ __('Name') }}',
            create: true,
            edit: true,
            list: false,
            required: true,
            maxlength: 255,
            tooltip: '{{ __("Note") }}: {{ __("If you write {name} it will be replaced with the actual name of the associated object, and will reflect any changes to the object name when viewed or exported.") }}',
         },
         partnerinfo: {
            list: true,
            create: false,
            edit: false,
            listClass: 'd-inline-block col-12',
            display: function(data){
               if(data.record.partner_id)
                  return $('<div />')
                     .css({'font-size': '10pt', 'font-style': 'italic'})
                     .text('{{ __("This risk is provided by") }} '+data.record.partner_name);
               else
                  return '';
            },
         },
         tags: showTags('Risk', $('#tableContainer')),
         id: {
            title: '{{ __('ID') }}',
            key: true,
            list: true,
            create: false,
            edit: false,
            listClass: 'd-inline-block col-6 col-md-4',
            display: function(data){
               return 'RISK-'+data.record.id;
            }
         },
         updatedts: {
            title: '{{ __('Updated') }}',
            list: true,
            edit: false,
            create: false,
            listClass: 'd-inline-block col-6 col-md-4',
         },
         context: {
            title: '{{ __('Assessment object') }}',
            list: true,
            edit: true,

            create: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
               { Label: '{{ __("Corporate") }}', Children: [{Value: null, DisplayText: '{{ config("ledningssystemet.company_name", __("Company")) }}' }]},
<?php
   // Sites
   echo("{ Label: '".__('Site')."', Children: [\r\n");
   foreach(\App\Models\Site::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
      echo("{ Value: 'Site_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
   echo("]},\r\n");

   // Customer
   echo("{ Label: '".__('Customer')."', Children: [\r\n");
   foreach(\App\Models\Customer::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
      echo("{ Value: 'Customer_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
   echo("]},\r\n");

   // Departments
   echo("{ Label: '".__('Department')."', Children: [\r\n");
   foreach(\App\Models\Department::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
      echo("{ Value: 'Department_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
   echo("]},\r\n");

   // Processes
   echo("{ Label: '".__('Process')."', Children: [\r\n");
   foreach(\App\Models\Process::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
 	 echo("{ Value: 'Process_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
   echo("]},\r\n");

   // Assets
   echo("{ Label: '".__('Asset')."', Children: [\r\n");
   foreach(\App\Models\Asset::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
      echo("{ Value: 'Asset_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
   echo("]},\r\n");

   // Information types
   echo("{ Label: '".__('Information type')."', Children: [\r\n");
   foreach(\App\Models\InformationType::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
      echo("{ Value: 'InformationType_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
   echo("]},\r\n");

   if(!config('ledningssystemet.disable_supplier'))
   {
      // Suppliers
      echo("{ Label: '".__('Supplier')."', Children: [\r\n");
      foreach(\App\Models\Supplier::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
         echo("{ Value: 'Supplier_".$obj->id."', DisplayText: ".json_encode($obj->name)." },\r\n");
      echo("]},\r\n");
   }

   // Process activities
   echo("{ Label: '".__('Tasks')."', Children: [\r\n");
   foreach(\App\Models\ProcessActivity::with('int_process')->orderBy('process_id')->orderBy('ordinal')->get() as $obj)
      echo("{ Value: 'ProcessActivity_".$obj->id."', DisplayText: ".json_encode($obj->int_process->name.'/'.$obj->name)." },\r\n");
   echo("]},\r\n");
?>
            ]
         },
         department_id: {
            title: '{{ __('Department') }}',
            create: true,
            edit: true,
            list: true,

            required: true,
            listClass: 'd-inline-block col-12 col-md-4',
            defaultValue: <?php $userdeps = auth()->user()->int_departments()->get(); echo((0 == count($userdeps)) ? '-1' : $userdeps[0]->id); ?>,
            options: [
@foreach(App\Models\Department::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { Value: {{$obj->id}}, DisplayText: <?php echo(json_encode($obj->name)); ?> },
@endforeach
            ]
         },
         riskowner_id: {
            title: '{{ __('Risk owner') }}',
            create: true,
            edit: true,

            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            defaultValue: {{ auth()->user()->id }},
            options: [
@foreach(App\Models\User::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
   @if(null != $obj->risklevel())
               { Value: {{$obj->id}}, DisplayText: <?php echo(json_encode($obj->name)); ?> },
   @endif
@endforeach
            ]
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
         scenariodescription: {
            title: '{{ __('Risk scenario') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: false,
            required: true,
            tooltip: '{{ __("Note") }}: {{ __("If you write {name} it will be replaced with the actual name of the associated object, and will reflect any changes to the object name when viewed or exported.") }}',
         },
         scenariodescription_pretty: {
            title: '{{ __('Risk scenario') }}',
            type: 'textarea',
            create: false,
            edit: false,
            list: true,
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
         consequencedescription: {
            title: '{{ __('Consequence description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: false,
            required: false,
            tooltip: '{{ __("Note") }}: {{ __("If you write {name} it will be replaced with the actual name of the associated object, and will reflect any changes to the object name when viewed or exported.") }}',
         },
         consequencedescription_pretty: {
            title: '{{ __('Consequence description') }}',
            type: 'textarea',
            create: false,
            edit: false,
            list: true,
         },
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
         risk_controls: {
            title: '{{ __('Controls') }}',
            create: false,
            edit: true,
            list: true,

            multiple: true,
            options: [
@foreach(App\Models\Control::orderBy('name')->select(['id', 'name', 'not_applicable_at', 'description'])->get()->each->setAppends([]) as $obj)
            { Value: {{ $obj->id }}, DisplayText: <?php echo(json_encode($obj->name.($obj->not_applicable_at ? " [".__("Not applicable")."]" : ""))); ?>, Tooltip: <?php echo(json_encode($obj->description)); ?> },
@endforeach
            ]
         },
         hr3: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
         probability_id: {
            title: '{{ __('Probability') }}',
            create: true,
            edit: true,
            list: false,
            options: [
               { Value: null, DisplayText: '{{ __("Not assessed") }}' },
@foreach(App\Models\ProbabilityLevel::orderBy('ordinal', 'desc')->select(['id', 'name', 'description'])->get()->each->setAppends([]) as $obj)
               { Value: {{$obj->id}}, DisplayText: <?php echo(json_encode($obj->name)); ?>, Tooltip: <?php echo(json_encode($obj->description)); ?>},
@endforeach
            ]
         },
         consequence_id: {
            title: '{{ __('Consequence') }}',
            create: true,
            edit: true,
            list: false,
            options: [
               { Value: null, DisplayText: '{{ __("Not assessed") }}' },
@foreach(App\Models\ConsequenceLevel::orderBy('ordinal', 'desc')->select(['id', 'name', 'description'])->get()->each->setAppends([]) as $obj)
               { Value: {{$obj->id}}, DisplayText: <?php echo(json_encode($obj->name)); ?>, Tooltip: <?php echo(json_encode($obj->description)); ?> },
@endforeach
            ]
         },
         assessment: {
            title: '{{ __("Estimated risk level") }}',
            type: 'textarea',
            list: true,
            create: false,
            edit: false,
            listClass: 'd-inline-block col-6',
            display: function(data){
               var retval = $('<div />')
                  .addClass('riskmatrix');

               var table = $('<table />')
                  .appendTo(retval);

@php
   $probabilitylevels = \App\Models\ProbabilityLevel::orderBy('ordinal', 'desc')->select(['id', 'name'])->get()->each->setAppends([]);
   $consequenceLevels = \App\Models\ConsequenceLevel::orderBy('ordinal')->select(['id', 'name'])->get()->each->setAppends([]);
@endphp

@foreach($probabilitylevels as $probability)
               var tr = $('<tr />')
                  .appendTo(table);
               $('<td />')
                  .append($('<span />'))
                  .appendTo(tr);

   @foreach($consequenceLevels as $consequence)
      @php $risklevel = \App\Models\RiskLevel::getRisklevel($probability, $consequence); @endphp
               $('<td />')
                  .addClass('matrixitem')
                  .attr('data-probability', {{ $probability->id }})
                  .attr('data-consequence', {{ $consequence->id }})
                  .attr('data-risklevel', {{ (null == $risklevel) ? 0 : $risklevel->id }})
                  .append($('<span />')
                     .css({ 'background-color': '#{{ $risklevel->color }}aa' })
                  )
                  .appendTo(tr);
   @endforeach
@endforeach
               var tr  = $('<tr />')
                  .addClass('bottomrow')
                  .appendTo(table);

               $('<td />')
                  .appendTo(tr);
@foreach($consequenceLevels as $consequence)
               $('<td />')
                  .append($('<span />'))
                  .appendTo(tr);
@endforeach
               if((null != data.record.probability_id) &&
                  (null != data.record.consequence_id))
               {
                  table.find('.matrixitem[data-probability="'+data.record.probability_id+'"][data-consequence="'+data.record.consequence_id+'"] span')
                     .append($('<span />')
                        .addClass('mark')
                        .html('&cross;'));
               }

               var retobj = $('<div />')
                  .append($('<div />')
                     .addClass('assessment-details'))
                  .append(retval);

               switch(data.record.probability_id)
               {
@foreach($probabilitylevels as $obj)
                  case {{ $obj->id }}:
                     retobj.find('.assessment-details').append('<strong>{{ __("Probability") }}:</strong> {{ $obj->name }}');
                     break;
@endforeach
               }

               switch(data.record.consequence_id)
               {
@foreach($consequenceLevels as $obj)
                  case {{ $obj->id }}:
                     if(data.record.probability_id)
                        retobj.find('.assessment-details').append(', ');

                     retobj.find('.assessment-details').append('<strong>{{ __("Consequence") }}:</strong> {{ $obj->name }}');
                     break;
@endforeach
               }

               return retobj;
            }
         },
         hr4: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
         assessmentcomment: {
            title: '{{ __('Assessment comment') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
         },
      @include('components.customproperty', ['classname' => 'App\Models\Risk'])
         hr5: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
@if(auth()->user()->can('useai'))
         ai_agent: {
            title: '',
            type: 'textarea',
            create: false,
            edit: false,
            list: true,
            footer: true,
            display: function(data) {
               if(data.record.assessed_at)
                  return '';

               var retobj = $('<div />');
               retobj.append($('<button />')
                  .addClass('btn btn-primary btn-sm text-light')
                  .text('{{ __('Get AI opinion') }}')
                  .prepend($('<span>support_agent</span>')
                  .addClass('material-symbols-rounded'))
                  .click(function(){
                     var aiFeedbackContainer = $(this).siblings('.ai-agent-feedback');
                     ajaxPost('/api/v1/ai/risk', { risk_id: $(this).closest('.jtable-data-row').attr('data-record-key') }, function(aidata, textStatus, jqXHR){
                        if(null == aidata.retval)
                           aiFeedbackContainer.text('{{ __("No response was received from AI agent") }}');
                        else
                           aiFeedbackContainer.text(aidata.retval);

                        aiFeedbackContainer.show();
                     });

                     return false;
                  }));

               retobj.append($('<div />')
               .addClass('ai-agent-feedback')
               .css({ 'display': 'none' }));

               return retobj;
            }
         },
@endif
         commands: {
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            listClass: 'd-inline-block',
            display: function(data){
               var retval = $('<span />');
               if(!data.record.assessed_at)
               {
                  if({{ auth()->user()->id }} == data.record.riskowner_id)
                  {
                     retval.append($('<button />')
                        .addClass('btn btn-primary btn-sm text-light')
                        .text('{{ __('Approve') }}')
                        .prepend($('<span>check</span>')
                           .addClass('material-symbols-rounded'))
                        .click(function(clickevent){
                           confirmDialog('{{ __("Approve risk") }}', '{{ __("By approving the risk, you certify that the risk is correctly assessed, that planned actions are relevant and sufficient to manage the risk and that you have the mandate to approve this risk") }}', function(){
                                 ajaxPost('/api/v1/items/Risk/'+data.record.id+'/approve', {}, function(){
                                    $(clickevent.target).closest('.jtable-main-container').parent('div').jtable('reload');
                                    });
                           });
                        }));
                  }

                  if(data.record.partner_id && !data.record.replacing_id)
                  {
                     retval.append($('<button />')
                        .addClass('btn btn-warning btn-sm')
                        .css({'margin-left': '10px'})
                        .text('{{ __('Ignore') }}')
                        .prepend($('<span>delete</span>')
                           .addClass('material-symbols-rounded'))
                        .click(function(clickevent){
                           confirmDialog('{{ __("Ignore") }}', '{{ __("By ignoring the risk, you certify that the provided risk is not relevant for assessment") }}', function(){
                                 ajaxPost('/api/v1/items/Risk/'+data.record.id+'/ignore', {}, function(){
                                       $(clickevent.target).closest('.jtable-data-row').remove();
                                    });
                           });
                        }));
                  }

                  return retval;
               }
               else
               {
                  return $('<button />')
                     .addClass('btn btn-primary btn-sm text-light')
                     .text('{{ __('Reassess risk') }}')
                     .prepend($('<span>restart_alt</span>')
                        .addClass('material-symbols-rounded'))
                     .click(function(clickevent){
                        confirmDialog('{{ __("Replace risk") }}', '{{ __("Are you sure? This will replace the existing assessment") }}', function(){
                              ajaxPost('/api/v1/items/Risk/'+data.record.id+'/replace', {}, function(){
                                    $(clickevent.target).closest('.jtable-main-container').parent('div').jtable('reload');
                                 });
                        });
                     });
               }

               return $('<span />');
            }
         },
         riskactions: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (sourcedata) {
               var actions = { listAction: '/api/v1/items/ControlAction?risk='+sourcedata.record.id };

               if(!sourcedata.record.readonly)
               {
                  actions.createAction = '/api/v1/items/ControlAction?risk_id='+sourcedata.record.id;
                  actions.updateAction = '/api/v1/items/ControlAction?risk_id='+sourcedata.record.id;
                  actions.deleteAction = '/api/v1/items/ControlAction?risk_id='+sourcedata.record.id;
               }

               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Actions') }}')
                  .prepend($('<span>format_list_bulleted</span>')
                     .addClass('material-symbols-rounded'));

               retobj.click(function () {
                  var selectedControls = [];
                  Object.values(sourcedata.record.risk_controls).forEach(elem => { selectedControls.push(elem.id); });
                  var includedcontrols = [];
                  var excludedcontrols = [];
@foreach(App\Models\Control::orderBy('name')->select(['id', 'name', 'not_applicable_at'])->get()->each->setAppends([]) as $obj)
   if(selectedControls.includes({{ $obj->id }}))
      includedcontrols.push({ Value: {{ $obj->id }}, DisplayText: <?php echo(json_encode($obj->name.($obj->not_applicable_at ? " [".__("Not applicable")."]" : ""))); ?>});
	else
      excludedcontrols.push({ Value: {{ $obj->id }}, DisplayText: <?php echo(json_encode($obj->name.($obj->not_applicable_at ? " [".__("Not applicable")."]" : ""))); ?>});
@endforeach
                  if(retobj.closest('.accordion-body').find('#planned-actions').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }

                   $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Actions") }}',
                        actions: actions,
                        tableId: 'planned-actions',
                        paging: false,
                        messages: {
                           addNewRecord: '{{ __('Add new action') }}',
                        },
                        fields: {
                           id: {
                              key: true,
                              list: false,
                              edit: false,
                              create: false,
                           },
                           status: {
                              title: '{{ __("Status") }}',
                              list: true,
                              edit: false,
                              create: false,
                              width: '1%',
                              display: function(data){
                                 var status = '';
                                 if(data.record.finished_at)
                                    status = '{{ __("Finished") }}';
                                 else
                                    status = '{{ __("Planned") }}';

                                 return $('<span />')
                                       .addClass('badge rounded-pill bg-info')
                                       .text(status);
                              }
                           },
                           existing_control_action_id: {
                              title: '{{ __('Existing control actions') }}',
                              list: false,
                              edit: false,
                              create: true,
                              options:[
                                 { Value: null, DisplayText: '-- {{ __("Add new action") }} --' },
@foreach(App\Models\ControlAction::whereNull('finished_at')->orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
                                    { Value: {{ $obj->id }}, DisplayText: '{{ $obj->name}}'},
@endforeach
                              ]
                           },
                           control_id: {
                              title: '{{ __('Control') }}',
                              width: '15%',
                              list: true,
                              edit: true,
                              create: true,
                              options: [
                                 { Label: '{{ __("Associated controls") }}', Children: includedcontrols},
                                 { Label: '{{ __("Other controls") }}', Children: excludedcontrols},
                              ],
                              required: true,
                           },
                           responsible_id: {
                              title: '{{ __('Responsible') }}',
                              width: '15%',
                              list: true,
                              edit: true,
                              create: true,

                              options: [
@foreach(\App\Models\User::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
                                 { Value: {{ $obj->id }}, DisplayText: <?php echo(json_encode($obj->name)); ?>},
@endforeach
                              ],
                              required: true,
                           },
                           due: {
                              title: '{{ __('Due') }}',
                              type: 'date',
                              width: '10%',
                              defaultValue: '{{ date("Y-m-d", strtotime("+3 MONTHS")) }}',
                              list: true,
                              edit: true,
                              create: true,
                              required: true,
                           },
                           name: {
                              title: '{{ __('Name') }}',
                              width: '25%',
                              list: true,
                              edit: true,
                              create: true,
                              required: true,
                              maxlength: 255,
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              type: 'textarea',
                              width: '40%',
                              list: true,
                              edit: true,
                              create: true,
                              required: true,
                           },
                           estimated_cost: {
                              title: '{{ __('Estimated cost') }}',
                              type: 'number',
                              min: 0,
                              step: 1,
                              create: true,
                              edit: true,
                              list: true,
                              display: function(data){
                                 if(!data.record.estimated_cost)
                                    return '-';

                                 // Format as currency
                                 return new Intl.NumberFormat('sv-SE', {
                                    style: 'currency',
                                    currency: 'SEK',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                 }).format(data.record.estimated_cost);
                              }
                           },
                        },
                        formCreated: function(event, data){
                           $('select#Edit-control_id').attr('required', true);

                           if(data.record)
                              return;

                           // Save the required-attributes for all fields
                           $(data.form).find('[name][required]').each(function(){
                              $(this).data('is-required', true);
                           });

                           // Create a on-change event for the existing_control_action_id field
                           $(data.form).find('#Edit-existing_control_action_id').change(function(){
                              if($(this).val())
                              {
                                 $(data.form).find('div.jtable-input-field-container').hide();
                                 $(data.form).find('div.jtable-input-field-container:has(#Edit-existing_control_action_id)').show();
                                 $(data.form).find('[required]').attr('required', false);
                              }
                              else {
                                 $(data.form).find('div.jtable-input-field-container').show();
                                 $(data.form).find('[name]').each(function(){
                                    if($(this).data('is-required'))
                                    $(this).attr('required', true);
                                 });
                              }
                           }).trigger('change');
                        },
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });

               return retobj;
            }
         },
         showHistory: showHistoryField('Risk', $('#tableContainer')),
         showMessages: showMessagesField('Risk', $('#tableContainer')),
      },
