import { useState, useEffect } from "react";
import axios from "axios";
import type { MenuBadgeStatus } from "@/types/menu";

interface UseMenuBadgesResult {
    itemBadges: Record<string, MenuBadgeStatus>;
    loading: boolean;
}

export function useMenuBadges(): UseMenuBadgesResult {
    const [itemBadges, setItemBadges] = useState<Record<string, MenuBadgeStatus>>({});
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;

        axios
            .get<Record<string, MenuBadgeStatus>>("/api/menu/badges")
            .then(({ data }) => {
                if (!cancelled) {
                    setItemBadges(data ?? {});
                    setLoading(false);
                }
            })
            .catch(() => {
                // Badges are non-critical - fail silently
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    return { itemBadges, loading };
}

