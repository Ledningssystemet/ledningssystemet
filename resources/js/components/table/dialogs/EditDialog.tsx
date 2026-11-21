import * as Dialog from "@radix-ui/react-dialog";
import { ItemForm } from "../forms/ItemForm";
import type { Item, ModuleConfig } from "../types";
import type { SaveResult } from "./types";
import { dialogBox, overlay } from "../helpers/styles";

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
            <Dialog.Title className="mb-4 text-lg font-semibold">{isNew ? "Skapa nytt objekt" : "Redigera objekt"}</Dialog.Title>
            <Dialog.Description className="sr-only">Fyll i fälten och spara.</Dialog.Description>
            {editing && <ItemForm tagSuggestions={allValues} config={config} item={editing} isNew={isNew} onCancel={() => setEditing(null)} onSave={save} />}
          </Dialog.Content>
        </div>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
