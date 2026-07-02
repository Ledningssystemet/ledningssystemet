import { useTranslations } from "@/hooks/useTranslations";
import { DashboardWidgetProps } from "@/types/dashboard";

export default function TopRisks({ data, loading, error }: DashboardWidgetProps) {
  const { t } = useTranslations();

  if (loading) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-muted-foreground">{t('pages.dashboard.shared.loading')}</div>;
  }

  if (error) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-destructive">{error}</div>;
  }

  return (
    <div className="bg-card rounded-xl border border-border card-shine">
      <div className="p-4 pb-3">
        <div className="flex items-center justify-between">
          <h3 className="font-heading font-semibold text-card-foreground">{t('pages.dashboard.top_risks.title')}</h3>
        </div>
      </div>
      <div className="divide-y divide-border">
        {data.topRisks.length === 0 && (
          <div className="px-4 py-6 text-sm text-muted-foreground">{t('pages.dashboard.top_risks.no_risks')}</div>
        )}
        {data.topRisks.map((risk) => (
          <button
            key={risk.id}
            className="w-full flex items-start gap-3 px-4 py-2.5 text-left hover:bg-muted/50 transition-colors group"
          >
            <span className="status-dot status-dot-danger mt-1.5" />
            <span className="text-sm text-card-foreground group-hover:text-primary transition-colors leading-snug">
              {risk.title}
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}
