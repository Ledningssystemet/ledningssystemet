import { Check, Minus, X } from "lucide-react";
import { useTranslations } from "@/hooks/useTranslations";
import { DashboardWidgetProps } from "@/types/dashboard";

export default function GoalsCard({ data, loading, error }: DashboardWidgetProps) {
  const { t } = useTranslations();
  const goals = data.goals;
  const statusConfig = {
    achieved: { label: t('pages.dashboard.goals.status_achieved'), dotClass: "status-dot-success", icon: Check },
    acceptable: { label: t('pages.dashboard.goals.status_acceptable'), dotClass: "status-dot-warning", icon: Minus },
    unacceptable: { label: t('pages.dashboard.goals.status_unacceptable'), dotClass: "status-dot-danger", icon: X },
  };

  if (loading) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-muted-foreground">{t('pages.dashboard.shared.loading')}</div>;
  }

  if (error) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-destructive">{error}</div>;
  }

  return (
    <div className="bg-card rounded-xl border border-border card-shine">
      <div className="p-4 pb-3">
        <h3 className="font-heading font-semibold text-card-foreground">{t('pages.dashboard.goals.title')}</h3>
        <div className="flex gap-4 mt-2">
          {(["achieved", "acceptable", "unacceptable"] as const).map((s) => (
            <div key={s} className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
              <span className={`status-dot ${statusConfig[s].dotClass}`} />
              {statusConfig[s].label}
            </div>
          ))}
        </div>
      </div>
      <div className="divide-y divide-border">
        {goals.length === 0 && (
          <div className="px-4 py-6 text-sm text-muted-foreground">{t('pages.dashboard.goals.no_goals')}</div>
        )}
        {goals.map((goal) => {
          const cfg = statusConfig[goal.status];
          return (
            <button
              key={goal.id}
              className="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-muted/50 transition-colors group"
            >
              <span className={`status-dot ${cfg.dotClass}`} />
              <span className="text-sm text-card-foreground group-hover:text-primary transition-colors flex-1">
                {goal.title}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}
