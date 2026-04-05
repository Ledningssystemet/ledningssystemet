import { Check, Minus, X } from "lucide-react";
import { useTranslations } from "@/hooks/useTranslations";

interface Goal {
  titleKey: string;
  status: "achieved" | "acceptable" | "unacceptable";
}

const goals: Goal[] = [
  { titleKey: "training_sessions", status: "achieved" },
  { titleKey: "psychology_cycle_time", status: "acceptable" },
  { titleKey: "platform_growth", status: "acceptable" },
  { titleKey: "hosted_instances", status: "unacceptable" },
  { titleKey: "ordersystem_start", status: "unacceptable" },
  { titleKey: "partner_portal_content", status: "unacceptable" },
];

export default function GoalsCard() {
  const { t } = useTranslations();
  const statusConfig = {
    achieved: { label: t('pages.dashboard.goals.status_achieved'), dotClass: "status-dot-success", icon: Check },
    acceptable: { label: t('pages.dashboard.goals.status_acceptable'), dotClass: "status-dot-warning", icon: Minus },
    unacceptable: { label: t('pages.dashboard.goals.status_unacceptable'), dotClass: "status-dot-danger", icon: X },
  };

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
        {goals.map((goal, i) => {
          const cfg = statusConfig[goal.status];
          return (
            <button
              key={i}
              className="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-muted/50 transition-colors group"
            >
              <span className={`status-dot ${cfg.dotClass}`} />
              <span className="text-sm text-card-foreground group-hover:text-primary transition-colors flex-1">
                {t(`pages.dashboard.goals.items.${goal.titleKey}`)}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}
