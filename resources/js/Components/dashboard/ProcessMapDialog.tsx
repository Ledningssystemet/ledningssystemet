import { useMemo } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import BpmnProcessViewer from './BpmnProcessViewer';
import { Check, RotateCcw } from 'lucide-react';
import { useTranslations } from '@/hooks/useTranslations';
import { DashboardProcessOption } from '@/types/dashboard';

interface ProcessMapDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  processName: string | null;
  xml: string | null;
  title: string;
  description: string;
  emptyMessage: string;
  invalidMessage: string;
  fitButtonLabel: string;
  showDetailsLabel?: string;
  processId?: number | null;
  processOptions: DashboardProcessOption[];
  selectedProcessId: number | null;
  onProcessChange: (processId: number | null) => void;
  onSetAsPreferred: () => void;
  onResetPreferred: () => void;
  isPreferred: boolean;
  hasPreferredProcess: boolean;
  setAsStartProcessLabel?: string;
  setAsStartProcessTooltip?: string;
  resetStartProcessLabel?: string;
  resetStartProcessTooltip?: string;
  onSubProcessClick?: (name: string) => void;
}

export default function ProcessMapDialog({
  open,
  onOpenChange,
  processName,
  xml,
  title,
  description,
  emptyMessage,
  invalidMessage,
  fitButtonLabel,
  showDetailsLabel,
  processId,
  processOptions,
  selectedProcessId,
  onProcessChange,
  onSetAsPreferred,
  onResetPreferred,
  isPreferred,
  hasPreferredProcess,
  setAsStartProcessLabel,
  setAsStartProcessTooltip,
  resetStartProcessLabel,
  resetStartProcessTooltip,
  onSubProcessClick,
}: ProcessMapDialogProps) {
  const { t } = useTranslations();

  const groupedProcessOptions = useMemo(() => {
    const groups = new Map<string, DashboardProcessOption[]>();

    processOptions.forEach((process) => {
      const groupLabel = process.departmentName ?? t('pages.dashboard.process.unassigned_department');
      const existing = groups.get(groupLabel) ?? [];
      groups.set(groupLabel, [...existing, process]);
    });

    return Array.from(groups.entries())
      .map(([label, options]) => ({ label, options }))
      .sort((a, b) => a.label.localeCompare(b.label));
  }, [processOptions, t]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex h-screen max-h-none max-w-none translate-x-[-50%] translate-y-[-50%] flex-col gap-0 rounded-none border-0 p-0 sm:rounded-none">
        <DialogHeader className="border-b border-border px-6 py-4 pr-14 text-left">
          <div className="space-y-3">
            <div>
              <DialogTitle>{processName ? `${title}: ${processName}` : title}</DialogTitle>
              <DialogDescription>{description}</DialogDescription>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <select
                value={selectedProcessId ?? ''}
                onChange={(event) => onProcessChange(event.target.value === '' ? null : Number(event.target.value))}
                className="min-w-[16rem] flex-1 text-sm border border-border rounded-lg px-3 py-1.5 bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              >
                {groupedProcessOptions.map((group) => (
                  <optgroup key={group.label} label={group.label}>
                    {group.options.map((process) => (
                      <option key={process.id} value={process.id}>
                        {process.name}{process.isStartProcess ? ` (${t('pages.dashboard.process.start_process')})` : ''}
                      </option>
                    ))}
                  </optgroup>
                ))}
              </select>
              {processId && (
              <button
                type="button"
                onClick={onSetAsPreferred}
                disabled={isPreferred}
                title={setAsStartProcessTooltip || t('pages.dashboard.process.set_as_preferred_process_tooltip')}
                className="inline-flex shrink-0 items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <Check className="h-4 w-4" />
                {setAsStartProcessLabel || t('pages.dashboard.process.set_as_preferred_process')}
              </button>
              )}
              {hasPreferredProcess && (
                <button
                  type="button"
                  onClick={onResetPreferred}
                  title={resetStartProcessTooltip || t('pages.dashboard.process.reset_preferred_process_tooltip')}
                  className="inline-flex shrink-0 items-center gap-2 rounded-md border border-border px-4 py-2 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
                >
                  <RotateCcw className="h-4 w-4" />
                  {resetStartProcessLabel || t('pages.dashboard.process.reset_preferred_process')}
                </button>
              )}
            </div>
          </div>
        </DialogHeader>
        <div className="flex-1 p-6">
          <BpmnProcessViewer
            xml={xml}
            emptyMessage={emptyMessage}
            invalidMessage={invalidMessage}
            fitButtonLabel={fitButtonLabel}
            showDetailsLabel={showDetailsLabel}
            onSubProcessClick={onSubProcessClick}
            className="h-full min-h-0"
          />
        </div>
      </DialogContent>
    </Dialog>
  );
}

