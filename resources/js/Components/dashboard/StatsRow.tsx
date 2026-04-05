import { CheckCircle2, AlertTriangle, ClipboardList, TrendingUp } from "lucide-react";
import { useTranslations } from "@/hooks/useTranslations";

export default function StatsRow() {
  const { t } = useTranslations();
  const stats = [
    { label: t('pages.dashboard.stats.open_activities'), value: "24", icon: ClipboardList, trend: t('pages.dashboard.stats.trend_open_activities'), color: "text-secondary" },
    { label: t('pages.dashboard.stats.overdue_controls'), value: "7", icon: AlertTriangle, trend: t('pages.dashboard.stats.trend_overdue_controls'), color: "text-destructive" },
    { label: t('pages.dashboard.stats.completed_goals'), value: "4/9", icon: CheckCircle2, trend: t('pages.dashboard.stats.trend_completed_goals'), color: "text-success" },
    { label: t('pages.dashboard.stats.risk_score_avg'), value: "3.2", icon: TrendingUp, trend: t('pages.dashboard.stats.trend_risk_score_avg'), color: "text-accent" },
  ];

  return (
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
      {stats.map((stat) => (
        <div
          key={stat.label}
          className="bg-card rounded-xl border border-border p-4 hover:shadow-md transition-shadow"
        >
          <div className="flex items-center justify-between mb-2">
            <stat.icon className={`h-5 w-5 ${stat.color}`} />
          </div>
          <div className="text-2xl font-heading font-bold text-card-foreground">{stat.value}</div>
          <div className="text-xs text-muted-foreground mt-0.5">{stat.label}</div>
          <div className="text-[11px] text-muted-foreground/70 mt-1">{stat.trend}</div>
        </div>
      ))}
    </div>
  );
}
