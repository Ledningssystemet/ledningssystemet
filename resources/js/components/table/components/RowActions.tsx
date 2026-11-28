import React from "react";
import { Pencil, Trash2 } from "lucide-react";
import type { Item, ModuleConfig } from "../helpers/types";
import { btnOutline } from "../helpers/styles";
import { t } from "../../../lib/i18n";

type RowCommand = NonNullable<ModuleConfig["rowCommands"]>[number];
type Props = {
  config: ModuleConfig;
  item: Item;
  canUpdate: boolean;
  canDelete: boolean;
  stacked?: boolean;
  group?: "all" | "fixed" | "commands";
  onRunCommand: (command: RowCommand, item: Item) => void;
  onEdit: (item: Item) => void;
  onDelete: (item: Item) => void;
};

export function RowActions({ config, item, canUpdate, canDelete, stacked = false, group = "all", onRunCommand, onEdit, onDelete }: Props) {
  const alignment = stacked ? " justify-center" : "";
  const showFixed = group !== "commands";
  const commands = group === "fixed" ? [] : (config.rowCommands ?? []).filter((command) => command.visible?.(item) ?? true);
  if ((!showFixed || (!canUpdate && !canDelete)) && commands.length === 0) return null;
  return (
    <div className={stacked ? "flex flex-col gap-1" : "flex flex-wrap gap-1"}>
      {showFixed && canUpdate && <button className={`${btnOutline} px-2 text-xs${alignment}`} aria-label={t("Edit")} onClick={(event) => { event.stopPropagation(); onEdit(item); }}>{t('Edit')}<Pencil className="h-4 w-4" /></button>}
      {showFixed && canDelete && <button className={`${btnOutline} px-2 text-destructive text-xs${alignment}`} aria-label={t("Delete")} onClick={(event) => { event.stopPropagation(); onDelete(item); }}>{t('Delete')}<Trash2 className="h-4 w-4" /></button>}
      {commands.map((command) => {
        const badge = command.badge?.(item);
        return <button key={command.label} className={`${btnOutline} px-2 py-1 text-xs${alignment}`} onClick={(event) => { event.stopPropagation(); onRunCommand(command, item); }}>{command.label}{badge != null && badge !== 0 && <span className="rounded-full bg-primary px-1.5 text-[10px] text-primary-foreground">{badge}</span>}</button>;
      })}
    </div>
  );
}
