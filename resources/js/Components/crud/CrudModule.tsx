import { useEffect, useMemo, useState } from "react";
import { CrudModuleConfig, FieldConfig, RowActionConfig, SubTableActionConfig } from "./types";
import { useCrudModule } from "./useCrudModule";
import { useCsvExport } from "./useCsvExport";
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { useTranslations } from "@/hooks/useTranslations";

interface CrudModuleProps {
   config: CrudModuleConfig;
   onEditFormDataChange?: (data: Record<string, any>) => void;
}

export function CrudModule({ config, onEditFormDataChange }: CrudModuleProps) {
  const { t } = useTranslations();

  // ── Merge filterFields into fields (hidden, non-editable, filterable) ──────
  const effectiveConfig = useMemo((): CrudModuleConfig => {
    if (!config.filterFields?.length) return config;
    const extraFields: FieldConfig[] = config.filterFields.map((ff) => ({
      key: ff.key,
      label: ff.label,
      type: ff.type,
      options: ff.options,
      optionsUrl: ff.optionsUrl,
      optionValueKey: ff.optionValueKey,
      optionLabelKey: ff.optionLabelKey,
      placeholder: ff.placeholder,
      hidden: true,
      editable: false,
      filterable: true,
    }));
    return { ...config, fields: [...config.fields, ...extraFields] };
  }, [config]);

  // ── Sub-table dialog state ────────────────────────────────────────────────
  // Maps actionKey → active item (null = dialog closed)
  const [subTableItems, setSubTableItems] = useState<Record<string, Record<string, any> | null>>({});

  const openSubTable = (key: string, item: Record<string, any>) =>
    setSubTableItems((prev) => ({ ...prev, [key]: item }));
  const closeSubTable = (key: string) =>
    setSubTableItems((prev) => ({ ...prev, [key]: null }));

  // Build extra rowActions from subTableActions
  const subTableRowActions: RowActionConfig[] = useMemo(
    () =>
      (effectiveConfig.subTableActions ?? []).map((sta) => ({
        key: sta.key,
        label: sta.label,
        icon: sta.icon,
        variant: sta.variant ?? "outline",
        isVisible: sta.isVisible,
        refreshOnComplete: false,
        onClick: (item) => openSubTable(sta.key, item),
      })),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [effectiveConfig.subTableActions]
  );

  const combinedRowActions: RowActionConfig[] = useMemo(
    () => [...(effectiveConfig.rowActions ?? []), ...subTableRowActions],
    [effectiveConfig.rowActions, subTableRowActions]
  );

  const configWithCombinedActions = useMemo(
    () => ({ ...effectiveConfig, rowActions: combinedRowActions }),
    [effectiveConfig, combinedRowActions]
  );

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
    buildExportQueryString,
  } = useCrudModule(configWithCombinedActions);

  const { exportSelected, exportAll, exporting: exportingAll } = useCsvExport({
    config: configWithCombinedActions,
    state,
    buildExportQueryString,
    title: configWithCombinedActions.title,
  });

  const [editOpen, setEditOpen] = useState(false);
  const [editItem, setEditItem] = useState<Record<string, any> | null>(null);
  const [massEditOpen, setMassEditOpen] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<{ type: "single" | "mass"; id?: string | number } | null>(null);

  const primaryKey = configWithCombinedActions.primaryKey || "id";
  const hasOrdinalField = configWithCombinedActions.fields.some((field) => field.key === "ordinal");
  const isSearchEnabled = !hasOrdinalField && configWithCombinedActions.searchable !== false;
  const canCreate = configWithCombinedActions.canCreate !== false;
  const canEdit = configWithCombinedActions.canEdit !== false;
  const canDelete = configWithCombinedActions.canDelete !== false;
  const canSelect = configWithCombinedActions.selectable ?? (canEdit || canDelete);

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
      {configWithCombinedActions.title && (
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-semibold">{configWithCombinedActions.title}</h1>
          {state.loading && (
            <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
          )}
        </div>
      )}

      {!configWithCombinedActions.title && state.loading && (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          {t("ui.common.loading")}
        </div>
      )}

      <FilterBar
        fields={configWithCombinedActions.fields}
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
        onExportSelected={state.selectedItems.size > 0 ? exportSelected : undefined}
        onExportAll={exportAll}
        exportingAll={exportingAll}
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
          fields={configWithCombinedActions.fields}
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
          getItemStatus={configWithCombinedActions.getItemStatus}
          getItemBadge={configWithCombinedActions.getItemBadge}
          editableKey={configWithCombinedActions.editableKey}
          deletableKey={configWithCombinedActions.deletableKey}
          rowActions={combinedRowActions}
          onRowAction={combinedRowActions.length > 0 ? handleRowAction : noopRowAction}
          reorderEnabled={hasOrdinalField}
          onReorder={reorderByOrdinal}
        />
      )}

      {state.viewMode === "master-detail" && (
        <MasterDetailView
          items={state.items}
          fields={configWithCombinedActions.fields}
          primaryKey={primaryKey}
          activeItem={state.activeItem}
          onSelectItem={setActiveItem}
          canEdit={canEdit}
          onEdit={canEdit ? openEdit : noopEdit}
          canDelete={canDelete}
          onDelete={canDelete ? confirmDelete : noopDelete}
          onInlineFieldUpdate={handleInlineFieldUpdate}
          getItemStatus={configWithCombinedActions.getItemStatus}
          getItemBadge={configWithCombinedActions.getItemBadge}
          deletableKey={configWithCombinedActions.deletableKey}
          rowActions={combinedRowActions}
          onRowAction={combinedRowActions.length > 0 ? handleRowAction : noopRowAction}
          reorderEnabled={hasOrdinalField}
          onReorder={reorderByOrdinal}
        />
      )}

      {state.viewMode === "accordion" && (
        <AccordionView
          items={state.items}
          fields={configWithCombinedActions.fields}
          primaryKey={primaryKey}
          canEdit={canEdit}
          onEdit={canEdit ? openEdit : noopEdit}
          canDelete={canDelete}
          onDelete={canDelete ? confirmDelete : noopDelete}
          onInlineFieldUpdate={handleInlineFieldUpdate}
          getItemStatus={configWithCombinedActions.getItemStatus}
          getItemBadge={configWithCombinedActions.getItemBadge}
          deletableKey={configWithCombinedActions.deletableKey}
          rowActions={combinedRowActions}
          onRowAction={combinedRowActions.length > 0 ? handleRowAction : noopRowAction}
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
         fields={configWithCombinedActions.fields}
         title={editItem ? configWithCombinedActions.editTitle : configWithCombinedActions.createTitle}
         onSave={saveItem}
         onFormDataChange={onEditFormDataChange}
       />

      {/* Mass edit dialog */}
      {canEdit && (
        <MassEditDialog
          open={massEditOpen}
          onOpenChange={setMassEditOpen}
          fields={configWithCombinedActions.fields.filter((f) => f.editable !== false && !f.hidden)}
          count={state.selectedItems.size}
          onSave={async (data) => {
            if (Object.keys(data).length === 0) return;
            const ids = Array.from(state.selectedItems);
            await Promise.all(
              ids.map((id) =>
                fetch(`${configWithCombinedActions.apiUrl}/${id}`, {
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
            <AlertDialogTitle>{t("ui.crud.delete_confirm.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {deleteConfirm?.type === "mass"
                ? t("ui.crud.delete_confirm.description_mass", { count: state.selectedItems.size })
                : t("ui.crud.delete_confirm.description_single")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("ui.crud.action_cancel")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={executeDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {t("ui.crud.action_delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Sub-table dialogs (generated from subTableActions) */}
      {(configWithCombinedActions.subTableActions ?? []).map((sta: SubTableActionConfig) => {
        const activeItem = subTableItems[sta.key] ?? null;
        const isOpen = Boolean(activeItem);
        const childConfig = isOpen ? sta.getConfig(activeItem!) : null;
        const titleStr = activeItem
          ? typeof sta.dialogTitle === "function"
            ? sta.dialogTitle(activeItem)
            : sta.dialogTitle
          : "";
        const descStr = activeItem && sta.dialogDescription
          ? typeof sta.dialogDescription === "function"
            ? sta.dialogDescription(activeItem)
            : sta.dialogDescription
          : undefined;

        return (
          <Dialog
            key={sta.key}
            open={isOpen}
            onOpenChange={(open) => !open && closeSubTable(sta.key)}
          >
            <DialogContent className={sta.dialogMaxWidth ?? "max-w-4xl"}>
              <DialogHeader>
                <DialogTitle className="flex items-center gap-2">
                  {sta.icon && <span className="flex h-5 w-5 items-center">{sta.icon}</span>}
                  {titleStr}
                </DialogTitle>
                {descStr && <DialogDescription>{descStr}</DialogDescription>}
              </DialogHeader>
              <div className="mt-2">
                {childConfig && (
                  <CrudModule
                    key={`${sta.key}-${activeItem?.[configWithCombinedActions.primaryKey ?? "id"]}`}
                    config={childConfig}
                    onEditFormDataChange={sta.onEditFormDataChange}
                  />
                )}
              </div>
            </DialogContent>
          </Dialog>
        );
      })}
    </div>
  );
}

// Re-export EditDialog for standalone use
export { EditDialog } from "./EditDialog";
