export type BadgeSeverity = "info" | "warning" | "danger";
export type MenuBadgeStatus = BadgeSeverity | "unknown";

export interface MenuItemDto {
    key: string;
    label: string;
    icon: string;
    description?: string;
    href?: string;
}

export interface MenuColumnDto {
    heading?: string;
    items: MenuItemDto[];
}

export interface MenuCategoryDto {
    label: string;
    /** Lucide icon name used in compact/icon-only nav mode */
    categoryIcon?: string;
    columns: MenuColumnDto[];
}
