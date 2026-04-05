import { useState } from "react";
import { CrudModuleConfig } from "./types";
import { useCrudModule } from "./useCrudModule";
import { FilterBar } from "./FilterBar";
import { TableView } from "./TableView";
import { MasterDetailView } from "./MasterDetailView";
import { AccordionView } from "./AccordionView";
import { EditDialog } from "./EditDialog";
import { MassEditDialog } from "./MassEditDialog";
import { CrudPagination } from "./Pagination";
import { Loader2 } from "lucide-react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";

interface CrudModuleProps {
  config: CrudModuleConfig;
}

export function CrudModule({ config }: CrudModuleProps) {
  const {
    state,
    setSearch,
    setFilter,
    setSort,
    setSortDirection,
    setPage,
    setPerPage,
    setViewMode,
    setActiveItem,
    toggleSelect,
    selectAll,
    clearSelection,
    saveItem,
    patchItem,
    deleteItem,
    massDelete,
    refetch,
  } = useCrudModule(config);

  const [editOpen, setEditOpen] = useState(false);
  const [editItem, setEditItem] = useState<Record<string, any> | null>(null);
  const [massEditOpen, setMassEditOpen] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<{ type: "single" | "mass"; id?: string | number } | null>(null);

  const primaryKey = config.primaryKey || "id";

  const openEdit = (item: Record<string, any> | null) => {
    setEditItem(item);
    setEditOpen(true);
  };

  const openMassEdit = () => {
    setMassEditOpen(true);
  };

  const confirmDelete = (id: string | number) => {
    setDeleteConfirm({ type: "single", id });
  };

  const confirmMassDelete = () => {
    setDeleteConfirm({ type: "mass" });
  };

  const executeDelete = async () => {
    if (!deleteConfirm) return;
    if (deleteConfirm.type === "single" && deleteConfirm.id !== undefined) {
      await deleteItem(deleteConfirm.id);
    } else if (deleteConfirm.type === "mass") {
      await massDelete();
    }
    setDeleteConfirm(null);
  };

  const handleInlineFieldUpdate = async (
    item: Record<string, any>,
    fieldKey: string,
    value: any,
  ) => {
    const id = item[primaryKey];
    if (id === undefined || id === null) {
      await saveItem({ ...item, [fieldKey]: value });
      return;
    }

    await patchItem(id, { [fieldKey]: value });
  };

  return (
    <div className="space-y-4">
      {config.title && (
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-semibold">{config.title}</h1>
          {state.loading && (
            <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
          )}
        </div>
      )}

      {!config.title && state.loading && (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          Laddar...
        </div>
      )}

      <FilterBar
        fields={config.fields}
        search={state.search}
        onSearchChange={setSearch}
        filters={state.filters}
        onFilterChange={setFilter}
        sort={state.sort}
        sortDirection={state.sortDirection}
        onSortChange={setSort}
        onSortDirectionChange={setSortDirection}
        viewMode={state.viewMode}
        onViewModeChange={setViewMode}
        onAdd={() => openEdit(null)}
        selectedCount={state.selectedItems.size}
        onMassEdit={state.selectedItems.size > 0 ? openMassEdit : undefined}
        onMassDelete={state.selectedItems.size > 0 ? confirmMassDelete : undefined}
        onClearSelection={state.selectedItems.size > 0 ? clearSelection : undefined}
        searchable={config.searchable !== false}
      />

      <CrudPagination
        page={state.page}
        totalPages={state.totalPages}
        total={state.total}
        perPage={state.perPage}
        onPageChange={setPage}
        onPerPageChange={setPerPage}
      />

      {state.viewMode === "table" && (
        <TableView
          items={state.items}
          fields={config.fields}
          primaryKey={primaryKey}
          selectedItems={state.selectedItems}
          onToggleSelect={toggleSelect}
          onSelectAll={selectAll}
          onEdit={openEdit}
          onDelete={confirmDelete}
          onInlineFieldUpdate={handleInlineFieldUpdate}
          getItemStatus={config.getItemStatus}
          getItemBadge={config.getItemBadge}
          editableKey={config.editableKey}
          deletableKey={config.deletableKey}
        />
      )}

      {state.viewMode === "master-detail" && (
        <MasterDetailView
          items={state.items}
          fields={config.fields}
          primaryKey={primaryKey}
          activeItem={state.activeItem}
          onSelectItem={setActiveItem}
          onEdit={openEdit}
          onInlineFieldUpdate={handleInlineFieldUpdate}
          getItemStatus={config.getItemStatus}
          getItemBadge={config.getItemBadge}
        />
      )}

      {state.viewMode === "accordion" && (
        <AccordionView
          items={state.items}
          fields={config.fields}
          primaryKey={primaryKey}
          onEdit={openEdit}
          onInlineFieldUpdate={handleInlineFieldUpdate}
          getItemStatus={config.getItemStatus}
          getItemBadge={config.getItemBadge}
        />
      )}

      <CrudPagination
        page={state.page}
        totalPages={state.totalPages}
        total={state.total}
        perPage={state.perPage}
        onPageChange={setPage}
        onPerPageChange={setPerPage}
      />

      {/* Edit single item */}
      <EditDialog
        open={editOpen}
        onOpenChange={setEditOpen}
        item={editItem}
        fields={config.fields}
        title={editItem ? config.editTitle : config.createTitle}
        onSave={saveItem}
      />

      {/* Mass edit dialog */}
      <MassEditDialog
        open={massEditOpen}
        onOpenChange={setMassEditOpen}
        fields={config.fields.filter((f) => f.editable !== false && !f.hidden)}
        count={state.selectedItems.size}
        onSave={async (data) => {
          if (Object.keys(data).length === 0) return;
          const ids = Array.from(state.selectedItems);
          await Promise.all(
            ids.map((id) =>
              fetch(`${config.apiUrl}/${id}`, {
                method: "PATCH",
                headers: { "Content-Type": "application/json", Accept: "application/json" },
                body: JSON.stringify(data),
              })
            )
          );
          await refetch();
          setMassEditOpen(false);
        }}
      />

      {/* Delete confirmation */}
      <AlertDialog open={!!deleteConfirm} onOpenChange={(open) => !open && setDeleteConfirm(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Bekräfta radering</AlertDialogTitle>
            <AlertDialogDescription>
              {deleteConfirm?.type === "mass"
                ? `Är du säker på att du vill radera ${state.selectedItems.size} valda poster? Detta kan inte ångras.`
                : "Är du säker på att du vill radera denna post? Detta kan inte ångras."}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Avbryt</AlertDialogCancel>
            <AlertDialogAction
              onClick={executeDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Radera
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

// Re-export EditDialog for standalone use
export { EditDialog } from "./EditDialog";
