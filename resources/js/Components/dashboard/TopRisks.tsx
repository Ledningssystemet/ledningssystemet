import { useTranslations } from "@/hooks/useTranslations";

const riskKeys = [
  "portal_intrusion",
  "first_aid",
  "fire_drill",
  "backup_prod",
  "intrusion_awareness",
  "backup_server",
  "backup_vulns",
  "threat_monitoring",
];

export default function TopRisks() {
  const { t } = useTranslations();

  return (
    <div className="bg-card rounded-xl border border-border card-shine">
      <div className="p-4 pb-3">
        <div className="flex items-center justify-between">
          <h3 className="font-heading font-semibold text-card-foreground">{t('pages.dashboard.top_risks.title')}</h3>
        </div>
      </div>
      <div className="divide-y divide-border">
        {riskKeys.map((riskKey, i) => (
          <button
            key={i}
            className="w-full flex items-start gap-3 px-4 py-2.5 text-left hover:bg-muted/50 transition-colors group"
          >
            <span className="status-dot status-dot-danger mt-1.5" />
            <span className="text-sm text-card-foreground group-hover:text-primary transition-colors leading-snug">
              {t(`pages.dashboard.top_risks.items.${riskKey}`)}
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}
