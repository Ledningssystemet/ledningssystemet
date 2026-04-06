import { useState, useEffect, useMemo } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Select2Field } from "./Select2Field";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { EditDialogProps, FieldConfig } from "./types";
import { Loader2, AlertCircle } from "lucide-react";

export function EditDialog({
  open,
  onOpenChange,
  item,
  fields,
  title,
  onSave,
}: EditDialogProps) {
  const [formData, setFormData] = useState<Record<string, any>>({});
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const isNew = !item?.id;

  useEffect(() => {
    if (open) {
      setFormData(item ? { ...item } : {});
      setError(null);
    }
  }, [open, item]);

  const editableFields = fields.filter(
    (f) => f.editable !== false && !f.hidden
  );

  const categories = useMemo(() => {
    const map = new Map<string, FieldConfig[]>();
    for (const f of editableFields) {
      const cat = f.category || "__uncategorized__";
      if (!map.has(cat)) map.set(cat, []);
      map.get(cat)!.push(f);
    }
    return Array.from(map.entries()).map(([category, fields]) => ({ category, fields }));
  }, [editableFields]);

  const hasTabs = categories.length > 1 || (categories.length === 1 && categories[0].category !== "__uncategorized__");

  const invalidCategories = useMemo(() => {
    const invalid = new Set<string>();
    for (const { category, fields: catFields } of categories) {
      for (const f of catFields) {
        if (f.required) {
          const val = formData[f.key];
          if (val === undefined || val === null || val === "" || (Array.isArray(val) && val.length === 0)) {
            invalid.add(category);
            break;
          }
        }
      }
    }
    return invalid;
  }, [categories, formData]);

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    try {
      await onSave(formData);
      onOpenChange(false);
    } catch (e: any) {
      setError(e.message || "Ett fel uppstod");
    } finally {
      setSaving(false);
    }
  };

  const setValue = (key: string, value: any) => {
    setFormData((prev) => ({ ...prev, [key]: value }));
  };

  const renderField = (field: FieldConfig) => {
    const val = formData[field.key];
    const isInvalid = field.required && (val === undefined || val === null || val === "" || (Array.isArray(val) && val.length === 0));

    return (
      <div key={field.key} className="grid gap-2">
        <Label htmlFor={field.key} className="flex items-center gap-1">
          {field.label}
          {field.required && <span className="text-destructive">*</span>}
          {isInvalid && <AlertCircle className="h-3.5 w-3.5 text-destructive" />}
        </Label>
        {renderFieldInput(field, val, setValue)}
      </div>
    );
  };

  const renderFieldsForCategory = (catFields: FieldConfig[]) => (
    <div className="grid gap-4">
      {catFields.map(renderField)}
    </div>
  );

  return (
    <Dialog open={open} onOpenChange={onOpenChange} modal={false}>
      <DialogContent
        className="max-w-lg max-h-[85vh] flex flex-col overflow-visible"
        onInteractOutside={(event) => event.preventDefault()}
        onEscapeKeyDown={(event) => event.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle>
            {title || (isNew ? "Skapa ny" : "Redigera")}
          </DialogTitle>
        </DialogHeader>

        <div className="flex-1 overflow-y-auto min-h-0">
          {hasTabs ? (
            <Tabs defaultValue={categories[0].category} className="py-2">
              <TabsList className="w-full flex-wrap h-auto gap-1">
                {categories.map(({ category }) => (
                  <TabsTrigger
                    key={category}
                    value={category}
                    className="relative flex items-center gap-1.5"
                  >
                    {category === "__uncategorized__" ? "Övrigt" : category}
                    {invalidCategories.has(category) && (
                      <span className="h-2 w-2 rounded-full bg-destructive" />
                    )}
                  </TabsTrigger>
                ))}
              </TabsList>
              {categories.map(({ category, fields: catFields }) => (
                <TabsContent key={category} value={category} className="mt-4">
                  {renderFieldsForCategory(catFields)}
                </TabsContent>
              ))}
            </Tabs>
          ) : (
            <div className="py-4">
              {renderFieldsForCategory(editableFields)}
            </div>
          )}

          {error && (
            <p className="text-sm text-destructive">{error}</p>
          )}
        </div>

        <DialogFooter className="flex items-center gap-2">
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
            Avbryt
          </Button>
          <Button onClick={handleSave} disabled={saving}>
            {saving && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
            {isNew ? "Skapa" : "Spara"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function renderFieldInput(
  field: FieldConfig,
  val: any,
  setValue: (key: string, value: any) => void
) {
  switch (field.type) {
    case "textarea":
      return (
        <Textarea
          value={val || ""}
          onChange={(e) => setValue(field.key, e.target.value)}
          placeholder={field.placeholder}
          rows={3}
        />
      );
    case "number":
      return (
        <Input
          type="number"
          value={val ?? ""}
          onChange={(e) => setValue(field.key, e.target.value ? Number(e.target.value) : "")}
          placeholder={field.placeholder}
        />
      );
    case "date":
      return (
        <Input
          type="date"
          value={val || ""}
          onChange={(e) => setValue(field.key, e.target.value)}
        />
      );
    case "boolean":
      return (
        <Switch
          checked={!!val}
          onCheckedChange={(checked) => setValue(field.key, checked)}
        />
      );
    case "select":
      return (
        <Select2Field
          options={field.options || []}
          optionsUrl={field.optionsUrl}
          createOptionUrl={field.createOptionUrl}
          optionValueKey={field.optionValueKey}
          optionLabelKey={field.optionLabelKey}
          createOptionPayloadKey={field.createOptionPayloadKey}
          tags={field.tags}
          value={val}
          onChange={(v) => setValue(field.key, v)}
          withinDialog
          placeholder={field.placeholder || "Välj..."}
        />
      );
    case "multiselect":
      return (
        <Select2Field
          options={field.options || []}
          optionsUrl={field.optionsUrl}
          createOptionUrl={field.createOptionUrl}
          optionValueKey={field.optionValueKey}
          optionLabelKey={field.optionLabelKey}
          createOptionPayloadKey={field.createOptionPayloadKey}
          tags={field.tags}
          value={Array.isArray(val) ? val : val ? [val] : []}
          onChange={(values) => setValue(field.key, values)}
          withinDialog
          placeholder={field.placeholder}
          isMulti
        />
      );
    case "tags":
    case "inline-tags":
      return (
        <Select2Field
          options={field.options || []}
          optionsUrl={field.optionsUrl}
          createOptionUrl={field.createOptionUrl}
          optionValueKey={field.optionValueKey}
          optionLabelKey={field.optionLabelKey}
          createOptionPayloadKey={field.createOptionPayloadKey}
          tags={field.tags ?? true}
          value={Array.isArray(val) ? val : val ? [val] : []}
          onChange={(values) => setValue(field.key, values)}
          withinDialog
          placeholder={field.placeholder || "Välj eller skapa..."}
          isMulti
          isCreatable
        />
      );
    default:
      return (
        <Input
          type={field.type === "email" ? "email" : field.type === "url" ? "url" : "text"}
          value={val || ""}
          onChange={(e) => setValue(field.key, e.target.value)}
          placeholder={field.placeholder}
        />
      );
  }
}
