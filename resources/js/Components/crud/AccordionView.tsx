import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { FieldConfig, ItemBadgeConfig, ItemStatus, RowActionConfig, SelectOption } from "./types";
import { GripVertical, Pencil, Trash2 } from "lucide-react";
import { StatusDot, statusRowClass } from "./StatusIndicator";
import { InlineTagsEditor } from "./InlineTagsEditor";
import { useAllSelectOptions, resolveOptions } from "./optionsCache";
import { DragEvent, Fragment, useEffect, useMemo, useRef, useState } from "react";
import { setupDragPreview } from "./dragPreview";
import { useTranslations } from "@/hooks/useTranslations";
import { CollapsedMultiValueBadges } from "./CollapsedMultiValueBadges";

type DropPosition = "before" | "after";
type CrudItem = Record<string, any>;

interface AccordionViewProps {
  items: CrudItem[];
  fields: FieldConfig[];
  primaryKey: string;
  canEdit?: boolean;
  onEdit?: (item: CrudItem) => void;
  canDelete?: boolean;
  onDelete?: (id: string | number) => void;
  rowActions?: RowActionConfig[];
  onRowAction?: (action: RowActionConfig, item: CrudItem) => Promise<void>;
  onInlineFieldUpdate?: (item: CrudItem, fieldKey: string, value: any) => Promise<void>;
  getItemStatus?: (item: CrudItem) => ItemStatus | null;
  getItemBadge?: (item: CrudItem) => ItemBadgeConfig | null;
  deletableKey?: string;
  reorderEnabled?: boolean;
  onReorder?: (orderedIds: Array<string | number>) => Promise<void>;
}

export function AccordionView({
  items,
  fields,
  primaryKey,
  canEdit = true,
  onEdit,
  canDelete = true,
  onDelete,
  rowActions = [],
  onRowAction,
  onInlineFieldUpdate,
  getItemStatus,
  getItemBadge,
  deletableKey,
  reorderEnabled = false,
  onReorder,
}: AccordionViewProps) {
  const { t } = useTranslations();
  // Gör komponenten robust mot att items är null, undefined eller objekt med data-array
  const normalizedItems: CrudItem[] = Array.isArray(items)
    ? items
    : Array.isArray((items as { data?: CrudItem[] } | null | undefined)?.data)
      ? (items as { data?: CrudItem[] }).data ?? []
      : [];
  const labelField = fields.find((f) => f.masterLabel) || fields[0];
  const descField = fields.find((f) => f.masterDescription);
  const detailFields = fields.filter((f) => !f.hidden);
  const optionsMap = useAllSelectOptions(fields);
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
      .map((item: CrudItem) => item[primaryKey])
      .filter((id): id is string | number => id !== undefined && id !== null),
    [normalizedItems, primaryKey]
  );

  const canReorder = reorderEnabled && Boolean(onReorder) && rowIds.length > 1;

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
    event.stopPropagation();
    setDraggedId(id);
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", String(id));
    dragPreviewCleanupRef.current?.();
    dragPreviewCleanupRef.current = setupDragPreview(event);
  };

  // Group by category
  const categories = groupByCategory(detailFields);

  return (
    <div className="border rounded-lg overflow-hidden">
      {normalizedItems.length === 0 ? (
        <div className="p-8 text-center text-muted-foreground">
          {t("ui.crud.no_results")}
        </div>
      ) : (
        <Accordion type="single" collapsible className="divide-y">
          {normalizedItems.map((item: CrudItem) => {
            const status = getItemStatus?.(item) ?? null;
            const itemId = item[primaryKey] as string | number;
            const canDeleteItem = canDelete && (deletableKey ? item[deletableKey] !== false : true);
            const visibleRowActions = rowActions.filter((action) => (action.isVisible ? action.isVisible(item) : true));
            const showActions = (canEdit && Boolean(onEdit)) || (canDeleteItem && Boolean(onDelete)) || visibleRowActions.length > 0;
            return (
              <Fragment key={item[primaryKey]}>
                {dropTarget && String(dropTarget.id) === String(itemId) && dropTarget.position === "before" && draggedId !== null && String(draggedId) !== String(itemId) && (
                  <div aria-hidden className="h-3 bg-success/20 border-y border-success/50" />
                )}
                <AccordionItem
                  data-crud-drag-item
                  value={String(item[primaryKey])}
                  className={`border-none crud-row-hover transition-all ${statusRowClass(status)} ${draggedId !== null && String(draggedId) === String(itemId) ? "opacity-45" : ""}`}
                  onDragOver={(event) => {
                    if (!canReorder) return;
                    event.preventDefault();
                    event.dataTransfer.dropEffect = "move";
                    setDropTarget({ id: itemId, position: getDropPosition(event) });
                  }}
                  onDrop={(event) => {
                    if (!canReorder) return;
                    event.preventDefault();
                    const position = dropTarget && String(dropTarget.id) === String(itemId)
                      ? dropTarget.position
                      : getDropPosition(event);
                    void handleDrop(itemId, position, event);
                  }}
                >
                <AccordionTrigger className="px-4 py-3 hover:no-underline">
                  <div className="flex items-center gap-3 text-left">
                    {canReorder && (
                      <span
                        className="inline-flex cursor-grab text-muted-foreground active:cursor-grabbing"
                        draggable={canReorder}
                        onDragStart={(event) => handleDragStart(itemId, event)}
                        onDragEnd={(event) => {
                          event.stopPropagation();
                          dragPreviewCleanupRef.current?.();
                          dragPreviewCleanupRef.current = null;
                          setDraggedId(null);
                          setDropTarget(null);
                        }}
                        onClick={(event) => event.preventDefault()}
                        title={t("ui.crud.drag_to_reorder")}
                      >
                        <GripVertical className="h-4 w-4" />
                      </span>
                    )}
                    <StatusDot status={status} />
                    <div>
                      <div className="flex items-center gap-2">
                        <p className="font-medium text-sm">{item[labelField.key]}</p>
                        {getItemBadge?.(item) && (
                          <Badge variant={getItemBadge(item)?.variant || "secondary"}>
                            {getItemBadge(item)?.label}
                          </Badge>
                        )}
                      </div>
                      {descField && (
                        <p className="text-xs text-muted-foreground">
                          {item[descField.key]}
                        </p>
                      )}
                    </div>
                  </div>
                </AccordionTrigger>
                <AccordionContent className="px-4 pb-4">
                  <div className="bg-muted/30 rounded-md p-4 space-y-4">
                    {categories.map(({ category, fields: catFields }) => (
                      <div key={category}>
                        {category !== "__uncategorized__" && (
                          <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2 border-b pb-1">
                            {category}
                          </h4>
                        )}
                        <div className="grid gap-3">
                          {catFields.map((field) => (
                            <div key={field.key} className="grid grid-cols-3 gap-2 text-sm">
                              <span className="text-muted-foreground font-medium">
                                {field.label}
                              </span>
                              <span className="col-span-2">
                                {field.type === "inline-tags" && field.editable !== false && onInlineFieldUpdate
                                  ? (
                                    <InlineTagsEditor
                                      item={item}
                                      field={field}
                                      value={item[field.key]}
                                      onSave={onInlineFieldUpdate}
                                    />
                                  )
                                  : field.renderDetail
                                  ? field.renderDetail(item[field.key], item)
                                  : field.renderCell
                                    ? field.renderCell(item[field.key], item)
                                    : renderAccVal(item[field.key], field, optionsMap, t)}
                              </span>
                            </div>
                          ))}
                        </div>
                      </div>
                    ))}
                    {showActions && (
                      <div className="pt-2 flex justify-end gap-2">
                        {canEdit && onEdit && (
                          <Button variant="outline" size="sm" onClick={() => onEdit(item)}>
                            <Pencil className="h-4 w-4 mr-1" />
                            {t("ui.crud.action_edit")}
                          </Button>
                        )}
                        {canDeleteItem && onDelete && (
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive hover:text-destructive"
                            onClick={() => onDelete(itemId)}
                          >
                            <Trash2 className="h-4 w-4 mr-1" />
                            {t("ui.crud.action_delete")}
                          </Button>
                        )}
                        {visibleRowActions.map((action) => (
                          <Button
                            key={action.key}
                            variant={action.variant || "outline"}
                            size="sm"
                            onClick={() => void onRowAction?.(action, item)}
                          >
                            {action.icon ? <span className="mr-1 inline-flex">{action.icon}</span> : null}
                            {action.label}
                          </Button>
                        ))}
                      </div>
                    )}
                  </div>
                </AccordionContent>
                </AccordionItem>
                {dropTarget && String(dropTarget.id) === String(itemId) && dropTarget.position === "after" && draggedId !== null && String(draggedId) !== String(itemId) && (
                  <div aria-hidden className="h-3 bg-success/20 border-y border-success/50" />
                )}
              </Fragment>
            );
          })}
        </Accordion>
      )}
    </div>
  );
}

function groupByCategory(fields: FieldConfig[]) {
  const map = new Map<string, FieldConfig[]>();
  for (const f of fields) {
    const cat = f.category || "__uncategorized__";
    if (!map.has(cat)) map.set(cat, []);
    map.get(cat)!.push(f);
  }
  return Array.from(map.entries()).map(([category, fields]) => ({ category, fields }));
}

function renderAccVal(
  value: any,
  field: FieldConfig,
  optionsMap: Map<string, SelectOption[]>,
  t: (key: string, replacements?: Record<string, string | number>) => string,
) {
  if (value == null) return "-";
  if (field.type === "boolean") return value ? t("ui.crud.yes") : t("ui.crud.no");
  if ((field.type === "multiselect" || field.type === "tags" || field.type === "inline-tags") && Array.isArray(value)) {
    const opts = resolveOptions(field, optionsMap);
    return (
      <CollapsedMultiValueBadges
        values={value}
        options={opts}
        moreLabel={(remaining) => t("ui.crud.multi_value_more", { count: remaining })}
      />
    );
  }
  if (Array.isArray(value)) {
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
    return opts.find((o) => String(o.value) === String(value))?.label ?? String(value);
  }
  if (field.type === "textarea") return <span className="whitespace-pre-line">{String(value)}</span>;
  return String(value);
}
