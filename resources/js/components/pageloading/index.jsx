import * as Dialog from "@radix-ui/react-dialog";
import {t} from "../../lib/i18n.ts";
import React from "react";

export default function PageLoading() {
   return <Dialog.Root open>
      <Dialog.Portal>
         <Dialog.Overlay className="ls-popup-overlay" />
         <Dialog.Content
            className="ls-popup"
            onEscapeKeyDown={event => event.preventDefault()}
            onInteractOutside={event => event.preventDefault()}
         >
            <div className="d-flex align-items-center gap-3 mb-3">
               <span className="spinner-border text-primary" aria-hidden="true" />
               <Dialog.Title className="ls-section-title">{t('Loading data…')}</Dialog.Title>
            </div>
            <Dialog.Description>{t('Please wait while data is loading.')}</Dialog.Description>
         </Dialog.Content>
      </Dialog.Portal>
   </Dialog.Root>;
}
