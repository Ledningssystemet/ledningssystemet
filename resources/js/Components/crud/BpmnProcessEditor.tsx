import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';
import { LocateFixed } from 'lucide-react';
import Modeler from 'bpmn-js/lib/Modeler';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import { cn } from '@/lib/utils';

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
}

interface BpmnProcessEditorProps {
  xml: string | null;
  className?: string;
  invalidMessage: string;
  fitButtonLabel?: string;
}

type BpmnXmlResult = {
  xml?: string;
};

const BpmnProcessEditor = forwardRef<BpmnProcessEditorHandle, BpmnProcessEditorProps>(
  ({ xml, className, invalidMessage, fitButtonLabel }, ref) => {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const modelerRef = useRef<Modeler | null>(null);
    const [renderError, setRenderError] = useState<string | null>(null);

    const fitViewport = () => {
      const modeler = modelerRef.current;
      if (!modeler) return;
      const canvas = modeler.get('canvas') as { zoom: (scale: 'fit-viewport', center: 'auto') => void };
      canvas.zoom('fit-viewport', 'auto');
    };

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
    }));

    useEffect(() => {
      if (!containerRef.current) return undefined;

      const modeler = new Modeler({
        container: containerRef.current,
      });
      modelerRef.current = modeler;

      return () => {
        modeler.destroy();
        modelerRef.current = null;
      };
    }, []);

    useEffect(() => {
      const modeler = modelerRef.current;
      if (!modeler) return;

      let cancelled = false;

      const importDiagram = async () => {
        setRenderError(null);

        try {
          await modeler.importXML(xml && xml.trim() !== '' ? xml : DEFAULT_BPMN_XML);
          if (cancelled) return;
          fitViewport();
        } catch {
          if (!cancelled) {
            setRenderError(invalidMessage);
          }
        }
      };

      void importDiagram();

      return () => {
        cancelled = true;
      };
    }, [invalidMessage, xml]);

    return (
      <div className={cn('relative overflow-hidden rounded-lg border border-border bg-muted/20', className)}>
        {fitButtonLabel && (
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

        {renderError && (
          <div className="absolute inset-0 z-10 flex items-center justify-center bg-background/90 p-4 text-center text-sm text-destructive">
            {renderError}
          </div>
        )}

        <div ref={containerRef} className="bpmn-editor h-full min-h-[50rem] w-full" />
      </div>
    );
  }
);

BpmnProcessEditor.displayName = 'BpmnProcessEditor';

export default BpmnProcessEditor;

