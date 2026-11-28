import * as Dialog from "@radix-ui/react-dialog";
import {t} from "../../lib/i18n.ts";
import {Compass} from "lucide-react";
import React from "react";
import "./loading.css";

export default function PageLoading() {
   return <Dialog.Root open>
      <Dialog.Portal>
         <Dialog.Overlay className="ls-popup-overlay" />
         <Dialog.Content
            className="ls-popup ls-page-loading"
            aria-busy="true"
            onEscapeKeyDown={event => event.preventDefault()}
            onInteractOutside={event => event.preventDefault()}
         >
            <div className="ls-page-loading__indicator" aria-hidden="true">
               <span className="ls-page-loading__ring" />
               <span className="ls-page-loading__compass">
                  <Compass size={38} strokeWidth={1.8} />
               </span>
            </div>
            <Dialog.Title className="ls-page-loading__title">{t('Loading page…')}</Dialog.Title>
            <Dialog.Description className="ls-page-loading__description">
               {t('Please wait while page is loading.')}
            </Dialog.Description>
         </Dialog.Content>
      </Dialog.Portal>
   </Dialog.Root>;
}
