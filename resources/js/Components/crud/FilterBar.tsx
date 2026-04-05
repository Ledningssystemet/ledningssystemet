import { useCallback, useState, useEffect } from "react";
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
import {
  Search,
  Plus,
  LayoutList,
  Table2,
  PanelLeftClose,
  X,
  Trash2,
  SlidersHorizontal,
  ArrowUp,
  ArrowDown,
} from "lucide-react";

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
  onAdd: () => void;
  selectedCount?: number;
  onMassEdit?: () => void;
  onMassDelete?: () => void;
  onClearSelection?: () => void;
  searchable?: boolean;
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
}: FilterBarProps) {
  const [localSearch, setLocalSearch] = useState(search);
  const [filterDialogOpen, setFilterDialogOpen] = useState(false);

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

  const filterableFields = fields.filter((f) => f.filterable && f.options);
  const sortableFields = fields.filter((f) => f.sortable);

  // Build active filter/sort badges
  const activeBadges: { key: string; label: string; onRemove: () => void }[] = [];

  for (const field of filterableFields) {
    const val = filters[field.key];
    if (val && val !== "") {
      if (Array.isArray(val) && val.length > 0) {
        const labels = val.map((v: any) => {
          const opt = field.options?.find((o) => String(o.value) === String(v));
          return opt?.label || v;
        });
        activeBadges.push({
          key: field.key,
          label: `${field.label}: ${labels.join(", ")}`,
          onRemove: () => onFilterChange(field.key, field.type === "multiselect" ? [] : ""),
        });
      } else if (!Array.isArray(val)) {
        const opt = field.options?.find((o) => String(o.value) === String(val));
        activeBadges.push({
          key: field.key,
          label: `${field.label}: ${opt?.label || val}`,
          onRemove: () => onFilterChange(field.key, ""),
        });
      }
    }
  }

  if (sort) {
    const sortField = sortableFields.find((f) => f.key === sort);
    activeBadges.push({
      key: "__sort__",
      label: `Sorterat: ${sortField?.label || sort} (${sortDirection === "asc" ? "stigande" : "fallande"})`,
      onRemove: () => onSortChange(""),
    });
  }

  const hasActiveFilters = activeBadges.length > 0 || search;

  const clearAll = () => {
    setLocalSearch("");
    onSearchChange("");
    filterableFields.forEach((f) =>
      onFilterChange(f.key, f.type === "multiselect" ? [] : "")
    );
    onSortChange("");
  };

  return (
    <>
      <div className="crud-toolbar flex-wrap">
        {searchable && (
          <div className="relative flex-1 min-w-[200px] max-w-sm">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              value={localSearch}
              onChange={(e) => {
                setLocalSearch(e.target.value);
                handleSearchDebounce(e.target.value);
              }}
              placeholder="Sök..."
              className="pl-9"
            />
          </div>
        )}

        <Button
          variant="outline"
          size="sm"
          onClick={() => setFilterDialogOpen(true)}
        >
          <SlidersHorizontal className="h-4 w-4 mr-1" />
          Filter & sortering
          {activeBadges.length > 0 && (
            <span className="ml-1.5 inline-flex items-center justify-center h-5 w-5 rounded-full bg-primary text-primary-foreground text-xs font-medium">
              {activeBadges.length}
            </span>
          )}
        </Button>

        {hasActiveFilters && (
          <Button variant="ghost" size="sm" onClick={clearAll}>
            <X className="h-4 w-4 mr-1" />
            Rensa
          </Button>
        )}

        <div className="flex items-center gap-1 ml-auto">
          {selectedCount > 0 && onMassEdit && (
            <Button variant="outline" size="sm" onClick={onMassEdit}>
              Redigera {selectedCount} st
            </Button>
          )}
          {selectedCount > 0 && onMassDelete && (
            <Button variant="destructive" size="sm" onClick={onMassDelete}>
              <Trash2 className="h-4 w-4 mr-1" />
              Radera {selectedCount} st
            </Button>
          )}
          {selectedCount > 0 && onClearSelection && (
            <Button variant="ghost" size="sm" onClick={onClearSelection}>
              <X className="h-4 w-4 mr-1" />
              Avmarkera
            </Button>
          )}

          <div className="flex border rounded-md overflow-hidden">
            <button
              onClick={() => onViewModeChange("master-detail")}
              className={`p-2 transition-colors ${
                viewMode === "master-detail"
                  ? "bg-primary text-primary-foreground"
                  : "hover:bg-muted"
              }`}
              title="Master/Detail"
            >
              <PanelLeftClose className="h-4 w-4" />
            </button>
            <button
              onClick={() => onViewModeChange("table")}
              className={`p-2 transition-colors ${
                viewMode === "table"
                  ? "bg-primary text-primary-foreground"
                  : "hover:bg-muted"
              }`}
              title="Tabell"
            >
              <Table2 className="h-4 w-4" />
            </button>
            <button
              onClick={() => onViewModeChange("accordion")}
              className={`p-2 transition-colors ${
                viewMode === "accordion"
                  ? "bg-primary text-primary-foreground"
                  : "hover:bg-muted"
              }`}
              title="Listvy"
            >
              <LayoutList className="h-4 w-4" />
            </button>
          </div>

          <Button onClick={onAdd} size="sm">
            <Plus className="h-4 w-4 mr-1" />
            Ny
          </Button>
        </div>
      </div>

      {/* Active filter/sort badges */}
      {activeBadges.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {activeBadges.map((badge) => (
            <Badge
              key={badge.key}
              variant="secondary"
              className="gap-1 pr-1 cursor-default"
            >
              {badge.label}
              <button
                onClick={badge.onRemove}
                className="ml-0.5 rounded-full hover:bg-muted-foreground/20 p-0.5"
              >
                <X className="h-3 w-3" />
              </button>
            </Badge>
          ))}
        </div>
      )}

      {/* Filter & sort dialog */}
      <Dialog open={filterDialogOpen} onOpenChange={setFilterDialogOpen}>
        <DialogContent
          className="max-w-md"
          onInteractOutside={(event) => event.preventDefault()}
          onEscapeKeyDown={(event) => event.preventDefault()}
        >
          <DialogHeader>
            <DialogTitle>Filter & sortering</DialogTitle>
          </DialogHeader>

          <div className="grid gap-4 py-2">
            {/* Filters */}
            {filterableFields.map((field) => (
              <div key={field.key} className="grid gap-1.5">
                <Label>{field.label}</Label>
                {field.type === "multiselect" ? (
                  <select
                    multiple
                    size={Math.min(5, field.options?.length || 1)}
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
                    {field.options?.map((opt) => (
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
                    <option value="">Alla</option>
                    {field.options?.map((opt) => (
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
                    <option value="">Alla</option>
                    {field.options?.map((opt) => (
                      <option key={opt.value} value={String(opt.value)}>
                        {opt.label}
                      </option>
                    ))}
                  </select>
                )}
              </div>
            ))}

            {/* Sort */}
            {sortableFields.length > 0 && (
              <div className="grid gap-1.5">
                <Label>Sortering</Label>
                <div className="flex items-center gap-2">
                  <select
                    value={sort}
                    onChange={(e) => onSortChange(e.target.value)}
                    className="flex h-10 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                  >
                    <option value="">Standard</option>
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
                        sortDirection === "asc" ? "Stigande" : "Fallande"
                      }
                    >
                      {sortDirection === "asc" ? (
                        <>
                          <ArrowUp className="h-4 w-4" /> Stigande
                        </>
                      ) : (
                        <>
                          <ArrowDown className="h-4 w-4" /> Fallande
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
              Rensa alla
            </Button>
            <Button size="sm" onClick={() => setFilterDialogOpen(false)}>
              Klar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
