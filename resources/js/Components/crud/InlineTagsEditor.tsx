import {useEffect, useMemo, useState} from "react";
import { MaterialSymbol } from "@/components/ui/material-symbol";
import {Button} from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {Select2Field} from "./Select2Field";
import {FieldConfig} from "./types";
import {useTranslations} from "@/hooks/useTranslations";

interface InlineTagsEditorProps {
    item: Record<string, any>;
    field: FieldConfig;
    value: any;
    onSave: (item: Record<string, any>, fieldKey: string, value: any) => Promise<void>;
}

export function InlineTagsEditor({item, field, value, onSave}: InlineTagsEditorProps) {
    const {t} = useTranslations();
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<any[]>([]);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const currentValues = useMemo(() => (Array.isArray(value) ? value : value ? [value] : []), [value]);

    useEffect(() => {
        if (open) {
            setDraft(currentValues);
            setError(null);
        }
    }, [open, currentValues]);

    const handleSave = async () => {
        setSaving(true);
        setError(null);
        try {
            await onSave(item, field.key, draft);
            setOpen(false);
        } catch (e: any) {
            setError(e?.message || t("ui.crud.inline_tags_save_error"));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="flex items-center gap-2 min-w-0">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="h-7 w-7 shrink-0"
                onClick={() => setOpen(true)}
                aria-label={t("ui.crud.inline_tags_open_aria", {field: field.label})}
            >
                <MaterialSymbol name="sell" className="h-3.5 w-3.5"/>
            </Button>
            <div className="flex flex-wrap gap-1 min-w-0">
                {currentValues.length === 0 ? (
                    <span className="text-muted-foreground">â€”</span>
                ) : (
                    currentValues.map((tagValue) => {
                        const option = field.options?.find((opt) => String(opt.value) === String(tagValue));
                        return (
                            <span
                                key={`${field.key}-${String(tagValue)}`}
                                className="inline-flex px-2 py-0.5 rounded-full text-xs bg-secondary text-secondary-foreground"
                            >
                {option?.label || String(tagValue)}
              </span>
                        );
                    })
                )}
            </div>


            <Dialog open={open} onOpenChange={setOpen} modal={false}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t("ui.crud.inline_tags_title", {field: field.label})}</DialogTitle>
                        <DialogDescription>{t("ui.crud.inline_tags_description")}</DialogDescription>
                    </DialogHeader>

                    <div className="py-1">
                        <Select2Field
                            options={field.options || []}
                            optionsUrl={field.optionsUrl}
                            createOptionUrl={field.createOptionUrl}
                            optionValueKey={field.optionValueKey}
                            optionLabelKey={field.optionLabelKey}
                            createOptionPayloadKey={field.createOptionPayloadKey}
                            tags={field.tags ?? true}
                            value={draft}
                            onChange={(values) => setDraft(values)}
                            isMulti
                            isCreatable
                            withinDialog
                            placeholder={field.placeholder || t("ui.crud.inline_tags_placeholder")}
                        />
                        {error && <p className="text-sm text-destructive mt-2">{error}</p>}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={saving}>
                            {t("ui.crud.action_cancel")}
                        </Button>
                        <Button type="button" onClick={handleSave} disabled={saving}>
                            {t("ui.crud.action_save")}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

