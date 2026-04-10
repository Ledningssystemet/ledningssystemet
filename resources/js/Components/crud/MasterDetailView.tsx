import { FieldConfig, ItemBadgeConfig, ItemStatus, SelectOption } from "./types";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Pencil, Trash2 } from "lucide-react";
import { ScrollArea } from "@/components/ui/scroll-area";
import { StatusDot, statusRowClass } from "./StatusIndicator";
import { ResizablePanelGroup, ResizablePanel, ResizableHandle } from "@/components/ui/resizable";
import { InlineTagsEditor } from "./InlineTagsEditor";
import { useAllSelectOptions, resolveOptions } from "./optionsCache";

interface MasterDetailViewProps {
  items: Record<string, any>[];
  fields: FieldConfig[];
  primaryKey: string;
  activeItem: Record<string, any> | null;
  onSelectItem: (item: Record<string, any>) => void;
  onEdit: (item: Record<string, any>) => void;
  onDelete: (id: string | number) => void;
  onInlineFieldUpdate?: (item: Record<string, any>, fieldKey: string, value: any) => Promise<void>;
  getItemStatus?: (item: Record<string, any>) => ItemStatus | null;
  getItemBadge?: (item: Record<string, any>) => ItemBadgeConfig | null;
  deletableKey?: string;
}

export function MasterDetailView({
  items,
  fields,
  primaryKey,
  activeItem,
  onSelectItem,
  onEdit,
  onDelete,
  onInlineFieldUpdate,
  getItemStatus,
  getItemBadge,
  deletableKey,
}: MasterDetailViewProps) {
  const labelField = fields.find((f) => f.masterLabel) || fields[0];
  const descField = fields.find((f) => f.masterDescription);
  const detailFields = fields.filter((f) => !f.hidden);
  const optionsMap = useAllSelectOptions(fields);

  // Group detail fields by category
  const categories = groupByCategory(detailFields);

  const activeItemId = activeItem?.[primaryKey];
  const canDeleteActiveItem = Boolean(
    activeItem && activeItemId !== undefined && activeItemId !== null && (deletableKey ? activeItem[deletableKey] !== false : true)
  );

  return (
    <ResizablePanelGroup orientation="horizontal" className="border rounded-lg overflow-hidden" style={{ height: "calc(100vh - 220px)" }}>
      <ResizablePanel defaultSize="30%" minSize="20%" maxSize="60%">
        <ScrollArea className="h-full bg-crud-master">
          <div className="divide-y">
            {items.map((item) => {
              const id = item[primaryKey];
              const isActive = activeItem?.[primaryKey] === id;
              const status = getItemStatus?.(item) ?? null;

              return (
                <button
                  key={id}
                  onClick={() => onSelectItem(item)}
                  className={`w-full text-left p-4 transition-colors crud-row-hover ${statusRowClass(status)} ${
                    isActive ? "crud-row-selected border-l-2 border-l-primary" : ""
                  }`}
                >
                  <div className="flex items-center gap-2">
                    <StatusDot status={status} />
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <p className="font-medium text-sm truncate">{item[labelField.key]}</p>
                        {getItemBadge?.(item) && (
                          <Badge variant={getItemBadge(item)?.variant || "secondary"} className="shrink-0">
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
              );
            })}
            {items.length === 0 && (
              <div className="p-8 text-center text-muted-foreground text-sm">
                Inga resultat
              </div>
            )}
          </div>
        </ScrollArea>
      </ResizablePanel>

      <ResizableHandle withHandle />

      <ResizablePanel defaultSize="70%" minSize="30%">
        <div className="h-full bg-crud-detail">
          {activeItem ? (
            <ScrollArea className="h-full">
              <div className="p-6">
                <div className="flex items-start justify-between mb-6">
                  <div className="flex items-center gap-2">
                    <StatusDot status={getItemStatus?.(activeItem) ?? null} className="h-3 w-3" />
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
                  <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" onClick={() => onEdit(activeItem)}>
                      <Pencil className="h-4 w-4 mr-1" />
                      Redigera
                    </Button>
                    {canDeleteActiveItem && (
                      <Button
                        variant="outline"
                        size="sm"
                        className="text-destructive hover:text-destructive"
                        onClick={() => onDelete(activeItemId as string | number)}
                      >
                        <Trash2 className="h-4 w-4 mr-1" />
                        Radera
                      </Button>
                    )}
                  </div>
                </div>

                {categories.map(({ category, fields: catFields }) => (
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
                                : renderDetailValue(value, field, optionsMap)}
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
              Välj ett element i listan
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
  return Array.from(map.entries()).map(([category, fields]) => ({ category, fields }));
}

function renderDetailValue(value: any, field: FieldConfig, optionsMap: Map<string, SelectOption[]>) {
  if (value == null) return "—";
  if (field.type === "boolean") return value ? "Ja" : "Nej";
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
    return opts.find((o) => String(o.value) === String(value))?.label ?? String(value);
  }
  if (field.type === "textarea") {
    return <span className="whitespace-pre-line">{String(value)}</span>;
  }
  return String(value);
}
