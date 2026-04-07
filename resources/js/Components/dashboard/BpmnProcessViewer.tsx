import { useEffect, useRef, useState } from 'react';
import { LocateFixed } from 'lucide-react';
import NavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import { cn } from '@/lib/utils';

interface BpmnProcessViewerProps {
  xml: string | null;
  className?: string;
  emptyMessage: string;
  invalidMessage: string;
  fitButtonLabel?: string;
}

export default function BpmnProcessViewer({
  xml,
  className,
  emptyMessage,
  invalidMessage,
  fitButtonLabel,
}: BpmnProcessViewerProps) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const viewerRef = useRef<NavigatedViewer | null>(null);
  const [renderError, setRenderError] = useState<string | null>(null);

  const fitViewport = () => {
    const viewer = viewerRef.current;
    if (!viewer) {
      return;
    }

    const canvas = viewer.get('canvas') as { zoom: (scale: 'fit-viewport', center: 'auto') => void };
    canvas.zoom('fit-viewport', 'auto');
  };

  useEffect(() => {
    if (!containerRef.current) {
      return undefined;
    }

    const viewer = new NavigatedViewer({
      container: containerRef.current,
    });

    viewerRef.current = viewer;

    return () => {
      viewer.destroy();
      viewerRef.current = null;
    };
  }, []);

  useEffect(() => {
    const viewer = viewerRef.current;
    const container = containerRef.current;

    if (!viewer || !container) {
      return;
    }

    let cancelled = false;

    const renderDiagram = async () => {
      setRenderError(null);

      if (!xml) {
        viewer.clear();
        return;
      }

      try {
        await viewer.importXML(xml);

        if (cancelled) {
          return;
        }

        fitViewport();
      } catch {
        if (!cancelled) {
          setRenderError(invalidMessage);
          viewer.clear();
        }
      }
    };

    void renderDiagram();

    return () => {
      cancelled = true;
    };
  }, [invalidMessage, xml]);

  return (
    <div className={cn('relative overflow-hidden rounded-lg border border-border bg-muted/20', className)}>
      {xml && fitButtonLabel && (
        <button
          type="button"
          onClick={fitViewport}
          className="absolute right-2 top-2 z-20 inline-flex items-center gap-1 rounded-md border border-border bg-background/95 px-2 py-1 text-xs text-foreground shadow-sm transition-colors hover:bg-muted"
          title={fitButtonLabel}
          aria-label={fitButtonLabel}
        >
          <LocateFixed className="h-3.5 w-3.5" />
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

