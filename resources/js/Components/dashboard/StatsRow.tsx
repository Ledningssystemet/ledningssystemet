import { useTranslations } from "@/hooks/useTranslations";
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { DashboardWidgetProps } from "@/types/dashboard";

export default function StatsRow({ data, loading, error }: DashboardWidgetProps) {
  const { t } = useTranslations();
  const stats = [
    { label: t('pages.dashboard.stats.open_activities'), value: String(data.stats.openActivities), icon: "assignment", color: "text-secondary" },
    { label: t('pages.dashboard.stats.overdue_controls'), value: String(data.stats.overdueControls), icon: "warning", color: "text-destructive" },
    {
      label: t('pages.dashboard.stats.completed_goals'),
      value: `${data.stats.completedGoals}/${data.stats.totalGoals}`,
      icon: "check_circle",
      color: "text-success",
    },
    { label: t('pages.dashboard.stats.risk_score_avg'), value: String(data.stats.averageRiskScore), icon: "trending_up", color: "text-accent" },
  ];

  if (loading) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-muted-foreground">{t('pages.dashboard.shared.loading')}</div>;
  }

  if (error) {
    return <div className="bg-card rounded-xl border border-border p-4 text-sm text-destructive">{error}</div>;
  }

  return (
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
      {stats.map((stat) => (
        <div
          key={stat.label}
          className="bg-card rounded-xl border border-border p-4 hover:shadow-md transition-shadow"
        >
          <div className="flex items-center justify-between mb-2">
            <MaterialSymbol name={stat.icon} className={`h-5 w-5 ${stat.color}`} />
          </div>
          <div className="text-2xl font-heading font-bold text-card-foreground">{stat.value}</div>
          <div className="text-xs text-muted-foreground mt-0.5">{stat.label}</div>
        </div>
      ))}
    </div>
  );
}
