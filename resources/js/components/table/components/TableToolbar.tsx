import React from "react";
import * as ToggleGroup from "@radix-ui/react-toggle-group";
import { GripVertical, LayoutList, Plus, Rows3, Search, SlidersHorizontal, Table2 } from "lucide-react";
import type { ViewMode } from "../helpers/types";
import { btnOutline, btnPrimary, inputCls } from "../helpers/styles";
import { t } from "../../../lib/i18n";

type Props = {
  title: string;
  count: number;
  reorderMode: boolean;
  idFilter: string | null;
  searchInput: string;
  onSearchChange: (value: string) => void;
  searchMax: number;
  activeFilterCount: number;
  view: ViewMode;
  onViewChange: (view: ViewMode) => void;
  canCreate: boolean;
  onCreate: () => void;
  onOpenFilters: () => void;
};

export function TableToolbar({ title, count, reorderMode, idFilter, searchInput, onSearchChange, searchMax, activeFilterCount, view, onViewChange, canCreate, onCreate, onOpenFilters }: Props) {
  return (
    <div className="flex flex-wrap items-center gap-2 border-b p-3">
      <h2 className="mr-2 text-lg font-semibold">{title} <span className="text-sm font-normal text-muted-foreground">({count})</span></h2>
      {reorderMode ? <p className="flex-1 text-sm text-muted-foreground"><GripVertical className="inline h-4 w-4" /> {t("Drag to change the order.")}</p> : <>
        <div className="relative min-w-[200px] flex-1">
          <Search className="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-muted-foreground" />
          <input type="search" disabled={!!idFilter} aria-label={t("Search")} placeholder={t("Search…")} maxLength={searchMax} value={searchInput} onChange={(event) => onSearchChange(event.target.value.slice(0, searchMax))} className={`${inputCls} pl-8`} />
        </div>
        <button className={btnOutline} disabled={!!idFilter} onClick={onOpenFilters}>
          <SlidersHorizontal className="h-4 w-4" />{t("Filter and sorting")}
          {activeFilterCount > 0 && <span className="rounded-full bg-primary px-1.5 text-xs text-primary-foreground">{activeFilterCount}</span>}
        </button>
        <ToggleGroup.Root type="single" value={view} onValueChange={(value) => value && onViewChange(value as ViewMode)} aria-label={t("Select view")} className="inline-flex overflow-hidden rounded-md border">
          {([["master-detail", "Master/Detail", LayoutList], ["table", "Table", Table2], ["accordion", "Accordion", Rows3]] as const).map(([value, label, Icon]) => (
            <ToggleGroup.Item key={value} value={value} className="inline-flex items-center gap-1.5 border-r px-3 py-1.5 text-sm last:border-0 hover:bg-surface-hover data-[state=on]:bg-primary data-[state=on]:text-primary-foreground">
              <Icon className="h-4 w-4" /><span className="hidden sm:inline">{t(label)}</span>
            </ToggleGroup.Item>
          ))}
        </ToggleGroup.Root>
      </>}
      {canCreate && <button className={btnPrimary} onClick={onCreate}><Plus className="h-4 w-4" />{t("New")}</button>}
    </div>
  );
}
