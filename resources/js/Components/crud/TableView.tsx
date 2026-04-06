import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { FieldConfig, ItemBadgeConfig, ItemStatus } from "./types";
import { Pencil, Trash2 } from "lucide-react";
import { StatusDot, statusRowClass } from "./StatusIndicator";
import { InlineTagsEditor } from "./InlineTagsEditor";

interface TableViewProps {
  items: Record<string, any>[];
  fields: FieldConfig[];
  primaryKey: string;
  selectedItems: Set<string | number>;
  onToggleSelect: (id: string | number) => void;
  onSelectAll: () => void;
  onEdit: (item: Record<string, any>) => void;
  onDelete: (id: string | number) => void;
  onInlineFieldUpdate?: (item: Record<string, any>, fieldKey: string, value: any) => Promise<void>;
  getItemStatus?: (item: Record<string, any>) => ItemStatus | null;
  getItemBadge?: (item: Record<string, any>) => ItemBadgeConfig | null;
  editableKey?: string;
  deletableKey?: string;
}

export function TableView({
  items,
  fields,
  primaryKey,
  selectedItems,
  onToggleSelect,
  onSelectAll,
  onEdit,
  onDelete,
  onInlineFieldUpdate,
  getItemStatus,
  getItemBadge,
  editableKey,
  deletableKey,
}: TableViewProps) {
  const visibleFields = fields.filter((f) => !f.hidden && !f.hiddenInTable);
  const allSelected = items.length > 0 && items.every((i) => selectedItems.has(i[primaryKey]));

  return (
    <div className="border rounded-lg overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50">
              <th className="p-3 w-10">
                <Checkbox
                  checked={allSelected}
                  onCheckedChange={onSelectAll}
                />
              </th>
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
              <th className="p-3 w-20" />
            </tr>
          </thead>
          <tbody>
            {items.map((item) => {
              const id = item[primaryKey];
              const isSelected = selectedItems.has(id);
              const status = getItemStatus?.(item) ?? null;

              return (
                <tr
                  key={id}
                  className={`border-b crud-row-hover ${isSelected ? "crud-row-selected" : ""} ${statusRowClass(status)}`}
                >
                  <td className="p-3">
                    <Checkbox
                      checked={isSelected}
                      onCheckedChange={() => onToggleSelect(id)}
                    />
                  </td>
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
                          <span>{field.renderCell ? field.renderCell(item[field.key], item) : renderValue(item[field.key], field)}</span>
                          {getItemBadge?.(item) && (
                            <Badge variant={getItemBadge(item)?.variant || "secondary"}>
                              {getItemBadge(item)?.label}
                            </Badge>
                          )}
                        </div>
                      ) : field.renderCell ? (
                        field.renderCell(item[field.key], item)
                      ) : renderValue(item[field.key], field)}
                    </td>
                  ))}
                  <td className="p-3">
                    <div className="flex items-center gap-1">
                      {(editableKey ? item[editableKey] !== false : true) && (
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8"
                          onClick={() => onEdit(item)}
                        >
                          <Pencil className="h-3.5 w-3.5" />
                        </Button>
                      )}
                      {(deletableKey ? item[deletableKey] !== false : true) && (
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8 text-destructive hover:text-destructive"
                          onClick={() => onDelete(id)}
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      )}
                    </div>
                  </td>
                </tr>
              );
            })}
            {items.length === 0 && (
              <tr>
                <td
                  colSpan={visibleFields.length + (getItemStatus ? 3 : 2)}
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

function renderValue(value: any, field: FieldConfig) {
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
    return (
      <div className="flex flex-wrap gap-1">
        {value.map((v: any) => {
          const opt = field.options?.find((o) => o.value === v);
          return (
            <Badge key={v} variant="secondary" className="text-xs">
              {opt?.label || v}
            </Badge>
          );
        })}
      </div>
    );
  }

  if (field.type === "select" && field.options) {
    const opt = field.options.find((o) => String(o.value) === String(value));
    return opt?.label || value;
  }

  return String(value);
}
