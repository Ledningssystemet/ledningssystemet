import { usePage } from "@inertiajs/react";
import type { MenuCategoryDto } from "@/types/menu";

export function useMenuData(): MenuCategoryDto[] {
    const { props } = usePage<{
        navigation?: {
            menu?: {
                categories?: MenuCategoryDto[];
            };
        };
    }>();

    return props.navigation?.menu?.categories ?? [];
}

