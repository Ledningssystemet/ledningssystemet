import Modeler from "./bpmn-js/lib/Modeler";
import Viewer from "./bpmn-js/lib/Viewer";
import './bpmn-js/dist/assets/diagram-js.css';
import './bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import CustomContextPad from './bpmncustoms/CustomContextPad';
import CustomTranslate from './bpmncustoms/CustomTranslate';
import CustomLabelEditor from './bpmncustoms/CustomLabelEditor';

// Custom translation
var customTranslateModule = {
   translate: [ 'value', CustomTranslate ]
};

// Initialize modeler
var modeler = null;


// Extend with functions
jQuery.fn.extend({
   bpmnedit: function(params, args){
      // If string, then assume it is a function call, else asume an init call
      if('string' === typeof params)
      {
         switch(params)
         {
            // Load XML
            case 'load':
               // Perform validation of arguments
               if('string' !== typeof args)
               {
                  console.error('[BPMNedit] Load called with invalid arguments');
                  return;
               }
               
               // Load XML
               modeler.importXML(args).then(function(){
                  modeler.get('canvas').zoom('fit-viewport');
                  modeler.get('canvas').scroll({dx: 50, dy: 80});
                  $('.bpmn-zoomin-button').on('click', function(){
                     modeler.get('zoomScroll').stepZoom(0.2);
                  });
                  $('.bpmn-zoomout-button').on('click', function(){
                     modeler.get('zoomScroll').stepZoom(-0.2);
                  });
                  $('.bpmn-zoomreset-button').on('click', function(){
                  modeler.get('canvas').zoom('fit-viewport');
                  modeler.get('canvas').scroll({dx: 50, dy: 80});
                  });
               });
               
               break;
            case 'save':
               if('undefined' === typeof args)
               {
                  console.error('[BPMNedit] Save called without providing arguments');
                  return;
               }
               if('function' !== typeof args.success)
               {
                  console.error('[BPMNedit] Save called without success callback');
                  return;
               }
                  
               modeler.saveXML({ format: true }).then(
                  function(retval){ // On XML save successful
                     const xml = retval.xml;
                     modeler.saveSVG({}).then(
                        function(retval){ // On SVG save successful
                           args.success(xml, retval.svg);
                        },
                        function(retval){ // On SVG save failed
                           showDialog(translateString("Action failed"), retval);
                        }
                     );
                  },
                  function(retval){ // On XML save failed
                     showDialog(translateString("Action failed"), retval);
                  });
                  
               break;
            default:
               console.error('[BPMNedit] Invalid function call '+params);
         }
      }
      else
      {
         modeler = new Modeler({
           keyboard: {
             bindTo: document
           },
           additionalModules: [
            {
               __init__: ["customContextPad"],
               customContextPad: ["type", CustomContextPad],
            },
            {
               __init__: ["customLabelEditor"],
               customLabelEditor: ["type", CustomLabelEditor],
            },
             customTranslateModule
           ]
         });
         
         
         // Attach modeler
         modeler.attachTo(this);
         return modeler;
      }
   },
   bpmnview: function(params, args){
      // If string, then assume it is a function call, else asume an init call
      if('string' === typeof params)
      {
         switch(params)
         {
            // Load XML
            case 'load':
               // Perform validation of arguments
               if('string' !== typeof args)
               {
                  console.error('[BPMNedit] Load called with invalid arguments');
                  return;
               }
               
               modeler.importXML(args).then(function(){
                  modeler.get('canvas').zoom('fit-viewport');
               });
               
               break;
               
            case 'click':
               var eventBus = modeler.get('eventBus');
               eventBus.on("element.click", args);               
               break;

            case 'dblclick':
               var eventBus = modeler.get('eventBus');
               eventBus.on("element.dblclick", args);               
               break;
         }
      }
      else
      {
         modeler = new Viewer({container:$(this)});
         return modeler;
      }
   }   
});
