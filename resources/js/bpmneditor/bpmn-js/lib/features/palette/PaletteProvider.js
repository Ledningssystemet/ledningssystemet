import {
  assign
} from 'min-dash';

import { getDi } from '../../util/ModelUtil';

/**
 * @typedef {import('diagram-js/lib/features/palette/Palette').default} Palette
 * @typedef {import('diagram-js/lib/features/create/Create').default} Create
 * @typedef {import('diagram-js/lib/core/ElementFactory').default} ElementFactory
 * @typedef {import('../space-tool/BpmnSpaceTool').default} SpaceTool
 * @typedef {import('diagram-js/lib/features/lasso-tool/LassoTool').default} LassoTool
 * @typedef {import('diagram-js/lib/features/hand-tool/HandTool').default} HandTool
 * @typedef {import('diagram-js/lib/features/global-connect/GlobalConnect').default} GlobalConnect
 * @typedef {import('diagram-js/lib/i18n/translate/translate').default} Translate
 *
 * @typedef {import('diagram-js/lib/features/palette/Palette').PaletteEntries} PaletteEntries
 */

/**
 * A palette provider for BPMN 2.0 elements.
 *
 * @param {Palette} palette
 * @param {Create} create
 * @param {ElementFactory} elementFactory
 * @param {SpaceTool} spaceTool
 * @param {LassoTool} lassoTool
 * @param {HandTool} handTool
 * @param {GlobalConnect} globalConnect
 * @param {Translate} translate
 */
export default function PaletteProvider(
    palette, create, elementFactory,
    spaceTool, lassoTool, handTool,
    globalConnect, translate) {

  this._palette = palette;
  this._create = create;
  this._elementFactory = elementFactory;
  this._spaceTool = spaceTool;
  this._lassoTool = lassoTool;
  this._handTool = handTool;
  this._globalConnect = globalConnect;
  this._translate = translate;

  palette.registerProvider(this);
}

PaletteProvider.$inject = [
  'palette',
  'create',
  'elementFactory',
  'spaceTool',
  'lassoTool',
  'handTool',
  'globalConnect',
  'translate'
];

/**
 * @return {PaletteEntries}
 */
PaletteProvider.prototype.getPaletteEntries = function() {

  var actions = {},
      create = this._create,
      elementFactory = this._elementFactory,
      spaceTool = this._spaceTool,
      lassoTool = this._lassoTool,
      handTool = this._handTool,
      globalConnect = this._globalConnect,
      translate = this._translate;

  function createAction(type, group, className, title, options) {

    function createListener(event) {
      var shape = elementFactory.createShape(assign({ type: type }, options));
      if (options) {
        var di = getDi(shape);
        di.isExpanded = options.isExpanded;
      }

      create.start(event, shape);
    }

    var shortType = type.replace(/^bpmn:/, '');

    return {
      group: group,
      className: className,
      title: title || translate('Create {type}', { type: shortType }),
      action: {
        dragstart: createListener,
        click: createListener
      }
    };
  }

   // Create subprocess
   function createSubprocess(event) {
      var subProcess = elementFactory.createShape({
         type: 'bpmn:SubProcess',
         x: 0,
         y: 0,
         isExpanded: false
      });
      
      create.start(event, [ subProcess ], {
         hints: {
           
           autoSelect: [ subProcess ]
         }
      });
   }      

  assign(actions, {
    'hand-tool': {
      group: 'tools',
      className: 'bpmn-icon-hand-tool',
      title: translateString('Activate the hand tool'),
      action: {
        click: function(event) {
          handTool.activateHand(event);
        }
      }
    },
    'lasso-tool': {
      group: 'tools',
      className: 'bpmn-icon-lasso-tool',
      title: translateString('Activate the lasso tool'),
      action: {
        click: function(event) {
          lassoTool.activateSelection(event);
        }
      }
    },
    'space-tool': {
      group: 'tools',
      className: 'bpmn-icon-space-tool',
      title: translateString('Activate the create/remove space tool'),
      action: {
        click: function(event) {
          spaceTool.activateSelection(event);
        }
      }
    },
    'global-connect-tool': {
      group: 'tools',
      className: 'bpmn-icon-connection-multi',
      title: translateString('Activate the global connect tool'),
      action: {
        click: function(event) {
          globalConnect.start(event);
        }
      }
    },
    'chart.zoomin': {
       group: 'tools',
       className: 'material-rounded-symbols-zoomin bpmn-zoomin-button',
       title: translateString('Zoom in'),
       action: {
          click: function(){}
       }
    },
    'chart.zoomout': {
       group: 'tools',
       className: 'material-rounded-symbols-zoomout bpmn-zoomout-button',
       title: translateString('Zoom out'),
       action: {
          click: function(){}
       }
    },
    'chart.zoomreset': {
       group: 'tools',
       className: 'material-rounded-symbols-zoomreset bpmn-zoomreset-button',
       title: translateString('Fit chart into screen'),
       action: {
          click: function(){}
       }
    },
    'create.start-event': createAction(
      'bpmn:StartEvent', 'event', 'bpmn-icon-start-event-none',
      translateString('Create StartEvent')
    ),
    'create.end-event': createAction(
      'bpmn:EndEvent', 'event', 'bpmn-icon-end-event-none',
      translateString('Create EndEvent')
    ),
    'create.exclusive-gateway': createAction(
      'bpmn:ExclusiveGateway', 'gateway', 'bpmn-icon-gateway-none',
      translateString('Create Gateway')
    ),
    'create.task': createAction(
      'bpmn:Task', 'activity', 'bpmn-icon-task',
      translateString('Create Task')
    ),
    'create.data-object': createAction(
      'bpmn:DataObjectReference', 'data-object', 'bpmn-icon-data-object',
      translateString('Create information type')
    ),
    'create.data-store': createAction(
      'bpmn:DataStoreReference', 'data-store', 'bpmn-icon-data-store',
      translateString('Create asset')
    ),
   'create.subprocess': {
      group: 'activity',
      className: 'bpmn-icon-subprocess-collapsed',
      title: translateString('Create process link'),
      action: {
         dragstart: createSubprocess,
         click: createSubprocess
      }
   },
  });

  return actions;
};
