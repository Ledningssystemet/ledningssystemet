import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState, type ChangeEvent } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { Button } from '@/components/ui/button';
import Modeler from 'bpmn-js/lib/Modeler';
import 'bpmn-js/dist/assets/diagram-js.css';
import '../../../css/bpmn-font-local.css';
import { cn } from '@/lib/utils';
import createBpmnEditorRestrictionsModule, { type BpmnEditorLabels } from './BpmnCustomRules';
import {
  applyElementStyleToExtensions,
  buildUpdatedElementStyle,
  type BpmnSidebarElement,
  type BpmnElementStyle,
  type BpmnPropertyEditorLabels,
  getElementStyle,
  getElementColor,
  getElementName,
  isSupportedPropertySidebarElement,
  isTaskElement,
  isValidHexColor,
  parsePositiveNumber,
  normalizeHexColor,
  resolvePropertyEditorLabels,
} from './BpmnPropertiesPanelModule';

const DEFAULT_BPMN_XML = `<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  id="Definitions_1"
                  targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
  </bpmn:process>
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="StartEvent_1_di" bpmnElement="StartEvent_1">
        <dc:Bounds x="173" y="102" width="36" height="36" />
      </bpmndi:BPMNShape>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>`;

export interface BpmnProcessEditorHandle {
  exportXml: () => Promise<string | null>;
  markCurrentStateAsSaved: () => void;
  markCurrentReferencesAsSaved: () => void;
}

interface BpmnProcessEditorProps {
  xml: string | null;
  className?: string;
  invalidMessage: string;
  fitButtonLabel?: string;
  editorLabels?: Partial<BpmnEditorLabels>;
  propertyEditorLabels?: Partial<BpmnPropertyEditorLabels>;
  informationTypeOptions?: BpmnNamedOption[];
  assetOptions?: BpmnNamedOption[];
  creationDialogLabels?: Partial<BpmnCreationDialogLabels>;
  onDirtyStateChange?: (isDirty: boolean) => void;
}

export interface BpmnNamedOption {
  id: number | string;
  name: string;
}

interface BpmnCreationDialogLabels {
  informationTypeTitle: string;
  assetTitle: string;
  selectExistingLabel: string;
  selectExistingPlaceholder: string;
  customNameLabel: string;
  customNamePlaceholder: string;
  applyName: string;
  cancel: string;
  nameRequired: string;
  invalidName: string;
}

const DEFAULT_BPMN_CREATION_DIALOG_LABELS: BpmnCreationDialogLabels = {
  informationTypeTitle: 'Choose information type name',
  assetTitle: 'Choose asset name',
  selectExistingLabel: 'Select existing name',
  selectExistingPlaceholder: 'No options found',
  customNameLabel: 'Or enter a custom name',
  customNamePlaceholder: 'Enter name',
  applyName: 'Use name',
  cancel: 'Cancel',
  nameRequired: 'Choose an existing name or enter a custom name.',
  invalidName: 'Use only letters, spaces, underscores, commas, periods, hyphens, and Swedish letters.',
};

type PendingCreationNameDialog = {
  shapeType: 'bpmn:DataObjectReference' | 'bpmn:DataStoreReference';
  options: BpmnNamedOption[];
};

type PendingReferenceCreation = {
  shapeType: 'bpmn:DataObjectReference' | 'bpmn:DataStoreReference';
};

type BpmnXmlResult = {
  xml?: string;
};

const EDITOR_HEIGHT = 800;

type BpmnCanvasApi = {
  zoom: (scale: 'fit-viewport', center: 'auto') => void;
  resized: () => void;
};

type BpmnEventBusApi = {
  on: (eventName: string, priority: number, handler: (event: Record<string, unknown>) => void) => void;
};

type BpmnElementRegistryApi = {
  getGraphics: (element: BpmnSidebarElement) => SVGElement | null;
  getAll: () => BpmnSidebarElement[];
};

type BpmnModdleApi = {
  create: (type: string, attributes: Record<string, unknown>) => {
    values?: unknown[];
  };
  createAny: (type: string, namespaceUri: string, attributes: Record<string, unknown>) => Record<string, unknown>;
};

type BpmnModelingApi = {
  updateProperties: (element: BpmnSidebarElement, properties: Record<string, unknown>) => void;
  resizeShape: (element: BpmnSidebarElement, bounds: { x: number; y: number; width: number; height: number }) => void;
  setColor: (elements: BpmnSidebarElement[], colors: { fill?: string; stroke?: string }) => void;
};

const TASK_BACKGROUND_IMAGE_ATTR = 'data-ledning-task-background-image';
const NEW_REFERENCE_MARKER_ATTR = 'data-ledning-new-reference-marker';
const NEW_REFERENCE_MARKER_CLASS = 'ledning-new-reference-marker';
const MAX_TASK_BACKGROUND_IMAGE_PADDING = 50;
const BPMN_TEXT_PATTERN = /^[a-zA-Z_ åäöÅÄÖ\-.,]*$/;

function isReferenceShapeType(type: string): type is 'bpmn:DataObjectReference' | 'bpmn:DataStoreReference' {
  return type === 'bpmn:DataObjectReference' || type === 'bpmn:DataStoreReference';
}

function getSidebarElementType(element: BpmnSidebarElement | null): string {
  if (!element) {
    return '';
  }

  if (typeof element.type === 'string') {
    return element.type;
  }

  return typeof element.businessObject?.$type === 'string' ? element.businessObject.$type : '';
}

function isLabelSidebarElement(element: BpmnSidebarElement | null): boolean {
  return getSidebarElementType(element) === 'label';
}

function getEditableTextTarget(element: BpmnSidebarElement | null): BpmnSidebarElement | null {
  if (!element) {
    return null;
  }

  return isLabelSidebarElement(element) ? ((element as BpmnSidebarElement & { labelTarget?: BpmnSidebarElement }).labelTarget ?? null) : element;
}

function getEditableTextKey(element: BpmnSidebarElement | null): 'name' | 'text' {
  return getSidebarElementType(getEditableTextTarget(element)) === 'bpmn:TextAnnotation' ? 'text' : 'name';
}

function isValidBpmnTextInput(value: string): boolean {
  return BPMN_TEXT_PATTERN.test(value);
}

function sanitizeBpmnTextInput(value: string): string {
  return value.replace(/[^a-zA-Z_ åäöÅÄÖ\-.,]/g, '');
}

function resolveCreationDialogLabels(labels: Partial<BpmnCreationDialogLabels> = {}): BpmnCreationDialogLabels {
  return { ...DEFAULT_BPMN_CREATION_DIALOG_LABELS, ...labels };
}

function findFirstValidCreationName(options: BpmnNamedOption[]): string {
  return options.find((option) => isValidBpmnTextInput(option.name.trim()))?.name ?? '';
}

const BpmnProcessEditor = forwardRef<BpmnProcessEditorHandle, BpmnProcessEditorProps>(
  ({
    xml,
    className,
    invalidMessage,
    fitButtonLabel,
    editorLabels,
    propertyEditorLabels,
    informationTypeOptions = [],
    assetOptions = [],
    creationDialogLabels,
    onDirtyStateChange,
  }, ref) => {
    const wrapperRef = useRef<HTMLDivElement | null>(null);
    const containerRef = useRef<HTMLDivElement | null>(null);
    const modelerRef = useRef<Modeler | null>(null);
    const informationTypeOptionsRef = useRef(informationTypeOptions);
    const assetOptionsRef = useRef(assetOptions);
    const pendingCreationDialogResolveRef = useRef<((value: string | null) => void) | null>(null);
    const pendingReferenceCreationRef = useRef<PendingReferenceCreation | null>(null);
    const unsavedReferenceElementIdsRef = useRef<Set<string>>(new Set());
    const creationDialogLabelsRef = useRef(resolveCreationDialogLabels(creationDialogLabels));
    const onDirtyStateChangeRef = useRef(onDirtyStateChange);
    const isDirtyRef = useRef(false);
    const isImportingRef = useRef(false);
    const [pendingCreationDialog, setPendingCreationDialog] = useState<PendingCreationNameDialog | null>(null);
    const [selectedCreationName, setSelectedCreationName] = useState('');
    const [customCreationName, setCustomCreationName] = useState('');
    const [creationDialogError, setCreationDialogError] = useState<string | null>(null);

    const requestShapeName = useCallback((shapeType: 'bpmn:DataObjectReference' | 'bpmn:DataStoreReference') => {
      const options = shapeType === 'bpmn:DataObjectReference' ? informationTypeOptionsRef.current : assetOptionsRef.current;

      pendingCreationDialogResolveRef.current?.(null);
      pendingReferenceCreationRef.current = null;
      setPendingCreationDialog({ shapeType, options });
      setSelectedCreationName(findFirstValidCreationName(options));
      setCustomCreationName('');
      setCreationDialogError(null);

      return new Promise<string | null>((resolve) => {
        pendingCreationDialogResolveRef.current = resolve;
      });
    }, []);

    const editorModuleRef = useRef(createBpmnEditorRestrictionsModule(editorLabels, {
      resolveShapeName: async (shapeType: string) => {
        if (shapeType === 'bpmn:DataObjectReference' || shapeType === 'bpmn:DataStoreReference') {
          return requestShapeName(shapeType);
        }

        return null;
      },
    }));
    const propertyLabelsRef = useRef(resolvePropertyEditorLabels(propertyEditorLabels));
    const [renderError, setRenderError] = useState<string | null>(null);
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);
    const [selectedElement, setSelectedElement] = useState<BpmnSidebarElement | null>(null);
    const [nameValue, setNameValue] = useState('');
    const [widthValue, setWidthValue] = useState('');
    const [heightValue, setHeightValue] = useState('');
    const [fillColorValue, setFillColorValue] = useState('');
    const [strokeColorValue, setStrokeColorValue] = useState('');
    const [textColorValue, setTextColorValue] = useState('');
    const [fontSizeValue, setFontSizeValue] = useState('');
    const [taskBackgroundImageValue, setTaskBackgroundImageValue] = useState('');
    const [taskBackgroundImageFitValue, setTaskBackgroundImageFitValue] = useState<'crop' | 'contain' | 'stretch'>('crop');
    const [taskBackgroundImagePaddingValue, setTaskBackgroundImagePaddingValue] = useState('');
    const [colorValidationError, setColorValidationError] = useState<string | null>(null);
    const [numberValidationError, setNumberValidationError] = useState<string | null>(null);
    const [textValidationError, setTextValidationError] = useState<string | null>(null);
    const [isSelectedReferenceUnsaved, setIsSelectedReferenceUnsaved] = useState(false);

    useEffect(() => {
      informationTypeOptionsRef.current = informationTypeOptions;
    }, [informationTypeOptions]);

    useEffect(() => {
      assetOptionsRef.current = assetOptions;
    }, [assetOptions]);

    useEffect(() => {
      creationDialogLabelsRef.current = resolveCreationDialogLabels(creationDialogLabels);
    }, [creationDialogLabels]);

    useEffect(() => {
      onDirtyStateChangeRef.current = onDirtyStateChange;
    }, [onDirtyStateChange]);

    useEffect(() => {
      return () => {
        pendingCreationDialogResolveRef.current?.(null);
        pendingCreationDialogResolveRef.current = null;
        pendingReferenceCreationRef.current = null;
      };
    }, []);

    const isUnsavedReferenceElement = useCallback((element: BpmnSidebarElement | null): boolean => {
      const editableElement = getEditableTextTarget(element);
      const editableElementType = getSidebarElementType(editableElement);
      const editableElementId = editableElement?.id;

      if (!isReferenceShapeType(editableElementType)) {
        return false;
      }

      if (typeof editableElementId !== 'string' || editableElementId.trim() === '') {
        return false;
      }

      return unsavedReferenceElementIdsRef.current.has(editableElementId);
    }, []);

    const runAfterRender = useCallback((callback: () => void) => {
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          callback();
        });
      });
    }, []);

    const updateDirtyState = useCallback((nextDirty: boolean) => {
      if (isDirtyRef.current === nextDirty) {
        return;
      }

      isDirtyRef.current = nextDirty;
      onDirtyStateChangeRef.current?.(nextDirty);
    }, []);

    const handleTextValueChange = useCallback((value: string, setter: (nextValue: string) => void) => {
      const sanitizedValue = sanitizeBpmnTextInput(value);
      setter(sanitizedValue);
      setTextValidationError(
        sanitizedValue === value
          ? null
          : (propertyLabelsRef.current as BpmnPropertyEditorLabels & { invalidTextValue: string }).invalidTextValue,
      );

      return sanitizedValue;
    }, []);

    const applyElementVisualStyle = useCallback((element: BpmnSidebarElement) => {
      const modeler = modelerRef.current;
      if (!modeler) {
        return;
      }

      const style = getElementStyle(element) as BpmnElementStyle & { taskBackgroundImagePadding?: number };
      const elementRegistry = modeler.get('elementRegistry') as BpmnElementRegistryApi;
      const graphics = elementRegistry.getGraphics(element);

      if (!graphics) {
        return;
      }

      const textNodes = graphics.querySelectorAll('text, tspan');
      const hideText = isTaskElement(element) && Boolean(style.taskBackgroundImage);
      textNodes.forEach((node) => {
        if (hideText) {
          node.setAttribute('display', 'none');
        } else {
          node.removeAttribute('display');
        }

        if (style.textColor && isValidHexColor(style.textColor)) {
          node.setAttribute('fill', style.textColor);
        } else {
          node.removeAttribute('fill');
        }

        if (typeof style.fontSize === 'number' && Number.isFinite(style.fontSize) && style.fontSize > 0) {
          node.setAttribute('font-size', `${style.fontSize}`);
        } else {
          node.removeAttribute('font-size');
        }
      });

      const visual = graphics.querySelector('.djs-visual');
      const visualChildren = visual ? Array.from(visual.children).filter((child): child is SVGElement => child instanceof SVGElement) : [];
      const baseShape = isTaskElement(element)
        ? visualChildren.find((child) => child.tagName.toLowerCase() === 'rect') ?? null
        : visualChildren.find((child) => ['rect', 'circle', 'ellipse', 'polygon', 'path'].includes(child.tagName.toLowerCase())) ?? null;

      if (!visual || !baseShape) {
        return;
      }

      const existingBackgroundImages = visualChildren.filter((child) => child.getAttribute(TASK_BACKGROUND_IMAGE_ATTR) === 'true');
      existingBackgroundImages.forEach((child) => child.remove());

      const existingNewReferenceMarkers = visualChildren.filter((child) => child.getAttribute(NEW_REFERENCE_MARKER_ATTR) === 'true');
      existingNewReferenceMarkers.forEach((child) => child.remove());

      if (isTaskElement(element) && style.taskBackgroundImage) {
        const x = Number(baseShape.getAttribute('x') ?? '0');
        const y = Number(baseShape.getAttribute('y') ?? '0');
        const width = Number(baseShape.getAttribute('width') ?? `${element.width ?? 100}`);
        const height = Number(baseShape.getAttribute('height') ?? `${element.height ?? 80}`);
        const rawPadding = typeof style.taskBackgroundImagePadding === 'number' && Number.isFinite(style.taskBackgroundImagePadding)
          ? style.taskBackgroundImagePadding
          : 5;
        const padding = Math.max(0, Math.min(rawPadding, MAX_TASK_BACKGROUND_IMAGE_PADDING, Math.floor(Math.min(width, height) / 2)));
        const innerX = x + padding;
        const innerY = y + padding;
        const innerWidth = Math.max(1, width - padding * 2);
        const innerHeight = Math.max(1, height - padding * 2);

        const image = document.createElementNS('http://www.w3.org/2000/svg', 'image');
        image.setAttribute(TASK_BACKGROUND_IMAGE_ATTR, 'true');
        image.setAttribute('href', style.taskBackgroundImage);
        image.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', style.taskBackgroundImage);
        image.setAttribute('x', `${innerX}`);
        image.setAttribute('y', `${innerY}`);
        image.setAttribute('width', `${innerWidth}`);
        image.setAttribute('height', `${innerHeight}`);
        image.setAttribute(
          'preserveAspectRatio',
          style.taskBackgroundImageFit === 'contain'
            ? 'xMidYMid meet'
            : style.taskBackgroundImageFit === 'stretch'
              ? 'none'
              : 'xMidYMid slice',
        );
        image.setAttribute('pointer-events', 'none');

        const firstTextNode = visualChildren.find((child) => child.tagName.toLowerCase() === 'text');
        if (firstTextNode) {
          visual.insertBefore(image, firstTextNode);
        } else {
          visual.appendChild(image);
        }
      }

      const isUnsavedReference = isUnsavedReferenceElement(element);
      if (isUnsavedReference) {
        const marker = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        marker.setAttribute(NEW_REFERENCE_MARKER_ATTR, 'true');
        marker.setAttribute('class', NEW_REFERENCE_MARKER_CLASS);
        marker.setAttribute('x', '-15');
        marker.setAttribute('y', '20');
        marker.setAttribute('font-size', '24');
        marker.setAttribute('font-weight', '700');
        marker.setAttribute('fill', '#ff7000');
        marker.setAttribute('pointer-events', 'none');
        marker.textContent = '*';
        visual.appendChild(marker);
      }
    }, [isUnsavedReferenceElement]);



    const fitViewport = useCallback(() => {
      const modeler = modelerRef.current;
      if (!modeler) return;
      const canvas = modeler.get('canvas') as BpmnCanvasApi;
      canvas.zoom('fit-viewport', 'auto');
    }, []);

    useImperativeHandle(ref, () => ({
      exportXml: async (): Promise<string | null> => {
        const modeler = modelerRef.current;
        if (!modeler) {
          return null;
        }

        try {
          const { xml: exportedXml } = (await modeler.saveXML({ format: true })) as BpmnXmlResult;
          return exportedXml ?? null;
        } catch {
          return null;
        }
      },
      markCurrentStateAsSaved: () => {
        updateDirtyState(false);
      },
      markCurrentReferencesAsSaved: () => {
        const modeler = modelerRef.current;
        if (!modeler) {
          return;
        }

        const changedIds = Array.from(unsavedReferenceElementIdsRef.current);
        if (changedIds.length === 0) {
          return;
        }

        unsavedReferenceElementIdsRef.current.clear();
        setIsSelectedReferenceUnsaved(false);

        const elementRegistry = modeler.get('elementRegistry') as BpmnElementRegistryApi;
        runAfterRender(() => {
          for (const elementId of changedIds) {
            const element = elementRegistry.getAll().find((candidate) => candidate.id === elementId);
            if (element) {
              applyElementVisualStyle(element);
            }
          }
        });
      },
    }));

    useEffect(() => {
      if (!containerRef.current) return undefined;

      const modeler = new Modeler({
        container: containerRef.current,
        additionalModules: [editorModuleRef.current],
      });
      modelerRef.current = modeler;

      const eventBus = modeler.get('eventBus') as BpmnEventBusApi;
      eventBus.on('selection.changed', 1500, (event) => {
        const candidate = Array.isArray(event.newSelection) ? (event.newSelection[0] as BpmnSidebarElement | undefined) : undefined;
        const nextElement = candidate ?? null;

        if (!nextElement || !isSupportedPropertySidebarElement(nextElement)) {
          setSelectedElement(null);
          setNameValue('');
          setWidthValue('');
          setHeightValue('');
          setFillColorValue('');
          setStrokeColorValue('');
          setTextColorValue('');
          setFontSizeValue('');
          setTaskBackgroundImageValue('');
          setTaskBackgroundImageFitValue('crop');
          setTaskBackgroundImagePaddingValue('');
          setColorValidationError(null);
          setNumberValidationError(null);
          setTextValidationError(null);
          setIsSelectedReferenceUnsaved(false);
          setIsSidebarOpen(false);
          return;
        }

        const style = getElementStyle(nextElement);

        setSelectedElement(nextElement);
        setNameValue(getElementName(nextElement));
        setWidthValue(`${nextElement.width ?? ''}`);
        setHeightValue(`${nextElement.height ?? ''}`);
        setFillColorValue(getElementColor(nextElement, 'bioc:fill'));
        setStrokeColorValue(getElementColor(nextElement, 'bioc:stroke'));
        setTextColorValue(style.textColor ?? '');
        setFontSizeValue(style.fontSize ? `${style.fontSize}` : '');
        setTaskBackgroundImageValue(style.taskBackgroundImage ?? '');
        setTaskBackgroundImageFitValue(style.taskBackgroundImageFit ?? 'crop');
        setTaskBackgroundImagePaddingValue(
          style.taskBackgroundImage
            ? `${typeof style.taskBackgroundImagePadding === 'number' ? style.taskBackgroundImagePadding : 5}`
            : '',
        );
        setColorValidationError(null);
        setNumberValidationError(null);
        setTextValidationError(null);
        setIsSelectedReferenceUnsaved(isUnsavedReferenceElement(nextElement));
        setIsSidebarOpen(true);
      });

      eventBus.on('shape.added', 1500, (event) => {
        const createdElement = event.element as BpmnSidebarElement | undefined;
        const pendingReferenceCreation = pendingReferenceCreationRef.current;

        if (!createdElement || !pendingReferenceCreation) {
          return;
        }

        if (getSidebarElementType(createdElement) !== pendingReferenceCreation.shapeType) {
          return;
        }

        if (typeof createdElement.id === 'string' && createdElement.id.trim() !== '') {
          unsavedReferenceElementIdsRef.current.add(createdElement.id);
        }

        pendingReferenceCreationRef.current = null;
      });

      eventBus.on('element.changed', 1500, (event) => {
        const changedElement = event.element as BpmnSidebarElement | undefined;
        if (!changedElement) {
          return;
        }

        const changedElementWithLabels = changedElement as BpmnSidebarElement & {
          label?: BpmnSidebarElement;
          labels?: BpmnSidebarElement[];
        };

        runAfterRender(() => {
          applyElementVisualStyle(changedElement);
          if (changedElementWithLabels.label) {
            applyElementVisualStyle(changedElementWithLabels.label);
          }

          (changedElementWithLabels.labels ?? []).forEach((labelElement: BpmnSidebarElement) => {
            applyElementVisualStyle(labelElement);
          });
        });
      });

      eventBus.on('commandStack.changed', 1500, () => {
        if (isImportingRef.current) {
          return;
        }

        updateDirtyState(true);
      });

      return () => {
        modeler.destroy();
        modelerRef.current = null;
      };
    }, [applyElementVisualStyle, runAfterRender, updateDirtyState]);

    useEffect(() => {
      const modeler = modelerRef.current;
      if (!modeler) return;

      let cancelled = false;

      const importDiagram = async () => {
        isImportingRef.current = true;
        updateDirtyState(false);
        setRenderError(null);
        setIsSidebarOpen(false);
        setSelectedElement(null);
        unsavedReferenceElementIdsRef.current.clear();
        setNameValue('');
        setWidthValue('');
        setHeightValue('');
        setFillColorValue('');
        setStrokeColorValue('');
        setTextColorValue('');
        setFontSizeValue('');
        setTaskBackgroundImageValue('');
        setTaskBackgroundImageFitValue('crop');
        setTaskBackgroundImagePaddingValue('');
        setColorValidationError(null);
        setNumberValidationError(null);
        setTextValidationError(null);
          setIsSelectedReferenceUnsaved(false);
          pendingReferenceCreationRef.current = null;

        try {
          await modeler.importXML(xml && xml.trim() !== '' ? xml : DEFAULT_BPMN_XML);
          if (cancelled) return;

          const elementRegistry = modeler.get('elementRegistry') as BpmnElementRegistryApi;
          runAfterRender(() => {
            elementRegistry.getAll().forEach((element) => applyElementVisualStyle(element));
          });

          fitViewport();
        } catch {
          if (!cancelled) {
            setRenderError(invalidMessage);
          }
        } finally {
          isImportingRef.current = false;
        }
      };

      void importDiagram();

      return () => {
        cancelled = true;
        isImportingRef.current = false;
      };
    }, [applyElementVisualStyle, invalidMessage, runAfterRender, updateDirtyState, xml]);

    useEffect(() => {
      const modeler = modelerRef.current;
      if (!modeler) {
        return;
      }

      const canvas = modeler.get('canvas') as BpmnCanvasApi;
      canvas.resized();
    }, [isSidebarOpen]);

    const applyName = useCallback((value?: string) => {
      const modeler = modelerRef.current;
      if (!modeler || !selectedElement) {
        return;
      }

      const isSelectedReference = isReferenceShapeType(getSidebarElementType(getEditableTextTarget(selectedElement)));
      if (isSelectedReference && !isSelectedReferenceUnsaved) {
        return;
      }

      const editableElement = getEditableTextTarget(selectedElement);
      if (!editableElement) {
        return;
      }

      const modeling = modeler.get('modeling') as BpmnModelingApi;
      const nextNameValue = value ?? nameValue;
      if (!isValidBpmnTextInput(nextNameValue)) {
        setTextValidationError((propertyLabelsRef.current as BpmnPropertyEditorLabels & { invalidTextValue: string }).invalidTextValue);
        return;
      }

      setTextValidationError(null);

      modeling.updateProperties(editableElement, {
        [getEditableTextKey(selectedElement)]: nextNameValue.trim() === '' ? undefined : nextNameValue,
      });
    }, [isSelectedReferenceUnsaved, nameValue, selectedElement]);

    const applyColor = useCallback((property: 'fill' | 'stroke', value: string) => {
      const modeler = modelerRef.current;
      if (!modeler || !selectedElement) {
        return;
      }

      if (!isValidHexColor(value)) {
        setColorValidationError(propertyLabelsRef.current.invalidHexColor);
        return;
      }

      setColorValidationError(null);

      const modeling = modeler.get('modeling') as BpmnModelingApi;
      const normalized = normalizeHexColor(value);
      modeling.setColor([selectedElement], {
        // Avoid serializing the literal string "undefined" when a color is cleared.
        [property]: normalized ?? '',
      });
    }, [selectedElement]);

    const handleColorInputChange = useCallback(
      (property: 'fill' | 'stroke', value: string, setter: (nextValue: string) => void) => {
        setter(value);

        if (!isValidHexColor(value)) {
          return;
        }

        applyColor(property, value);
      },
      [applyColor],
    );

    const applySize = useCallback(() => {
      const modeler = modelerRef.current;
      if (!modeler || !selectedElement) {
        return;
      }

      const width = parsePositiveNumber(widthValue);
      const height = parsePositiveNumber(heightValue);

      if (width === null || height === null) {
        setNumberValidationError(propertyLabelsRef.current.invalidNumber);
        return;
      }

      setNumberValidationError(null);

      const modeling = modeler.get('modeling') as BpmnModelingApi;
      modeling.resizeShape(selectedElement, {
        x: selectedElement.x ?? 0,
        y: selectedElement.y ?? 0,
        width,
        height,
      });
    }, [heightValue, selectedElement, widthValue]);

    const applyTextStyle = useCallback((overrides?: { textColor?: string; fontSize?: string }) => {
      const modeler = modelerRef.current;
      if (!modeler || !selectedElement) {
        return;
      }

      const editableElement = getEditableTextTarget(selectedElement);
      if (!editableElement) {
        return;
      }

      const nextTextColorValue = overrides?.textColor ?? textColorValue;
      const nextFontSizeValue = overrides?.fontSize ?? fontSizeValue;

      if (!isValidHexColor(nextTextColorValue)) {
        setColorValidationError(propertyLabelsRef.current.invalidHexColor);
        return;
      }

      const parsedFontSize = parsePositiveNumber(nextFontSizeValue);
      if (nextFontSizeValue.trim() !== '' && parsedFontSize === null) {
        setNumberValidationError(propertyLabelsRef.current.invalidNumber);
        return;
      }

      setColorValidationError(null);
      setNumberValidationError(null);

      const modeling = modeler.get('modeling') as BpmnModelingApi;
      const moddle = modeler.get('moddle') as BpmnModdleApi;

      const stylePatch: Partial<BpmnElementStyle> = {
        textColor: normalizeHexColor(nextTextColorValue),
        fontSize: parsedFontSize ?? undefined,
      };

      const nextStyle = buildUpdatedElementStyle(editableElement, stylePatch);
      const extensionElements = applyElementStyleToExtensions(moddle, editableElement, nextStyle);

      modeling.updateProperties(editableElement, {
        extensionElements,
      });
    }, [fontSizeValue, selectedElement, textColorValue]);

    const onTaskBackgroundFileChanged = useCallback(async (event: ChangeEvent<HTMLInputElement>) => {
      const modeler = modelerRef.current;
      if (!modeler || !selectedElement || !isTaskElement(selectedElement)) {
        return;
      }

      const [file] = Array.from(event.target.files ?? []);
      if (!file) {
        return;
      }

      const fileAsDataUrl = await new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(file);
      }).catch(() => '');

      if (!fileAsDataUrl) {
        return;
      }

      const modeling = modeler.get('modeling') as BpmnModelingApi;
      const moddle = modeler.get('moddle') as BpmnModdleApi;
      const nextStyle = buildUpdatedElementStyle(selectedElement, {
        taskBackgroundImage: fileAsDataUrl,
        taskBackgroundImageFit: taskBackgroundImageFitValue,
        taskBackgroundImagePadding: 5,
      } as Partial<BpmnElementStyle>);
      const extensionElements = applyElementStyleToExtensions(moddle, selectedElement, nextStyle);

      setTaskBackgroundImageValue(fileAsDataUrl);
      setTaskBackgroundImagePaddingValue('5');
      modeling.updateProperties(selectedElement, {
        extensionElements,
      });

      runAfterRender(() => {
        applyElementVisualStyle(selectedElement);
      });

      event.target.value = '';
    }, [applyElementVisualStyle, runAfterRender, selectedElement, taskBackgroundImageFitValue]);

    const applyTaskBackgroundImageFit = useCallback((fit: 'crop' | 'contain' | 'stretch') => {
      const modeler = modelerRef.current;
      if (!modeler || !selectedElement || !isTaskElement(selectedElement)) {
        return;
      }

      setTaskBackgroundImageFitValue(fit);

      const moddle = modeler.get('moddle') as BpmnModdleApi;
      const modeling = modeler.get('modeling') as BpmnModelingApi;
      const nextStyle = buildUpdatedElementStyle(selectedElement, {
        taskBackgroundImageFit: fit,
      });
      const extensionElements = applyElementStyleToExtensions(moddle, selectedElement, nextStyle);

      modeling.updateProperties(selectedElement, {
        extensionElements,
      });

      runAfterRender(() => {
        applyElementVisualStyle(selectedElement);
      });
    }, [applyElementVisualStyle, runAfterRender, selectedElement]);

    const applyTaskBackgroundImagePadding = useCallback((paddingValue: string) => {
      const modeler = modelerRef.current;
      if (!modeler || !selectedElement || !isTaskElement(selectedElement) || taskBackgroundImageValue.trim() === '') {
        return;
      }

      const trimmed = paddingValue.trim();
      if (trimmed === '') {
        setNumberValidationError(null);
        return;
      }

      const parsedPadding = Number(trimmed);

      if (!Number.isFinite(parsedPadding) || parsedPadding < 0) {
        setNumberValidationError(propertyLabelsRef.current.invalidNumber);
        return;
      }

      setNumberValidationError(null);

      const roundedPadding = Math.round(parsedPadding);
      const clampedPadding = Math.min(roundedPadding, MAX_TASK_BACKGROUND_IMAGE_PADDING);
      const normalizedPadding = `${clampedPadding}`;
      if (normalizedPadding !== paddingValue) {
        setTaskBackgroundImagePaddingValue(normalizedPadding);
      }

      const moddle = modeler.get('moddle') as BpmnModdleApi;
      const modeling = modeler.get('modeling') as BpmnModelingApi;
      const nextStyle = buildUpdatedElementStyle(selectedElement, {
        taskBackgroundImagePadding: clampedPadding,
      } as Partial<BpmnElementStyle>);
      const extensionElements = applyElementStyleToExtensions(moddle, selectedElement, nextStyle);

      modeling.updateProperties(selectedElement, {
        extensionElements,
      });

      runAfterRender(() => {
        applyElementVisualStyle(selectedElement);
      });
    }, [applyElementVisualStyle, runAfterRender, selectedElement, taskBackgroundImageValue]);

    const clearTaskBackgroundImage = useCallback(() => {
      const modeler = modelerRef.current;
      if (!modeler || !selectedElement || !isTaskElement(selectedElement)) {
        return;
      }

      const modeling = modeler.get('modeling') as BpmnModelingApi;
      const moddle = modeler.get('moddle') as BpmnModdleApi;
      const nextStyle = buildUpdatedElementStyle(selectedElement, {
        taskBackgroundImage: undefined,
        taskBackgroundImageFit: undefined,
        taskBackgroundImagePadding: undefined,
      } as Partial<BpmnElementStyle>);
      const extensionElements = applyElementStyleToExtensions(moddle, selectedElement, nextStyle);

      setTaskBackgroundImageValue('');
      setTaskBackgroundImageFitValue('crop');
      setTaskBackgroundImagePaddingValue('');
      modeling.updateProperties(selectedElement, {
        extensionElements,
      });

      runAfterRender(() => {
        applyElementVisualStyle(selectedElement);
      });
    }, [applyElementVisualStyle, runAfterRender, selectedElement]);

    const cancelCreationDialog = useCallback(() => {
      pendingCreationDialogResolveRef.current?.(null);
      pendingCreationDialogResolveRef.current = null;
      pendingReferenceCreationRef.current = null;
      setPendingCreationDialog(null);
      setCreationDialogError(null);
      setCustomCreationName('');
      setSelectedCreationName('');
    }, []);

    const confirmCreationDialog = useCallback(() => {
      const customName = customCreationName.trim();
      const selectedName = selectedCreationName.trim();
      const resolvedName = customName !== '' ? customName : selectedName;

      if (resolvedName === '') {
        setCreationDialogError(creationDialogLabelsRef.current.nameRequired);
        return;
      }

      if (!isValidBpmnTextInput(resolvedName)) {
        setCreationDialogError(creationDialogLabelsRef.current.invalidName);
        return;
      }

      pendingReferenceCreationRef.current = pendingCreationDialog ? { shapeType: pendingCreationDialog.shapeType } : null;

      pendingCreationDialogResolveRef.current?.(resolvedName);
      pendingCreationDialogResolveRef.current = null;
      setPendingCreationDialog(null);
      setCreationDialogError(null);
      setCustomCreationName('');
      setSelectedCreationName('');
    }, [customCreationName, pendingCreationDialog, selectedCreationName]);

    const labels: BpmnPropertyEditorLabels = propertyLabelsRef.current;
    const lockedReferenceNameMessage = (labels as BpmnPropertyEditorLabels & { lockedReferenceNameMessage?: string }).lockedReferenceNameMessage ?? '';
    const creationLabels = creationDialogLabelsRef.current;
    const getColorPickerValue = (value: string, fallback: string) => normalizeHexColor(value) ?? fallback;
    const showAppearanceControls = !isLabelSidebarElement(selectedElement);
    const selectedReferenceType = getSidebarElementType(getEditableTextTarget(selectedElement));
    const isSelectedReferenceElement = isReferenceShapeType(selectedReferenceType);
    const isSelectedReferenceNameLocked = isSelectedReferenceElement && !isSelectedReferenceUnsaved;

    return (
      <div ref={wrapperRef} className={cn('relative h-full min-h-[50rem] overflow-hidden rounded-lg border border-border bg-muted/20', className)}>
        {fitButtonLabel && (
          <button
            type="button"
            onClick={fitViewport}
            className="absolute right-2 top-2 z-20 inline-flex items-center gap-1 rounded-md border border-border bg-background/95 px-2 py-1 text-xs text-foreground shadow-sm transition-colors hover:bg-muted"
            title={fitButtonLabel}
            aria-label={fitButtonLabel}
          >
            <MaterialSymbol name="my_location" className="h-3.5 w-3.5" />
            {fitButtonLabel}
          </button>
        )}

        {pendingCreationDialog && (
          <div className="absolute inset-0 z-30 flex items-center justify-center bg-background/75 p-4">
            <div className="w-full max-w-md space-y-4 rounded-lg border border-border bg-card p-4 shadow-xl" role="dialog" aria-modal="true">
              <h2 className="text-base font-semibold text-foreground">
                {pendingCreationDialog.shapeType === 'bpmn:DataObjectReference'
                  ? creationLabels.informationTypeTitle
                  : creationLabels.assetTitle}
              </h2>

              <div className="space-y-2">
                <label htmlFor="ledning-create-existing-name" className="block text-xs font-medium text-foreground">
                  {creationLabels.selectExistingLabel}
                </label>
                <select
                  id="ledning-create-existing-name"
                  value={selectedCreationName}
                  onChange={(event) => {
                    setSelectedCreationName(event.target.value);
                    setCreationDialogError(null);
                  }}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  {pendingCreationDialog.options.length === 0 && (
                    <option value="">{creationLabels.selectExistingPlaceholder}</option>
                  )}
                  {pendingCreationDialog.options.map((option) => (
                    <option key={`${option.id}`} value={option.name}>{option.name}</option>
                  ))}
                </select>
              </div>

              <div className="space-y-2">
                <label htmlFor="ledning-create-custom-name" className="block text-xs font-medium text-foreground">
                  {creationLabels.customNameLabel}
                </label>
                <input
                  id="ledning-create-custom-name"
                  type="text"
                  value={customCreationName}
                  placeholder={creationLabels.customNamePlaceholder}
                  onChange={(event) => {
                    const sanitizedValue = sanitizeBpmnTextInput(event.target.value);
                    setCustomCreationName(sanitizedValue);
                    setCreationDialogError(sanitizedValue === event.target.value ? null : creationLabels.invalidName);
                  }}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
              </div>

              {creationDialogError && (
                <p className="text-xs text-destructive">{creationDialogError}</p>
              )}

              <div className="flex items-center justify-end gap-2">
                <Button type="button" variant="outline" size="sm" onClick={cancelCreationDialog}>
                  {creationLabels.cancel}
                </Button>
                <Button type="button" size="sm" onClick={confirmCreationDialog}>
                  {creationLabels.applyName}
                </Button>
              </div>
            </div>
          </div>
        )}

        {renderError && (
          <div className="absolute inset-0 z-10 flex items-center justify-center bg-background/90 p-4 text-center text-sm text-destructive">
            {renderError}
          </div>
        )}

        <div className="flex h-full w-full">
          <div ref={containerRef} className="bpmn-editor min-w-0 flex-1" style={{ height: `${EDITOR_HEIGHT}px` }} />

          <aside
            className={cn(
              'h-full border-l border-border bg-background transition-all duration-150 ease-out',
              isSidebarOpen ? 'w-[320px] opacity-100' : 'w-0 opacity-0 pointer-events-none',
            )}
            aria-hidden={!isSidebarOpen}
          >
            <div className="h-full space-y-5 overflow-auto p-4" style={{ height: `${EDITOR_HEIGHT}px` }}>
              <div className="space-y-1">
                <h2 className="text-sm font-semibold text-foreground">{labels.panelTitle}</h2>
                <p className="text-xs text-muted-foreground">{selectedElement?.type ?? ''}</p>
              </div>

              <div className="space-y-2">
                <label htmlFor="ledning-name" className="block text-xs font-medium text-foreground">{labels.name}</label>
                <input
                  id="ledning-name"
                  type="text"
                  value={nameValue}
                  disabled={isSelectedReferenceNameLocked}
                  onChange={(event) => {
                    const sanitizedValue = handleTextValueChange(event.target.value, setNameValue);
                    applyName(sanitizedValue);
                  }}
                  onBlur={() => applyName()}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />

                {isSelectedReferenceNameLocked && (
                  <p className="text-xs text-muted-foreground">{lockedReferenceNameMessage}</p>
                )}

                {textValidationError && (
                  <p className="text-xs text-destructive">{textValidationError}</p>
                )}
              </div>

              <div className="space-y-3 rounded-md border border-border bg-muted/20 p-3">
                <p className="text-xs font-medium text-foreground">{labels.sizeGroup}</p>

                <div className="space-y-2">
                  <label htmlFor="ledning-width" className="block text-xs font-medium text-foreground">{labels.width}</label>
                  <input
                    id="ledning-width"
                    type="number"
                    min={1}
                    value={widthValue}
                    onChange={(event) => setWidthValue(event.target.value)}
                    onBlur={applySize}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  />
                </div>

                <div className="space-y-2">
                  <label htmlFor="ledning-height" className="block text-xs font-medium text-foreground">{labels.height}</label>
                  <input
                    id="ledning-height"
                    type="number"
                    min={1}
                    value={heightValue}
                    onChange={(event) => setHeightValue(event.target.value)}
                    onBlur={applySize}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  />
                </div>
              </div>

              {showAppearanceControls && (
                <div className="space-y-3 rounded-md border border-border bg-muted/20 p-3">
                <p className="text-xs font-medium text-foreground">{labels.appearanceGroup}</p>

                <div className="space-y-2">
                  <label htmlFor="ledning-fill-color" className="block text-xs font-medium text-foreground">{labels.fillColor}</label>
                  <div className="flex items-center gap-2">
                    <input
                      id="ledning-fill-color-picker"
                      type="color"
                      value={getColorPickerValue(fillColorValue, '#ffffff')}
                      onChange={(event) => {
                        setFillColorValue(event.target.value);
                        applyColor('fill', event.target.value);
                      }}
                      className="h-10 w-10 cursor-pointer rounded-md border border-input bg-background p-1"
                    />
                    <input
                      id="ledning-fill-color"
                      type="text"
                      value={fillColorValue}
                      onChange={(event) => handleColorInputChange('fill', event.target.value, setFillColorValue)}
                      onBlur={() => applyColor('fill', fillColorValue)}
                      placeholder="#ffffff"
                      className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <label htmlFor="ledning-stroke-color" className="block text-xs font-medium text-foreground">{labels.strokeColor}</label>
                  <div className="flex items-center gap-2">
                    <input
                      id="ledning-stroke-color-picker"
                      type="color"
                      value={getColorPickerValue(strokeColorValue, '#000000')}
                      onChange={(event) => {
                        setStrokeColorValue(event.target.value);
                        applyColor('stroke', event.target.value);
                      }}
                      className="h-10 w-10 cursor-pointer rounded-md border border-input bg-background p-1"
                    />
                    <input
                      id="ledning-stroke-color"
                      type="text"
                      value={strokeColorValue}
                      onChange={(event) => handleColorInputChange('stroke', event.target.value, setStrokeColorValue)}
                      onBlur={() => applyColor('stroke', strokeColorValue)}
                      placeholder="#000000"
                      className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                  </div>
                </div>

                {colorValidationError && (
                  <p className="text-xs text-destructive">{colorValidationError}</p>
                )}
                </div>
              )}

              <div className="space-y-3 rounded-md border border-border bg-muted/20 p-3">
                <p className="text-xs font-medium text-foreground">{labels.textGroup}</p>

                <div className="space-y-2">
                  <label htmlFor="ledning-text-color" className="block text-xs font-medium text-foreground">{labels.textColor}</label>
                  <div className="flex items-center gap-2">
                    <input
                      id="ledning-text-color-picker"
                      type="color"
                      value={getColorPickerValue(textColorValue, '#000000')}
                      onChange={(event) => {
                        setTextColorValue(event.target.value);
                        applyTextStyle({ textColor: event.target.value });
                      }}
                      onBlur={() => applyTextStyle()}
                      className="h-10 w-10 cursor-pointer rounded-md border border-input bg-background p-1"
                    />
                    <input
                      id="ledning-text-color"
                      type="text"
                      value={textColorValue}
                      onChange={(event) => {
                        setTextColorValue(event.target.value);
                        if (isValidHexColor(event.target.value)) {
                          applyTextStyle({ textColor: event.target.value });
                        }
                      }}
                      onBlur={() => applyTextStyle()}
                      placeholder="#000000"
                      className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <label htmlFor="ledning-font-size" className="block text-xs font-medium text-foreground">{labels.fontSize}</label>
                  <input
                    id="ledning-font-size"
                    type="number"
                    min={1}
                    value={fontSizeValue}
                    onChange={(event) => setFontSizeValue(event.target.value)}
                    onBlur={() => applyTextStyle()}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  />
                </div>

                {!showAppearanceControls && colorValidationError && (
                  <p className="text-xs text-destructive">{colorValidationError}</p>
                )}
              </div>

              {isTaskElement(selectedElement) && (
                <div className="space-y-3 rounded-md border border-border bg-muted/20 p-3">
                  <p className="text-xs font-medium text-foreground">{labels.taskBackgroundImage}</p>

                  {taskBackgroundImageValue.trim() !== '' && (
                    <div className="overflow-hidden rounded-md border border-border bg-background">
                      <img
                        src={taskBackgroundImageValue}
                        alt={labels.taskBackgroundImage}
                        className="h-24 w-full object-contain"
                      />
                    </div>
                  )}

                  <input
                    id="ledning-task-background-image"
                    type="file"
                    accept="image/*"
                    onChange={(event) => {
                      void onTaskBackgroundFileChanged(event);
                    }}
                    className="w-full text-xs text-foreground"
                  />

                  <div className="space-y-2">
                    <label htmlFor="ledning-task-background-image-fit" className="block text-xs font-medium text-foreground">
                      {labels.taskBackgroundImageFit}
                    </label>
                    <select
                      id="ledning-task-background-image-fit"
                      value={taskBackgroundImageFitValue}
                      onChange={(event) => applyTaskBackgroundImageFit(event.target.value as 'crop' | 'contain' | 'stretch')}
                      className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      <option value="crop">{labels.taskBackgroundImageFitCrop}</option>
                      <option value="contain">{labels.taskBackgroundImageFitContain}</option>
                      <option value="stretch">{labels.taskBackgroundImageFitStretch}</option>
                    </select>
                  </div>

                  {taskBackgroundImageValue.trim() !== '' && (
                    <div className="space-y-2">
                      <label htmlFor="ledning-task-background-image-padding" className="block text-xs font-medium text-foreground">
                        {labels.taskBackgroundImagePadding}
                      </label>
                      <input
                        id="ledning-task-background-image-padding"
                        type="number"
                        min={0}
                        max={50}
                        step={1}
                        value={taskBackgroundImagePaddingValue}
                        onChange={(event) => {
                          setTaskBackgroundImagePaddingValue(event.target.value);
                          applyTaskBackgroundImagePadding(event.target.value);
                        }}
                        onBlur={() => applyTaskBackgroundImagePadding(taskBackgroundImagePaddingValue)}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      />
                    </div>
                  )}

                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={clearTaskBackgroundImage}
                    disabled={taskBackgroundImageValue.trim() === ''}
                  >
                    {labels.clearTaskBackgroundImage}
                  </Button>
                </div>
              )}

              {numberValidationError && (
                <p className="text-xs text-destructive">{numberValidationError}</p>
              )}
            </div>
          </aside>
        </div>
      </div>
    );
  }
);

BpmnProcessEditor.displayName = 'BpmnProcessEditor';

export default BpmnProcessEditor;

