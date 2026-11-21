import React from "react";
import * as AlertDialog from "@radix-ui/react-alert-dialog";
import type { Item } from "../helpers/types";
import { btnDanger, btnOutline, dialogBox, overlay } from "../helpers/styles";
import { t } from "../../../lib/i18n";
import { translate } from "../helpers/translate";

type Props = {
  open: boolean;
  setOpen: (open: boolean) => void;
  busy: boolean;
  checkedCount: number;
  checkedItems: Item[];
  canDelete: (item: Item) => boolean;
  runBulkDelete: () => void;
  titleKey: string;
};

export function BulkDeleteDialog({ open, setOpen, busy, checkedCount, checkedItems, canDelete, runBulkDelete, titleKey }: Props) {
  return (
    <AlertDialog.Root open={open} onOpenChange={(nextOpen) => !nextOpen && !busy && setOpen(false)}>
      <AlertDialog.Portal>
        <div className="ls-table">
          <AlertDialog.Overlay className={overlay} />
          <AlertDialog.Content className={dialogBox}>
            <AlertDialog.Title className="text-lg font-semibold">{translate("Delete {count} items?", { count: checkedCount })}</AlertDialog.Title>
            <AlertDialog.Description className="mt-2 text-sm text-muted-foreground">
              {t("The selected items will be permanently deleted.")}
              {checkedItems.some((item) => !canDelete(item)) && ` ${translate("{count} cannot be deleted and will be skipped.", { count: checkedItems.filter((item) => !canDelete(item)).length })}`}
            </AlertDialog.Description>
            <ul className="mt-3 max-h-40 list-disc overflow-y-auto pl-5 text-sm">{checkedItems.map((item) => <li key={item.id} className={canDelete(item) ? "" : "text-muted-foreground line-through"}>{item[titleKey]}</li>)}</ul>
            <div className="mt-6 flex justify-end gap-2">
              <AlertDialog.Cancel className={btnOutline} disabled={busy}>{t("Cancel")}</AlertDialog.Cancel>
              <AlertDialog.Action className={btnDanger} disabled={busy || !checkedItems.some(canDelete)} onClick={(event) => { event.preventDefault(); runBulkDelete(); }}>{t(busy ? "Deleting…" : "Delete")}</AlertDialog.Action>
            </div>
          </AlertDialog.Content>
        </div>
      </AlertDialog.Portal>
    </AlertDialog.Root>
  );
}
