import React from "react";
import * as Dialog from "@radix-ui/react-dialog";
import { ItemForm } from "../forms/ItemForm";
import type { Item, ModuleConfig } from "../helpers/types";
import type { SaveResult } from "./types";
import { dialogBox, overlay } from "../helpers/styles";
import { t } from "../../../lib/i18n";

type Props = {
  config: ModuleConfig;
  editing: Item | null;
  setEditing: (item: Item | null) => void;
  isNew: boolean;
  save: (item: Item) => Promise<SaveResult>;
  allValues: (key: string) => string[];
};

export function EditDialog({ config, editing, setEditing, isNew, save, allValues }: Props) {
  return (
    <Dialog.Root open={config.editMode === "popup" && !!editing} onOpenChange={(open) => !open && setEditing(null)}>
      <Dialog.Portal>
        <div className="ls-table">
          <Dialog.Overlay className={overlay} />
          <Dialog.Content
            className={dialogBox}
            onPointerDownOutside={(event) => event.preventDefault()}
            onInteractOutside={(event) => event.preventDefault()}
          >
            <Dialog.Title className="mb-4 text-lg font-semibold">{t(isNew ? "Create new item" : "Edit item")}</Dialog.Title>
            <Dialog.Description className="sr-only">{t("Fill in the fields and save.")}</Dialog.Description>
            {editing && <ItemForm tagSuggestions={allValues} config={config} item={editing} isNew={isNew} onCancel={() => setEditing(null)} onSave={save} />}
          </Dialog.Content>
        </div>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
