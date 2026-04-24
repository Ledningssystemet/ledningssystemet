/**
 * Custom BPMN connection rules that restrict which elements can be connected.
 *
 * Allowed connections:
 *   startEvent          → task
 *   task                → task
 *   task                → exclusiveGateway
 *   exclusiveGateway    → task
 *   task                → endEvent
 *   task                → dataObjectReference
 *   dataObjectReference → dataStoreReference
 *   task                → subProcess
 *   any                 ↔ textAnnotation  (Association)
 */

import { is } from 'bpmn-js/lib/util/ModelUtil';
import { isAny } from "bpmn-js/lib/features/modeling/util/ModelingUtil";


// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyObj = Record<string, any>;

// Priority higher than bpmn-js built-in rules (default ~1000) so our rules win.
const HIGH_PRIORITY = 1500;

/** Directional whitelist for SequenceFlow / DataAssociation connections. */
const ALLOWED_CONNECTIONS: [string, string, any][] = [
    ['bpmn:StartEvent', 'bpmn:Task', { 'type': 'bpmn:SequenceFlow'}],
    ['bpmn:Task', 'bpmn:Task', { 'type': 'bpmn:SequenceFlow'}],
    ['bpmn:Task', 'bpmn:ExclusiveGateway', { 'type': 'bpmn:SequenceFlow'}],
    ['bpmn:ExclusiveGateway', 'bpmn:Task', { 'type': 'bpmn:SequenceFlow'}],
    ['bpmn:Task', 'bpmn:EndEvent', { 'type': 'bpmn:SequenceFlow'}],
    ['bpmn:Task', 'bpmn:DataObjectReference', { 'type': 'bpmn:Association' }],
    ['bpmn:DataObjectReference', 'bpmn:Task', { 'type': 'bpmn:Association' }],
    ['bpmn:DataObjectReference', 'bpmn:DataStoreReference', { 'type': 'bpmn:Association', associationDirection: 'None' }],
    ['bpmn:DataStoreReference', 'bpmn:DataObjectReference', { 'type': 'bpmn:Association', associationDirection: 'None' }],
    ['bpmn:Task', 'bpmn:SubProcess', { 'type': 'bpmn:SequenceFlow'}],
    ['bpmn:SubProcess', 'bpmn:Task', { 'type': 'bpmn:SequenceFlow'}],
    ['bpmn:DataStoreReference', 'bpmn:DataObjectReference', { 'type': 'bpmn:Association', associationDirection: 'None' }],
];
// ---------------------------------------------------------------------------
// Provider class — mirrors the pattern of diagram-js RuleProvider.
// We extend it by prototypal inheritance so that bpmn-js's dependency
// injection (didi) can resolve it correctly.
// ---------------------------------------------------------------------------

import RuleProvider from 'diagram-js/lib/features/rules/RuleProvider';
import type EventBus from 'diagram-js/lib/core/EventBus';
import {all} from "axios";

class BpmnCustomRulesProvider extends RuleProvider {
    static $inject = ['eventBus'];

    constructor(eventBus: AnyObj) {
        super(eventBus as unknown as EventBus);
    }

    // Called by RuleProvider constructor.
    override init(): void {
        this.addRule(['connection.create'], HIGH_PRIORITY, (context: AnyObj) => {
            const source = context.source,
                target = context.target,
                hints = context.hints || {},
                targetParent = hints.targetParent,
                targetAttach = hints.targetAttach;

            if (targetAttach) {
                return false;
            }

            if (targetParent) {
                target.parent = targetParent;
            }

            if (!source || !target) {
                // Keep default behavior until both sides are known.
                return undefined;
            }

           let retAssociationType: any = false;

            Object(ALLOWED_CONNECTIONS).values().forEach(([sourceType, targetType, associationType] : [string, string, any]) => {
                if(is(source, 'bpmn:TextAnnotation') || is(target, 'bpmn:TextAnnotation'))
                    retAssociationType = { 'type': 'bpmn:Association', associationDirection: 'None' };
                else if(is(source, sourceType) && is(target, targetType))
                    retAssociationType = associationType;
            });

            try {
                return retAssociationType;
            } finally {

                // unset temporary target parent
                if (targetParent) {
                    target.parent = null;
                }
            }
        });
    }
}

const BpmnCustomRulesModule = {
    __init__: ['bpmnCustomRulesProvider'],
    bpmnCustomRulesProvider: ['type', BpmnCustomRulesProvider],
};

export default BpmnCustomRulesModule;

