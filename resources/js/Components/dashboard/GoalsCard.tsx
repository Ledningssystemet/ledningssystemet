import { Check, Minus, X } from "lucide-react";

interface Goal {
  title: string;
  status: "achieved" | "acceptable" | "unacceptable";
}

const goals: Goal[] = [
  { title: "Genomförda träningspass Segloravägen 24", status: "achieved" },
  { title: "Källekullen Psykologi AB – Minskade ledtider", status: "acceptable" },
  { title: "Ledningssystemet.se – Förbättringar och funktionstillväxt", status: "acceptable" },
  { title: "Minst 20 debiterbara hostade instanser", status: "unacceptable" },
  { title: "Ordersystemet.se – Uppstart nyutveckling", status: "unacceptable" },
  { title: "Partnerportal – Utveckling av innehåll", status: "unacceptable" },
];

const statusConfig = {
  achieved: { label: "Mål uppnått", dotClass: "status-dot-success", icon: Check },
  acceptable: { label: "Acceptabel", dotClass: "status-dot-warning", icon: Minus },
  unacceptable: { label: "Oacceptabel", dotClass: "status-dot-danger", icon: X },
};

export default function GoalsCard() {
  return (
    <div className="bg-card rounded-xl border border-border card-shine">
      <div className="p-4 pb-3">
        <h3 className="font-heading font-semibold text-card-foreground">Bolagsmål</h3>
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
                {goal.title}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}
