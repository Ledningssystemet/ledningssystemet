import { Pencil, Trash2 } from "lucide-react";
import type { Item, ModuleConfig } from "../helpers/types";
import { btnOutline } from "../helpers/styles";

type RowCommand = NonNullable<ModuleConfig["rowCommands"]>[number];
type Props = {
  config: ModuleConfig;
  item: Item;
  canUpdate: boolean;
  canDelete: boolean;
  onRunCommand: (command: RowCommand, item: Item) => void;
  onEdit: (item: Item) => void;
  onDelete: (item: Item) => void;
};

export function RowActions({ config, item, canUpdate, canDelete, onRunCommand, onEdit, onDelete }: Props) {
  return (
    <div className="flex flex-wrap gap-1">
      {config.rowCommands?.filter((command) => command.visible?.(item) ?? true).map((command) => {
        const badge = command.badge?.(item);
        return <button key={command.label} className={`${btnOutline} px-2 py-1 text-xs`} onClick={(event) => { event.stopPropagation(); onRunCommand(command, item); }}>{command.label}{badge != null && badge !== 0 && <span className="rounded-full bg-primary px-1.5 text-[10px] text-primary-foreground">{badge}</span>}</button>;
      })}
      {canUpdate && <button className={`${btnOutline} px-2`} aria-label="Redigera" onClick={(event) => { event.stopPropagation(); onEdit(item); }}><Pencil className="h-4 w-4" /></button>}
      {canDelete && <button className={`${btnOutline} px-2 text-destructive`} aria-label="Ta bort" onClick={(event) => { event.stopPropagation(); onDelete(item); }}><Trash2 className="h-4 w-4" /></button>}
    </div>
  );
}
