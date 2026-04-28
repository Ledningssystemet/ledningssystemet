export interface BpmnPropertyEditorLabels {
    panelTitle: string;
    appearanceGroup: string;
    sizeGroup: string;
    textGroup: string;
    width: string;
    height: string;
    fontSize: string;
    textColor: string;
    taskBackgroundImage: string;
    taskBackgroundImageFit: string;
    taskBackgroundImageFitCrop: string;
    taskBackgroundImageFitContain: string;
    taskBackgroundImageFitStretch: string;
    taskBackgroundImagePadding: string;
    clearTaskBackgroundImage: string;
    name: string;
    fillColor: string;
    strokeColor: string;
    invalidHexColor: string;
    invalidNumber: string;
    invalidTextValue: string;
    lockedReferenceNameMessage: string;
}

export const DEFAULT_BPMN_PROPERTY_EDITOR_LABELS: BpmnPropertyEditorLabels = {
    panelTitle: 'Element properties',
    appearanceGroup: 'Appearance',
    sizeGroup: 'Size',
    textGroup: 'Text',
    width: 'Width',
    height: 'Height',
    fontSize: 'Font size',
    textColor: 'Text color',
    taskBackgroundImage: 'Task background image',
    taskBackgroundImageFit: 'Image fit',
    taskBackgroundImageFitCrop: 'Crop',
    taskBackgroundImageFitContain: 'Resize (contain)',
    taskBackgroundImageFitStretch: 'Resize (stretch)',
    taskBackgroundImagePadding: 'Image padding (px)',
    clearTaskBackgroundImage: 'Clear image',
    name: 'Name',
    fillColor: 'Fill color',
    strokeColor: 'Stroke color',
    invalidHexColor: 'Use a HEX color, for example #0ea5e9.',
    invalidNumber: 'Use a positive number.',
    invalidTextValue: 'Use only letters, spaces, underscores, commas, periods, hyphens, and Swedish letters.',
    lockedReferenceNameMessage: 'The object name cannot be changed for already saved objects.',
};

const SIDEBAR_SUPPORTED_TYPES = new Set([
    'bpmn:StartEvent',
    'bpmn:EndEvent',
    'bpmn:Task',
    'bpmn:DataObjectReference',
    'bpmn:DataStoreReference',
    'bpmn:ExclusiveGateway',
    'bpmn:TextAnnotation',
    'label',
]);

const HEX_COLOR_PATTERN = /^#(?:[0-9a-fA-F]{3}){1,2}$/;
const BPMN_TEXT_PATTERN = /^[a-zA-Z_ åäöÅÄÖ\-.,]*$/;
const MAX_TASK_BACKGROUND_IMAGE_PADDING = 50;

function sanitizeOptionalString(value: unknown): string | undefined {
    if (typeof value !== 'string') {
        return undefined;
    }

    const trimmed = value.trim();

    if (trimmed === '' || trimmed.toLowerCase() === 'undefined' || trimmed.toLowerCase() === 'null') {
        return undefined;
    }

    return trimmed;
}

export type BpmnSidebarElement = {
    id?: string;
    type?: string;
    width?: number;
    height?: number;
    x?: number;
    y?: number;
    businessObject?: {
        name?: string;
        text?: string;
        $type?: string;
        extensionElements?: {
            values?: unknown[];
        };
    };
    label?: BpmnSidebarElement;
    labels?: BpmnSidebarElement[];
    labelTarget?: BpmnSidebarElement;
    di?: {
        get?: (property: string) => string | undefined;
    };
};

export interface BpmnElementStyle {
    textColor?: string;
    fontSize?: number;
    taskBackgroundImage?: string;
    taskBackgroundImageFit?: 'crop' | 'contain' | 'stretch';
    taskBackgroundImagePadding?: number;
}

const STYLE_NAMESPACE_URI = 'https://ledningssystemet.se/schema/bpmn-style/1.0';
const STYLE_TYPE = 'ledning:style';

type ModdleApi = {
    create: (type: string, attributes: Record<string, unknown>) => {
        values?: unknown[];
    };
    createAny: (type: string, namespaceUri: string, attributes: Record<string, unknown>) => Record<string, unknown>;
};

export function resolvePropertyEditorLabels(labels: Partial<BpmnPropertyEditorLabels> = {}): BpmnPropertyEditorLabels {
    return { ...DEFAULT_BPMN_PROPERTY_EDITOR_LABELS, ...labels };
}

export function isSupportedPropertySidebarElement(element: unknown): boolean {
    return SIDEBAR_SUPPORTED_TYPES.has(getElementType(element));
}

export function getElementType(element: unknown): string {
    if (!element || typeof element !== 'object') {
        return '';
    }

    const candidate = element as BpmnSidebarElement;

    if (typeof candidate.type === 'string') {
        return candidate.type;
    }

    if (typeof candidate.businessObject?.$type === 'string') {
        return candidate.businessObject.$type;
    }

    return '';
}

export function isLabelElement(element: BpmnSidebarElement | null): boolean {
    return getElementType(element) === 'label';
}

export function isTextAnnotationElement(element: BpmnSidebarElement | null): boolean {
    return getElementType(element) === 'bpmn:TextAnnotation';
}

export function getEditableTextElement(element: BpmnSidebarElement | null): BpmnSidebarElement | null {
    if (!element) {
        return null;
    }

    return isLabelElement(element) ? element.labelTarget ?? null : element;
}

export function getEditableTextProperty(element: BpmnSidebarElement | null): 'name' | 'text' {
    return isTextAnnotationElement(getEditableTextElement(element)) ? 'text' : 'name';
}

export function getElementName(element: BpmnSidebarElement | null): string {
    const editableElement = getEditableTextElement(element);

    if (!editableElement) {
        return '';
    }

    return getEditableTextProperty(editableElement) === 'text'
        ? editableElement.businessObject?.text ?? ''
        : editableElement.businessObject?.name ?? '';
}

export function getElementColor(element: BpmnSidebarElement | null, property: 'bioc:fill' | 'bioc:stroke'): string {
    const value = element?.di?.get?.(property);

    return sanitizeOptionalString(value) ?? '';
}

export function isTaskElement(element: BpmnSidebarElement | null): boolean {
    return getElementType(element) === 'bpmn:Task';
}

export function isValidBpmnTextValue(value: string): boolean {
    return BPMN_TEXT_PATTERN.test(value);
}

export function sanitizeBpmnTextValue(value: string): string {
    return value.replace(/[^a-zA-Z_ åäöÅÄÖ\-.,]/g, '');
}

export function parsePositiveNumber(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    if (!Number.isFinite(parsed) || parsed <= 0) {
        return null;
    }

    return parsed;
}

function getStyleExtensionContainer(element: BpmnSidebarElement | null): Record<string, unknown> | null {
    const editableElement = getEditableTextElement(element);
    const extensionValues = Array.isArray(editableElement?.businessObject?.extensionElements?.values)
        ? editableElement.businessObject?.extensionElements?.values
        : [];

    for (const value of extensionValues) {
        if (!value || typeof value !== 'object') {
            continue;
        }

        const candidate = value as { $type?: unknown };
        if (candidate.$type === STYLE_TYPE) {
            return value as Record<string, unknown>;
        }
    }

    return null;
}

export function getElementStyle(element: BpmnSidebarElement | null): BpmnElementStyle {
    const style = getStyleExtensionContainer(element);

    if (!style) {
        return {};
    }

    const fontSizeCandidate = style.fontSize;
    const fitCandidate = style.taskBackgroundImageFit;
    const paddingCandidate = style.taskBackgroundImagePadding;

    const parsedPadding =
        typeof paddingCandidate === 'number'
            ? paddingCandidate
            : typeof paddingCandidate === 'string' && Number.isFinite(Number(paddingCandidate))
                ? Number(paddingCandidate)
                : undefined;

    return {
        textColor: sanitizeOptionalString(style.textColor),
        fontSize: typeof fontSizeCandidate === 'number'
            ? fontSizeCandidate
            : typeof fontSizeCandidate === 'string' && Number.isFinite(Number(fontSizeCandidate))
                ? Number(fontSizeCandidate)
                : undefined,
        taskBackgroundImage: sanitizeOptionalString(style.taskBackgroundImage),
        taskBackgroundImageFit:
            fitCandidate === 'contain' || fitCandidate === 'stretch' || fitCandidate === 'crop'
                ? fitCandidate
                : undefined,
        taskBackgroundImagePadding:
            typeof parsedPadding === 'number'
                ? Math.max(0, Math.min(parsedPadding, MAX_TASK_BACKGROUND_IMAGE_PADDING))
                : undefined,
    };
}

export function buildUpdatedElementStyle(element: BpmnSidebarElement | null, patch: Partial<BpmnElementStyle>): BpmnElementStyle {
    return {
        ...getElementStyle(element),
        ...patch,
    };
}

export function hasElementStyle(style: BpmnElementStyle): boolean {
    return Boolean(style.textColor || style.fontSize || style.taskBackgroundImage || style.taskBackgroundImageFit || style.taskBackgroundImagePadding !== undefined);
}

export function applyElementStyleToExtensions(
    moddle: ModdleApi,
    element: BpmnSidebarElement,
    style: BpmnElementStyle,
): { values?: unknown[] } | undefined {
    const businessObject = element.businessObject;

    if (!businessObject) {
        return undefined;
    }

    const existingExtensionElements = businessObject.extensionElements;
    const existingValues = Array.isArray(existingExtensionElements?.values) ? existingExtensionElements.values : [];
    const filteredValues = existingValues.filter((value) => {
        if (!value || typeof value !== 'object') {
            return true;
        }

        return (value as { $type?: unknown }).$type !== STYLE_TYPE;
    });

    if (!hasElementStyle(style)) {
        if (filteredValues.length === 0) {
            return undefined;
        }

        return moddle.create('bpmn:ExtensionElements', {
            values: filteredValues,
        });
    }

    const styleAttributes: Record<string, unknown> = {};

    if (typeof style.textColor === 'string') {
        styleAttributes.textColor = style.textColor;
    }

    if (typeof style.fontSize === 'number') {
        styleAttributes.fontSize = style.fontSize;
    }

    if (typeof style.taskBackgroundImage === 'string') {
        styleAttributes.taskBackgroundImage = style.taskBackgroundImage;
    }

    if (style.taskBackgroundImageFit === 'crop' || style.taskBackgroundImageFit === 'contain' || style.taskBackgroundImageFit === 'stretch') {
        styleAttributes.taskBackgroundImageFit = style.taskBackgroundImageFit;
    }

    if (typeof style.taskBackgroundImagePadding === 'number' && Number.isFinite(style.taskBackgroundImagePadding) && style.taskBackgroundImagePadding >= 0) {
        styleAttributes.taskBackgroundImagePadding = Math.min(style.taskBackgroundImagePadding, MAX_TASK_BACKGROUND_IMAGE_PADDING);
    }

    const styleElement = moddle.createAny(STYLE_TYPE, STYLE_NAMESPACE_URI, styleAttributes);

    return moddle.create('bpmn:ExtensionElements', {
        values: [...filteredValues, styleElement],
    });
}

export function isValidHexColor(value: string): boolean {
    const trimmed = value.trim();

    return trimmed === '' || HEX_COLOR_PATTERN.test(trimmed);
}

export function normalizeHexColor(value: string): string | undefined {
    const trimmed = value.trim();

    if (trimmed === '') {
        return undefined;
    }

    return HEX_COLOR_PATTERN.test(trimmed) ? trimmed : undefined;
}


