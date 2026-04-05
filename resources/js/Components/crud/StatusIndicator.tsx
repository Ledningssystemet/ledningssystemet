import { ItemStatus } from "./types";
import { cn } from "@/lib/utils";

interface StatusIndicatorProps {
  status: ItemStatus | null | undefined;
  className?: string;
}

const statusStyles: Record<ItemStatus, string> = {
  info: "bg-blue-500",
  warning: "bg-yellow-500",
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

export function statusRowClass(status: ItemStatus | null | undefined): string {
  if (!status) return "";
  const map: Record<ItemStatus, string> = {
    info: "border-l-2 border-l-blue-500",
    warning: "border-l-2 border-l-yellow-500",
    danger: "border-l-2 border-l-destructive bg-destructive/5",
  };
  return map[status];
}
