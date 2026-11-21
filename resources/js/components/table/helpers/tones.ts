import type { Tone } from "../types";

export const badgeTone: Record<Tone, string> = {
  success: "bg-badge-success text-badge-success-foreground",
  info: "bg-badge-info text-badge-info-foreground",
  warning: "bg-badge-warning text-badge-warning-foreground",
  danger: "bg-[hsl(var(--badge-danger))] text-[hsl(var(--badge-danger-foreground))]",
  secondary: "bg-secondary text-secondary-foreground",
};

export const dotTone: Record<Tone, string> = {
  success: "bg-[hsl(var(--dot-success))]",
  warning: "bg-[hsl(var(--dot-warning))]",
  secondary: "bg-[hsl(var(--dot-secondary))]",
  info: "bg-primary",
  danger: "bg-destructive",
};
