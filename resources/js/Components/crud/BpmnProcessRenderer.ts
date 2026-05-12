import type { BpmnSidebarElement, BpmnElementStyle } from './BpmnPropertiesPanelModule';
import { getElementColor, getElementName, getElementStyle, isTaskElement, isValidHexColor, normalizeHexColor } from './BpmnPropertiesPanelModule';

const TASK_BACKGROUND_IMAGE_ATTR = 'data-ledning-task-background-image';
const NEW_REFERENCE_MARKER_ATTR = 'data-ledning-new-reference-marker';
const NEW_REFERENCE_MARKER_CLASS = 'ledning-new-reference-marker';
export const MAX_TASK_BACKGROUND_IMAGE_PADDING = 50;

export interface BpmnRendererHost {
  get: (serviceName: string) => unknown;
}

export interface BpmnVisualStyleOptions {
  isUnsavedReferenceElement?: (element: BpmnSidebarElement | null) => boolean;
}

type BpmnCanvasApi = {
  zoom: (scale: 'fit-viewport', center: 'auto') => void;
  resized?: () => void;
};

type BpmnElementRegistryApi = {
  getAll: () => BpmnSidebarElement[];
  getGraphics: (element: BpmnSidebarElement | string) => SVGElement | null;
};

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

  return isLabelSidebarElement(element) ? (element.labelTarget ?? null) : element;
}

function getGraphicsForElement(elementRegistry: BpmnElementRegistryApi, element: BpmnSidebarElement): SVGElement | null {
  return typeof element.id === 'string' ? elementRegistry.getGraphics(element.id) ?? elementRegistry.getGraphics(element) : elementRegistry.getGraphics(element);
}

export function applyBpmnElementVisualStyle(
  host: BpmnRendererHost,
  element: BpmnSidebarElement,
  options: BpmnVisualStyleOptions = {},
): void {
  const style = getElementStyle(element) as BpmnElementStyle & { taskBackgroundImagePadding?: number };
  const elementRegistry = host.get('elementRegistry') as BpmnElementRegistryApi;
  const graphics = getGraphicsForElement(elementRegistry, element);

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

  visualChildren.filter((child) => child.getAttribute(TASK_BACKGROUND_IMAGE_ATTR) === 'true').forEach((child) => child.remove());
  visualChildren.filter((child) => child.getAttribute(NEW_REFERENCE_MARKER_ATTR) === 'true').forEach((child) => child.remove());

  if (isTaskElement(element) && style.taskBackgroundImage) {
    const x = Number(baseShape.getAttribute('x') ?? '0');
    const y = Number(baseShape.getAttribute('y') ?? '0');
    const width = Number(baseShape.getAttribute('width') ?? `${element.width ?? 100}`);
    const height = Number(baseShape.getAttribute('height') ?? `${element.height ?? 80}`);
    const rawPadding = typeof style.taskBackgroundImagePadding === 'number' && Number.isFinite(style.taskBackgroundImagePadding)
      ? style.taskBackgroundImagePadding
      : 5;
    const padding = Math.max(0, Math.min(rawPadding, MAX_TASK_BACKGROUND_IMAGE_PADDING, Math.floor(Math.min(width, height) / 2)));

    const image = document.createElementNS('http://www.w3.org/2000/svg', 'image');
    image.setAttribute(TASK_BACKGROUND_IMAGE_ATTR, 'true');
    image.setAttribute('href', style.taskBackgroundImage);
    image.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', style.taskBackgroundImage);
    image.setAttribute('x', `${x + padding}`);
    image.setAttribute('y', `${y + padding}`);
    image.setAttribute('width', `${Math.max(1, width - padding * 2)}`);
    image.setAttribute('height', `${Math.max(1, height - padding * 2)}`);
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

  const shouldMarkUnsavedReference = options.isUnsavedReferenceElement?.(element) ?? false;
  if (shouldMarkUnsavedReference) {
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
}

export function applyBpmnDiagramVisualStyles(host: BpmnRendererHost, options: BpmnVisualStyleOptions = {}): void {
  const elementRegistry = host.get('elementRegistry') as BpmnElementRegistryApi;
  elementRegistry.getAll().forEach((element) => {
    applyBpmnElementVisualStyle(host, element, options);
  });
}

export function applyBpmnDetailVisibility(host: BpmnRendererHost, showDetails: boolean): void {
  const elementRegistry = host.get('elementRegistry') as BpmnElementRegistryApi;
  const detailElementTypes = new Set(['bpmn:DataObjectReference', 'bpmn:DataStoreReference']);

  elementRegistry.getAll().forEach((element) => {
    const { type } = element;
    const elementType = type ?? '';
    let shouldHide = false;

    if (detailElementTypes.has(elementType)) {
      shouldHide = true;
    } else if (
      elementType === 'bpmn:Association' ||
      elementType === 'bpmn:DataInputAssociation' ||
      elementType === 'bpmn:DataOutputAssociation'
    ) {
      const sourceType = getSidebarElementType((element as BpmnSidebarElement & { source?: BpmnSidebarElement }).source ?? null);
      const targetType = getSidebarElementType((element as BpmnSidebarElement & { target?: BpmnSidebarElement }).target ?? null);
      if (detailElementTypes.has(sourceType) || detailElementTypes.has(targetType)) {
        shouldHide = true;
      }
    } else if (type === 'label') {
      const labelTargetType = getSidebarElementType(element.labelTarget ?? null);
      if (detailElementTypes.has(labelTargetType)) {
        shouldHide = true;
      }
    }

    if (shouldHide) {
      const gfx = elementRegistry.getGraphics(element.id ?? '');
      if (gfx) {
        gfx.style.display = showDetails ? '' : 'none';
      }
    }
  });
}

export function fitBpmnViewport(host: BpmnRendererHost): void {
  const canvas = host.get('canvas') as BpmnCanvasApi | null;
  canvas?.zoom('fit-viewport', 'auto');
}

export { getElementColor, getElementName, getEditableTextTarget, getSidebarElementType, isLabelSidebarElement, normalizeHexColor };


