import { useCallback, useState, useEffect } from "react";
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/Components/ui/dialog";
import { Label } from "@/Components/ui/label";
import { FieldConfig, ViewMode } from "./types";
import { useAllSelectOptions, resolveOptions } from "./optionsCache";
import { useTranslations } from "@/hooks/useTranslations";

interface FilterBarProps {
  fields: FieldConfig[];
  search: string;
  onSearchChange: (search: string) => void;
  filters: Record<string, any>;
  onFilterChange: (key: string, value: any) => void;
  sort: string;
  sortDirection: "asc" | "desc";
  onSortChange: (field: string) => void;
  onSortDirectionChange: (dir: "asc" | "desc") => void;
  viewMode: ViewMode;
  onViewModeChange: (mode: ViewMode) => void;
  onAdd?: () => void;
  selectedCount?: number;
  onMassEdit?: () => void;
  onMassDelete?: () => void;
  onClearSelection?: () => void;
  searchable?: boolean;
  sortLocked?: boolean;
  reorderEnabled?: boolean;
  hideSortFilterControls?: boolean;
  onExportSelected?: () => void;
  onExportAll?: () => Promise<void>;
  exportingAll?: boolean;
}

export function FilterBar({
  fields,
  search,
  onSearchChange,
  filters,
  onFilterChange,
  sort,
  sortDirection,
  onSortChange,
  onSortDirectionChange,
  viewMode,
  onViewModeChange,
  onAdd,
  selectedCount = 0,
  onMassEdit,
  onMassDelete,
  onClearSelection,
  searchable = true,
  sortLocked = false,
  reorderEnabled = false,
  hideSortFilterControls = false,
  onExportSelected,
  onExportAll,
  exportingAll = false,
}: FilterBarProps) {
  const { t } = useTranslations();
  const [localSearch, setLocalSearch] = useState(search);
  const [filterDialogOpen, setFilterDialogOpen] = useState(false);
  const optionsMap = useAllSelectOptions(fields);

  useEffect(() => {
    setLocalSearch(search);
  }, [search]);

  const handleSearchDebounce = useCallback(
    (() => {
      let timer: ReturnType<typeof setTimeout>;
      return (val: string) => {
        clearTimeout(timer);
        timer = setTimeout(() => onSearchChange(val), 300);
      };
    })(),
    [onSearchChange]
  );

  const filterableFields = fields.filter(
    (f) => f.filterable && ((f.options && f.options.length > 0) || !!f.optionsUrl)
  );
  const sortableFields = fields.filter((f) => f.sortable);
  const showSortFilterControls = !hideSortFilterControls;

  // Build active filter/sort badges
  const activeBadges: { key: string; label: string; onRemove: () => void; removable?: boolean }[] = [];

  for (const field of filterableFields) {
    const val = filters[field.key];
    if (val && val !== "") {
      if (Array.isArray(val) && val.length > 0) {
        const labels = val.map((v: any) => {
          const opt = resolveOptions(field, optionsMap).find((o) => String(o.value) === String(v));
          return opt?.label || v;
        });
        activeBadges.push({
          key: field.key,
          label: `${field.label}: ${labels.join(", ")}`,
          onRemove: () => onFilterChange(field.key, field.type === "multiselect" ? [] : ""),
          removable: true,
        });
      } else if (!Array.isArray(val)) {
        const opt = resolveOptions(field, optionsMap).find((o) => String(o.value) === String(val));
        activeBadges.push({
          key: field.key,
          label: `${field.label}: ${opt?.label || val}`,
          onRemove: () => onFilterChange(field.key, ""),
          removable: true,
        });
      }
    }
  }

  if (sort) {
    const sortField = sortableFields.find((f) => f.key === sort);
    activeBadges.push({
      key: "__sort__",
      label: t("ui.crud.filter.sorted_badge", {
        field: sortField?.label || sort,
        direction: sortDirection === "asc" ? t("ui.crud.filter.direction_asc") : t("ui.crud.filter.direction_desc"),
      }),
      onRemove: () => (sortLocked ? undefined : onSortChange("")),
      removable: !sortLocked,
    });
  }

  if (sortLocked) {
    activeBadges.push({
      key: "__ordinal_lock__",
      label: t("ui.crud.filter.locked_sort_badge"),
      onRemove: () => undefined,
      removable: false,
    });
  }

  const hasActiveFilters = activeBadges.length > 0 || search;

  const clearAll = () => {
    setLocalSearch("");
    onSearchChange("");
    filterableFields.forEach((f) =>
      onFilterChange(f.key, f.type === "multiselect" ? [] : "")
    );
    if (!sortLocked) {
      onSortChange("");
    }
  };

  return (
    <>
      <div className="crud-toolbar flex-wrap">
        {showSortFilterControls && searchable && (
          <div className="relative flex-1 min-w-[200px] max-w-sm">
            <MaterialSymbol name="search" className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              data-testid="crud-search-input"
              value={localSearch}
              onChange={(e) => {
                setLocalSearch(e.target.value);
                handleSearchDebounce(e.target.value);
              }}
              placeholder={t("ui.crud.filter.search_placeholder")}
              className="pl-9"
            />
          </div>
        )}

        {showSortFilterControls && (
          <Button
            variant="outline"
            size="sm"
            onClick={() => setFilterDialogOpen(true)}
          >
            <MaterialSymbol name="tune" className="h-4 w-4 mr-1" />
            {t("ui.crud.filter.open_dialog")}
            {activeBadges.length > 0 && (
              <span className="ml-1.5 inline-flex items-center justify-center h-5 w-5 rounded-full bg-primary text-primary-foreground text-xs font-medium">
                {activeBadges.length}
              </span>
            )}
          </Button>
        )}

        {showSortFilterControls && hasActiveFilters && (
          <Button variant="ghost" size="sm" onClick={clearAll}>
            <MaterialSymbol name="close" className="h-4 w-4 mr-1" />
            {t("ui.crud.filter.clear")}
          </Button>
        )}

        {showSortFilterControls && reorderEnabled && (
          <span className="text-xs text-muted-foreground">{t("ui.crud.filter.reorder_hint")}</span>
        )}

        <div className="flex items-center gap-1 ml-auto">
          {selectedCount > 0 && onMassEdit && (
            <Button variant="outline" size="sm" onClick={onMassEdit}>
              {t("ui.crud.filter.mass_edit_selected", { count: selectedCount })}
            </Button>
          )}
          {selectedCount > 0 && onMassDelete && (
            <Button variant="destructive" size="sm" onClick={onMassDelete}>
              <MaterialSymbol name="delete" className="h-4 w-4 mr-1" />
              {t("ui.crud.filter.mass_delete_selected", { count: selectedCount })}
            </Button>
          )}
          {selectedCount > 0 && onClearSelection && (
            <Button variant="ghost" size="sm" onClick={onClearSelection}>
              <MaterialSymbol name="close" className="h-4 w-4 mr-1" />
              {t("ui.crud.filter.clear_selection")}
            </Button>
          )}

          {(onExportSelected || onExportAll) && (
            <>
              {onExportSelected && selectedCount > 0 && (
                <Button variant="outline" size="sm" onClick={onExportSelected} disabled={exportingAll}>
                  <MaterialSymbol name="download" className="h-4 w-4 mr-1" />
                  {t("ui.crud.filter.export_selected", { count: selectedCount })}
                </Button>
              )}
              {onExportAll && (
                <Button variant="outline" size="sm" onClick={() => void onExportAll()} disabled={exportingAll}>
                  {exportingAll ? (
                    <MaterialSymbol name="progress_activity" className="h-4 w-4 mr-1 animate-spin" />
                  ) : (
                    <MaterialSymbol name="download" className="h-4 w-4 mr-1" />
                  )}
                  {t("ui.crud.filter.export_all")}
                </Button>
              )}
            </>
          )}

          <div className="flex h-9 border rounded-md overflow-hidden">
            <button
              onClick={() => onViewModeChange("master-detail")}
              className={`h-full px-2 inline-flex items-center justify-center transition-colors ${
                viewMode === "master-detail"
                  ? "bg-primary text-primary-foreground"
                  : "hover:bg-muted"
              }`}
              title={t("ui.crud.filter.view_master_detail")}
            >
              <MaterialSymbol name="left_panel_close" className="h-4 w-4" />
            </button>
            <button
              onClick={() => onViewModeChange("table")}
              className={`h-full px-2 inline-flex items-center justify-center transition-colors ${
                viewMode === "table"
                  ? "bg-primary text-primary-foreground"
                  : "hover:bg-muted"
              }`}
              title={t("ui.crud.filter.view_table")}
            >
              <MaterialSymbol name="table_view" className="h-4 w-4" />
            </button>
            <button
              onClick={() => onViewModeChange("accordion")}
              className={`h-full px-2 inline-flex items-center justify-center transition-colors ${
                viewMode === "accordion"
                  ? "bg-primary text-primary-foreground"
                  : "hover:bg-muted"
              }`}
              title={t("ui.crud.filter.view_list")}
            >
              <MaterialSymbol name="view_list" className="h-4 w-4" />
            </button>
          </div>

          {onAdd && (
            <Button onClick={onAdd} size="sm" data-testid="crud-add-button">
              <MaterialSymbol name="add" className="h-4 w-4 mr-1" />
              {t("ui.crud.filter.add_new")}
            </Button>
          )}
        </div>
      </div>

      {/* Active filter/sort badges */}
      {showSortFilterControls && activeBadges.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {activeBadges.map((badge) => (
            <Badge
              key={badge.key}
              variant="secondary"
              className="gap-1 pr-1 cursor-default"
            >
              {badge.label}
              {badge.removable !== false && (
                <button
                  onClick={badge.onRemove}
                  className="ml-0.5 rounded-full hover:bg-muted-foreground/20 p-0.5"
                >
                  <MaterialSymbol name="close" className="h-3 w-3" />
                </button>
              )}
            </Badge>
          ))}
        </div>
      )}

      {/* Filter & sort dialog */}
      {showSortFilterControls && (
        <Dialog open={filterDialogOpen} onOpenChange={setFilterDialogOpen}>
          <DialogContent
            className="max-w-md"
            onInteractOutside={(event) => event.preventDefault()}
            onEscapeKeyDown={(event) => event.preventDefault()}
          >
            <DialogHeader>
              <DialogTitle>{t("ui.crud.filter.dialog_title")}</DialogTitle>
            </DialogHeader>

          <div className="grid gap-4 py-2">
            {/* Filters */}
            {filterableFields.map((field) => {
              const options = resolveOptions(field, optionsMap);

              return (
              <div key={field.key} className="grid gap-1.5">
                <Label>{field.label}</Label>
                {field.type === "multiselect" ? (
                  <select
                    multiple
                    size={Math.min(5, options.length || 1)}
                    value={
                      Array.isArray(filters[field.key])
                        ? filters[field.key].map(String)
                        : []
                    }
                    onChange={(e) => {
                      const selected = Array.from(e.target.selectedOptions).map(
                        (o) => o.value
                      );
                      onFilterChange(field.key, selected);
                    }}
                    className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                  >
                    {options.map((opt) => (
                      <option key={opt.value} value={String(opt.value)}>
                        {opt.label}
                      </option>
                    ))}
                  </select>
                ) : field.type === "boolean" ? (
                  <select
                    value={filters[field.key] ?? ""}
                    onChange={(e) =>
                      onFilterChange(field.key, e.target.value === "" ? "" : e.target.value)
                    }
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                  >
                    <option value="">{t("ui.crud.filter.option_all")}</option>
                    {options.map((opt) => (
                      <option key={String(opt.value)} value={String(opt.value)}>
                        {opt.label}
                      </option>
                    ))}
                  </select>
                ) : (
                  <select
                    value={filters[field.key] || ""}
                    onChange={(e) =>
                      onFilterChange(field.key, e.target.value === "" ? "" : e.target.value)
                    }
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                  >
                    <option value="">{t("ui.crud.filter.option_all")}</option>
                    {options.map((opt) => (
                      <option key={opt.value} value={String(opt.value)}>
                        {opt.label}
                      </option>
                    ))}
                  </select>
                )}
              </div>
              );
            })}

            {/* Sort */}
            {sortableFields.length > 0 && !sortLocked && (
              <div className="grid gap-1.5">
                <Label>{t("ui.crud.filter.sort_label")}</Label>
                <div className="flex items-center gap-2">
                  <select
                    value={sort}
                    onChange={(e) => onSortChange(e.target.value)}
                    className="flex h-10 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                  >
                    <option value="">{t("ui.crud.filter.sort_default")}</option>
                    {sortableFields.map((f) => (
                      <option key={f.key} value={f.key}>
                        {f.label}
                      </option>
                    ))}
                  </select>
                  {sort && (
                    <button
                      onClick={() =>
                        onSortDirectionChange(
                          sortDirection === "asc" ? "desc" : "asc"
                        )
                      }
                      className="flex items-center gap-1 h-10 px-3 rounded-md border border-input hover:bg-muted transition-colors text-sm"
                      title={
                        sortDirection === "asc" ? t("ui.crud.filter.direction_asc") : t("ui.crud.filter.direction_desc")
                      }
                    >
                      {sortDirection === "asc" ? (
                        <>
                          <MaterialSymbol name="arrow_upward" className="h-4 w-4" /> {t("ui.crud.filter.direction_asc")}
                        </>
                      ) : (
                        <>
                          <MaterialSymbol name="arrow_downward" className="h-4 w-4" /> {t("ui.crud.filter.direction_desc")}
                        </>
                      )}
                    </button>
                  )}
                </div>
              </div>
            )}
          </div>

            <DialogFooter>
              <Button variant="outline" size="sm" onClick={clearAll}>
                {t("ui.crud.filter.clear_all")}
              </Button>
              <Button size="sm" onClick={() => setFilterDialogOpen(false)}>
                {t("ui.crud.filter.done")}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}
    </>
  );
}
