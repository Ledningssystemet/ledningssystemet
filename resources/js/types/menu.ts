export type BadgeSeverity = "info" | "warning" | "danger";

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

export interface MenuResponse {
    categories: MenuCategoryDto[];
}

export interface BadgeDto {
    count: string;
    severity: BadgeSeverity;
}

export interface MenuBadgesResponse {
    /** Badges keyed by menu item `key` */
    items: Record<string, BadgeDto>;
    /** Badges keyed by category `label` */
    categories: Record<string, BadgeDto>;
}

