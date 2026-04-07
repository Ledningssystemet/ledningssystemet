import { useEffect, useMemo, useState } from "react";
import { Check, Maximize2, RotateCcw } from "lucide-react";
import { useTranslations } from "@/hooks/useTranslations";
import { DashboardWidgetProps } from "@/types/dashboard";
import BpmnProcessViewer from "./BpmnProcessViewer";
import ProcessMapDialog from "./ProcessMapDialog";
import Cookies from "js-cookie";

const PREFERRED_PROCESS_COOKIE = 'dashboard_preferred_process_id';

export default function ProcessCard({ data, loading, error, setSelectedProcessId }: DashboardWidgetProps) {
  const { t } = useTranslations();
  const [isFullscreenOpen, setIsFullscreenOpen] = useState(false);
  const [preferredProcessId, setPreferredProcessId] = useState<number | null>(null);
  const [showSavedNotice, setShowSavedNotice] = useState(false);
  const hasProcess = data.selectedProcessId !== null;
  const isPreferred = hasProcess && preferredProcessId === data.selectedProcessId;
  const hasPreferredProcess = preferredProcessId !== null;

  useEffect(() => {
    const rawPreferredProcessId = Cookies.get(PREFERRED_PROCESS_COOKIE);
    if (!rawPreferredProcessId) {
      setPreferredProcessId(null);
      return;
    }

    const parsedProcessId = Number(rawPreferredProcessId);
    setPreferredProcessId(Number.isFinite(parsedProcessId) ? parsedProcessId : null);
  }, []);

  const groupedProcessOptions = useMemo(() => {
    const groups = new Map<string, typeof data.processOptions>();

    data.processOptions.forEach((process) => {
      const groupLabel = process.departmentName ?? t('pages.dashboard.process.unassigned_department');
      const existing = groups.get(groupLabel) ?? [];
      groups.set(groupLabel, [...existing, process]);
    });

    return Array.from(groups.entries())
      .map(([label, options]) => ({
        label,
        options,
      }))
      .sort((a, b) => a.label.localeCompare(b.label));
  }, [data.processOptions, t]);

  const handleProcessChange = (value: string) => {
    setSelectedProcessId(value === '' ? null : Number(value));
  };

  const handleSetAsPreferred = () => {
    if (data.selectedProcessId === null) {
      return;
    }

    Cookies.set(PREFERRED_PROCESS_COOKIE, String(data.selectedProcessId), { expires: 365 });
    setPreferredProcessId(data.selectedProcessId);
    setShowSavedNotice(true);

    setTimeout(() => {
      setShowSavedNotice(false);
    }, 2200);
  };

  const handleResetPreferredProcess = () => {
    Cookies.remove(PREFERRED_PROCESS_COOKIE);
    setPreferredProcessId(null);

    const defaultProcessId = data.processOptions.find((process) => process.isStartProcess)?.id ?? data.processOptions[0]?.id ?? null;
    setSelectedProcessId(defaultProcessId);
  };

  if (loading) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-muted-foreground">{t('pages.dashboard.shared.loading')}</div>;
  }

  if (error) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-destructive">{error}</div>;
  }

  return (
    <>
      <div className="bg-card rounded-xl border border-border card-shine h-full flex flex-col">
        <div className="p-4 pb-3 flex items-center justify-between">
          <h3 className="font-heading font-semibold text-card-foreground">{t('pages.dashboard.process.title')}</h3>
          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={() => setIsFullscreenOpen(true)}
              disabled={!hasProcess}
              aria-label={t('pages.dashboard.process.fullscreen')}
              title={t('pages.dashboard.process.fullscreen')}
              className="p-1.5 rounded-md hover:bg-muted transition-colors disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Maximize2 className="h-3.5 w-3.5 text-muted-foreground" />
            </button>
          </div>
        </div>
        <div className="px-4 pb-4 flex flex-1 flex-col">
          {data.processOptions.length > 0 && (
            <div className="mb-3 flex flex-wrap items-center gap-2">
              <select
                value={data.selectedProcessId ?? ''}
                onChange={(event) => handleProcessChange(event.target.value)}
                className="min-w-[12rem] flex-1 text-sm border border-border rounded-lg px-3 py-1.5 bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
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
              {hasProcess && (
                <button
                  type="button"
                  onClick={handleSetAsPreferred}
                  disabled={isPreferred}
                  title={t('pages.dashboard.process.set_as_preferred_process_tooltip')}
                  className="inline-flex shrink-0 items-center gap-2 rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Check className="h-3.5 w-3.5" />
                  {t('pages.dashboard.process.set_as_preferred_process')}
                </button>
              )}
              {hasPreferredProcess && (
                <button
                  type="button"
                  onClick={handleResetPreferredProcess}
                  title={t('pages.dashboard.process.reset_preferred_process_tooltip')}
                  className="inline-flex shrink-0 items-center gap-2 rounded-md border border-border px-3 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-muted"
                >
                  <RotateCcw className="h-3.5 w-3.5" />
                  {t('pages.dashboard.process.reset_preferred_process')}
                </button>
              )}
            </div>
          )}

          {showSavedNotice && hasProcess && (
            <p className="mb-3 text-xs text-success">
              {t('pages.dashboard.process.preferred_process_saved', { process: data.selectedProcessName || 'Process' })}
            </p>
          )}

          {!hasProcess && (
            <p className="text-xs text-muted-foreground italic">{t('pages.dashboard.process.no_processes')}</p>
          )}

          {hasProcess && (
            <>
              <div className="mb-3 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                <span className="min-w-0 truncate font-medium text-foreground">{data.selectedProcessName}</span>
                <span className="shrink-0">{t('pages.dashboard.process.zoom_hint')}</span>
              </div>
              <BpmnProcessViewer
                xml={data.selectedProcessBpmn}
                emptyMessage={t('pages.dashboard.process.no_published_bpmn')}
                invalidMessage={t('pages.dashboard.process.invalid_bpmn')}
                fitButtonLabel={t('pages.dashboard.process.fit_to_screen')}
                className="flex-1 min-h-[18rem]"
              />
            </>
          )}
        </div>
      </div>

      <ProcessMapDialog
        open={isFullscreenOpen}
        onOpenChange={setIsFullscreenOpen}
        processName={data.selectedProcessName}
        xml={data.selectedProcessBpmn}
        title={t('pages.dashboard.process.fullscreen_title')}
        description={t('pages.dashboard.process.zoom_hint')}
        emptyMessage={t('pages.dashboard.process.no_published_bpmn')}
        invalidMessage={t('pages.dashboard.process.invalid_bpmn')}
        fitButtonLabel={t('pages.dashboard.process.fit_to_screen')}
        processId={data.selectedProcessId}
        processOptions={data.processOptions}
        selectedProcessId={data.selectedProcessId}
        onProcessChange={setSelectedProcessId}
        onSetAsPreferred={handleSetAsPreferred}
        onResetPreferred={handleResetPreferredProcess}
        isPreferred={Boolean(isPreferred)}
        hasPreferredProcess={hasPreferredProcess}
        setAsStartProcessLabel={t('pages.dashboard.process.set_as_preferred_process')}
        setAsStartProcessTooltip={t('pages.dashboard.process.set_as_preferred_process_tooltip')}
        resetStartProcessLabel={t('pages.dashboard.process.reset_preferred_process')}
        resetStartProcessTooltip={t('pages.dashboard.process.reset_preferred_process_tooltip')}
      />
    </>
  );
}
