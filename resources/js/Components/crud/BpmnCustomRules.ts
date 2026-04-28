import { is } from 'bpmn-js/lib/util/ModelUtil';
import RuleProvider from 'diagram-js/lib/features/rules/RuleProvider';
import type EventBus from 'diagram-js/lib/core/EventBus';

type AnyObj = Record<string, unknown>;

export interface BpmnEditorLabels {
    activateHandTool: string;
    activateLassoTool: string;
    activateSpaceTool: string;
    activateGlobalConnectTool: string;
    createStartEvent: string;
    createEndEvent: string;
    createExclusiveGateway: string;
    createTask: string;
    createDataObjectReference: string;
    createDataStoreReference: string;
    createSubProcess: string;
    appendTask: string;
    appendEndEvent: string;
    appendExclusiveGateway: string;
    appendDataObjectReference: string;
    appendDataStoreReference: string;
    appendSubProcess: string;
    connect: string;
    delete: string;
}

export const DEFAULT_BPMN_EDITOR_LABELS: BpmnEditorLabels = {
    activateHandTool: 'Activate hand tool',
    activateLassoTool: 'Activate lasso tool',
    activateSpaceTool: 'Activate create/remove space tool',
    activateGlobalConnectTool: 'Activate global connect tool',
    createStartEvent: 'Create start event',
    createEndEvent: 'Create end event',
    createExclusiveGateway: 'Create exclusive gateway',
    createTask: 'Create task',
    createDataObjectReference: 'Create data object reference',
    createDataStoreReference: 'Create data store reference',
    createSubProcess: 'Create expanded sub-process',
    appendTask: 'Append task',
    appendEndEvent: 'Append end event',
    appendExclusiveGateway: 'Append exclusive gateway',
    appendDataObjectReference: 'Append data object reference',
    appendDataStoreReference: 'Append data store reference',
    appendSubProcess: 'Append sub-process',
    connect: 'Start connection',
    delete: 'Delete',
};

type ConnectionDefinition = {
    sourceType: string;
    targetType: string;
    connection: Record<string, string>;
};

type ShapeDefinition = {
    type: string;
    options?: Record<string, unknown>;
};

type PaletteActionDefinition = ShapeDefinition & {
    actionId: string;
    group: string;
    className: string;
    getTitle: (labels: BpmnEditorLabels) => string;
};

type ContextPadActionDefinition = ShapeDefinition & {
    actionId: string;
    className: string;
    getTitle: (labels: BpmnEditorLabels) => string;
};

export type BpmnShapeNameResolver = (shapeType: string) => Promise<string | null>;

type BpmnUiModule = {
    __init__: string[];
    bpmnEditorLabels: ['value', BpmnEditorLabels];
    bpmnShapeNameResolver: ['value', BpmnShapeNameResolver | null];
    bpmnCustomRulesProvider: ['type', typeof BpmnCustomRulesProvider];
    bpmnDirectEditingRestrictionProvider: ['type', typeof BpmnDirectEditingRestrictionProvider];
    paletteProvider: ['type', typeof RestrictedBpmnPaletteProvider];
    contextPadProvider: ['type', typeof RestrictedBpmnContextPadProvider];
};

const HIGH_PRIORITY = 1500;

const ALLOWED_CREATABLE_TYPES = new Set([
    'bpmn:Task',
    'bpmn:DataObjectReference',
    'bpmn:DataStoreReference',
    'bpmn:ExclusiveGateway',
    'bpmn:StartEvent',
    'bpmn:EndEvent',
    'bpmn:SubProcess',
]);

const ALLOWED_CONNECTIONS: ConnectionDefinition[] = [
    { sourceType: 'bpmn:StartEvent', targetType: 'bpmn:Task', connection: { type: 'bpmn:SequenceFlow' } },
    { sourceType: 'bpmn:Task', targetType: 'bpmn:Task', connection: { type: 'bpmn:SequenceFlow' } },
    { sourceType: 'bpmn:Task', targetType: 'bpmn:ExclusiveGateway', connection: { type: 'bpmn:SequenceFlow' } },
    { sourceType: 'bpmn:ExclusiveGateway', targetType: 'bpmn:Task', connection: { type: 'bpmn:SequenceFlow' } },
    { sourceType: 'bpmn:Task', targetType: 'bpmn:EndEvent', connection: { type: 'bpmn:SequenceFlow' } },
    { sourceType: 'bpmn:Task', targetType: 'bpmn:DataObjectReference', connection: { type: 'bpmn:Association', associationDirection: 'None' } },
    { sourceType: 'bpmn:DataObjectReference', targetType: 'bpmn:DataStoreReference', connection: { type: 'bpmn:Association', associationDirection: 'None' } },
    { sourceType: 'bpmn:Task', targetType: 'bpmn:SubProcess', connection: { type: 'bpmn:Association', associationDirection: 'None' } },
];

const PALETTE_ACTIONS: PaletteActionDefinition[] = [
    {
        actionId: 'create.start-event',
        group: 'event',
        className: 'bpmn-icon-start-event-none',
        type: 'bpmn:StartEvent',
        getTitle: (labels) => labels.createStartEvent,
    },
    {
        actionId: 'create.end-event',
        group: 'event',
        className: 'bpmn-icon-end-event-none',
        type: 'bpmn:EndEvent',
        getTitle: (labels) => labels.createEndEvent,
    },
    {
        actionId: 'create.exclusive-gateway',
        group: 'gateway',
        className: 'bpmn-icon-gateway-none',
        type: 'bpmn:ExclusiveGateway',
        getTitle: (labels) => labels.createExclusiveGateway,
    },
    {
        actionId: 'create.task',
        group: 'activity',
        className: 'bpmn-icon-task',
        type: 'bpmn:Task',
        getTitle: (labels) => labels.createTask,
    },
    {
        actionId: 'create.data-object-reference',
        group: 'data-object',
        className: 'bpmn-icon-data-object',
        type: 'bpmn:DataObjectReference',
        getTitle: (labels) => labels.createDataObjectReference,
    },
    {
        actionId: 'create.data-store-reference',
        group: 'data-store',
        className: 'bpmn-icon-data-store',
        type: 'bpmn:DataStoreReference',
        getTitle: (labels) => labels.createDataStoreReference,
    },
    {
        actionId: 'create.sub-process',
        group: 'activity',
        className: 'bpmn-icon-subprocess-collapsed',
        type: 'bpmn:SubProcess',
        options: { isExpanded: false },
        getTitle: (labels) => labels.createSubProcess,
    }
];

const CONTEXT_PAD_APPEND_ACTIONS: Record<string, ContextPadActionDefinition[]> = {
    'bpmn:StartEvent': [
        {
            actionId: 'append.task',
            className: 'bpmn-icon-task',
            type: 'bpmn:Task',
            getTitle: (labels) => labels.appendTask,
        },
    ],
    'bpmn:Task': [
        {
            actionId: 'append.task',
            className: 'bpmn-icon-task',
            type: 'bpmn:Task',
            getTitle: (labels) => labels.appendTask,
        },
        {
            actionId: 'append.exclusive-gateway',
            className: 'bpmn-icon-gateway-none',
            type: 'bpmn:ExclusiveGateway',
            getTitle: (labels) => labels.appendExclusiveGateway,
        },
        {
            actionId: 'append.end-event',
            className: 'bpmn-icon-end-event-none',
            type: 'bpmn:EndEvent',
            getTitle: (labels) => labels.appendEndEvent,
        },
        {
            actionId: 'append.data-object-reference',
            className: 'bpmn-icon-data-object',
            type: 'bpmn:DataObjectReference',
            getTitle: (labels) => labels.appendDataObjectReference,
        },
        {
            actionId: 'append.sub-process',
            className: 'bpmn-icon-subprocess-expanded',
            type: 'bpmn:SubProcess',
            options: { isExpanded: false },
            getTitle: (labels) => labels.appendSubProcess,
        },
    ],
    'bpmn:ExclusiveGateway': [
        {
            actionId: 'append.task',
            className: 'bpmn-icon-task',
            type: 'bpmn:Task',
            getTitle: (labels) => labels.appendTask,
        },
    ],
    'bpmn:DataObjectReference': [
        {
            actionId: 'append.data-store-reference',
            className: 'bpmn-icon-data-store',
            type: 'bpmn:DataStoreReference',
            getTitle: (labels) => labels.appendDataStoreReference,
        },
    ],
};

function getElementType(element: unknown): string | null {
    if (!element || typeof element !== 'object') {
        return null;
    }

    const candidate = element as {
        type?: unknown;
        businessObject?: {
            $type?: unknown;
        };
    };

    if (typeof candidate.type === 'string') {
        return candidate.type;
    }

    if (typeof candidate.businessObject?.$type === 'string') {
        return candidate.businessObject.$type;
    }

    return null;
}

function isInternalEditorShape(element: unknown): boolean {
    const elementType = getElementType(element);

    return elementType === null || elementType === 'label' || !elementType.startsWith('bpmn:');
}

function isAllowedCreatableShape(element: unknown): boolean {
    const elementType = getElementType(element);

    if (elementType === null) {
        return false;
    }

    return ALLOWED_CREATABLE_TYPES.has(elementType);
}

function requiresResolvedName(shapeType: string): boolean {
    return shapeType === 'bpmn:DataObjectReference' || shapeType === 'bpmn:DataStoreReference';
}

async function resolveShapeName(shapeType: string, resolver: BpmnShapeNameResolver | null): Promise<string | null | undefined> {
    if (!requiresResolvedName(shapeType) || !resolver) {
        return undefined;
    }

    return resolver(shapeType);
}

function createShape(
    elementFactory: { createShape: (attributes: Record<string, unknown>) => unknown },
    definition: ShapeDefinition,
    resolvedName?: string,
): unknown {
    const shape = elementFactory.createShape({
        type: definition.type,
        ...(resolvedName ? { name: resolvedName } : {}),
        ...definition.options,
    });

    if (!resolvedName || !shape || typeof shape !== 'object') {
        return shape;
    }

    const shapeCandidate = shape as { name?: unknown; businessObject?: unknown };
    shapeCandidate.name = resolvedName;

    const businessObject = shapeCandidate.businessObject;
    if (!businessObject || typeof businessObject !== 'object') {
        return shape;
    }

    const businessObjectCandidate = businessObject as {
        name?: unknown;
        dataObjectRef?: unknown;
        dataStoreRef?: unknown;
    };

    businessObjectCandidate.name = resolvedName;

    const dataObjectRef = businessObjectCandidate.dataObjectRef;
    if (dataObjectRef && typeof dataObjectRef === 'object') {
        (dataObjectRef as { name?: unknown }).name = resolvedName;
    }

    const dataStoreRef = businessObjectCandidate.dataStoreRef;
    if (dataStoreRef && typeof dataStoreRef === 'object') {
        (dataStoreRef as { name?: unknown }).name = resolvedName;
    }

    return shape;
}

function createPaletteAction(
    create: { start: (event: MouseEvent, shape: unknown) => void },
    elementFactory: { createShape: (attributes: Record<string, unknown>) => unknown },
    definition: PaletteActionDefinition,
    labels: BpmnEditorLabels,
    nameResolver: BpmnShapeNameResolver | null,
) {
    const startCreate = async (event: MouseEvent) => {
        const resolvedName = await resolveShapeName(definition.type, nameResolver);
        if (resolvedName === null) {
            return;
        }

        const shape = createShape(elementFactory, definition, resolvedName ?? undefined);
        create.start(event, shape);
    };

    return {
        group: definition.group,
        className: definition.className,
        title: definition.getTitle(labels),
        action: {
            dragstart: startCreate,
            click: startCreate,
        },
    };
}

function createContextPadAppendAction(
    create: { start: (event: MouseEvent, shape: unknown, context: { source: unknown }) => void },
    elementFactory: { createShape: (attributes: Record<string, unknown>) => unknown },
    definition: ContextPadActionDefinition,
    labels: BpmnEditorLabels,
    nameResolver: BpmnShapeNameResolver | null,
) {
    const append = async (event: MouseEvent, element: unknown) => {
        const resolvedName = await resolveShapeName(definition.type, nameResolver);
        if (resolvedName === null) {
            return;
        }

        const shape = createShape(elementFactory, definition, resolvedName ?? undefined);
        create.start(event, shape, { source: element });
    };

    return {
        group: 'model',
        className: definition.className,
        title: definition.getTitle(labels),
        action: {
            dragstart: append,
            click: append,
        },
    };
}

function getAllowedConnection(source: unknown, target: unknown): Record<string, string> | false {
    if (is(source, 'bpmn:TextAnnotation') || is(target, 'bpmn:TextAnnotation')) {
        return { type: 'bpmn:Association', associationDirection: 'None' };
    }

    const allowedConnection = ALLOWED_CONNECTIONS.find(({ sourceType, targetType }) => is(source, sourceType) && is(target, targetType));

    return allowedConnection?.connection ?? false;
}

class BpmnCustomRulesProvider extends RuleProvider {
    static $inject = ['eventBus'];

    constructor(eventBus: EventBus) {
        super(eventBus);
    }

    override init(): void {
        this.addRule(['shape.create', 'shape.append'], HIGH_PRIORITY, (context: AnyObj) => {
            const shape = context.shape;

            if (isInternalEditorShape(shape)) {
                return undefined;
            }

            return isAllowedCreatableShape(shape) ? undefined : false;
        });

        this.addRule(['elements.create'], HIGH_PRIORITY, (context: AnyObj) => {
            const elements = Array.isArray(context.elements) ? context.elements : [];

            if (elements.length === 0) {
                return undefined;
            }

            const hasDisallowedElement = elements.some((element) => !isInternalEditorShape(element) && !isAllowedCreatableShape(element));

            return hasDisallowedElement ? false : undefined;
        });

        this.addRule(['shape.replace'], HIGH_PRIORITY, (context: AnyObj) => {
            const target = context.target;

            if (isInternalEditorShape(target)) {
                return undefined;
            }

            return isAllowedCreatableShape(target) ? undefined : false;
        });

        this.addRule(['connection.create'], HIGH_PRIORITY, (context: AnyObj) => {
            const source = context.source;
            const target = context.target;
            const hints = typeof context.hints === 'object' && context.hints !== null ? (context.hints as { targetParent?: unknown; targetAttach?: unknown }) : {};
            const targetParent = hints.targetParent;
            const targetAttach = hints.targetAttach;

            if (targetAttach) {
                return false;
            }

            if (target && targetParent && typeof target === 'object') {
                (target as { parent?: unknown }).parent = targetParent;
            }

            if (!source || !target) {
                return undefined;
            }

            try {
                return getAllowedConnection(source, target);
            } finally {
                if (target && targetParent && typeof target === 'object') {
                    (target as { parent?: unknown }).parent = null;
                }
            }
        });
    }
}

class BpmnDirectEditingRestrictionProvider {
    static $inject = ['eventBus'];

    constructor(eventBus: EventBus) {
        eventBus.on('element.dblclick', HIGH_PRIORITY, (event: AnyObj) => {
            const element = event.element;
            if (!element) {
                return undefined;
            }

            const elementType = getElementType(element);

            return elementType === 'label' || (elementType !== null && elementType.startsWith('bpmn:')) ? false : undefined;
        });
    }
}

class RestrictedBpmnPaletteProvider {
    static $inject = ['palette', 'create', 'elementFactory', 'spaceTool', 'lassoTool', 'handTool', 'globalConnect', 'bpmnEditorLabels', 'bpmnShapeNameResolver'];

    private readonly create: { start: (event: MouseEvent, shape: unknown) => void };

    private readonly elementFactory: { createShape: (attributes: Record<string, unknown>) => unknown };

    private readonly spaceTool: { activateSelection: (event: MouseEvent) => void };

    private readonly lassoTool: { activateSelection: (event: MouseEvent) => void };

    private readonly handTool: { activateHand: (event: MouseEvent) => void };

    private readonly globalConnect: { start: (event: MouseEvent) => void };

    private readonly labels: BpmnEditorLabels;

    private readonly nameResolver: BpmnShapeNameResolver | null;

    constructor(
        palette: { registerProvider: (provider: RestrictedBpmnPaletteProvider) => void },
        create: { start: (event: MouseEvent, shape: unknown) => void },
        elementFactory: { createShape: (attributes: Record<string, unknown>) => unknown },
        spaceTool: { activateSelection: (event: MouseEvent) => void },
        lassoTool: { activateSelection: (event: MouseEvent) => void },
        handTool: { activateHand: (event: MouseEvent) => void },
        globalConnect: { start: (event: MouseEvent) => void },
        labels: BpmnEditorLabels,
        nameResolver: BpmnShapeNameResolver | null,
    ) {
        this.create = create;
        this.elementFactory = elementFactory;
        this.spaceTool = spaceTool;
        this.lassoTool = lassoTool;
        this.handTool = handTool;
        this.globalConnect = globalConnect;
        this.labels = labels;
        this.nameResolver = nameResolver;

        palette.registerProvider(this);
    }

    getPaletteEntries() {
        const actions: Record<string, unknown> = {
            'hand-tool': {
                group: 'tools',
                className: 'bpmn-icon-hand-tool',
                title: this.labels.activateHandTool,
                action: {
                    click: (event: MouseEvent) => {
                        this.handTool.activateHand(event);
                    },
                },
            },
            'lasso-tool': {
                group: 'tools',
                className: 'bpmn-icon-lasso-tool',
                title: this.labels.activateLassoTool,
                action: {
                    click: (event: MouseEvent) => {
                        this.lassoTool.activateSelection(event);
                    },
                },
            },
            'space-tool': {
                group: 'tools',
                className: 'bpmn-icon-space-tool',
                title: this.labels.activateSpaceTool,
                action: {
                    click: (event: MouseEvent) => {
                        this.spaceTool.activateSelection(event);
                    },
                },
            },
            'global-connect-tool': {
                group: 'tools',
                className: 'bpmn-icon-connection-multi',
                title: this.labels.activateGlobalConnectTool,
                action: {
                    click: (event: MouseEvent) => {
                        this.globalConnect.start(event);
                    },
                },
            },
            'tool-separator': {
                group: 'tools',
                separator: true,
            },
        };

        for (const definition of PALETTE_ACTIONS) {
            actions[definition.actionId] = createPaletteAction(this.create, this.elementFactory, definition, this.labels, this.nameResolver);
        }

        return actions;
    }
}

class RestrictedBpmnContextPadProvider {
    static $inject = ['contextPad', 'modeling', 'elementFactory', 'connect', 'create', 'rules', 'bpmnEditorLabels', 'bpmnShapeNameResolver'];

    private readonly modeling: { removeElements: (elements: unknown[]) => void };

    private readonly elementFactory: { createShape: (attributes: Record<string, unknown>) => unknown };

    private readonly connect: { start: (event: MouseEvent, element: unknown) => void };

    private readonly create: { start: (event: MouseEvent, shape: unknown, context: { source: unknown }) => void };

    private readonly rules: { allowed: (action: string, context: Record<string, unknown>) => boolean | unknown[] };

    private readonly labels: BpmnEditorLabels;

    private readonly nameResolver: BpmnShapeNameResolver | null;

    constructor(
        contextPad: { registerProvider: (provider: RestrictedBpmnContextPadProvider) => void },
        modeling: { removeElements: (elements: unknown[]) => void },
        elementFactory: { createShape: (attributes: Record<string, unknown>) => unknown },
        connect: { start: (event: MouseEvent, element: unknown) => void },
        create: { start: (event: MouseEvent, shape: unknown, context: { source: unknown }) => void },
        rules: { allowed: (action: string, context: Record<string, unknown>) => boolean | unknown[] },
        labels: BpmnEditorLabels,
        nameResolver: BpmnShapeNameResolver | null,
    ) {
        this.modeling = modeling;
        this.elementFactory = elementFactory;
        this.connect = connect;
        this.create = create;
        this.rules = rules;
        this.labels = labels;
        this.nameResolver = nameResolver;

        contextPad.registerProvider(this);
    }

    getContextPadEntries(element?: { businessObject?: unknown }) {
        const actions: Record<string, unknown> = {};

        if (!element) {
            return actions;
        }

        if (this.isDeleteAllowed([element])) {
            actions.delete = {
                group: 'edit',
                className: 'bpmn-icon-trash',
                title: this.labels.delete,
                action: {
                    click: (_event: MouseEvent, currentElement: unknown) => {
                        if (!currentElement) {
                            return;
                        }

                        this.modeling.removeElements([currentElement]);
                    },
                },
            };
        }

        if (getElementType(element) === 'label') {
            return actions;
        }

        actions.connect = {
            group: 'connect',
            className: 'bpmn-icon-connection-multi',
            title: this.labels.connect,
            action: {
                click: (event: MouseEvent, currentElement: unknown) => {
                    if (!currentElement) {
                        return;
                    }

                    this.connect.start(event, currentElement);
                },
            },
        };

        const elementType = getElementType(element.businessObject ?? element);
        const appendDefinitions = elementType ? CONTEXT_PAD_APPEND_ACTIONS[elementType] ?? [] : [];

        for (const definition of appendDefinitions) {
            actions[definition.actionId] = createContextPadAppendAction(this.create, this.elementFactory, definition, this.labels, this.nameResolver);
        }

        return actions;
    }

    getMultiElementContextPadEntries(elements: unknown[]) {
        if (!this.isDeleteAllowed(elements)) {
            return {};
        }

        return {
            delete: {
                group: 'edit',
                className: 'bpmn-icon-trash',
                title: this.labels.delete,
                action: {
                    click: () => {
                        this.modeling.removeElements(elements.slice());
                    },
                },
            },
        };
    }

    private isDeleteAllowed(elements: unknown[]): boolean {
        const deletableElements = elements.filter((element) => Boolean(element));

        if (deletableElements.length === 0) {
            return false;
        }

        const allowed = this.rules.allowed('elements.delete', { elements: deletableElements });

        if (Array.isArray(allowed)) {
            return deletableElements.every((element) => allowed.includes(element));
        }

        return Boolean(allowed);
    }
}

export default function createBpmnEditorRestrictionsModule(
    labels: Partial<BpmnEditorLabels> = {},
    options: { resolveShapeName?: BpmnShapeNameResolver } = {},
): BpmnUiModule {
    return {
        __init__: ['bpmnCustomRulesProvider', 'bpmnDirectEditingRestrictionProvider', 'paletteProvider', 'contextPadProvider'],
        bpmnEditorLabels: ['value', { ...DEFAULT_BPMN_EDITOR_LABELS, ...labels }],
        bpmnShapeNameResolver: ['value', options.resolveShapeName ?? null],
        bpmnCustomRulesProvider: ['type', BpmnCustomRulesProvider],
        bpmnDirectEditingRestrictionProvider: ['type', BpmnDirectEditingRestrictionProvider],
        paletteProvider: ['type', RestrictedBpmnPaletteProvider],
        contextPadProvider: ['type', RestrictedBpmnContextPadProvider],
    };
}

