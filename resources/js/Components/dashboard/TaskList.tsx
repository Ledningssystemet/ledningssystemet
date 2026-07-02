import { useTranslations } from "@/hooks/useTranslations";
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import { DashboardWidgetProps } from "@/types/dashboard";

export default function TaskList({ data, loading, error }: DashboardWidgetProps) {
  const { t, locale } = useTranslations();
  const tasks = data.tasks;

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
          <h3 className="font-heading font-semibold text-card-foreground">{t('pages.dashboard.tasks.title')}</h3>
          <span className="text-xs font-medium text-muted-foreground bg-muted px-2 py-0.5 rounded-full">
            {t('ui.task.count', { count: tasks.length })}
          </span>
        </div>
      </div>
      <div className="divide-y divide-border">
        {tasks.length === 0 && (
          <div className="px-4 py-6 text-sm text-muted-foreground">{t('pages.dashboard.tasks.no_tasks')}</div>
        )}
        {tasks.map((task) => (
          <button
            key={task.id}
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
                <span className="text-[11px] text-muted-foreground">
                  {task.date ? new Date(task.date).toLocaleDateString() : '-'}
                </span>
                <span className="text-[10px] text-muted-foreground/60 bg-muted px-1.5 py-0.5 rounded">
                  {task.type === "activity" ? t('ui.task.activity') : t('ui.task.control_action')}
                </span>
              </div>
            </div>
            <MaterialSymbol name="open_in_new" className="h-3.5 w-3.5 text-muted-foreground/0 group-hover:text-muted-foreground transition-colors mt-1" />
          </button>
        ))}
      </div>
    </div>
  );
}
