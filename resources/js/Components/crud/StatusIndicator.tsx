import { ItemStatus } from "./types";
import { cn } from "@/lib/utils";

interface StatusIndicatorProps {
  status: ItemStatus | null | undefined;
  className?: string;
}

const statusStyles: Record<ItemStatus, string> = {
  info: "bg-success",
  warning: "bg-warning",
  danger: "bg-destructive",
};

export function StatusDot({ status, className }: StatusIndicatorProps) {
  if (!status) return null;
  return (
    <span
      className={cn("inline-block h-2.5 w-2.5 rounded-full shrink-0", statusStyles[status], className)}
      title={status}
    />
  );
}
