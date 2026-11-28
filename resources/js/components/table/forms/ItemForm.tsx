import React, { useEffect, useState, type FormEvent, type ReactNode } from "react";
import * as Tabs from "@radix-ui/react-tabs";
import * as Label from "@radix-ui/react-label";
import { formatDate, validateField } from "../helpers/logic";
import { MultiSelect, TagSelect } from "../components/MultiSelect";
import { NestedTableEditor } from "../components/NestedTable";
import { RSelect } from "../components/RSelect";
import { colOptions } from "../helpers/tableConfig";
import type { ColumnConfig, DetailSection, Item, ModuleConfig } from "../helpers/types";
import { btnOutline, btnPrimary, inputCls } from "../helpers/styles";
import { t } from "../../../lib/i18n";
import { translate } from "../helpers/translate";

type FormEntry = { kind: "col"; col: ColumnConfig; readOnly: boolean } | { kind: "section"; section: DetailSection };
type SaveResult = { fields?: Record<string, string>; message?: string } | null;
type Props = {
  tagSuggestions: (key: string) => string[];
  config: ModuleConfig;
  item: Item;
  isNew: boolean;
  onSave: (item: Item) => Promise<SaveResult>;
  onCancel: () => void;
};

export function ItemForm({ config, item, isNew, onSave, onCancel, tagSuggestions }: Props) {
  const [saving, setSaving] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [draft, setDraft] = useState<Item>(item);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const tabs = config.formTabs?.length ? config.formTabs : null;
  const [tab, setTab] = useState(tabs?.[0] ?? "");

  const inForm = (column: ColumnConfig) => {
    if (column.key === "createdAt" || column.key === "updatedAt") return false;
    const form = column.form ?? "both";
    if (form === "none") return false;
    if (form === "create") return isNew;
    if (form === "edit" || form === "readonly-edit") return !isNew;
    return true;
  };
  const entries: FormEntry[] = [
    ...config.columns.filter(inForm).map((column) => ({ kind: "col" as const, col: column, readOnly: column.form === "readonly-edit" })),
    ...config.detailSections.filter((section) => !section.childConfig).map((section) => ({ kind: "section" as const, section })),
  ];
  const tabOf = (entry: FormEntry) => {
    const entryTab = entry.kind === "col" ? entry.col.tab : entry.section.tab;
    return tabs && entryTab && tabs.includes(entryTab) ? entryTab : tabs?.[0] ?? "";
  };
  const formState = config.formState?.(draft, { isNew }) ?? {};
  useEffect(() => {
    if (!formState.values) return;
    const diff = Object.entries(formState.values).filter(([key, value]) => draft[key] !== value);
    if (diff.length) setDraft((current) => ({ ...current, ...Object.fromEntries(diff) }));
  }); // eslint-disable-line react-hooks/exhaustive-deps
  const isHidden = (key: string) => formState.hidden?.includes(key);
  const isDisabled = (key: string) => formState.disabled?.includes(key);
  const rule = (column: ColumnConfig) => ({ ...column, required: column.required || column.key === config.titleKey || !!formState.required?.includes(column.key) });

  const set = (key: string, value: any) => {
    setErrors((current) => { if (!current[key]) return current; const next = { ...current }; delete next[key]; return next; });
    setDraft((current) => {
      let next = { ...current, [key]: value };
      // FK-26: beroende val — rensa värden som inte längre finns bland alternativen, i flera led.
      for (let pass = 0; pass < 5; pass++) {
        let changed = false;
        for (const column of config.columns) {
          if (!column.dependsOn || !column.optionsFor || next[column.key] == null || next[column.key] === "") continue;
          const allowed = colOptions(column, next).map((option) => option.value);
          const currentValue = next[column.key];
          if (Array.isArray(currentValue) ? currentValue.some((entry) => !allowed.includes(entry)) : !allowed.includes(String(currentValue))) {
            next = { ...next, [column.key]: Array.isArray(currentValue) ? currentValue.filter((entry) => allowed.includes(entry)) : "" };
            changed = true;
          }
        }
        if (!changed) break;
      }
      return next;
    });
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const nextErrors: Record<string, string> = {};
    let firstInvalid: FormEntry | null = null;
    for (const entry of entries) {
      if (entry.kind !== "col" || entry.readOnly || isHidden(entry.col.key) || isDisabled(entry.col.key)) continue;
      const message = validateField(rule(entry.col), draft[entry.col.key]);
      if (message) { nextErrors[entry.col.key] = message; firstInvalid ??= entry; }
    }
    setErrors(nextErrors);
    if (firstInvalid && firstInvalid.kind === "col") {
      const key = firstInvalid.col.key;
      if (tabs) setTab(tabOf(firstInvalid));
      setTimeout(() => document.getElementById(`f-${key}`)?.focus(), 30);
      return;
    }
    if (saving) return;
    setSaving(true);
    setServerError(null);
    onSave(draft).then((result) => {
      setSaving(false);
      if (!result) return;
      // FK-23/40: visa serverns fel, behåll inmatade värden.
      if (result.fields && Object.keys(result.fields).length) {
        setErrors(result.fields);
        const invalid = entries.find((entry) => entry.kind === "col" && result.fields![entry.col.key]);
        if (invalid && tabs) setTab(tabOf(invalid));
      }
      setServerError(result.message ?? t("Could not save."));
    });
  };

  const field = (entry: FormEntry) => {
    if (entry.kind === "section") {
      const section = entry.section;
      return (
        <div key={section.key}>
          <Label.Root htmlFor={`f-${section.key}`} className="mb-1 block text-sm">{section.label}{section.type === "tags" && ` ${t("(search or create with Enter)")}`}{section.type === "properties" && ` ${t("(one per line: key: value)")}`}</Label.Root>
          {section.type === "textarea" && <textarea id={`f-${section.key}`} rows={3} className={inputCls} value={draft[section.key] ?? ""} onChange={(event) => set(section.key, event.target.value)} />}
          {section.type === "table" && <NestedTableEditor section={section} rows={draft[section.key] ?? []} onChange={(value) => set(section.key, value)} />}
          {section.type === "tags" && <TagSelect label={section.label} id={`f-${section.key}`} suggestions={tagSuggestions(section.key)} value={draft[section.key] ?? []} onChange={(value) => set(section.key, value)} />}
          {section.type === "properties" && (
            <textarea id={`f-${section.key}`} rows={4} className={`${inputCls} font-mono`} defaultValue={Object.entries(draft[section.key] ?? {}).map(([key, value]) => `${key}: ${value}`).join("\n")}
              onChange={(event) => set(section.key, Object.fromEntries(event.target.value.split("\n").map((line) => line.split(/:([\s\S]*)/).map((part) => part.trim())).filter(([key]) => key).map(([key, value]) => [key, value ?? ""])))} />
          )}
        </div>
      );
    }
    const column = entry.col;
    if (isHidden(column.key)) return null;
    const id = `f-${column.key}`;
    const error = errors[column.key];
    const errorId = `${id}-err`;
    const common = { id, "aria-invalid": error ? true : undefined, "aria-describedby": error ? errorId : undefined, className: `${inputCls} ${error ? "border-destructive" : ""}` };
    const value = draft[column.key];
    const required = rule(column).required;
    let control: ReactNode;
    if (entry.readOnly || isDisabled(column.key)) {
      control = <p className="rounded-md bg-muted px-3 py-1.5 text-sm">{String(value ?? "–")}</p>;
    } else if (column.type === "select" && column.multiple) {
      const selected = Array.isArray(value)
        ? value.map((option) => String(option && typeof option === "object" ? option.id : option)).filter(Boolean)
        : [];
      control = <MultiSelect id={id} label={column.label} options={colOptions(column, draft)} value={selected} emptyLabel={t("Select…")} onChange={(next) => set(column.key, next)} />;
    } else if (column.type === "select") {
      const options = colOptions(column, draft);
      const chosen = options.find((option) => option.value === String(value ?? ""));
      control = (
        <>
          <RSelect id={id} label={column.label} invalid={!!error} value={value ? String(value) : ""} onChange={(next) => set(column.key, next)} placeholder={column.dependsOn && !options.length ? translate("Select {field} first", { field: config.columns.find((candidate) => candidate.key === column.dependsOn)?.label ?? "" }) : t("Select…")} options={options} />
          {chosen?.description && <p className="mt-1 text-xs text-muted-foreground">{chosen.description}</p>}
        </>
      );
    } else if (column.type === "textarea") {
      control = <textarea {...common} rows={3} value={value ?? ""} onChange={(event) => set(column.key, event.target.value)} />;
    } else if (column.type === "number") {
      control = <input {...common} type="number" min={column.min} max={column.max} step={column.step} value={value ?? ""} onChange={(event) => set(column.key, event.target.value === "" ? "" : Number(event.target.value))} />;
    } else if (column.type === "date") {
      control = <input {...common} type="date" value={formatDate(value)} onChange={(event) => set(column.key, event.target.value)} />;
    } else if (column.type === "datetime") {
      control = <input {...common} type="datetime-local" value={value ? formatDate(value, true).replace(" ", "T") : ""} onChange={(event) => set(column.key, event.target.value)} />;
    } else if (column.type === "file") {
      control = (
        <>
          <input {...common} type="file" accept={column.accept} onChange={(event) => { const file = event.target.files?.[0]; set(column.key, file ? { name: file.name, size: file.size, type: file.type } : null); }} />
          {value?.name && <p className="mt-1 text-xs text-muted-foreground">{translate("Selected file: {name}", { name: String(value.name) })}</p>}
        </>
      );
    } else {
      control = <input {...common} value={value ?? ""} onChange={(event) => set(column.key, event.target.value)} />;
    }
    return (
      <div key={column.key}>
        <Label.Root htmlFor={id} className="mb-1 block text-sm">{column.label}{required && !entry.readOnly && <span className="text-destructive"> *</span>}</Label.Root>
        {control}
        {column.help && <p className="mt-1 text-xs text-muted-foreground">{column.help}</p>}
        {error && <p id={errorId} role="alert" className="mt-1 text-xs text-destructive">{error}</p>}
      </div>
    );
  };

  const tabHasError = (formTab: string) => entries.some((entry) => entry.kind === "col" && errors[entry.col.key] && tabOf(entry) === formTab);
  return (
    <form noValidate className="space-y-3" onSubmit={submit}>
      {tabs ? (
        <Tabs.Root value={tab} onValueChange={setTab}>
          <Tabs.List aria-label={t("Form tabs")} className="mb-3 flex border-b">
            {tabs.map((formTab) => (
              <Tabs.Trigger key={formTab} value={formTab} className={`-mb-px inline-flex items-center gap-1.5 border-b-2 border-transparent px-3 py-1.5 text-sm data-[state=active]:border-primary data-[state=active]:font-medium ${tabHasError(formTab) ? "text-destructive" : ""}`}>
                {formTab}{tabHasError(formTab) && <span aria-label={t("contains errors")} className="h-2 w-2 rounded-full bg-destructive" />}
              </Tabs.Trigger>
            ))}
          </Tabs.List>
          {tabs.map((formTab) => (
            <Tabs.Content key={formTab} value={formTab} className="space-y-3">{entries.filter((entry) => tabOf(entry) === formTab).map(field)}</Tabs.Content>
          ))}
        </Tabs.Root>
      ) : entries.map(field)}
      {serverError && <p role="alert" className="text-sm text-destructive">{serverError}</p>}
      {Object.keys(errors).length > 0 && !serverError && <p className="text-sm text-destructive">{t("Correct the highlighted fields before saving.")}</p>}
      <div className="flex justify-end gap-2 pt-2">
        <button type="button" className={btnOutline} onClick={onCancel}>{t("Cancel")}</button>
        <button type="submit" disabled={saving} className={btnPrimary}>{t(saving ? "Saving…" : "Save")}</button>
      </div>
    </form>
  );
}
