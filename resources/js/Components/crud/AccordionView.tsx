import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";
import { MaterialSymbol } from "@/components/ui/material-symbol";
import {Badge} from "@/components/ui/badge";
import {FieldConfig, ItemBadgeConfig, RowActionConfig} from "./types";
import {StatusDot} from "./StatusIndicator";
import {getItemStatus} from "./itemStatus";
import {DragEvent, Fragment, useEffect, useMemo, useRef, useState} from "react";
import {setupDragPreview} from "./dragPreview";
import {useTranslations} from "@/hooks/useTranslations";
import {ItemDetailsContent} from "./ItemDetailsContent";

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
                                  getItemBadge,
                                  deletableKey,
                                  reorderEnabled = false,
                                  onReorder,
                              }: AccordionViewProps) {
    const {t} = useTranslations();
    // GÃ¶r komponenten robust mot att items Ã¤r null, undefined eller objekt med data-array
    const normalizedItems: CrudItem[] = Array.isArray(items)
        ? items
        : Array.isArray((items as { data?: CrudItem[] } | null | undefined)?.data)
            ? (items as { data?: CrudItem[] }).data ?? []
            : [];
    const labelField = fields.find((f) => f.masterLabel) || fields[0];
    const descField = fields.find((f) => f.masterDescription);
    const detailFields = fields.filter((f) => !f.hidden);
    const [draggedId, setDraggedId] = useState<string | number | null>(null);
    const [dropTarget, setDropTarget] = useState<{ id: string | number; position: DropPosition } | null>(null);
    const [expandedItems, setExpandedItems] = useState<string[]>([]);
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

    return (
        <div className="overflow-hidden">
            {normalizedItems.length === 0 ? (
                <div className="p-8 text-center text-muted-foreground">
                    {t("ui.crud.no_results")}
                </div>
            ) : (
                <Accordion
                    type="multiple"
                    className="divide-y"
                    value={expandedItems}
                    onValueChange={setExpandedItems}
                >
                    {normalizedItems.map((item: CrudItem) => {
                        const status = getItemStatus(item);
                        const itemId = item[primaryKey] as string | number;
                        const isExpanded = expandedItems.includes(String(itemId));
                        return (
                            <Fragment key={item[primaryKey]}>
                                {dropTarget && String(dropTarget.id) === String(itemId) && dropTarget.position === "before" && draggedId !== null && String(draggedId) !== String(itemId) && (
                                    <div aria-hidden className="h-3 bg-success/20 border-y border-success/50"/>
                                )}
                                <AccordionItem
                                    data-crud-drag-item
                                    value={String(item[primaryKey])}
                                    className={`border rounded-lg crud-row-hover mb-3 transition-all ${draggedId !== null && String(draggedId) === String(itemId) ? "opacity-45" : ""}`}
                                    onDragOver={(event) => {
                                        if (!canReorder) return;
                                        event.preventDefault();
                                        event.dataTransfer.dropEffect = "move";
                                        setDropTarget({id: itemId, position: getDropPosition(event)});
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
                        <MaterialSymbol name="drag_indicator" className="h-4 w-4"/>
                      </span>
                                            )}
                                            <StatusDot status={status}/>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-sm">{item[labelField.key]}</p>
                                                    {getItemBadge?.(item) && (
                                                        <Badge variant={getItemBadge(item)?.variant || "secondary"}>
                                                            {getItemBadge(item)?.label}
                                                        </Badge>
                                                    )}
                                                </div>
                                                {descField && !isExpanded && (
                                                    <p className="text-xs text-muted-foreground overflow-hidden max-h-8 font-normal">
                                                        {item[descField.key]}
                                                    </p>
                                                )}
                                                {isExpanded && status?.explanation? (
                                                    <p className="text-sm text-muted-foreground mt-1">{status.explanation}</p>
                                                ) : null}

                                            </div>
                                        </div>
                                    </AccordionTrigger>
                                    <AccordionContent className="px-4 pb-4">
                                        <ItemDetailsContent
                                            item={item}
                                            fields={detailFields}
                                            onInlineFieldUpdate={onInlineFieldUpdate}
                                            primaryKey={primaryKey}
                                            canEdit={canEdit}
                                            onEdit={onEdit}
                                            canDelete={canDelete}
                                            onDelete={onDelete}
                                            rowActions={rowActions}
                                            onRowAction={onRowAction}
                                            deletableKey={deletableKey}
                                        />
                                    </AccordionContent>
                                </AccordionItem>
                                {dropTarget && String(dropTarget.id) === String(itemId) && dropTarget.position === "after" && draggedId !== null && String(draggedId) !== String(itemId) && (
                                    <div aria-hidden className="h-3 bg-success/20 border-y border-success/50"/>
                                )}
                            </Fragment>
                        );
                    })}
                </Accordion>
            )}
        </div>
    );
}

