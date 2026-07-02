import React, { useState, useRef, useEffect } from "react";
import { cn } from "@/Lib/utils";
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import logo from "@/Assets/logo_se.svg";
import {
    APP_HOME_PATH,
    getMenuItemPath,
    isExternalUrl,
} from "@/app/routes";
import type { MenuCategoryDto, MenuBadgeStatus } from "@/types/menu";
import { useMenuData } from "@/hooks/useMenuData";
import { useMenuBadges } from "@/hooks/useMenuBadges";
import { useTranslations } from "@/hooks/useTranslations";
import { Link, NavLink, useLocation } from "react-router-dom";
import AppHeader from "@/Components/layout/AppHeader";
import { getCategoryMenuBadgeSeverity } from "@/Lib/menuBadgeSeverity";


// ─── Badge helpers ───────────────────────────────────────────────────────────

function iconBgClasses(severity?: MenuBadgeStatus): string {
    switch (severity) {
        case "warning": return "bg-yellow-400";
        case "danger":  return "bg-orange-500";
        case "info":    return "bg-teal-500";
        default:        return "bg-gray-500";
    }
}

function categoryBarClasses(severity?: MenuBadgeStatus): string {
    switch (severity) {
        case "warning": return "bg-yellow-400";
        case "danger":  return "bg-orange-500";
        case "info":    return "bg-teal-500";
        default:        return "bg-gray-300";
    }
}


// ─── Top-level nav styles ─────────────────────────────────────────────────────
function getTopLevelNavClasses(isActive: boolean) {
    return cn(
        "flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium transition-colors",
        isActive
            ? "text-primary-foreground bg-primary-foreground/10"
            : "text-primary-foreground/70 hover:text-primary-foreground hover:bg-primary-foreground/10",
    );
}

// ─── Dropdown item styles ─────────────────────────────────────────────────────
function getDropdownItemClasses(isActive: boolean) {
    return cn(
        "w-full flex items-start gap-3 px-3 py-2.5 rounded-lg transition-colors group text-left",
        isActive ? "bg-muted/70" : "hover:bg-muted/70",
    );
}

// ─── Mobile item styles ───────────────────────────────────────────────────────
function getMobileItemClasses(isActive: boolean) {
    return cn(
        "w-full flex items-center gap-2.5 px-3 py-2 rounded-md transition-colors text-sm",
        isActive
            ? "bg-muted text-foreground"
            : "text-muted-foreground hover:bg-muted hover:text-foreground",
    );
}

// ─── Mega-menu dropdown ───────────────────────────────────────────────────────
interface MegaMenuDropdownProps {
    category: MenuCategoryDto;
    itemBadges: Record<string, MenuBadgeStatus>;
    activePath: string;
    onClose: () => void;
}

function MegaMenuDropdown({ category, itemBadges, activePath, onClose }: MegaMenuDropdownProps) {
    return (
        <div className="absolute top-full left-0 right-0 z-50 animate-in fade-in slide-in-from-top-1 duration-150">
            <div className="bg-card border-b border-border shadow-lg">
                <div className="max-w-[1600px] mx-auto px-6 py-6">
                    <div className={cn(
                        "grid gap-8",
                        // Make each mega-menu column roughly ~2x wider than before.
                        category.columns.length === 1 && "grid-cols-1 max-w-[44rem]",
                        category.columns.length === 2 && "grid-cols-2 max-w-[84rem]",
                        category.columns.length >= 3 && "grid-cols-3 max-w-[100rem]",
                    )}>
                        {category.columns.map((col, ci) => (
                            <div key={ci}>
                                {col.heading && (
                                    <h4 className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-3 pb-2 border-b border-border">
                                        {col.heading}
                                    </h4>
                                )}
                                <div className="space-y-1">
                                    {col.items.map((item) => {
                                        const badge = itemBadges[item.key];

                                        const content = (
                                            <>
                                                <div className={cn("mt-0.5 p-1.5 rounded-md shrink-0", iconBgClasses(badge))}>
                                                    <MaterialSymbol name={item.icon} className="h-4 w-4 text-white" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-medium text-card-foreground group-hover:text-primary transition-colors">
                                                            {item.label}
                                                        </span>
                                                    </div>
                                                    {item.description && (
                                                        <p className="text-xs text-muted-foreground mt-0.5 leading-relaxed">
                                                            {item.description}
                                                        </p>
                                                    )}
                                                </div>
                                            </>
                                        );

                                        const external = isExternalUrl(item.href);
                                        const target = getMenuItemPath(item);
                                        const isActive = !external && activePath === target;
                                        const className = getDropdownItemClasses(isActive);

                                        if (external && item.href) {
                                            return (
                                                <a key={item.key} href={item.href}
                                                    target={external ? "_blank" : undefined}
                                                    rel={external ? "noreferrer noopener" : undefined}
                                                    onClick={onClose} className={className}>
                                                    {content}
                                                </a>
                                            );
                                        }
                                        return (
                                            <Link key={item.key} to={target ?? APP_HOME_PATH}
                                                onClick={onClose} className={className}>
                                                {content}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
            <div className="fixed inset-0 -z-10 bg-foreground/10" onClick={onClose} />
        </div>
    );
}

// ─── Mobile menu ──────────────────────────────────────────────────────────────
interface MobileMenuProps {
    open: boolean;
    onClose: () => void;
    categories: MenuCategoryDto[];
    itemBadges: Record<string, MenuBadgeStatus>;
    activePath: string;
    preferredHomePath: string;
}

function MobileMenu({ open, onClose, categories, itemBadges, activePath, preferredHomePath }: MobileMenuProps) {
    const { t } = useTranslations();
    const [expandedCategory, setExpandedCategory] = useState<string | null>(null);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 lg:hidden">
            <div className="absolute inset-0 bg-foreground/30" onClick={onClose} />
            <div className="absolute inset-y-0 left-0 w-80 max-w-[85vw] bg-card shadow-xl overflow-y-auto animate-in slide-in-from-left duration-200">
                <div className="flex items-center justify-between p-4 border-b border-border">
                    <img src={logo} alt="Ledningssystemet.se" className="h-7" />
                    <button onClick={onClose} className="p-2 rounded-md hover:bg-muted transition-colors">
                        <MaterialSymbol name="close" className="h-5 w-5 text-foreground" />
                    </button>
                </div>

                <div className="p-3">
                    <Link to={preferredHomePath} onClick={onClose}
                        className={cn(
                            "w-full flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium text-sm mb-1 transition-colors",
                            activePath === preferredHomePath
                                ? "bg-primary/5 text-primary"
                                : "text-foreground hover:bg-muted",
                        )}>
                        <MaterialSymbol name="home" className="h-4 w-4" />
                        {t('ui.common.home')}
                    </Link>

                    {categories.map((cat) => {
                        const allItems = cat.columns.flatMap((c) => c.items);
                        const categorySeverity = getCategoryMenuBadgeSeverity(cat, itemBadges);
                        return (
                            <div key={cat.label} className="mt-1">
                                <button type="button"
                                    onClick={() => setExpandedCategory(
                                        expandedCategory === cat.label ? null : cat.label
                                    )}
                                    className="w-full flex items-center gap-2 px-2 py-2.5 rounded-lg hover:bg-muted transition-colors text-sm font-medium text-foreground">
                                    <span className={cn("w-1 self-stretch rounded-full my-1 shrink-0", categoryBarClasses(categorySeverity))} />
                                    <MaterialSymbol name={(cat.categoryIcon ?? "help")} className="h-4 w-4 text-muted-foreground" />
                                    <span className="flex-1 text-left">{cat.label}</span>
                                    <MaterialSymbol name="keyboard_arrow_down" className={cn(
                                        "h-4 w-4 transition-transform",
                                        expandedCategory === cat.label && "rotate-180",
                                    )} />
                                </button>

                                {expandedCategory === cat.label && (
                                    <div className="ml-2 border-l-2 border-border pl-2 mt-1 mb-2 space-y-0.5">
                                        {allItems.map((item) => {
                                            const badge = itemBadges[item.key];
                                            const content = (
                                                <>
                                                    <span className={cn("flex h-7 w-7 shrink-0 items-center justify-center rounded-md", iconBgClasses(badge))}>
                                                        <MaterialSymbol name={item.icon} className="h-4 w-4 text-white" />
                                                    </span>
                                                    <span>{item.label}</span>
                                                </>
                                            );
                                            const external = isExternalUrl(item.href);
                                            const target = getMenuItemPath(item);
                                            const className = getMobileItemClasses(!external && activePath === target);
                                            if (external && item.href) {
                                                return (
                                                    <a key={item.key} href={item.href}
                                                        target={external ? "_blank" : undefined}
                                                        rel={external ? "noreferrer noopener" : undefined}
                                                        onClick={onClose} className={className}>
                                                        {content}
                                                    </a>
                                                );
                                            }
                                            return (
                                                <Link key={item.key} to={target ?? APP_HOME_PATH} onClick={onClose} className={className}>
                                                    {content}
                                                </Link>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────
export default function MegaNav() {
    const { t } = useTranslations();
    const categories = useMenuData();
    const { itemBadges } = useMenuBadges();
    const location = useLocation();

    const [openMenu, setOpenMenu] = useState<string | null>(null);
    const [mobileOpen, setMobileOpen] = useState(false);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const preferredHomePath = APP_HOME_PATH;

    const openCategory = categories.find((c) => c.label === openMenu) ?? null;

    const handleMouseEnter = (label: string) => {
        if (timeoutRef.current) clearTimeout(timeoutRef.current);
        setOpenMenu(label || null);
    };

    const handleMouseLeave = () => {
        timeoutRef.current = setTimeout(() => setOpenMenu(null), 150);
    };

    useEffect(() => {
        return () => {
            if (timeoutRef.current) clearTimeout(timeoutRef.current);
        };
    }, []);

    useEffect(() => {
        setOpenMenu(null);
        setMobileOpen(false);
    }, [location.pathname]);

    return (
        <>
            <nav className="relative z-50 flex-shrink-0" data-testid="mega-nav">
                <AppHeader
                    logoPath={preferredHomePath}
                    onLogoClick={() => setOpenMenu(null)}
                    onBeforeProfileToggle={() => setOpenMenu(null)}
                    leftContent={(
                        <button
                            type="button"
                            onClick={() => setMobileOpen(true)}
                            className="-ml-2 rounded-md p-2 text-primary-foreground/70 transition-colors hover:text-primary-foreground lg:hidden"
                        >
                            <MaterialSymbol name="menu" className="h-5 w-5" />
                        </button>
                    )}
                    navigationContent={(
                        <div className="hidden flex-1 items-center gap-1 lg:flex" onMouseLeave={handleMouseLeave}>
                            <NavLink
                                to={preferredHomePath}
                                end
                                className={({ isActive }) => getTopLevelNavClasses(isActive)}
                                onClick={() => setOpenMenu(null)}
                                onMouseEnter={() => handleMouseEnter("")}
                            >
                                <MaterialSymbol name="home" className="h-4 w-4" />
                                <span className="hidden xl:inline">{t("ui.common.home")}</span>
                            </NavLink>

                            {categories.map((cat) => {
                                const categorySeverity = getCategoryMenuBadgeSeverity(cat, itemBadges);
                                return (
                                    <button
                                        key={cat.label}
                                        type="button"
                                        className={cn(
                                            "relative flex items-center gap-1.5 whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition-colors",
                                            openMenu === cat.label
                                                ? "bg-primary-foreground/10 text-primary-foreground"
                                                : "text-primary-foreground/70 hover:bg-primary-foreground/5 hover:text-primary-foreground",
                                        )}
                                        onClick={() => setOpenMenu(openMenu === cat.label ? null : cat.label)}
                                        onMouseEnter={() => handleMouseEnter(cat.label)}
                                        title={cat.label}
                                    >
                                        <span className={cn("my-1 w-1 shrink-0 self-stretch rounded-full", categoryBarClasses(categorySeverity))} />
                                        <MaterialSymbol name={cat.categoryIcon ?? "help"} className="h-4 w-4 flex-shrink-0" />
                                        <span className="hidden xl:inline">{cat.label}</span>
                                        <MaterialSymbol
                                            name="keyboard_arrow_down"
                                            className={cn(
                                                "h-3.5 w-3.5 flex-shrink-0 transition-transform duration-200",
                                                openMenu === cat.label && "rotate-180",
                                            )}
                                        />
                                    </button>
                                );
                            })}
                        </div>
                    )}
                />

                {/* Dropdown */}
                {openCategory && (
                    <div
                        onMouseEnter={() => { if (timeoutRef.current) clearTimeout(timeoutRef.current); }}
                        onMouseLeave={handleMouseLeave}>
                        <MegaMenuDropdown
                            category={openCategory}
                            itemBadges={itemBadges}
                            activePath={location.pathname}
                            onClose={() => setOpenMenu(null)}
                        />
                    </div>
                )}
            </nav>

            <MobileMenu
                open={mobileOpen}
                onClose={() => setMobileOpen(false)}
                categories={categories}
                itemBadges={itemBadges}
                activePath={location.pathname}
                preferredHomePath={preferredHomePath}
            />
        </>
    );
}

