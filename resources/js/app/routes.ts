import type { MenuCategoryDto, MenuItemDto } from "@/types/menu";

export const APP_BASE_PATH = "/app";
export const APP_HOME_PATH = "/";
export const APP_MY_PROFILE_PATH = "/my-profile";
export const APP_PROCESSES_PATH = "/processes";
export const APP_PROCESS_EDITOR_PATH = "/processes/:processId/editor";
export const APP_DOCUMENTS_PATH = "/documents";
export const APP_DOCUMENT_EDITOR_PATH = "/documents/:libraryDocumentId/editor";

export interface AppSectionRoute {
    key: string;
    path: string;
    label: string;
    description?: string;
    categoryLabel?: string;
    sectionLabel?: string;
    icon?: string;
}

export function isExternalUrl(href?: string): boolean {
    return Boolean(href && /^(https?:)?\/\//.test(href));
}

function normalizeInternalPath(href: string): string {
    if (href.startsWith(APP_BASE_PATH)) {
        const stripped = href.slice(APP_BASE_PATH.length);
        if (stripped === "") {
            return APP_HOME_PATH;
        }

        return stripped.startsWith("/") ? stripped : `/${stripped}`;
    }

    return href.startsWith("/") ? href : `/${href}`;
}

export function getMenuItemPath(item: MenuItemDto): string {
    if (item.href && !isExternalUrl(item.href)) {
        return normalizeInternalPath(item.href);
    }

    return `/${item.key}`;
}

export function buildMenuRoutes(categories: MenuCategoryDto[]): AppSectionRoute[] {
    const routes = new Map<string, AppSectionRoute>();

    for (const category of categories) {
        for (const column of category.columns) {
            for (const item of column.items) {
                if (item.href && isExternalUrl(item.href)) {
                    continue;
                }

                const path = getMenuItemPath(item);

                if (!routes.has(path)) {
                    routes.set(path, {
                        key: item.key,
                        path,
                        label: item.label,
                        description: item.description,
                        categoryLabel: category.label,
                        sectionLabel: column.heading,
                        icon: item.icon,
                    });
                }
            }
        }
    }

    return Array.from(routes.values());
}

export function buildProcessEditorPath(processId: number | string): string {
    return `/processes/${processId}/editor`;
}
