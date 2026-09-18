@php if(config('ledningssystemet.disable_gdpr')) abort(404); @endphp
@php if(!request()->user()->canAny(['processingregister.read', 'processingregister.edit'])) abort(403);  @endphp
@extends('layouts.master')

@section('container')
<style>
   #tableContainerController {
      margin-top: 60px;
   }
   
   h1, .fst-italic {
      margin: 20px 0 20px 0;
   }
   
   #pageTabs {
      margin-top: 30px;
      margin-bottom: 30px;
   }
   
   .customercontainer {
      display: block;
      margin-bottom: 20px;
      border: 1px solid #ddd;
      background-color: #f5f5f5;
      border-radius: 3px;
      padding: 10px;
   }
   
   .customername {
      display: block;
   }
   
   .customername::before {
      display: inline-block;
      float: left;
      content: '{{ __("Customer")." " }}';
      font-weight: 600;
   }
   .customerdescription {
      display: block;
   }
   
   .customerdescription:not(:empty)::before {
      margin-top: 1em;
      display: block;
      content: '{{ __("Description") }}';
      font-weight: 600;
   }
   
   .customerdponame:not(:empty)::before {
      display: block;
      content: '{{ __("Data protection contact") }}';
      font-weight: 600;
      margin-top: 1em;
   }

   .customerdponame {
      display: inline;
   }
   
   .customerdpoemail {
      display: inline;
   }
   
   .customerdpoemail:not(:empty)::before {
      display: inline;
      content: ' (';
   }
   .customerdpoemail:not(:empty)::after {
      display: inline;
      content: ')';
   }
   
   
   .processcontainer {
      display: block;
      margin-bottom: 20px;
      border: 1px solid #ddd;
      background-color: #f5f5f5;
      border-radius: 3px;
      padding: 10px;
   }
   
   .processname {
      display: block;
   }
   
   .processname::before {
      display: inline-block;
      float: left;
      content: '{{ __("Process")." " }}';
      font-weight: 600;
   }
   
   .processdescription {
      display: block;
      margin-top: 1em;
   }
   
   .processdescription:not(:empty)::before {
      margin-top: 1em;
      display: block;
      content: '{{ __("Description") }}';
      font-weight: 600;
   }
   
   .processingactivities:not(:empty)::before {
      margin-top: 1em;
      display: block;
      content: '{{ __("Processing activities") }}';
      font-weight: 600;
   }

   .processingactivities {
      display: block;
   }
   
   .thirdcountrytransferdescription:not(:empty)::before {
      margin-top: 1em;
      display: block;
      content: '{{ __("Third country transfer") }}';
      font-weight: 600;
   }

   .thirdcountrytransferdescription {
      display: block;
   }

   .thirdcountrytransferprotectiondescription:not(:empty)::before {
      margin-top: 1em;
      display: block;
      content: '{{ __("Third country transfer protection") }}';
      font-weight: 600;
   }

   .thirdcountrytransferprotectiondescription {
      display: block;
   }

   .securitymeasuredescription:not(:empty)::before {
      margin-top: 1em;
      display: block;
      content: '{{ __("Specific security controls") }}';
      font-weight: 600;
   }

   .securitymeasuredescription {
      display: block;
   }
</style>
<script>
$(function(){
   $('#tableContainerController').jtable({
      title: '',
      tableId: 'gdprregister',
      bootstrap: true,
      accordion:true,
      filter: {
         department_id: {
            type: 'select',
            text: '{{ __("Department") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
@foreach(\App\Models\Department::orderBy('name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach               
            ]
         },
      },
      actions: {
         listAction: '/api/v1/processDataProcessingRegister/controller',
      },
      fields: {
         id: {
            key: true,
            list: false,
         },
         header: {
            title: '',
            header: true,
            display: function(data){
               return $('<span />').text(data.record.process.name);
            }
         },
         description: {
            title: '{{__("Description")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               return $('<span />').text(data.record.process.description);
            }
         },
         process: {
            title: '{{__("Process")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               return $('<span />').text(data.record.process.name);
            }
         },
         department_id: {
            title: '{{__("Department")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               return $('<span />').text(data.record.department.name);
            }
         },
         hr0: {
            title: '',
            display: function(data)
            {
               return '<hr />';
            }
         },
         activities: {
            title: '{{__("Processing activities")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.activities).forEach(obj => {
                  var item = $('<span />')
                     .addClass('dataprocessingregisterinfoitem')
                     .text(obj.name)
                     .appendTo(retval);
                     
                  if(obj.description)
                     item
                        .addClass('hasdescription')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', obj.description);
               });
               
               return retval;
            }
         },
         subjectcategories: {
            title: '{{__("Subject categories")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.subjectcategories).forEach(obj => {
                  var item = $('<span />')
                     .addClass('dataprocessingregisterinfoitem')
                     .text(obj.name)
                     .appendTo(retval);
                     
                  if(obj.description)
                     item
                        .addClass('hasdescription')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', obj.description);
               });
               
               return retval;
            }
         },
         datacategories: {
            title: '{{__("Data categories")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.datacategories).forEach(obj => {
                  var item = $('<span />')
                     .addClass('dataprocessingregisterinfoitem')
                     .text(obj.name + (obj.sensitive ? ' ({{ __("sensitive PII") }})' : ''))
                     .appendTo(retval);
                     
                  if(obj.description)
                     item
                        .addClass('hasdescription')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', obj.description);
               });
               
               return retval;
            }
         },
         hr1: {
            title: '',
            display: function(data)
            {
               return '<hr />';
            }
         },
         legalbasises: {
            title: '{{__("Legal basis")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.legalbasises).forEach(obj => {
                  var item = $('<span />')
                     .addClass('dataprocessingregisterinfoitem')
                     .text(obj.name)
                     .appendTo(retval);
                     
                  if(obj.description)
                     item
                        .addClass('hasdescription')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', obj.description);
               });
               
               return retval;
            }
         },
         legalbasisdescription: {
            title: '{{__("Legal basis description")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               return $('<span />').text(data.record.process.legalbasisdescription);
            }
         },
         hr2: {
            title: '',
            display: function(data)
            {
               return '<hr />';
            }
         },
         informationtypes: {
            title: '{{__("Information types")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.informationtypes).forEach(obj => {
                  var item = $('<span />')
                     .addClass('dataprocessingregisterinfoitem')
                     .text(obj.name + ((obj.retention && (obj.retention > 0)) ? ' ({{ __("retained for") }} '+obj.retention+' {{ __("months") }})' : ''))
                     .appendTo(retval);
                     
                  if(obj.description)
                     item
                        .addClass('hasdescription')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', obj.description);
               });
               
               return retval;
            }
         },
         assets: {
            title: '{{__("Assets")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.assets).forEach(obj => {
                  var item = $('<span />')
                     .addClass('dataprocessingregisterinfoitem')
                     .text(obj.name)
                     .appendTo(retval);
                     
                  if(obj.description)
                     item
                        .addClass('hasdescription')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', obj.description);
               });
               
               return retval;
            }
         },
         securitymeasuredescription	: {
            title: '{{__("Specific security controls")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               return $('<span />').text(data.record.process.securitymeasuredescription);
            }
         },
         hr3: {
            title: '',
            display: function(data)
            {
               return '<hr />';
            }
         },
@if(!config('ledningssystemet.disable_supplier'))
         processors: {
            title: '{{__("Data processors")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.processors).forEach(obj => {
                  var item = $('<span />')
                     .addClass('dataprocessingregisterinfoitem')
                     .text(obj.name)
                     .appendTo(retval);
                     
                  if(obj.description)
                     item
                        .addClass('hasdescription')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', obj.description);
               });
               
               return retval;
            }
         },
@endif
         recipientcategories: {
            title: '{{__("Recipient categories")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.recipientcategories).forEach(obj => {
                  var item = $('<span />')
                     .addClass('dataprocessingregisterinfoitem')
                     .text(obj.name)
                     .appendTo(retval);
                     
                  if(obj.description)
                     item
                        .addClass('hasdescription')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', obj.description);
               });
               
               return retval;
            }
         },
         hr4: {
            title: '',
            display: function(data)
            {
               return '<hr />';
            }
         },
         thirdcountrytransferdescription: {
            title: '{{__("Third country transfer")}}',
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               return $('<span />').text(data.record.process.thirdcountrytransferdescription);
            }
         },
         thirdcountrytransferprotectiondescription: {
            title: '{{__("Third country transfer protection")}}',
            listClass: 'd-inline-block col-12 col-md-8',
            display: function(data)
            {
               return $('<span />').text(data.record.process.thirdcountrytransferprotectiondescription);
            }
         },
      },
   });
   $('#tableContainerController').jtable('load');      
   
   $('#tableContainerProcessor').jtable({
      title: '',
      tableId: 'gdprregister',
      bootstrap: true,
      accordion:true,
      filter: {
         customer_id: {
            type: 'select',
            text: '',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all customers') }}' },
@foreach(\App\Models\Customer::orderBy('name')->get() as $obj)
@if($obj->int_processes->count())
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endif
@endforeach               
            ]
         },
      },
      actions: {
         listAction: '/api/v1/processDataProcessingRegister/processorprocess',
      },
      fields: {
         id: {
            key: true,
            list: false,
         },
         name: {
            title: '',
            header: true,
         },
         description: {
            title: '{{__("Description")}}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         data_processor_processing_activities: {
            title: '{{__("Processing activities")}}',
            listClass: 'd-inline-block col-12 col-md-8',
         },
         hr0: {
            title: '',
            display: function(){
               return '<hr />';
            }
         },
         securitymeasuredescription	: {
            title: '{{__("Specific security controls")}}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         thirdcountrytransferdescription: {
            title: '{{__("Third country transfer")}}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         thirdcountrytransferprotectiondescription: {
            title: '{{__("Third country transfer protection")}}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         hr1: {
            title: '',
            display: function(){
               return '<hr />';
            }
         },
         customers: {
            title: '{{__("Data controllers")}}',
            listClass: 'd-inline-block col-12',
            display: function(data) {
               var retobj = $('<div />');
               Object.values(data.record.customers).forEach((obj) => {
                  retobj.append($('<div />')
                     .addClass('customercontainer')
                     .append($('<span />').addClass('customername').text(obj.name))
                     .append($('<span />').addClass('customerdescription').text(obj.description))
                     .append($('<span />').addClass('customerdponame').text(obj.dpo_name))
                     .append($('<span />').addClass('customerdpoemail').text(obj.dpo_email))
                  );
               });
               
               return retobj;
            }
         },
      },
   });
   $('#tableContainerProcessor').jtable('load');         
   
   $('#tableContainerProcessorCustomer').jtable({
      title: '',
      tableId: 'gdprregister',
      bootstrap: true,
      accordion:true,
      filter: {
         process_id: {
            type: 'select',
            text: '',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all processes') }}' },
@foreach(\App\Models\Process::where('dataprocessor', 1)->orderBy('name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach               
            ]
         },
      },
      actions: {
         listAction: '/api/v1/processDataProcessingRegister/processorcustomer',
      },
      fields: {
         id: {
            key: true,
            list: false,
         },
         name: {
            title: '',
            header: true,
         },
         description: {
            title: '{{__("Description")}}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         dpo_name: {
            title: '{{__("Data controller contact name")}}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         dpo_email: {
            title: '{{__("Data controller contact email")}}',
            listClass: 'd-inline-block col-12 col-md-4',
         },
         hr0: {
            title: '',
            display: function(){
               return '<hr />';
            }
         },
         processes: {
            title: '{{__("Data processing")}}',
            listClass: 'd-inline-block col-12',
            display: function(data) {
               var retobj = $('<div />');
               Object.values(data.record.processes).forEach((obj) => {
                  retobj.append($('<div />')
                     .addClass('processcontainer')
                     .append($('<span />').addClass('processname').text(obj.name))
                     .append($('<span />').addClass('processdescription').text(obj.description))
                     .append($('<span />').addClass('processingactivities').text(obj.data_processor_processing_activities))
                     .append($('<span />').addClass('thirdcountrytransferdescription').text(obj.thirdcountrytransferdescription))
                     .append($('<span />').addClass('thirdcountrytransferprotectiondescription').text(obj.thirdcountrytransferprotectiondescription))
                     .append($('<span />').addClass('securitymeasuredescription').text(obj.securitymeasuredescription))
                  );
               });
               return retobj;
            }
         },
      },
   });
   $('#tableContainerProcessorCustomer').jtable('load');                   
   
});
</script>

<h1>{{ __("Record of processing activities") }}</h1>
<div class="fst-italic">{{ __("This register is automatically created based on process mappings and serves the purpose of describing processing activities in the role as data processor according to EU 2016/679 Article 30. For descriptions on our applicable technical and organizational security arrangements, please consult the ") }}<a href="/inventory/controls">{{ __("Controls register") }}</a>.</div>

<ul class="nav nav-tabs" id="pageTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="datacontrollertab" data-bs-toggle="tab" data-bs-target="#datacontrollertabcontent" type="button" role="tab" aria-controls="datacontrollertab" aria-selected="true">{{ __("Data controller") }}</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="dataprocessortab" data-bs-toggle="tab" data-bs-target="#dataprocessortabbcontent" type="button" role="tab" aria-controls="dataprocessortab" aria-selected="true">{{ __("Data processor - per process") }}</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="dataprocessorcustomertab" data-bs-toggle="tab" data-bs-target="#dataprocessorcustomertabbcontent" type="button" role="tab" aria-controls="dataprocessorcustomertab" aria-selected="true">{{ __("Data processor - per customer") }}</button>
  </li>
</ul>
<div class="tab-content" id="tabContents">
  <div class="tab-pane fade show active" id="datacontrollertabcontent" role="tabpanel" aria-labelledby="datacontrollertab">
      <div class="">{{ __("Data controller") }}: <span style="font-weight: 600;">{{ config('ledningssystemet.company_name') }}</span></div>
      <div class="">{{ __("Data controller contact") }}: <span style="font-weight: 600;">{{ config('ledningssystemet.data_privacy_contact_name').' ('.config('ledningssystemet.data_privacy_contact_email').')' }}</span></div>
      
      <div id="tableContainerController" class=""></div>
  </div>
  <div class="tab-pane fade show" id="dataprocessortabbcontent" role="tabpanel" aria-labelledby="dataprocessortab">
      <div class="">{{ __("Data processor") }}: <span style="font-weight: 600;">{{ config('ledningssystemet.company_name') }}</span></div>
      <div class="">{{ __("Data processor contact") }}: <span style="font-weight: 600;">{{ config('ledningssystemet.data_privacy_contact_name').' ('.config('ledningssystemet.data_privacy_contact_email').')' }}</span></div>
      
      <div id="tableContainerProcessor" class=""></div>
  </div>
  
  <div class="tab-pane fade show" id="dataprocessorcustomertabbcontent" role="tabpanel" aria-labelledby="dataprocessorcustomertab">
      <div class="">{{ __("Data processor") }}: <span style="font-weight: 600;">{{ config('ledningssystemet.company_name') }}</span></div>
      <div class="">{{ __("Data processor contact") }}: <span style="font-weight: 600;">{{ config('ledningssystemet.data_privacy_contact_name').' ('.config('ledningssystemet.data_privacy_contact_email').')' }}</span></div>
      
      <div id="tableContainerProcessorCustomer" class=""></div>
  </div>
</div>   
   
   
@endsection
