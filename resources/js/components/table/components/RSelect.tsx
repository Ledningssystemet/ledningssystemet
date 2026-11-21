import React from "react";
import * as Select from "@radix-ui/react-select";
import { Check, ChevronDown } from "lucide-react";
import type { OptionDef } from "../helpers/types";
import { inputCls } from "../helpers/styles";

interface Props {
  value: string;
  onChange: (value: string) => void;
  options: OptionDef[];
  placeholder: string;
  label: string;
  id?: string;
  invalid?: boolean;
}

export function RSelect({ value, onChange, options, placeholder, label, id, invalid }: Props) {
  const groups: [string, OptionDef[]][] = [];
  for (const option of options) {
    const group = option.group ?? "";
    const found = groups.find(([key]) => key === group);
    found ? found[1].push(option) : groups.push([group, [option]]);
  }

  return (
    <Select.Root value={value} onValueChange={onChange}>
      <Select.Trigger id={id} aria-label={label} aria-invalid={invalid || undefined} className={`${inputCls} flex items-center justify-between ${invalid ? "border-destructive" : ""}`}>
        <Select.Value placeholder={placeholder} />
        <Select.Icon><ChevronDown className="h-4 w-4 opacity-60" /></Select.Icon>
      </Select.Trigger>
      <Select.Portal>
        <div className="ls-table">
          <Select.Content position="popper" sideOffset={4} className="z-[60] max-h-80 min-w-[var(--radix-select-trigger-width)] overflow-y-auto rounded-md border bg-popover p-1 shadow-lg">
            <Select.Viewport>
              {groups.map(([group, groupOptions]) => (
                <Select.Group key={group || "_"}>
                  {group && <Select.Label className="px-2 pb-0.5 pt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{group}</Select.Label>}
                  {groupOptions.map((option) => (
                    <Select.Item key={option.value} value={option.value} disabled={option.disabled} className="relative flex cursor-pointer select-none flex-col items-start rounded px-7 py-1.5 text-sm outline-none data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground">
                      <Select.ItemIndicator className="absolute left-2 top-2"><Check className="h-3.5 w-3.5" /></Select.ItemIndicator>
                      <Select.ItemText>{option.label}</Select.ItemText>
                      {option.description && <span className="text-xs text-muted-foreground">{option.description}</span>}
                    </Select.Item>
                  ))}
                </Select.Group>
              ))}
            </Select.Viewport>
          </Select.Content>
        </div>
      </Select.Portal>
    </Select.Root>
  );
}
