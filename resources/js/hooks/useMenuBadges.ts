import { useState, useEffect } from "react";
import axios from "axios";
import type { BadgeDto, MenuBadgesResponse } from "@/types/menu";

interface UseMenuBadgesResult {
    itemBadges: Record<string, BadgeDto>;
    categoryBadges: Record<string, BadgeDto>;
    loading: boolean;
}

export function useMenuBadges(): UseMenuBadgesResult {
    const [itemBadges, setItemBadges] = useState<Record<string, BadgeDto>>({});
    const [categoryBadges, setCategoryBadges] = useState<Record<string, BadgeDto>>({});
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;

        axios
            .get<MenuBadgesResponse>("/api/menu/badges")
            .then(({ data }) => {
                if (!cancelled) {
                    setItemBadges(data.items ?? {});
                    setCategoryBadges(data.categories ?? {});
                    setLoading(false);
                }
            })
            .catch(() => {
                // Badges are non-critical – fail silently
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    return { itemBadges, categoryBadges, loading };
}

