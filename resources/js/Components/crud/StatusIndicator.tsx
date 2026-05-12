import { CrudItemStatus, ItemStatusLevel } from "./types";
import { cn } from "@/lib/utils";

interface StatusIndicatorProps {
  status: CrudItemStatus | null | undefined;
  className?: string;
}

const statusStyles: Record<ItemStatusLevel, string> = {
  unknown: "bg-muted-foreground/50",
  success: "bg-success",
  warning: "bg-warning",
  danger: "bg-destructive",
};

export function StatusDot({ status, className }: StatusIndicatorProps) {
  if (!status) return null;

  const title = status.explanation || status.level;

  return (
    <span
      className={cn("inline-block h-2.5 w-2.5 rounded-full shrink-0", statusStyles[status.level], className)}
      title={title}
    />
  );
}
