import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { FieldConfig, ItemBadgeConfig, ItemStatus, RowActionConfig, SelectOption } from "./types";
import { GripVertical, Pencil, Trash2 } from "lucide-react";
import { StatusDot, statusRowClass } from "./StatusIndicator";
import { InlineTagsEditor } from "./InlineTagsEditor";
import { useAllSelectOptions, resolveOptions } from "./optionsCache";
import { DragEvent, Fragment, useEffect, useMemo, useRef, useState } from "react";
import { setupDragPreview } from "./dragPreview";

type DropPosition = "before" | "after";

interface TableViewProps {
  items: Record<string, any>[];
  fields: FieldConfig[];
  primaryKey: string;
  selectable?: boolean;
  selectedItems: Set<string | number>;
  onToggleSelect?: (id: string | number) => void;
  onSelectAll?: () => void;
  canEdit?: boolean;
  onEdit?: (item: Record<string, any>) => void;
  canDelete?: boolean;
  onDelete?: (id: string | number) => void;
  rowActions?: RowActionConfig[];
  onRowAction?: (action: RowActionConfig, item: Record<string, any>) => Promise<void>;
  onInlineFieldUpdate?: (item: Record<string, any>, fieldKey: string, value: any) => Promise<void>;
  getItemStatus?: (item: Record<string, any>) => ItemStatus | null;
  getItemBadge?: (item: Record<string, any>) => ItemBadgeConfig | null;
  editableKey?: string;
  deletableKey?: string;
  reorderEnabled?: boolean;
  onReorder?: (orderedIds: Array<string | number>) => Promise<void>;
}

export function TableView({
  items,
  fields,
  primaryKey,
  selectable = true,
  selectedItems,
  onToggleSelect,
  onSelectAll,
  canEdit = true,
  onEdit,
  canDelete = true,
  onDelete,
  rowActions = [],
  onRowAction,
  onInlineFieldUpdate,
  getItemStatus,
  getItemBadge,
  editableKey,
  deletableKey,
  reorderEnabled = false,
  onReorder,
}: TableViewProps) {
  const normalizedItems = Array.isArray(items) ? items : [];
  const visibleFields = fields.filter((f) => !f.hidden && !f.hiddenInTable);
  const allSelected = selectable && normalizedItems.length > 0 && normalizedItems.every((i) => selectedItems.has(i[primaryKey]));
  const optionsMap = useAllSelectOptions(fields);
  const showActions = canEdit || canDelete || rowActions.length > 0;
  const [draggedId, setDraggedId] = useState<string | number | null>(null);
  const [dropTarget, setDropTarget] = useState<{ id: string | number; position: DropPosition } | null>(null);
  const dragPreviewCleanupRef = useRef<(() => void) | null>(null);

  useEffect(() => {
    return () => {
      dragPreviewCleanupRef.current?.();
      dragPreviewCleanupRef.current = null;
    };
  }, []);

  const rowIds = useMemo(
    () => normalizedItems
      .map((item) => item[primaryKey])
      .filter((id): id is string | number => id !== undefined && id !== null),
    [normalizedItems, primaryKey]
  );

  const canReorder = reorderEnabled && Boolean(onReorder) && rowIds.length > 1;

  const columnCount = visibleFields.length + (selectable ? 1 : 0) + (canReorder ? 1 : 0) + (getItemStatus ? 1 : 0) + (showActions ? 1 : 0);

  const getDropPosition = (event: DragEvent<HTMLElement>): DropPosition => {
    const rect = event.currentTarget.getBoundingClientRect();
    return event.clientY < rect.top + rect.height / 2 ? "before" : "after";
  };

  const handleDrop = async (targetId: string | number, position: DropPosition, event?: DragEvent<HTMLElement>) => {
    const dragDataId = event?.dataTransfer?.getData("text/plain") || null;
    const sourceId = draggedId ?? dragDataId;

    if (!canReorder || sourceId === null || String(sourceId) === String(targetId)) {
      setDraggedId(null);
      setDropTarget(null);
      return;
    }

    const resolvedSourceId = rowIds.find((id) => String(id) === String(sourceId));
    const ordered = rowIds.filter((id) => String(id) !== String(sourceId));
    const toIndex = ordered.findIndex((id) => String(id) === String(targetId));

    if (resolvedSourceId === undefined || toIndex === -1) {
      setDraggedId(null);
      setDropTarget(null);
      return;
    }

    const insertIndex = position === "before" ? toIndex : toIndex + 1;
    ordered.splice(insertIndex, 0, resolvedSourceId);
    await onReorder?.(ordered);

    setDraggedId(null);
    setDropTarget(null);
  };

  const handleDragStart = (id: string | number, event: DragEvent<HTMLElement>) => {
    if (!canReorder) return;
    setDraggedId(id);
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", String(id));
    dragPreviewCleanupRef.current?.();
    dragPreviewCleanupRef.current = setupDragPreview(event);
  };

  return (
    <div className="border rounded-lg overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50">
              {selectable && (
                <th className="p-3 w-10">
                  <Checkbox
                    checked={allSelected}
                    onCheckedChange={onSelectAll}
                  />
                </th>
              )}
              {canReorder && <th className="p-3 w-10" />}
              {getItemStatus && <th className="p-3 w-8" />}
              {visibleFields.map((field) => (
                <th
                  key={field.key}
                  className="p-3 text-left font-medium text-muted-foreground"
                  style={field.width ? { width: field.width } : undefined}
                >
                  {field.label}
                </th>
              ))}
              {showActions && <th className="p-3 w-20" />}
            </tr>
          </thead>
          <tbody>
            {normalizedItems.map((item) => {
              const id = item[primaryKey];
              const isSelected = selectedItems.has(id);
              const status = getItemStatus?.(item) ?? null;
              const visibleRowActions = rowActions.filter((action) => (action.isVisible ? action.isVisible(item) : true));

              return (
                <Fragment key={id}>
                  {dropTarget && String(dropTarget.id) === String(id) && dropTarget.position === "before" && draggedId !== null && String(draggedId) !== String(id) && (
                    <tr aria-hidden className="border-b border-transparent">
                      <td colSpan={columnCount} className="p-0">
                        <div className="h-3 bg-success/20 border-y border-success/50" />
                      </td>
                    </tr>
                  )}
                  <tr
                    data-crud-drag-item
                    className={`border-b crud-row-hover ${isSelected ? "crud-row-selected" : ""} ${statusRowClass(status)} ${draggedId !== null && String(draggedId) === String(id) ? "opacity-45" : ""}`}
                    onDragOver={(event) => {
                      if (!canReorder) return;
                      event.preventDefault();
                      event.dataTransfer.dropEffect = "move";
                      setDropTarget({ id, position: getDropPosition(event) });
                    }}
                    onDrop={(event) => {
                      if (!canReorder) return;
                      event.preventDefault();
                      const position = dropTarget && String(dropTarget.id) === String(id)
                        ? dropTarget.position
                        : getDropPosition(event);
                      void handleDrop(id, position, event);
                    }}
                  >
                  {selectable && (
                    <td className="p-3">
                      <Checkbox
                        checked={isSelected}
                        onCheckedChange={() => onToggleSelect?.(id)}
                      />
                    </td>
                  )}
                  {canReorder && (
                    <td className="p-3 text-muted-foreground">
                      <span
                        className="inline-flex cursor-grab active:cursor-grabbing"
                        draggable={canReorder}
                        onDragStart={(event) => handleDragStart(id, event)}
                        onDragEnd={() => {
                          dragPreviewCleanupRef.current?.();
                          dragPreviewCleanupRef.current = null;
                          setDraggedId(null);
                          setDropTarget(null);
                        }}
                        title="Dra for att sortera"
                      >
                        <GripVertical className="h-4 w-4" />
                      </span>
                    </td>
                  )}
                  {getItemStatus && (
                    <td className="p-3">
                      <StatusDot status={status} />
                    </td>
                  )}
                  {visibleFields.map((field) => (
                    <td key={field.key} className="p-3">
                      {field.type === "inline-tags" && field.editable !== false && onInlineFieldUpdate ? (
                        <InlineTagsEditor
                          item={item}
                          field={field}
                          value={item[field.key]}
                          onSave={onInlineFieldUpdate}
                        />
                      ) : field.masterLabel ? (
                        <div className="flex items-center gap-2">
                          <span>{field.renderCell ? field.renderCell(item[field.key], item) : renderValue(item[field.key], field, optionsMap)}</span>
                          {getItemBadge?.(item) && (
                            <Badge variant={getItemBadge(item)?.variant || "secondary"}>
                              {getItemBadge(item)?.label}
                            </Badge>
                          )}
                        </div>
                      ) : field.renderCell ? (
                        field.renderCell(item[field.key], item)
                      ) : renderValue(item[field.key], field, optionsMap)}
                    </td>
                  ))}
                  {showActions && (
                    <td className="p-3">
                      <div className="flex items-center gap-1">
                        {canEdit && (editableKey ? item[editableKey] !== false : true) && onEdit && (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8"
                            onClick={() => onEdit(item)}
                          >
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                        )}
                        {canDelete && (deletableKey ? item[deletableKey] !== false : true) && onDelete && (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-destructive hover:text-destructive"
                            onClick={() => onDelete(id)}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        )}
                        {visibleRowActions.map((action) => (
                          <Button
                            key={action.key}
                            variant={action.variant || "ghost"}
                            size="sm"
                            className="h-8"
                            onClick={() => void onRowAction?.(action, item)}
                          >
                            {action.icon ? <span className="mr-1 inline-flex">{action.icon}</span> : null}
                            {action.label}
                          </Button>
                        ))}
                      </div>
                    </td>
                  )}
                  </tr>
                  {dropTarget && String(dropTarget.id) === String(id) && dropTarget.position === "after" && draggedId !== null && String(draggedId) !== String(id) && (
                    <tr aria-hidden className="border-b border-transparent">
                      <td colSpan={columnCount} className="p-0">
                        <div className="h-3 bg-success/20 border-y border-success/50" />
                      </td>
                    </tr>
                  )}
                </Fragment>
              );
            })}
            {items.length === 0 && (
              <tr>
                <td
                  colSpan={columnCount}
                  className="p-8 text-center text-muted-foreground"
                >
                  Inga resultat hittades
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function renderValue(value: any, field: FieldConfig, optionsMap: Map<string, SelectOption[]>) {
  if (value == null) return <span className="text-muted-foreground">—</span>;

  if (field.type === "boolean") {
    return (
      <span
        className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
          value
            ? "bg-success/10 text-success"
            : "bg-muted text-muted-foreground"
        }`}
      >
        {value ? "Ja" : "Nej"}
      </span>
    );
  }

  if ((field.type === "multiselect" || field.type === "tags" || field.type === "inline-tags") && Array.isArray(value)) {
    const opts = resolveOptions(field, optionsMap);
    return (
      <div className="flex flex-wrap gap-1">
        {value.map((v: any) => {
          const opt = opts.find((o) => String(o.value) === String(v));
          return (
            <Badge key={v} variant="secondary" className="text-xs">
              {opt?.label || v}
            </Badge>
          );
        })}
      </div>
    );
  }

  if (field.type === "select") {
    const opts = resolveOptions(field, optionsMap);
    const opt = opts.find((o) => String(o.value) === String(value));
    return opt?.label ?? String(value);
  }

  return String(value);
}
