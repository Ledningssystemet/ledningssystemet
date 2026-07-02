import type { MenuBadgeStatus, MenuCategoryDto } from "@/types/menu";

const severityRank: Record<MenuBadgeStatus, number> = {
    unknown: 0,
    info: 1,
    warning: 2,
    danger: 3,
};

export function getMaxMenuBadgeSeverity(current: MenuBadgeStatus, next: MenuBadgeStatus): MenuBadgeStatus {
    return severityRank[next] > severityRank[current] ? next : current;
}

export function getCategoryMenuBadgeSeverity(
    category: MenuCategoryDto,
    itemBadges: Record<string, MenuBadgeStatus>,
): MenuBadgeStatus {
    return category.columns
        .flatMap((column) => column.items)
        .reduce<MenuBadgeStatus>((maxSeverity, item) => {
            const itemSeverity = itemBadges[item.key] ?? "unknown";
            return getMaxMenuBadgeSeverity(maxSeverity, itemSeverity);
        }, "unknown");
}

