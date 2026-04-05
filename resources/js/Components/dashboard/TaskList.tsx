import { ExternalLink } from "lucide-react";
import { useTranslations } from "@/hooks/useTranslations";

interface Task {
  titleKey: string;
  date: string;
  status: "overdue" | "upcoming" | "done";
  type: "activity" | "control";
}

const tasks: Task[] = [
  { titleKey: "review_dependency_licenses", date: "2026-02-28", status: "overdue", type: "activity" },
  { titleKey: "book_rise_workshop", date: "2026-02-28", status: "overdue", type: "activity" },
  { titleKey: "check_backups", date: "2026-03-09", status: "overdue", type: "control" },
  { titleKey: "report_kpis", date: "2026-03-09", status: "overdue", type: "activity" },
  { titleKey: "check_cert_se", date: "2026-03-13", status: "overdue", type: "control" },
  { titleKey: "check_law_source", date: "2026-03-13", status: "overdue", type: "control" },
  { titleKey: "monthly_letter", date: "2026-03-16", status: "upcoming", type: "activity" },
  { titleKey: "archive_binders_and_systems", date: "2026-03-31", status: "upcoming", type: "activity" },
];

export default function TaskList() {
  const { t } = useTranslations();

  return (
    <div className="bg-card rounded-xl border border-border card-shine">
      <div className="p-4 pb-3">
        <div className="flex items-center justify-between">
          <h3 className="font-heading font-semibold text-card-foreground">{t('pages.dashboard.tasks.title')}</h3>
          <span className="text-xs font-medium text-muted-foreground bg-muted px-2 py-0.5 rounded-full">
            {t('ui.task.count', { count: tasks.length })}
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
                {t(`pages.dashboard.tasks.items.${task.titleKey}`)}
              </p>
              <div className="flex items-center gap-2 mt-0.5">
                <span className="text-[11px] text-muted-foreground">{task.date}</span>
                <span className="text-[10px] text-muted-foreground/60 bg-muted px-1.5 py-0.5 rounded">
                  {task.type === "activity" ? t('ui.task.activity') : t('ui.task.control')}
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
