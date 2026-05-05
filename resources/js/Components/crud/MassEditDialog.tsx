import { useState, useEffect } from "react";
import { MaterialSymbol } from "@/components/ui/material-symbol";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Select2Field } from "./Select2Field";
import { FieldConfig } from "./types";
import { useTranslations } from "@/hooks/useTranslations";

interface MassEditDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  fields: FieldConfig[];
  count: number;
  onSave: (data: Record<string, any>) => Promise<void>;
}

export function MassEditDialog({ open, onOpenChange, fields, count, onSave }: MassEditDialogProps) {
  const { t } = useTranslations();
  const [enabled, setEnabled] = useState<Set<string>>(new Set());
  const [formData, setFormData] = useState<Record<string, any>>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (open) {
      setEnabled(new Set());
      setFormData({});
    }
  }, [open]);

  const toggleField = (key: string, checked: boolean) => {
    setEnabled((prev) => {
      const next = new Set(prev);
      if (checked) next.add(key);
      else next.delete(key);
      return next;
    });
  };

  const setValue = (key: string, value: any) => {
    setFormData((prev) => ({ ...prev, [key]: value }));
  };

  const handleSave = async () => {
    const data: Record<string, any> = {};
    for (const key of enabled) {
      data[key] = formData[key] ?? null;
    }
    setSaving(true);
    try {
      await onSave(data);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-w-lg max-h-[85vh] flex flex-col overflow-visible"
        onEscapeKeyDown={(event) => event.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle>{t("ui.crud.mass_edit.title", { count })}</DialogTitle>
        </DialogHeader>

        <div className="flex-1 overflow-y-auto min-h-0">
          <p className="text-sm text-muted-foreground mb-3">
            {t("ui.crud.mass_edit.description")}
          </p>

          <div className="grid gap-4 py-2">
            {fields.map((field) => {
              const isEnabled = enabled.has(field.key);
              const val = formData[field.key];

              return (
                <div key={field.key} className="space-y-2">
                  <div className="flex items-center gap-2">
                    <Checkbox
                      id={`mass-toggle-${field.key}`}
                      checked={isEnabled}
                      onCheckedChange={(checked) => toggleField(field.key, !!checked)}
                    />
                    <Label
                      htmlFor={`mass-toggle-${field.key}`}
                      className={isEnabled ? "font-medium" : "text-muted-foreground"}
                    >
                      {field.label}
                    </Label>
                  </div>

                  {isEnabled && (
                    <div className="pl-6">
                      {renderMassField(field, val, setValue, t)}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
            {t("ui.crud.action_cancel")}
          </Button>
          <Button onClick={handleSave} disabled={saving || enabled.size === 0}>
            {saving && <MaterialSymbol name="progress_activity" className="h-4 w-4 mr-1 animate-spin" />}
            {t("ui.crud.mass_edit.update_selected", { count })}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function renderMassField(
  field: FieldConfig,
  val: any,
  setValue: (key: string, value: any) => void,
  t: (key: string, replacements?: Record<string, string | number>) => string,
) {
  switch (field.type) {
    case "textarea":
      return (
        <Textarea
          value={val || ""}
          onChange={(e) => setValue(field.key, e.target.value)}
          placeholder={field.placeholder}
          rows={2}
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
          placeholder={field.placeholder || t("ui.crud.select_placeholder")}
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
          placeholder={field.placeholder || t("ui.crud.select_or_create_placeholder")}
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
