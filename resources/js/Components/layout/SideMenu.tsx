import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { APP_HOME_PATH, getMenuItemPath, isExternalUrl } from "@/app/routes";
import { useMenuData } from "@/hooks/useMenuData";
import { useMenuBadges } from "@/hooks/useMenuBadges";
import { useTranslations } from "@/hooks/useTranslations";
import { getCategoryMenuBadgeSeverity } from "@/lib/menuBadgeSeverity";
import type { MenuBadgeStatus } from "@/types/menu";

interface SideMenuProps {
    mobileOpen?: boolean;
    onCloseMobile?: () => void;
}

function iconBgClasses(severity?: MenuBadgeStatus): string {
    switch (severity) {
        case "warning":
            return "bg-yellow-400";
        case "danger":
            return "bg-orange-500";
        case "info":
            return "bg-teal-500";
        default:
            return "bg-gray-500";
    }
}

function categoryBarClasses(severity?: MenuBadgeStatus): string {
    switch (severity) {
        case "warning":
            return "bg-yellow-400";
        case "danger":
            return "bg-orange-500";
        case "info":
            return "bg-teal-500";
        default:
            return "bg-gray-500";
    }
}

function activeLinkClasses(isActive: boolean): string {
    return isActive
        ? "bg-[#fbfbfb] border border-black text-black"
        : "bg-[#fbfbfb] border border-transparent text-black hover:text-[#6c757d]";
}


function SideMenuContent() {
    const { t } = useTranslations();
    const categories = useMenuData();
    const { itemBadges } = useMenuBadges();
    const location = useLocation();
    const [expandedCategory, setExpandedCategory] = useState<string | null>(null);

    useEffect(() => {
        const activeCategory = categories.find((category) => {
            const allItems = category.columns.flatMap((column) => column.items);
            return allItems.some((item) => {
                if (item.href && isExternalUrl(item.href)) {
                    return false;
                }

                return getMenuItemPath(item) === location.pathname;
            });
        });

        setExpandedCategory((current) => current ?? activeCategory?.label ?? categories[0]?.label ?? null);
    }, [categories, location.pathname]);

    return (
        <div className="flex h-full flex-col">

            <nav className="flex-1 overflow-y-auto px-3 py-3" data-testid="side-menu">
                <Link
                    to={APP_HOME_PATH}
                    className={`mb-6 flex h-[45px] items-center rounded-md px-4 text-sm font-medium border-none ${activeLinkClasses(location.pathname === APP_HOME_PATH)}`}
                >
                    <MaterialSymbol name="home" className="mr-3 h-4 w-4" />
                    {t("ui.common.home")}
                </Link>

                {categories.map((category) => {
                    const categoryIconName = category.categoryIcon ?? "help";
                    const isExpanded = expandedCategory === category.label;
                    const categorySeverity = getCategoryMenuBadgeSeverity(category, itemBadges);

                    return (
                        <section key={category.label} className="mt-2">
                            <button
                                type="button"
                                onClick={() => setExpandedCategory(isExpanded ? null : category.label)}
                                className="flex h-[45px] w-full items-center rounded-md px-2 text-left text-sm font-medium text-black gap-2"
                            >
                                <span className={`w-1 self-stretch rounded-full my-2 shrink-0 ${categoryBarClasses(categorySeverity)}`} />
                                <MaterialSymbol name={categoryIconName} className="h-4 w-4" />
                                <span className="flex-1 truncate">{category.label}</span>
                                <MaterialSymbol name="keyboard_arrow_down" className={`h-4 w-4 transition-transform ${isExpanded ? "rotate-180" : ""}`} />
                            </button>

                            {isExpanded && (
                                <div className="mt-2 space-y-1 pl-2">
                                    {category.columns.map((column, columnIndex) => (
                                        <div key={`${category.label}-${columnIndex}`} className="space-y-1">
                                            {column.heading && (
                                                <p className="px-3 mt-5 pt-1 text-[10px] font-semibold uppercase tracking-wider text-[#6c757d]">
                                                    {column.heading}
                                                </p>
                                            )}
                                            {column.items.map((item) => {
                                                const itemIconName = item.icon;
                                                const itemBadge = itemBadges[item.key];
                                                const isExternal = isExternalUrl(item.href);
                                                const target = getMenuItemPath(item);
                                                const isActive = !isExternal && location.pathname === target;
                                                const className = `flex min-h-[38px] items-center rounded-md px-3 mb-3 border-none text-sm ${activeLinkClasses(isActive)}`;

                                                const content = (
                                                    <>
                                                        <span className={`mr-3 flex h-7 w-7 shrink-0 items-center justify-center rounded-md ${iconBgClasses(itemBadge)}`}>
                                                            <MaterialSymbol name={itemIconName} className="h-4 w-4 text-white" />
                                                        </span>
                                                        <span className="flex-1 truncate">{item.label}</span>
                                                    </>
                                                );

                                                if (isExternal && item.href) {
                                                    return (
                                                        <a
                                                            key={item.key}
                                                            href={item.href}
                                                            target="_blank"
                                                            rel="noreferrer noopener"
                                                            className={className}
                                                        >
                                                            {content}
                                                        </a>
                                                    );
                                                }

                                                return (
                                                    <Link key={item.key} to={target ?? APP_HOME_PATH} className={className}>
                                                        {content}
                                                    </Link>
                                                );
                                            })}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    );
                })}
            </nav>
        </div>
    );
}

export default function SideMenu(_props: SideMenuProps = {}) {
    return (
        <aside className="hidden h-full w-80 border-r border-[#e6e6e6] bg-white lg:block">
            <SideMenuContent />
        </aside>
    );
}
