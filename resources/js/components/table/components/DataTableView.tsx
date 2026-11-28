import React, { Fragment, type ReactNode } from "react";
import * as Checkbox from "@radix-ui/react-checkbox";
import { Check, ChevronRight, GripVertical, X } from "lucide-react";
import { ItemForm } from "../forms/ItemForm";
import type { DetailSection, Item, ModuleConfig, SortState } from "../helpers/types";
import { btnOutline, cbCls } from "../helpers/styles";
import { t } from "../../../lib/i18n";
import { translate } from "../helpers/translate";

type Props = {
  items: Item[];
  config: ModuleConfig;
  columns: string[];
  childSections: DetailSection[];
  canMove: boolean;
  isReorderMode: boolean;
  checked: Set<string>;
  onToggle: (id: string) => void;
  onTogglePage: (checked: boolean) => void;
  sort: SortState;
  onSortChange: (sort: SortState | ((current: SortState) => SortState)) => void;
  editing: Item | null;
  isInlineEditing: boolean;
  isNew: boolean;
  tagSuggestions: (key: string) => string[];
  onCancelEdit: () => void;
  onSave: (item: Item) => Promise<{ fields?: Record<string, string>; message?: string } | null>;
  renderValue: (key: string, value: any, item?: Item) => ReactNode;
  renderActions: (item: Item) => ReactNode;
  onMove: (item: Item, toIndex: number) => void;
  onMoveBy: (item: Item, direction: -1 | 1) => void;
  dragId: string | null;
  setDragId: (id: string | null) => void;
  dropIndex: number | null;
  setDropIndex: (index: number | null) => void;
  openChild: Record<string, string>;
  onToggleChild: (itemId: string, sectionKey: string) => void;
  onCloseChild: (itemId: string) => void;
  renderChildTable: (item: Item, section: DetailSection) => ReactNode;
};

export function DataTableView(props: Props) {
  const {
    items, config, columns, childSections, canMove, isReorderMode, checked, onToggle,
    onTogglePage, sort, onSortChange, editing, isInlineEditing, isNew, tagSuggestions,
    onCancelEdit, onSave, renderValue, renderActions, onMove, onMoveBy, dragId,
    setDragId, dropIndex, setDropIndex, openChild, onToggleChild, onCloseChild,
    renderChildTable,
  } = props;
  const extraColumns = childSections.length ? 1 : 0;
  const columnCount = columns.length + 2 + extraColumns + (canMove ? 1 : 0);
  const allPageChecked = items.length > 0 && items.every((item) => checked.has(item.id));
  const somePageChecked = items.some((item) => checked.has(item.id));
  const colLabel = (key: string) => config.columns.find((column) => column.key === key)?.label ?? key;

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="bg-muted text-left"><tr>
          <th className="w-8 px-2 py-2">
            <Checkbox.Root aria-label={t("Select all on this page")} className={cbCls}
              checked={allPageChecked ? true : somePageChecked ? "indeterminate" : false}
              onCheckedChange={(value) => onTogglePage(value === true)}>
              <Checkbox.Indicator>{allPageChecked ? <Check className="h-3 w-3" /> : <span className="block h-0.5 w-2 bg-current" />}</Checkbox.Indicator>
            </Checkbox.Root>
          </th>
          {canMove && <th className="w-8 px-2 py-2"><span className="sr-only">{t("Move")}</span></th>}
          {columns.map((key) => (
            <th key={key} className="px-3 py-2 font-medium">
              {isReorderMode || !config.columns.find((column) => column.key === key)?.sortable ? colLabel(key) : (
                <button className="inline-flex items-center gap-1" onClick={() => onSortChange((current) => ({ key, dir: current.key === key && current.dir === "asc" ? "desc" : "asc" }))}>
                  {colLabel(key)}{sort.key === key && (sort.dir === "asc" ? " ↑" : " ↓")}
                </button>
              )}
            </th>
          ))}
          {extraColumns > 0 && <th className="px-3 py-2 font-medium">{t("Sub-lists")}</th>}
          <th className="px-3 py-2" />
        </tr></thead>
        <tbody>
          {items.map((item, index) => isInlineEditing && editing?.id === item.id && !isNew ? (
            <tr key={item.id}><td colSpan={columnCount} className="p-4"><ItemForm tagSuggestions={tagSuggestions} config={config} item={editing} isNew={false} onCancel={onCancelEdit} onSave={onSave} /></td></tr>
          ) : (
            <Fragment key={item.id}>
              <tr
                className={`border-t hover:bg-surface-hover ${dragId === item.id ? "opacity-40" : ""} ${dropIndex === index && dragId && dragId !== item.id ? "outline outline-2 -outline-offset-2 outline-primary" : ""}`}
                onDragOver={canMove && dragId ? (event) => { event.preventDefault(); event.dataTransfer.dropEffect = "move"; setDropIndex(index); } : undefined}
                onDrop={canMove ? (event) => { event.preventDefault(); const dragged = items.find((candidate) => candidate.id === dragId); if (dragged) onMove(dragged, index); setDragId(null); setDropIndex(null); } : undefined}
              >
                <td className="px-2 py-2">
                  <Checkbox.Root aria-label={translate("Select {item}", { item: String(item[config.titleKey]) })} className={cbCls} checked={checked.has(item.id)} onCheckedChange={() => onToggle(item.id)}>
                    <Checkbox.Indicator><Check className="h-3 w-3" /></Checkbox.Indicator>
                  </Checkbox.Root>
                </td>
                {canMove && (
                  <td className="px-2 py-2">
                    {(config.reorder!.canReorder?.(item) ?? true) && (
                      <span draggable role="button" tabIndex={0} aria-label={translate("Move {item} (drag or use the up/down arrows)", { item: String(item[config.titleKey]) })} title={t("Drag to move")}
                        className="inline-flex cursor-grab rounded p-0.5 text-muted-foreground hover:bg-accent active:cursor-grabbing"
                        onDragStart={(event) => { setDragId(item.id); event.dataTransfer.effectAllowed = "move"; event.dataTransfer.setData("text/plain", item.id); }}
                        onDragEnd={() => { setDragId(null); setDropIndex(null); }}
                        onKeyDown={(event) => { if (event.key === "ArrowUp") { event.preventDefault(); onMoveBy(item, -1); } if (event.key === "ArrowDown") { event.preventDefault(); onMoveBy(item, 1); } }}>
                        <GripVertical className="h-4 w-4" />
                      </span>
                    )}
                  </td>
                )}
                {columns.map((key) => <td key={key} className="px-3 py-2">{renderValue(key, item[key], item)}</td>)}
                {extraColumns > 0 && (
                  <td className="px-3 py-2"><div className="flex flex-wrap gap-1">
                    {childSections.map((section) => {
                      const isOpen = openChild[item.id] === section.key;
                      return (
                        <button key={section.key} aria-expanded={isOpen} className={`${btnOutline} px-2 py-1 text-xs ${isOpen ? "border-primary bg-accent" : ""}`}
                          onClick={() => onToggleChild(item.id, section.key)}>
                          <ChevronRight className={`h-3.5 w-3.5 transition-transform ${isOpen ? "rotate-90" : ""}`} />{section.label}{Array.isArray(item[section.key]) ? ` (${item[section.key].length})` : ""}
                        </button>
                      );
                    })}
                  </div></td>
                )}
                <td className="px-3 py-2">{renderActions(item)}</td>
              </tr>
              {openChild[item.id] && (() => {
                const section = childSections.find((candidate) => candidate.key === openChild[item.id])!;
                return (
                  <tr className="bg-background"><td colSpan={columnCount} className="py-3 pl-8 pr-3">
                    <div className="mb-2 flex items-center justify-between">
                      <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{translate("{section} for {item}", { section: section.label, item: String(item[config.titleKey]) })}</h4>
                      <button className={`${btnOutline} px-2 py-1 text-xs`} onClick={() => onCloseChild(item.id)}><X className="h-3.5 w-3.5" />{t("Close")}</button>
                    </div>
                    {renderChildTable(item, section)}
                  </td></tr>
                );
              })()}
            </Fragment>
          ))}
        </tbody>
      </table>
    </div>
  );
}
