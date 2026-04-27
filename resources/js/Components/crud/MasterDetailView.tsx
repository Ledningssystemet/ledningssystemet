import {FieldConfig, ItemBadgeConfig, ItemStatus, RowActionConfig, SelectOption} from "./types";
import { MaterialSymbol } from "@/components/ui/material-symbol";
import {Button} from "@/components/ui/button";
import {Badge} from "@/components/ui/badge";
import {ScrollArea} from "@/components/ui/scroll-area";
import {StatusDot, statusRowClass} from "./StatusIndicator";
import {ResizablePanelGroup, ResizablePanel, ResizableHandle} from "@/components/ui/resizable";
import {InlineTagsEditor} from "./InlineTagsEditor";
import {useAllSelectOptions, resolveOptions} from "./optionsCache";
import {DragEvent, Fragment, useEffect, useMemo, useRef, useState} from "react";
import {setupDragPreview} from "./dragPreview";
import {useTranslations} from "@/hooks/useTranslations";
import {CollapsedMultiValueBadges} from "./CollapsedMultiValueBadges";

type DropPosition = "before" | "after";

interface MasterDetailViewProps {
    items: Record<string, any>[];
    fields: FieldConfig[];
    primaryKey: string;
    activeItem: Record<string, any> | null;
    onSelectItem: (item: Record<string, any>) => void;
    canEdit?: boolean;
    onEdit?: (item: Record<string, any>) => void;
    canDelete?: boolean;
    onDelete?: (id: string | number) => void;
    rowActions?: RowActionConfig[];
    onRowAction?: (action: RowActionConfig, item: Record<string, any>) => Promise<void>;
    onInlineFieldUpdate?: (item: Record<string, any>, fieldKey: string, value: any) => Promise<void>;
    getItemStatus?: (item: Record<string, any>) => ItemStatus | null;
    getItemBadge?: (item: Record<string, any>) => ItemBadgeConfig | null;
    deletableKey?: string;
    reorderEnabled?: boolean;
    onReorder?: (orderedIds: Array<string | number>) => Promise<void>;
}

export function MasterDetailView({
                                     items,
                                     fields,
                                     primaryKey,
                                     activeItem,
                                     onSelectItem,
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
                                 }: MasterDetailViewProps) {
    const {t} = useTranslations();
    const normalizedItems = Array.isArray(items) ? items : [];
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
            .map((item) => item[primaryKey])
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

    // Group detail fields by category
    const categories = groupByCategory(detailFields);

    const activeItemId = activeItem?.[primaryKey];
    const canDeleteActiveItem = Boolean(
        canDelete && activeItem && activeItemId !== undefined && activeItemId !== null && (deletableKey ? activeItem[deletableKey] !== false : true)
    );
    const visibleActiveRowActions = activeItem
        ? rowActions.filter((action) => (action.isVisible ? action.isVisible(activeItem) : true))
        : [];
    const showActions = canEdit || canDeleteActiveItem || visibleActiveRowActions.length > 0;

    return (
        <ResizablePanelGroup orientation="horizontal" className="border rounded-lg overflow-hidden"
                             style={{height: "calc(100vh - 220px)"}}>
            <ResizablePanel defaultSize="30%" minSize="20%" maxSize="60%">
                <ScrollArea className="h-full bg-crud-master">
                    <div className="divide-y">
                        {normalizedItems.map((item) => {
                            const id = item[primaryKey];
                            const isActive = activeItem?.[primaryKey] === id;
                            const status = getItemStatus?.(item) ?? null;

                            return (
                                <Fragment key={id}>
                                    {dropTarget && String(dropTarget.id) === String(id) && dropTarget.position === "before" && draggedId !== null && String(draggedId) !== String(id) && (
                                        <div aria-hidden className="h-3 bg-success/20 border-y border-success/50"/>
                                    )}
                                    <button
                                        data-crud-drag-item
                                        onClick={() => onSelectItem(item)}
                                        className={`w-full text-left p-4 transition-colors crud-row-hover ${statusRowClass(status)} ${
                                            isActive ? "crud-row-selected border-l-2 border-l-primary" : ""
                                        } ${draggedId !== null && String(draggedId) === String(id) ? "opacity-45" : ""}`}
                                        onDragOver={(event) => {
                                            if (!canReorder) return;
                                            event.preventDefault();
                                            event.dataTransfer.dropEffect = "move";
                                            setDropTarget({id, position: getDropPosition(event)});
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
                                        <div className="flex items-center gap-2">
                                            {canReorder && (
                                                <span
                                                    className="inline-flex cursor-grab text-muted-foreground active:cursor-grabbing"
                                                    draggable={canReorder}
                                                    onDragStart={(event) => handleDragStart(id, event)}
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
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-sm truncate">{item[labelField.key]}</p>
                                                    {getItemBadge?.(item) && (
                                                        <Badge variant={getItemBadge(item)?.variant || "secondary"}
                                                               className="shrink-0">
                                                            {getItemBadge(item)?.label}
                                                        </Badge>
                                                    )}
                                                </div>
                                                {descField && (
                                                    <p className="text-xs text-muted-foreground mt-1 truncate">
                                                        {item[descField.key]}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </button>
                                    {dropTarget && String(dropTarget.id) === String(id) && dropTarget.position === "after" && draggedId !== null && String(draggedId) !== String(id) && (
                                        <div aria-hidden className="h-3 bg-success/20 border-y border-success/50"/>
                                    )}
                                </Fragment>
                            );
                        })}
                        {items.length === 0 && (
                            <div className="p-8 text-center text-muted-foreground text-sm">
                                {t("ui.crud.no_results")}
                            </div>
                        )}
                    </div>
                </ScrollArea>
            </ResizablePanel>

            <ResizableHandle withHandle/>

            <ResizablePanel defaultSize="70%" minSize="30%">
                <div className="h-full bg-crud-detail">
                    {activeItem ? (
                        <ScrollArea className="h-full">
                            <div className="p-6">
                                <div className="flex items-start justify-between mb-6">
                                    <div className="flex items-center gap-2">
                                        <StatusDot status={getItemStatus?.(activeItem) ?? null} className="h-3 w-3"/>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h2 className="text-xl font-semibold">{activeItem[labelField.key]}</h2>
                                                {getItemBadge?.(activeItem) && (
                                                    <Badge variant={getItemBadge(activeItem)?.variant || "secondary"}>
                                                        {getItemBadge(activeItem)?.label}
                                                    </Badge>
                                                )}
                                            </div>
                                            {descField && (
                                                <p className="text-muted-foreground mt-1 whitespace-pre-line">
                                                    {activeItem[descField.key]}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    {showActions && (
                                        <div className="flex items-center gap-2">
                                            {canEdit && onEdit && (
                                                <Button variant="outline" size="sm" onClick={() => onEdit(activeItem)}>
                                                    <MaterialSymbol name="edit" className="h-4 w-4 mr-1"/>
                                                    {t("ui.crud.action_edit")}
                                                </Button>
                                            )}
                                            {canDeleteActiveItem && onDelete && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => onDelete(activeItemId as string | number)}
                                                >
                                                    <MaterialSymbol name="delete" className="h-4 w-4 mr-1"/>
                                                    {t("ui.crud.action_delete")}
                                                </Button>
                                            )}
                                            {activeItem && visibleActiveRowActions.map((action) => (
                                                <Button
                                                    key={action.key}
                                                    variant={action.variant || "outline"}
                                                    size="sm"
                                                    onClick={() => void onRowAction?.(action, activeItem)}
                                                >
                                                    {action.icon ?
                                                        <span className="mr-1 inline-flex">{action.icon}</span> : null}
                                                    {action.label}
                                                </Button>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                {categories.map(({category, fields: catFields}) => (
                                    <div key={category} className="mb-6">
                                        {category !== "__uncategorized__" && (
                                            <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3 border-b pb-1">
                                                {category}
                                            </h3>
                                        )}
                                        <div className="grid gap-4">
                                            {catFields.map((field) => {
                                                const value = activeItem[field.key];
                                                return (
                                                    <div key={field.key} className="grid grid-cols-3 gap-2">
                            <span className="text-sm font-medium text-muted-foreground">
                              {field.label}
                            </span>
                                                        <span className="text-sm col-span-2">
                              {field.type === "inline-tags" && field.editable !== false && onInlineFieldUpdate
                                  ? (
                                      <InlineTagsEditor
                                          item={activeItem}
                                          field={field}
                                          value={value}
                                          onSave={onInlineFieldUpdate}
                                      />
                                  )
                                  : field.renderDetail
                                      ? field.renderDetail(value, activeItem)
                                      : renderDetailValue(value, field, optionsMap, t)}
                            </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </ScrollArea>
                    ) : (
                        <div className="flex items-center justify-center h-full text-muted-foreground">
                            {t("ui.crud.select_item_in_list")}
                        </div>
                    )}
                </div>
            </ResizablePanel>
        </ResizablePanelGroup>
    );
}

function groupByCategory(fields: FieldConfig[]) {
    const map = new Map<string, FieldConfig[]>();
    for (const f of fields) {
        const cat = f.category || "__uncategorized__";
        if (!map.has(cat)) map.set(cat, []);
        map.get(cat)!.push(f);
    }
    return Array.from(map.entries()).map(([category, fields]) => ({category, fields}));
}

function renderDetailValue(
    value: any,
    field: FieldConfig,
    optionsMap: Map<string, SelectOption[]>,
    t: (key: string, replacements?: Record<string, string | number>) => string,
) {
    if (value == null) return "â€”";
    if (field.type === "boolean") return value ? t("ui.crud.yes") : t("ui.crud.no");
    if ((field.type === "multiselect" || field.type === "tags" || field.type === "inline-tags") && Array.isArray(value)) {
        const opts = resolveOptions(field, optionsMap);
        return (
            <CollapsedMultiValueBadges
                values={value}
                options={opts}
                moreLabel={(remaining) => t("ui.crud.multi_value_more", {count: remaining})}
            />
        );
    }
    if (field.type === "select") {
        const opts = resolveOptions(field, optionsMap);
        return opts.find((o) => String(o.value) === String(value))?.label ?? String(value);
    }
    if (field.type === "textarea") {
        return <span className="whitespace-pre-line">{String(value)}</span>;
    }

    if (field.type === "date" && typeof value === "string") {
        return new Date(value).toLocaleDateString();
    }

    if (field.type === "datetime" && typeof value === "string") {
        return new Date(value).toLocaleString();
    }

    return String(value);
}
