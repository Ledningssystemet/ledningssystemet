import { useState, useEffect } from "react";
import axios from "axios";
import type { MenuCategoryDto, MenuResponse } from "@/types/menu";

interface UseMenuDataResult {
    categories: MenuCategoryDto[];
    loading: boolean;
    error: string | null;
}

export function useMenuData(): UseMenuDataResult {
    const [categories, setCategories] = useState<MenuCategoryDto[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        axios
            .get<MenuResponse>("/api/menu")
            .then(({ data }) => {
                if (!cancelled) {
                    setCategories(data.categories ?? []);
                    setLoading(false);
                }
            })
            .catch((err: unknown) => {
                if (!cancelled) {
                    const message =
                        err instanceof Error ? err.message : "Kunde inte ladda menyn";
                    setError(message);
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    return { categories, loading, error };
}

