import * as Checkbox from "@radix-ui/react-checkbox";
import * as Dialog from "@radix-ui/react-dialog";
import { Check } from "lucide-react";
import { ItemForm } from "../forms/ItemForm";
import type { Item, ModuleConfig } from "../helpers/types";
import type { BulkStep, SaveResult } from "./types";
import { btnOutline, btnPrimary, cbCls, dialogBox, overlay } from "../helpers/styles";

type Props = {
  config: ModuleConfig;
  step: BulkStep;
  setStep: (step: BulkStep) => void;
  checkedCount: number;
  checkedItems: Item[];
  canUpdate: (item: Item) => boolean;
  fields: string[];
  setFields: (fields: string[] | ((current: string[]) => string[])) => void;
  bulkConfig: ModuleConfig;
  bulkStart: () => Item;
  saveBulk: (item: Item) => Promise<SaveResult>;
  allValues: (key: string) => string[];
};

export function BulkEditDialog({ config, step, setStep, checkedCount, checkedItems, canUpdate, fields, setFields, bulkConfig, bulkStart, saveBulk, allValues }: Props) {
  const editable = [
    ...config.columns.filter((column) => column.key !== "id" && column.key !== "createdAt" && column.key !== "updatedAt" && column.form !== "none" && column.form !== "create" && column.form !== "readonly-edit").map((column) => ({ key: column.key, label: column.label })),
    ...config.detailSections.filter((section) => section.type !== "table").map((section) => ({ key: section.key, label: section.label })),
  ];
  return (
    <Dialog.Root open={!!step} onOpenChange={(open) => !open && setStep(null)}>
      <Dialog.Portal>
        <div className="ls-table">
          <Dialog.Overlay className={overlay} />
          <Dialog.Content className={dialogBox} onPointerDownOutside={(event) => event.preventDefault()} onInteractOutside={(event) => event.preventDefault()}>
            <Dialog.Title className="mb-1 text-lg font-semibold">Redigera {checkedCount} markerade</Dialog.Title>
            <Dialog.Description className="mb-4 text-sm text-muted-foreground">
              {step === "fields" ? "Välj vilka egenskaper du vill ändra." : "De nya värdena sätts på alla markerade objekt."}
              {checkedItems.some((item) => !canUpdate(item)) && ` ${checkedItems.filter((item) => !canUpdate(item)).length} av dem får inte ändras och hoppas över.`}
            </Dialog.Description>
            {step === "fields" ? (
              <>
                <div className="space-y-2">
                  {editable.map((field) => (
                    <label key={field.key} className="flex cursor-pointer items-center gap-2 text-sm">
                      <Checkbox.Root className={cbCls} checked={fields.includes(field.key)} onCheckedChange={() => setFields((current) => (current.includes(field.key) ? current.filter((key) => key !== field.key) : [...current, field.key]))}>
                        <Checkbox.Indicator><Check className="h-3 w-3" /></Checkbox.Indicator>
                      </Checkbox.Root>
                      {field.label}
                    </label>
                  ))}
                </div>
                <div className="mt-6 flex justify-end gap-2">
                  <button className={btnOutline} onClick={() => setStep(null)}>Avbryt</button>
                  <button className={btnPrimary} disabled={!fields.length} onClick={() => setStep("form")}>Nästa</button>
                </div>
              </>
            ) : step === "form" ? (
              <ItemForm key={fields.join(",")} tagSuggestions={allValues} config={bulkConfig} item={bulkStart()} isNew={false} onCancel={() => setStep("fields")} onSave={saveBulk} />
            ) : null}
          </Dialog.Content>
        </div>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
