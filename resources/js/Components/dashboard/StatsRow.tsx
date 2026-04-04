import { CheckCircle2, AlertTriangle, ClipboardList, TrendingUp } from "lucide-react";

const stats = [
  { label: "Öppna aktiviteter", value: "24", icon: ClipboardList, trend: "+3 denna vecka", color: "text-secondary" },
  { label: "Förfallna kontroller", value: "7", icon: AlertTriangle, trend: "Kräver åtgärd", color: "text-destructive" },
  { label: "Avslutade mål", value: "4/9", icon: CheckCircle2, trend: "44% klart", color: "text-success" },
  { label: "Riskpoäng (medel)", value: "3.2", icon: TrendingUp, trend: "↓ 0.4 senaste månaden", color: "text-accent" },
];

export default function StatsRow() {
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
