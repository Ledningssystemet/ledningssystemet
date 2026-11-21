import React, { useState } from "react";
import * as Popover from "@radix-ui/react-popover";
import * as Checkbox from "@radix-ui/react-checkbox";
import { Check, ChevronDown, Plus, X } from "lucide-react";
import { t } from "../../../lib/i18n";
import { translate } from "../helpers/translate";

const field = "flex min-h-[34px] w-full flex-wrap items-center gap-1 rounded-md border border-input bg-card px-2 py-1 text-sm focus-within:ring-2 focus-within:ring-ring";
const chip = "inline-flex items-center gap-1 rounded bg-accent px-1.5 py-0.5 text-xs text-accent-foreground";

function Chip({ label, onRemove }: { label: string; onRemove: () => void }) {
  return (
    <span className={chip}>
      {label}
      <button type="button" aria-label={translate("Remove {label}", { label })} className="rounded hover:bg-primary/15" onClick={(e) => { e.stopPropagation(); onRemove(); }}><X className="h-3 w-3" /></button>
    </span>
  );
}

/** Multiselect: pick several of a fixed list of options via checkboxes. */
export function MultiSelect({ label, options, value, onChange }: { label: string; options: string[]; value: string[]; onChange: (v: string[]) => void }) {
  const toggle = (o: string) => onChange(value.includes(o) ? value.filter((v) => v !== o) : [...value, o]);
  return (
    <Popover.Root>
      <Popover.Trigger asChild>
        <button type="button" aria-label={label} className={`${field} justify-between text-left`}>
          <span className="flex flex-wrap gap-1">
            {value.length ? value.map((v) => <Chip key={v} label={v} onRemove={() => toggle(v)} />) : <span className="text-muted-foreground">{t("All")}</span>}
          </span>
          <ChevronDown className="h-4 w-4 shrink-0 opacity-60" />
        </button>
      </Popover.Trigger>
      <Popover.Portal>
        <div className="ls-table">
        <Popover.Content align="start" sideOffset={4} className="z-[60] w-[var(--radix-popover-trigger-width)] rounded-md border bg-popover p-1 shadow-lg">
          {options.map((o) => (
            <label key={o} className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-accent">
              <Checkbox.Root checked={value.includes(o)} onCheckedChange={() => toggle(o)} aria-label={o} className="flex h-4 w-4 items-center justify-center rounded border border-input bg-card data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground">
                <Checkbox.Indicator><Check className="h-3 w-3" /></Checkbox.Indicator>
              </Checkbox.Root>
              {o}
            </label>
          ))}
          {value.length > 0 && <button type="button" className="mt-1 w-full rounded px-2 py-1 text-left text-xs text-muted-foreground hover:bg-accent" onClick={() => onChange([])}>{t("Clear selection")}</button>}
        </Popover.Content>
        </div>
      </Popover.Portal>
    </Popover.Root>
  );
}

/** Multiselect with tagging: type to search suggestions, Enter or comma creates a new tag. */
export function TagSelect({ label, id, suggestions, value, onChange, allowCreate = true }: { label: string; id?: string; suggestions: string[]; value: string[]; onChange: (v: string[]) => void; allowCreate?: boolean }) {
  const [text, setText] = useState("");
  const [open, setOpen] = useState(false);
  const q = text.trim();
  const matches = suggestions.filter((s) => !value.includes(s) && s.toLowerCase().includes(q.toLowerCase())).slice(0, 8);
  const canCreate = allowCreate && q && !suggestions.some((s) => s.toLowerCase() === q.toLowerCase()) && !value.includes(q);
  const add = (t: string) => { if (t && !value.includes(t)) onChange([...value, t]); setText(""); setOpen(false); };
  return (
    <Popover.Root open={open && (matches.length > 0 || !!canCreate)} onOpenChange={setOpen}>
      <Popover.Anchor asChild>
        <div className={field} onClick={() => document.getElementById(id ?? `tag-${label}`)?.focus()}>
          {value.map((v) => <Chip key={v} label={v} onRemove={() => onChange(value.filter((x) => x !== v))} />)}
          <input
            id={id ?? `tag-${label}`} aria-label={label} value={text} placeholder={value.length ? "" : allowCreate ? t("Type to search or create…") : t("Type to search…")}
            className="min-w-[120px] flex-1 bg-transparent py-0.5 outline-none"
            onFocus={() => setOpen(true)}
            onChange={(e) => { setText(e.target.value); setOpen(true); }}
            onKeyDown={(e) => {
              if ((e.key === "Enter" || e.key === ",") && q) { e.preventDefault(); add(matches.find((m) => m.toLowerCase() === q.toLowerCase()) ?? (allowCreate ? q : matches[0] ?? "")); }
              if (e.key === "Backspace" && !text && value.length) onChange(value.slice(0, -1));
            }}
          />
        </div>
      </Popover.Anchor>
      <Popover.Portal>
        <div className="ls-table">
        <Popover.Content align="start" sideOffset={4} onOpenAutoFocus={(e) => e.preventDefault()} onInteractOutside={(e) => { if ((e.target as HTMLElement).id === (id ?? `tag-${label}`)) e.preventDefault(); }}
          className="z-[60] max-h-60 w-[var(--radix-popover-trigger-width)] min-w-[220px] overflow-y-auto rounded-md border bg-popover p-1 shadow-lg">
          {matches.map((m) => (
            <button type="button" key={m} className="block w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent" onMouseDown={(e) => e.preventDefault()} onClick={() => add(m)}>{m}</button>
          ))}
          {canCreate && (
            <button type="button" className="flex w-full items-center gap-1.5 rounded px-2 py-1.5 text-left text-sm text-primary hover:bg-accent" onMouseDown={(e) => e.preventDefault()} onClick={() => add(q)}>
              <Plus className="h-3.5 w-3.5" />{translate('Create tag "{tag}"', { tag: q })}
            </button>
          )}
        </Popover.Content>
        </div>
      </Popover.Portal>
    </Popover.Root>
  );
}
