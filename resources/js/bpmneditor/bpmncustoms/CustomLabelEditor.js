var cleModeling = null;
var cleRegistry = null;

export default function CustomLabelEditor(eventBus, modeling, registry) {
   
   cleModeling = modeling;
   cleRegistry = registry;
   
   eventBus.on('element.dblclick', 15000, (event) => {

      switch(event.element.type)
      {
         case 'bpmn:DataObjectReference':
         case 'bpmn:DataStoreReference':
         case 'bpmn:SubProcess':
            if(window.showSelectLabel)
            {
               window.showSelectLabel(event.element, cleModeling, cleRegistry.getAll());
               event.stopPropagation();
               return false;
            }
            break;
         case 'label':
            event.stopPropagation();
            break;
         default:
            break;
      }
   });
}

CustomLabelEditor.$inject = [ 'eventBus', 'modeling', 'elementRegistry' ];
