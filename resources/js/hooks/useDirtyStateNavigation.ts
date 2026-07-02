import { useEffect } from 'react';

/**
 * Hook that prevents navigation when there are unsaved changes.
 * Works with React Router navigation through Link, useNavigate, and manual pushState.
 * Also handles browser back/forward buttons and page close.
 *
 * @param isDirty - Whether there are unsaved changes
 * @param confirmMessage - Message to show in confirmation dialog
 */
export function useDirtyStateNavigation(isDirty: boolean, confirmMessage: string): void {
    useEffect(() => {
        if (!isDirty) {
            return;
        }

        // Handle beforeunload (browser close, refresh, etc.)
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };

        // Handle popstate (back/forward buttons)
        const handlePopState = (event: PopStateEvent) => {
            if (!window.confirm(confirmMessage)) {
                event.preventDefault();
                // Push the current state back to prevent the navigation
                window.history.pushState(null, '', window.location.href);
            }
        };

        // Handle Link clicks and other navigation attempts
        const handleClickCapture = (event: Event) => {
            const target = event.target as HTMLElement;

            // Check if this is a link that would navigate
            const link = target.closest('a');
            if (!link) {
                return;
            }

            const href = link.getAttribute('href');

            // Skip if not a valid navigation link
            if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) {
                return;
            }

            // Skip if link is disabled or has download attribute
            if (link.hasAttribute('disabled') || link.hasAttribute('download')) {
                return;
            }

            // Skip if ctrl/cmd/shift/meta is pressed (new tab/window)
            const clickEvent = event as MouseEvent;
            if (clickEvent.ctrlKey || clickEvent.metaKey || clickEvent.shiftKey || clickEvent.altKey) {
                return;
            }

            // Skip internal hash links or same-page links
            const currentUrl = window.location.href.split('#')[0];
            const targetUrl = new URL(href, window.location.href).href.split('#')[0];

            if (currentUrl === targetUrl) {
                return;
            }

            // Show confirmation for navigation away from this page
            if (!window.confirm(confirmMessage)) {
                event.preventDefault();
                event.stopPropagation();
            }
        };

        window.addEventListener('beforeunload', handleBeforeUnload);
        window.addEventListener('popstate', handlePopState);
        document.addEventListener('click', handleClickCapture, true);

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            window.removeEventListener('popstate', handlePopState);
            document.removeEventListener('click', handleClickCapture, true);
        };
    }, [isDirty, confirmMessage]);
}

