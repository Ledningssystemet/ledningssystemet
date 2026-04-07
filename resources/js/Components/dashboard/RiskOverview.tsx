import { cn } from "@/lib/utils";
import { useTranslations } from "@/hooks/useTranslations";
import { DashboardWidgetProps } from "@/types/dashboard";

export default function RiskOverview({ data, loading, error }: DashboardWidgetProps) {
  const { t } = useTranslations();
  const consequences = data.riskMatrixMeta.consequences;
  const likelihoods = data.riskMatrixMeta.probabilities;

  if (loading) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-muted-foreground">{t('pages.dashboard.shared.loading')}</div>;
  }

  if (error) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-destructive">{error}</div>;
  }

  if (consequences.length === 0 || likelihoods.length === 0) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-muted-foreground">{t('pages.dashboard.risk_overview.no_configuration')}</div>;
  }

  return (
    <div className="bg-card rounded-xl border border-border card-shine">
      <div className="p-4 pb-3">
        <h3 className="font-heading font-semibold text-card-foreground">{t('pages.dashboard.risk_overview.title')}</h3>
      </div>
      <div className="px-4 pb-4">
        <div
          className="grid gap-px bg-border rounded-lg overflow-hidden text-xs"
          style={{ gridTemplateColumns: `repeat(${consequences.length + 1}, minmax(0, 1fr))` }}
        >
          {/* Header row */}
          <div className="bg-muted p-2 flex items-center justify-center font-medium text-muted-foreground" />
          {consequences.map((c) => (
            <div key={c.id} className="bg-muted p-1.5 flex items-center justify-center font-medium text-muted-foreground text-center text-[10px] leading-tight">
              {c.name}
            </div>
          ))}
          {/* Data rows */}
          {data.riskMatrix.map((row, ri) => (
            <div key={`row-${ri}`} className="contents">
              <div key={`label-${ri}`} className="bg-muted p-1.5 flex items-center justify-center font-medium text-muted-foreground text-[10px] text-center leading-tight">
                {likelihoods[ri]?.name ?? ''}
              </div>
              {row.map((val, ci) => (
                <div
                  key={`${ri}-${ci}`}
                  title={data.riskMatrixMeta.cellMeta[ri]?.[ci]?.riskLevelName ?? undefined}
                  style={{ backgroundColor: data.riskMatrixMeta.cellMeta[ri]?.[ci]?.backgroundColor ?? undefined }}
                  className={cn(
                    "p-2 flex items-center justify-center font-bold text-sm transition-transform hover:scale-105 cursor-pointer",
                    data.riskMatrixMeta.cellMeta[ri]?.[ci]?.className ?? 'risk-cell-green'
                  )}
                >
                  {val > 0 ? val : ""}
                </div>
              ))}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
