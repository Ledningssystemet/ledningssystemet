@php if(Auth::user()->cannot('update', $process)) abort(403); @endphp
@extends('layouts.master')

@section('container')
   <style>
      div#processchartcanvas { width: 1400px; height: 800px;  background-color: var(--bs-light); float: left; display: inline-block; border: 1px solid #aaaaaa;}
      .bjs-powered-by { display: none; }
      div[data-entry-id="id"], div[data-entry-id="isExecutable"] { display: none; }
      div.djs-palette.open {
         background-color: var(--bs-light);
      }
      
      .pristine {
         stroke-dasharray: 3 5;
      }

   </style>
   <script>
   var bpmnModeler = null;
   var bpmnDirty = false;
   
   var bpmnDbObjects = null;
   

   function appendStyles(){
      $('svg[data-element-id="Process"] .djs-visual[data-name][data-type]').each(function(){
         var exists = false;
         var annotate = null;
         var type = $(this).attr('data-type');
         var id = $(this).closest('.djs-element').attr('data-element-id');
         var name = $(this).attr('data-name');
         switch(type)
         {
            case 'subprocess':
               annotate = $($(this).find('rect')[0]);
               Object.values(bpmnDbObjects.processes).forEach((obj) => {
                  if(obj.name == name)
                     exists = true;
               });
               break;
            case 'task':
               annotate = $($(this).find('rect')[0]);
               Object.values(bpmnDbObjects.activities).forEach((obj) => {
                  if(obj.bpmnid == id)
                     exists = true;
               });
               break;
            case 'dataobjectreference':
               annotate = $($(this).find('path')[0]);
               Object.values(bpmnDbObjects.informationtypes).forEach((obj) => {
                  if(obj.name == name)
                     exists = true;
               });
               break;
            case 'datastorereference':
               annotate = $($(this).find('path')[0]);
               Object.values(bpmnDbObjects.assets).forEach((obj) => {
                  if(obj.name == name)
                     exists = true;
               });
               break;
         }
         
         if(annotate)
         {
            if(exists)
            {
               annotate.removeClass('pristine');
               annotate.addClass('existing');
            }
            else
            {
               annotate.removeClass('existing');
               annotate.addClass('pristine');
            }
         }
      });
   }

   $(function(){
      // Initialize bpmn editor
      window.addEventListener("beforeunload", beforeUnloadHandler);
      bpmnModeler = $('div#processchartcanvas').bpmnedit();
      var eventBus = bpmnModeler.get('eventBus');
      eventBus.on('commandStack.changed', function(event){
         bpmnDirty = true;
      });
      
      eventBus.on('import.render.complete', function(event){
         appendStyles();
      });
      
      eventBus.on('element.changed', function(event){
         appendStyles();
      });
      
      // Set onchange event
      load();
   });

   function save(save, validate, publish)
   {
      $('div#processchartcanvas').bpmnedit('save', {
         success: function(xml, svg){
            ajaxPost("/inventory/processedit/{{ $process->id }}/save", { xml: xml, svg: svg, _token: '{{ csrf_token() }}', save: save, validate: validate, publish: publish }, function(args){
               // Remove event listener if save
               if(save || publish)
                  bpmnDirty = false;
               
               $('div.alert').remove();
               
               if(args.info &&
                  args.info.length > 0)
               {
                  $('<div class="alert alert-info alert-dismissable fade show" role="alert"></div>')
                        .append($('<div style="margin-bottom: 20px;"></div>').html('<strong>Info</strong><br>'+args.info))
                        .append($('<button class="btn btn-sm btn-success" data-bs-dismiss="alert" aria-label="{{ __("Close"); }}">{{ __("Close"); }}</button>'))
                        .appendTo($('div#alertcontainer'));
               }
               
               if(args.warning &&
                  args.warning.length > 0)
               {
                  $('<div class="alert alert-warning alert-dismissable fade show" role="alert"></div>')
                        .append($('<div style="margin-bottom: 20px;"></div>').html('<strong>Operation failed</strong><br>'+args.warning))
                        .append($('<button class="btn btn-sm btn-success" data-bs-dismiss="alert" aria-label="{{ __("Close"); }}">{{ __("Close"); }}</button>'))
                        .appendTo($('div#alertcontainer'));
               }
               loadDbObjects(function(){
                  appendStyles();
               });               
            });
         }
      });
    }
    
   function loadDbObjects(onLoadedCallback = null)
   {
      ajaxGet("/inventory/processload/{{ $process->id }}", function(data){
         bpmnDbObjects = {
            processes: data.processes, 
            activities: data.activities, 
            informationtypes: data.informationtypes, 
            assets: data.assets, 
         };
         
         if(onLoadedCallback)
            onLoadedCallback(data);
      });
   }
    
   function load()
   {
      loadDbObjects(function(data){
         $('div#processchartcanvas').bpmnedit('load', data.bpmn);
      });
   }

   function showSelectLabel(element, modeling, elements)
   {
      var data = [];
      var tags = true;
      
      // Add stored types
      switch(element.type)
      {
         case 'bpmn:DataObjectReference':
 @foreach(App\Models\InformationType::orderBy('name')->get()->each->setAppends([]) as $obj)
            data.push({id: '{{ $obj->name }}', text: '{{ $obj->name }}', selected: false});
 @endforeach
            break;
         case 'bpmn:DataStoreReference':
 @foreach(App\Models\Asset::orderBy('name')->get()->each->setAppends([]) as $obj)
            data.push({id: '{{ $obj->name }}', text: '{{ $obj->name }}', selected: false});
 @endforeach
            break;
         case 'bpmn:SubProcess':
 @foreach(App\Models\Process::where('id', '<>', $process->id)->orderBy('name')->get()->each->setAppends([]) as $obj)
            data.push({id: '{{ $obj->name }}', text: '{{ $obj->name }}', selected: false});
 @endforeach
               tags = false;
            break;
         default:
            return;
      }
      
      if(tags)
      {
         // Add dynamically created types if not already in the list
         for(var i = 0; i < elements.length; i++)
         {
            if(elements[i].type != element.type)
               continue;
            
            if('undefined' == typeof elements[i].businessObject.name)
               continue;
            
            var foundMatch = false;
            for(var j = 0; j < data.length; j++)
            {
               if(data[j].text.toUpperCase() == elements[i].businessObject.name.toUpperCase())
               {
                  // Match!
                  foundMatch = true;
                  break;
               }
            }
            if(!foundMatch)
               data.push({id: elements[i].businessObject.name, text: elements[i].businessObject.name, selected: false});
         }
      }
      
      // Set current element text to be the currently selected item in list
      if('undefined' != typeof element.businessObject.name)
      {
         for(var i = data.length - 1; i >= 0; i--)
         {
            if(data[i].text.toUpperCase() == element.businessObject.name.toUpperCase())
            {
               data[i].selected = true;
               break;
            }
         }
      }
      else
         data.push({id: 'noname', text: '', selected: true, disabled: true});
      
      // Open dialog
      openSelectDialog('', {
         data: data,
         tags: tags,
      },
      function(value){
         if(null !== value)
            modeling.updateLabel(element, value.text);
      });
   }
   
   function revert()
   {
      confirmDialog('{{ __("Delete draft"); }}', '{{ __("Are you sure that you want to undo all changes and revert to the published version version of this chart? All your changes will be lost."); }}', function(){
         bpmnDirty = false;
         ajaxGet("/api/v1/items/Process/{{ $process->id }}/revert", function(args){
            window.location='/inventory/processedit/{{ $process->id }}';
         });
      }, {warning: true});
   }   
   
   const beforeUnloadHandler = (event) => {
      if(bpmnDirty)
      {
         event.preventDefault();
         event.returnValue = true;
      }
   };   
   </script>
   <h1>{{ __("Edit process") }} {{ $process->name }}</h1>

   <a href="/inventory/processes?jtId[processeslist]={{ $process->id }}" class="btn btn-outline-primary btn-sm" style="margin: 20px 0 20px 0;">
      <span class="material-symbols-rounded">arrow_back</span>
      {{ __("Return to process"); }}
   </a>
   <button onClick="save(false, true, false);" class="btn btn-outline-primary btn-sm" style="margin: 20px 0 20px 0;">
      <span class="material-symbols-rounded">check</span>
      {{ __("Validate"); }}
   </button>
   <button onClick="save(true, false, false);" class="btn btn-outline-primary btn-sm" style="margin: 20px 0 20px 0;">
      <span class="material-symbols-rounded">save</span>
      {{ __("Save draft"); }}
   </button>
   <button onClick="save(true, true, true);" class="btn btn-outline-primary btn-sm" style="margin: 20px 0 20px 0;">
      <span class="material-symbols-rounded">publish</span>
      {{ __("Save and publish"); }}
   </button>
   <button onClick="revert();" class="btn btn-outline-danger btn-sm" style="margin: 20px 0 20px 0;">
      <span class="material-symbols-rounded">history</span>
         {{ __("Delete draft"); }}
   </button>

   <div id="alertcontainer"></div>
   <div class="alert alert-info"  style="font-size: 0.8em;">
      <span style="font-weight: 600">{{ __("Keyboard commands") }}:</span><br />
      Ctrl/Cmd + Z: {{ __("Undo") }}<br />
      Ctrl/Cmd + Y: {{ __("Redo") }}
   </div>

   <div id="processcontainer">
      <div id="processchartcanvas" data-process-id="{{ $process->id }}"></div>
</div>
@endsection
