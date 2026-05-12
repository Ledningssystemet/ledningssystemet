import { CrudItemStatus, ItemStatusLevel } from "./types";

const allowedLevels: ItemStatusLevel[] = ["unknown", "success", "warning", "danger"];

function isItemStatusLevel(value: unknown): value is ItemStatusLevel {
  return typeof value === "string" && allowedLevels.includes(value as ItemStatusLevel);
}

function normalizeLegacyLevel(level: unknown): ItemStatusLevel | null {
  if (level === "info") {
    return "success";
  }

  return isItemStatusLevel(level) ? level : null;
}

export function getItemStatus(item: Record<string, any>): CrudItemStatus | null {
  const status = item?.status;

  if (!status) {
    return null;
  }

  if (typeof status === "string") {
    const level = normalizeLegacyLevel(status);
    return level ? { level, explanation: "" } : null;
  }

  if (typeof status !== "object") {
    return null;
  }

  const level = normalizeLegacyLevel((status as { level?: unknown }).level);

  if (!level) {
    return null;
  }

  return {
    level,
    explanation: String((status as { explanation?: unknown }).explanation ?? "").trim(),
  };
}

export function hasVisibleStatus(items: Record<string, any>[]): boolean {
  return items.some((item) => getItemStatus(item) !== null);
}

