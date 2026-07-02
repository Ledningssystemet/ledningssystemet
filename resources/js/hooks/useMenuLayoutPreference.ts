import { useCallback, useEffect, useState } from "react";

export type MenuLayoutPreference = "mega-menu" | "side-menu";

const MENU_LAYOUT_COOKIE = "menu_layout_preference";
const MENU_LAYOUT_EVENT = "menu-layout-preference-changed";
const DEFAULT_MENU_LAYOUT: MenuLayoutPreference = "mega-menu";

function isMenuLayoutPreference(value: string | undefined): value is MenuLayoutPreference {
    return value === "mega-menu" || value === "side-menu";
}

function readMenuLayoutCookie(): MenuLayoutPreference {
    if (typeof document === "undefined") {
        return DEFAULT_MENU_LAYOUT;
    }

    const cookie = document.cookie
        .split(";")
        .map((part) => part.trim())
        .find((part) => part.startsWith(`${MENU_LAYOUT_COOKIE}=`));

    if (!cookie) {
        return DEFAULT_MENU_LAYOUT;
    }

    const value = decodeURIComponent(cookie.slice(MENU_LAYOUT_COOKIE.length + 1));
    return isMenuLayoutPreference(value) ? value : DEFAULT_MENU_LAYOUT;
}

function writeMenuLayoutCookie(layout: MenuLayoutPreference): void {
    if (typeof document === "undefined") {
        return;
    }

    document.cookie = `${MENU_LAYOUT_COOKIE}=${encodeURIComponent(layout)}; path=/; max-age=31536000; samesite=lax`;
}

function notifyLayoutChange(layout: MenuLayoutPreference): void {
    if (typeof window === "undefined") {
        return;
    }

    window.dispatchEvent(new CustomEvent(MENU_LAYOUT_EVENT, { detail: { layout } }));
}

export function useMenuLayoutPreference() {
    const [menuLayout, setMenuLayoutState] = useState<MenuLayoutPreference>(() => readMenuLayoutCookie());

    useEffect(() => {
        if (typeof window === "undefined") {
            return;
        }

        const handleLayoutChange = (event: Event) => {
            const customEvent = event as CustomEvent<{ layout?: string }>;
            const next = customEvent.detail?.layout;

            if (isMenuLayoutPreference(next)) {
                setMenuLayoutState(next);
                return;
            }

            setMenuLayoutState(readMenuLayoutCookie());
        };

        window.addEventListener(MENU_LAYOUT_EVENT, handleLayoutChange as EventListener);
        return () => {
            window.removeEventListener(MENU_LAYOUT_EVENT, handleLayoutChange as EventListener);
        };
    }, []);

    const setMenuLayout = useCallback((layout: MenuLayoutPreference) => {
        writeMenuLayoutCookie(layout);
        setMenuLayoutState(layout);
        notifyLayoutChange(layout);
    }, []);

    return {
        menuLayout,
        isSideMenuLayout: menuLayout === "side-menu",
        setMenuLayout,
    };
}

