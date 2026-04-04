import { ExternalLink } from "lucide-react";

interface Task {
  title: string;
  date: string;
  status: "overdue" | "upcoming" | "done";
  type: "activity" | "control";
}

const tasks: Task[] = [
  { title: "Granska licenser i tredjepartsberoenden", date: "2026-02-28", status: "overdue", type: "activity" },
  { title: "Boka upp RISE workshop (Nätverkscertifiering)", date: "2026-02-28", status: "overdue", type: "activity" },
  { title: "Kontrollera säkerhetskopior", date: "2026-03-09", status: "overdue", type: "control" },
  { title: "Rapportera in nyckeltal", date: "2026-03-09", status: "overdue", type: "activity" },
  { title: "Kontrollera cert.se", date: "2026-03-13", status: "overdue", type: "control" },
  { title: "Kontrollera svenskförfattningssamling.se", date: "2026-03-13", status: "overdue", type: "control" },
  { title: "Månadsbrev", date: "2026-03-16", status: "upcoming", type: "activity" },
  { title: "Gallra bokföringspärmar och system", date: "2026-03-31", status: "upcoming", type: "activity" },
];

export default function TaskList() {
  return (
    <div className="bg-card rounded-xl border border-border card-shine">
      <div className="p-4 pb-3">
        <div className="flex items-center justify-between">
          <h3 className="font-heading font-semibold text-card-foreground">Att göra</h3>
          <span className="text-xs font-medium text-muted-foreground bg-muted px-2 py-0.5 rounded-full">
            {tasks.length} uppgifter
          </span>
        </div>
      </div>
      <div className="divide-y divide-border">
        {tasks.map((task, i) => (
          <button
            key={i}
            className="w-full flex items-start gap-3 px-4 py-3 text-left hover:bg-muted/50 transition-colors group"
          >
            <span
              className={`status-dot mt-1.5 ${
                task.status === "overdue" ? "status-dot-danger" :
                task.status === "upcoming" ? "status-dot-warning" :
                "status-dot-success"
              }`}
            />
            <div className="flex-1 min-w-0">
              <p className="text-sm text-card-foreground group-hover:text-primary transition-colors truncate">
                {task.title}
              </p>
              <div className="flex items-center gap-2 mt-0.5">
                <span className="text-[11px] text-muted-foreground">{task.date}</span>
                <span className="text-[10px] text-muted-foreground/60 bg-muted px-1.5 py-0.5 rounded">
                  {task.type === "activity" ? "Aktivitet" : "Kontroll"}
                </span>
              </div>
            </div>
            <ExternalLink className="h-3.5 w-3.5 text-muted-foreground/0 group-hover:text-muted-foreground transition-colors mt-1" />
          </button>
        ))}
      </div>
    </div>
  );
}
