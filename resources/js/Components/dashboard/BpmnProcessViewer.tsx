import { useEffect, useRef, useState } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import NavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import '../../../css/bpmn-font-local.css';
import { cn } from '@/Lib/utils';
import {
  applyBpmnDiagramVisualStyles,
  applyBpmnDetailVisibility,
  fitBpmnViewport,
} from '@/Components/crud/BpmnProcessRenderer';

interface BpmnProcessViewerProps {
  xml: string | null;
  className?: string;
  emptyMessage: string;
  invalidMessage: string;
  fitButtonLabel?: string;
  showDetailsLabel?: string;
  onSubProcessClick?: (name: string) => void;
}

export default function BpmnProcessViewer({
  xml,
  className,
  emptyMessage,
  invalidMessage,
  fitButtonLabel,
  showDetailsLabel,
  onSubProcessClick,
}: BpmnProcessViewerProps) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const viewerRef = useRef<NavigatedViewer | null>(null);
  const [renderError, setRenderError] = useState<string | null>(null);
  const [showDetails, setShowDetails] = useState(false);

  // Keep refs so event handlers and async render always see current values
  const showDetailsRef = useRef(false);
  const onSubProcessClickRef = useRef(onSubProcessClick);

  useEffect(() => {
    onSubProcessClickRef.current = onSubProcessClick;
  }, [onSubProcessClick]);

  const fitViewport = () => {
    const viewer = viewerRef.current;
    if (!viewer) return;

    fitBpmnViewport(viewer);
  };

  // Create viewer once and register subProcess click handler
  useEffect(() => {
    if (!containerRef.current) return undefined;

    const viewer = new NavigatedViewer({ container: containerRef.current });
    viewerRef.current = viewer;

    const eventBus = viewer.get('eventBus') as {
      on: (event: string, handler: (e: { element: { type: string; businessObject?: { name?: string } } }) => void) => void;
    };

    eventBus.on('element.click', (event) => {
      const { element } = event;
      if (element?.type === 'bpmn:SubProcess') {
        const name = element.businessObject?.name;
        if (name) {
          onSubProcessClickRef.current?.(name);
        }
      }
    });

    return () => {
      viewer.destroy();
      viewerRef.current = null;
    };
  }, []);

  // Re-render when XML changes
  useEffect(() => {
    const viewer = viewerRef.current;
    const container = containerRef.current;
    if (!viewer || !container) return;

    let cancelled = false;

    const renderDiagram = async () => {
      setRenderError(null);

      if (!xml) {
        viewer.clear();
        return;
      }

      try {
        await viewer.importXML(xml);
        if (cancelled) return;

        fitViewport();
        applyBpmnDiagramVisualStyles(viewer);
        applyBpmnDetailVisibility(viewer, showDetailsRef.current);
      } catch {
        if (!cancelled) {
          setRenderError(invalidMessage);
          viewer.clear();
        }
      }
    };

    void renderDiagram();
    return () => { cancelled = true; };
  }, [invalidMessage, xml]);

  // Apply visibility whenever showDetails or xml changes
  useEffect(() => {
    showDetailsRef.current = showDetails;
    const viewer = viewerRef.current;
    if (viewer && xml) {
      applyBpmnDetailVisibility(viewer, showDetails);
    }
  }, [showDetails, xml]);

  return (
    <div className={cn('relative overflow-hidden rounded-lg border border-border bg-muted/20', className)}>
      {xml && showDetailsLabel && (
        <label className="absolute left-2 top-2 z-20 inline-flex cursor-pointer select-none items-center gap-1.5 rounded-md border border-border bg-background/95 px-2 py-1 text-xs text-foreground shadow-sm transition-colors hover:bg-muted">
          <input
            type="checkbox"
            checked={showDetails}
            onChange={(e) => setShowDetails(e.target.checked)}
            className="h-3 w-3 accent-primary"
          />
          {showDetailsLabel}
        </label>
      )}
      {xml && fitButtonLabel && (
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
      {!xml && (
        <div className="absolute inset-0 z-10 flex items-center justify-center p-4 text-center text-sm text-muted-foreground">
          {emptyMessage}
        </div>
      )}
      {renderError && (
        <div className="absolute inset-0 z-10 flex items-center justify-center bg-background/90 p-4 text-center text-sm text-destructive">
          {renderError}
        </div>
      )}
      <div
        ref={containerRef}
        className={cn('bpmn-viewer h-full min-h-[18rem] w-full', !xml && 'opacity-0')}
      />
    </div>
  );
}

