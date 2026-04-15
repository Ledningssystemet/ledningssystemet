import { useEffect, useState } from "react";
import { CrudModuleConfig, RowActionConfig } from "./types";
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
} from "@/components/ui/alert-dialog";

interface CrudModuleProps {
   config: CrudModuleConfig;
   onEditFormDataChange?: (data: Record<string, any>) => void;
}

export function CrudModule({ config, onEditFormDataChange }: CrudModuleProps) {
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
    reorderByOrdinal,
    refetch,
  } = useCrudModule(config);

  const [editOpen, setEditOpen] = useState(false);
  const [editItem, setEditItem] = useState<Record<string, any> | null>(null);
  const [massEditOpen, setMassEditOpen] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<{ type: "single" | "mass"; id?: string | number } | null>(null);

  const primaryKey = config.primaryKey || "id";
  const hasOrdinalField = config.fields.some((field) => field.key === "ordinal");
  const isSearchEnabled = !hasOrdinalField && config.searchable !== false;
  const canCreate = config.canCreate !== false;
  const canEdit = config.canEdit !== false;
  const canDelete = config.canDelete !== false;
  const canSelect = config.selectable ?? (canEdit || canDelete);

  const noopToggleSelect = () => {};
  const noopSelectAll = () => {};
  const noopEdit = () => {};
  const noopDelete = () => {};
  const noopRowAction = async () => {};

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

  const handleRowAction = async (action: RowActionConfig, item: Record<string, any>) => {
    await action.onClick(item);
    if (action.refreshOnComplete !== false) {
      await refetch();
    }
  };

  useEffect(() => {
    if (hasOrdinalField && state.search) {
      setSearch("");
    }
  }, [hasOrdinalField, setSearch, state.search]);

  return (
    <div className="min-w-0 space-y-4">
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
        onAdd={canCreate ? () => openEdit(null) : undefined}
        selectedCount={state.selectedItems.size}
        onMassEdit={canSelect && canEdit && state.selectedItems.size > 0 ? openMassEdit : undefined}
        onMassDelete={canSelect && canDelete && state.selectedItems.size > 0 ? confirmMassDelete : undefined}
        onClearSelection={canSelect && state.selectedItems.size > 0 ? clearSelection : undefined}
        searchable={isSearchEnabled}
        sortLocked={hasOrdinalField}
        reorderEnabled={hasOrdinalField}
        hideSortFilterControls={hasOrdinalField}
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
          selectable={canSelect}
          selectedItems={state.selectedItems}
          onToggleSelect={canSelect ? toggleSelect : noopToggleSelect}
          onSelectAll={canSelect ? selectAll : noopSelectAll}
          canEdit={canEdit}
          onEdit={canEdit ? openEdit : noopEdit}
          canDelete={canDelete}
          onDelete={canDelete ? confirmDelete : noopDelete}
          onInlineFieldUpdate={handleInlineFieldUpdate}
          getItemStatus={config.getItemStatus}
          getItemBadge={config.getItemBadge}
          editableKey={config.editableKey}
          deletableKey={config.deletableKey}
          rowActions={config.rowActions || []}
          onRowAction={config.rowActions ? handleRowAction : noopRowAction}
          reorderEnabled={hasOrdinalField}
          onReorder={reorderByOrdinal}
        />
      )}

      {state.viewMode === "master-detail" && (
        <MasterDetailView
          items={state.items}
          fields={config.fields}
          primaryKey={primaryKey}
          activeItem={state.activeItem}
          onSelectItem={setActiveItem}
          canEdit={canEdit}
          onEdit={canEdit ? openEdit : noopEdit}
          canDelete={canDelete}
          onDelete={canDelete ? confirmDelete : noopDelete}
          onInlineFieldUpdate={handleInlineFieldUpdate}
          getItemStatus={config.getItemStatus}
          getItemBadge={config.getItemBadge}
          deletableKey={config.deletableKey}
          rowActions={config.rowActions || []}
          onRowAction={config.rowActions ? handleRowAction : noopRowAction}
          reorderEnabled={hasOrdinalField}
          onReorder={reorderByOrdinal}
        />
      )}

      {state.viewMode === "accordion" && (
        <AccordionView
          items={state.items}
          fields={config.fields}
          primaryKey={primaryKey}
          canEdit={canEdit}
          onEdit={canEdit ? openEdit : noopEdit}
          canDelete={canDelete}
          onDelete={canDelete ? confirmDelete : noopDelete}
          onInlineFieldUpdate={handleInlineFieldUpdate}
          getItemStatus={config.getItemStatus}
          getItemBadge={config.getItemBadge}
          deletableKey={config.deletableKey}
          rowActions={config.rowActions || []}
          onRowAction={config.rowActions ? handleRowAction : noopRowAction}
          reorderEnabled={hasOrdinalField}
          onReorder={reorderByOrdinal}
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
         onFormDataChange={onEditFormDataChange}
       />

      {/* Mass edit dialog */}
      {canEdit && (
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
      )}

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
