import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { FieldConfig, ItemBadgeConfig, ItemStatus, SelectOption } from "./types";
import { Pencil } from "lucide-react";
import { StatusDot, statusRowClass } from "./StatusIndicator";
import { InlineTagsEditor } from "./InlineTagsEditor";
import { useAllSelectOptions, resolveOptions } from "./optionsCache";

interface AccordionViewProps {
  items: Record<string, any>[];
  fields: FieldConfig[];
  primaryKey: string;
  onEdit: (item: Record<string, any>) => void;
  onInlineFieldUpdate?: (item: Record<string, any>, fieldKey: string, value: any) => Promise<void>;
  getItemStatus?: (item: Record<string, any>) => ItemStatus | null;
  getItemBadge?: (item: Record<string, any>) => ItemBadgeConfig | null;
}

export function AccordionView({
  items,
  fields,
  primaryKey,
  onEdit,
  onInlineFieldUpdate,
  getItemStatus,
  getItemBadge,
}: AccordionViewProps) {
  const labelField = fields.find((f) => f.masterLabel) || fields[0];
  const descField = fields.find((f) => f.masterDescription);
  const detailFields = fields.filter((f) => !f.hidden);
  const optionsMap = useAllSelectOptions(fields);

  // Group by category
  const categories = groupByCategory(detailFields);

  return (
    <div className="border rounded-lg overflow-hidden">
      {items.length === 0 ? (
        <div className="p-8 text-center text-muted-foreground">
          Inga resultat hittades
        </div>
      ) : (
        <Accordion type="single" collapsible className="divide-y">
          {items.map((item) => {
            const status = getItemStatus?.(item) ?? null;
            return (
              <AccordionItem
                key={item[primaryKey]}
                value={String(item[primaryKey])}
                className={`border-none crud-row-hover ${statusRowClass(status)}`}
              >
                <AccordionTrigger className="px-4 py-3 hover:no-underline">
                  <div className="flex items-center gap-3 text-left">
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
                                    : renderAccVal(item[field.key], field, optionsMap)}
                              </span>
                            </div>
                          ))}
                        </div>
                      </div>
                    ))}
                    <div className="pt-2 flex justify-end">
                      <Button variant="outline" size="sm" onClick={() => onEdit(item)}>
                        <Pencil className="h-4 w-4 mr-1" />
                        Redigera
                      </Button>
                    </div>
                  </div>
                </AccordionContent>
              </AccordionItem>
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

function renderAccVal(value: any, field: FieldConfig, optionsMap: Map<string, SelectOption[]>) {
  if (value == null) return "—";
  if (field.type === "boolean") return value ? "Ja" : "Nej";
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
