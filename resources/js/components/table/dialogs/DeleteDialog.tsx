import React from "react";
import * as AlertDialog from "@radix-ui/react-alert-dialog";
import type { Item, ModuleConfig } from "../helpers/types";
import { btnDanger, btnOutline, dialogBox, overlay } from "../helpers/styles";
import { t } from "../../../lib/i18n";
import { translate } from "../helpers/translate";

type Props = {
  config: ModuleConfig;
  deleting: Item | null;
  setDeleting: (item: Item | null) => void;
  deleteError: string | null;
  setDeleteError: (message: string | null) => void;
  remove: (item: Item) => void;
};

export function DeleteDialog({ config, deleting, setDeleting, deleteError, setDeleteError, remove }: Props) {
  return (
    <AlertDialog.Root open={!!deleting} onOpenChange={(open) => { if (!open) { setDeleting(null); setDeleteError(null); } }}>
      <AlertDialog.Portal>
        <div className="ls-table">
          <AlertDialog.Overlay className={overlay} />
          <AlertDialog.Content className={dialogBox}>
            <AlertDialog.Title className="text-lg font-semibold">{t("Delete item?")}</AlertDialog.Title>
            <AlertDialog.Description className="mt-2 text-sm text-muted-foreground">{translate('"{item}" will be permanently deleted.', { item: String(deleting?.[config.titleKey] ?? "") })}</AlertDialog.Description>
            {deleteError && <p role="alert" className="mt-3 text-sm text-destructive">{deleteError}</p>}
            <div className="mt-6 flex justify-end gap-2">
              <AlertDialog.Cancel className={btnOutline}>{t("Cancel")}</AlertDialog.Cancel>
              <AlertDialog.Action className={btnDanger} onClick={(event) => { if (config.dataSource) event.preventDefault(); if (deleting) remove(deleting); }}>{t("Delete")}</AlertDialog.Action>
            </div>
          </AlertDialog.Content>
        </div>
      </AlertDialog.Portal>
    </AlertDialog.Root>
  );
}
