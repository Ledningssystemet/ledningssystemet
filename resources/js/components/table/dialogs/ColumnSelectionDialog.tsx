import React from "react";
import * as Checkbox from "@radix-ui/react-checkbox";
import * as Dialog from "@radix-ui/react-dialog";
import { Check } from "lucide-react";
import type { ModuleConfig } from "../helpers/types";
import { btnOutline, btnPrimary, cbCls, dialogBox, overlay } from "../helpers/styles";
import { t } from "../../../lib/i18n";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  config: ModuleConfig;
  selected: string[];
  onChange: (columns: string[] | null) => void;
};

export function ColumnSelectionDialog({ open, onOpenChange, config, selected, onChange }: Props) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <div className="ls-table">
          <Dialog.Overlay className={overlay} />
          <Dialog.Content className={dialogBox}>
            <Dialog.Title className="mb-1 text-lg font-semibold">{t("Columns")}</Dialog.Title>
            <Dialog.Description className="mb-4 text-sm text-muted-foreground">{t("Select columns to display in the table.")}</Dialog.Description>
            <div className="space-y-2">
              {config.tableColumns.map((key) => (
                <label key={key} className="flex cursor-pointer items-center gap-2 text-sm">
                  <Checkbox.Root className={cbCls} checked={selected.includes(key)} onCheckedChange={() => onChange(selected.includes(key) ? selected.filter((value) => value !== key) : [...selected, key])}>
                    <Checkbox.Indicator><Check className="h-3 w-3" /></Checkbox.Indicator>
                  </Checkbox.Root>
                  {config.columns.find((column) => column.key === key)?.label ?? key}
                </label>
              ))}
            </div>
            <div className="mt-6 flex justify-between">
              <div className="flex flex-wrap gap-2">
                <button className={btnOutline} onClick={() => onChange([...config.tableColumns])}>{t("Show all")}</button>
                <button className={btnOutline} onClick={() => onChange(null)}>{t("Reset columns")}</button>
              </div>
              <Dialog.Close className={btnPrimary}>{t("Done")}</Dialog.Close>
            </div>
          </Dialog.Content>
        </div>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
