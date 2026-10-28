import ContextPadProvider from '../bpmn-js/lib/features/context-pad/ContextPadProvider';

export default class CustomContextPadProvider extends ContextPadProvider {
   constructor(config, injector, eventBus, contextPad, modeling, elementFactory, connect, create, popupMenu, canvas, rules, translate, appendPreview) {
      super(config, injector, eventBus, contextPad, modeling, elementFactory, connect, create, popupMenu, canvas, rules, translate, appendPreview);
      
      contextPad.registerProvider(this);
   }
   
   getContextPadEntries(element) {
      var dbElemType = null;
      var dbElemId = null;
      switch(element.type)
      {
         case 'bpmn:SubProcess':
            Object.values(bpmnDbObjects.processes).forEach((obj) => {
               if(element.businessObject && element.businessObject.name && (obj.name == element.businessObject.name))
               {
                  dbElemType = 'Process';
                  dbElemId = obj.id;
               }
            });
            break;
            
         case 'bpmn:Task':
            Object.values(bpmnDbObjects.activities).forEach((obj) => {
               if(obj.bpmnid == element.id)
               {
                  dbElemType = 'ProcessActivity';
                  dbElemId = obj.id;
               }
            });
            break;
         case 'bpmn:DataObjectReference':
            Object.values(bpmnDbObjects.informationtypes).forEach((obj) => {
               if(element.businessObject && element.businessObject.name && (obj.name == element.businessObject.name))
               {
                  dbElemType = 'InformationType';
                  dbElemId = obj.id;
               }
            });
            break;
         case 'bpmn:DataStoreReference':
            Object.values(bpmnDbObjects.assets).forEach((obj) => {
               if(element.businessObject && element.businessObject.name && (obj.name == element.businessObject.name))
               {
                  dbElemType = 'Asset';
                  dbElemId = obj.id;
               }
            });
            break;
         default:
            break;
      }
      return function(entries){
         delete entries["append.intermediate-event"];
         delete entries["replace"];
         switch(element.type)
         {
            case 'bpmn:Task':
               break;
            default:
               delete entries["append.text-annotation"];
               break;
         }


         return entries;
      };
   }
}

CustomContextPadProvider.$inject = [   "config",
                                       "injector",
                                       "eventBus",
                                       "contextPad",
                                       "modeling",
                                       "elementFactory",
                                       "connect",
                                       "create",
                                       "popupMenu",
                                       "canvas",
                                       "rules",
                                       "translate",
                                       "appendPreview"
                                       ];
