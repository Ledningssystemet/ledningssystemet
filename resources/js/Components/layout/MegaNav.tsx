import { useState, useRef, useEffect } from "react";
import { cn } from "@/Lib/utils";
import logoWhite from "@/Assets/logo_se_white.svg";
import logo from "@/Assets/logo_se.svg";
import {
    APP_DASHBOARD_PATH,
    APP_HELP_PATH,
    APP_HOME_PATH,
    APP_OBSERVATION_PATH,
    APP_SETTINGS_PATH,
    getMenuItemPath,
    isExternalUrl,
} from "@/app/routes";
import {
    Home, LayoutDashboard, ChevronDown, Menu, X,
    ClipboardList, UserCircle, FileText,
    Scale, GitBranch, Shield, Globe,
    Truck, FileSignature, CheckCircle2, Leaf, FlaskConical,
    Database, AlertTriangle,
    Settings, HelpCircle, Search, Bell, User,
    FolderOpen, RefreshCcw, ScanSearch,
    type LucideProps,
} from "lucide-react";
import type { MenuCategoryDto, BadgeDto, BadgeSeverity } from "@/types/menu";
import { useMenuData } from "@/hooks/useMenuData";
import { useMenuBadges } from "@/hooks/useMenuBadges";
import { useTranslations } from "@/hooks/useTranslations";
import { Link, NavLink, useLocation } from "react-router-dom";

// ─── Icon registry ────────────────────────────────────────────────────────────
type IconComponent = React.ComponentType<LucideProps>;

const iconRegistry: Record<string, IconComponent> = {
    AlertTriangle, Bell, CheckCircle2, ChevronDown, ClipboardList,
    Database, FileSignature, FileText, FlaskConical, FolderOpen,
    GitBranch, Globe, Home, HelpCircle, LayoutDashboard, Leaf,
    Menu, RefreshCcw, Scale, ScanSearch, Search, Settings,
    Shield, Truck, User, UserCircle, X,
};

function resolveIcon(name: string): IconComponent {
    return iconRegistry[name] ?? Shield;
}

// ─── Badge helpers ────────────────────────────────────────────────────────────
function getSubtleBadgeClasses(severity: BadgeSeverity = "info") {
    switch (severity) {
        case "warning": return "bg-warning/15 text-warning";
        case "danger":  return "bg-destructive/15 text-destructive";
        default:        return "bg-accent/15 text-accent";
    }
}

function getSolidBadgeClasses(severity: BadgeSeverity = "info") {
    switch (severity) {
        case "warning": return "bg-warning text-warning-foreground";
        case "danger":  return "bg-destructive text-destructive-foreground";
        default:        return "bg-accent text-accent-foreground";
    }
}

function getTopLevelNavClasses(isActive: boolean) {
    return cn(
        "flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium transition-colors",
        isActive
            ? "text-primary-foreground bg-primary-foreground/10"
            : "text-primary-foreground/70 hover:text-primary-foreground hover:bg-primary-foreground/10",
    );
}

function getDropdownItemClasses(isActive: boolean) {
    return cn(
        "w-full flex items-start gap-3 px-3 py-2.5 rounded-lg transition-colors group text-left",
        isActive ? "bg-muted/70" : "hover:bg-muted/70",
    );
}

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
    itemBadges: Record<string, BadgeDto>;
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
                        category.columns.length === 1 && "grid-cols-1 max-w-sm",
                        category.columns.length === 2 && "grid-cols-2 max-w-2xl",
                        category.columns.length >= 3 && "grid-cols-3",
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
                                        const ItemIcon = resolveIcon(item.icon);
                                        const badge = itemBadges[item.key];

                                        const content = (
                                            <>
                                                <div className="mt-0.5 p-1.5 rounded-md bg-muted group-hover:bg-primary/10 transition-colors">
                                                    <ItemIcon className="h-4 w-4 text-muted-foreground group-hover:text-primary transition-colors" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-medium text-card-foreground group-hover:text-primary transition-colors">
                                                            {item.label}
                                                        </span>
                                                        {badge && (
                                                            <span className={cn(
                                                                "text-[10px] font-bold px-1.5 py-0.5 rounded-full",
                                                                getSubtleBadgeClasses(badge.severity),
                                                            )}>
                                                                {badge.count}
                                                            </span>
                                                        )}
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
    itemBadges: Record<string, BadgeDto>;
    activePath: string;
}

function MobileMenu({ open, onClose, categories, itemBadges, activePath }: MobileMenuProps) {
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
                        <X className="h-5 w-5 text-foreground" />
                    </button>
                </div>

                <div className="p-3">
                    <div className="relative mb-3">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                        <input
                            type="text"
                            placeholder={t('ui.common.search_placeholder')}
                            className="w-full bg-muted text-foreground text-sm rounded-lg pl-9 pr-3 py-2.5 border border-border focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>

                    <Link to={APP_HOME_PATH} onClick={onClose}
                        className={cn(
                            "w-full flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium text-sm mb-1 transition-colors",
                            activePath === APP_HOME_PATH
                                ? "bg-primary/5 text-primary"
                                : "text-foreground hover:bg-muted",
                        )}>
                        <Home className="h-4 w-4" />
                        {t('ui.common.home')}
                    </Link>
                    <Link to={APP_DASHBOARD_PATH} onClick={onClose}
                        className={cn(
                            "w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm mb-1 transition-colors",
                            activePath === APP_DASHBOARD_PATH
                                ? "bg-primary/5 text-primary font-medium"
                                : "text-foreground hover:bg-muted",
                        )}>
                        <LayoutDashboard className="h-4 w-4" />
                        {t('ui.common.dashboard')}
                    </Link>

                    {categories.map((cat) => {
                        const CatIcon = resolveIcon(cat.categoryIcon ?? "Home");
                        const allItems = cat.columns.flatMap((c) => c.items);
                        return (
                            <div key={cat.label} className="mt-1">
                                <button type="button"
                                    onClick={() => setExpandedCategory(
                                        expandedCategory === cat.label ? null : cat.label
                                    )}
                                    className="w-full flex items-center justify-between px-3 py-2.5 rounded-lg hover:bg-muted transition-colors text-sm font-medium text-foreground">
                                    <span className="flex items-center gap-2">
                                        <CatIcon className="h-4 w-4 text-muted-foreground" />
                                        {cat.label}
                                    </span>
                                    <ChevronDown className={cn(
                                        "h-4 w-4 transition-transform",
                                        expandedCategory === cat.label && "rotate-180",
                                    )} />
                                </button>

                                {expandedCategory === cat.label && (
                                    <div className="ml-2 border-l-2 border-border pl-2 mt-1 mb-2 space-y-0.5">
                                        {allItems.map((item) => {
                                            const ItemIcon = resolveIcon(item.icon);
                                            const badge = itemBadges[item.key];
                                            const content = (
                                                <>
                                                    <ItemIcon className="h-4 w-4" />
                                                    <span>{item.label}</span>
                                                    {badge && (
                                                        <span className={cn(
                                                            "text-[10px] font-bold px-1.5 py-0.5 rounded-full ml-auto",
                                                            getSubtleBadgeClasses(badge.severity),
                                                        )}>
                                                            {badge.count}
                                                        </span>
                                                    )}
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

                <div className="border-t border-border p-3 mt-2">
                    <div className="flex gap-2">
                        <Link to={APP_SETTINGS_PATH} onClick={onClose}
                            className={cn(
                                "flex-1 flex items-center justify-center gap-2 py-2 text-xs rounded-md transition-colors",
                                activePath === APP_SETTINGS_PATH
                                    ? "bg-primary/5 text-primary"
                                    : "bg-muted text-muted-foreground hover:text-foreground",
                            )}>
                            <Settings className="h-3.5 w-3.5" />
                            {t('ui.common.settings')}
                        </Link>
                        <Link to={APP_HELP_PATH} onClick={onClose}
                            className={cn(
                                "flex-1 flex items-center justify-center gap-2 py-2 text-xs rounded-md transition-colors",
                                activePath === APP_HELP_PATH
                                    ? "bg-primary/5 text-primary"
                                    : "bg-muted text-muted-foreground hover:text-foreground",
                            )}>
                            <HelpCircle className="h-3.5 w-3.5" />
                            {t('ui.common.help')}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────
export default function MegaNav() {
    const { t } = useTranslations();
    const categories = useMenuData();
    const { itemBadges, categoryBadges } = useMenuBadges();
    const location = useLocation();

    const [openMenu, setOpenMenu] = useState<string | null>(null);
    const [mobileOpen, setMobileOpen] = useState(false);
    const navRef = useRef<HTMLElement>(null);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

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
            <nav ref={navRef} className="relative topbar-gradient border-b border-border/10 flex-shrink-0 z-50">
                <div className="max-w-[1600px] mx-auto px-4 lg:px-6 flex items-center h-14">

                    {/* Hamburger (mobile) */}
                    <button type="button" onClick={() => setMobileOpen(true)}
                        className="lg:hidden p-2 -ml-2 rounded-md text-primary-foreground/70 hover:text-primary-foreground transition-colors">
                        <Menu className="h-5 w-5" />
                    </button>

                    {/* Logo */}
                    <Link to={APP_HOME_PATH} className="flex items-center mr-8" onClick={() => setOpenMenu(null)}>
                        <img src={logoWhite} alt="Ledningssystemet.se" className="h-7" />
                    </Link>

                    {/* Desktop nav */}
                    <div className="hidden lg:flex items-center gap-1 flex-1" onMouseLeave={handleMouseLeave}>
                        {/* Hem */}
                        <NavLink to={APP_HOME_PATH} end
                            className={({ isActive }) => getTopLevelNavClasses(isActive)}
                            onClick={() => setOpenMenu(null)} onMouseEnter={() => handleMouseEnter("")}>
                            <Home className="h-4 w-4" />
                            <span className="hidden xl:inline">{t('ui.common.home')}</span>
                        </NavLink>

                        {/* Dashboard */}
                        <NavLink to={APP_DASHBOARD_PATH}
                            className={({ isActive }) => getTopLevelNavClasses(isActive)}
                            onClick={() => setOpenMenu(null)} onMouseEnter={() => handleMouseEnter("")}>
                            <LayoutDashboard className="h-4 w-4" />
                            <span className="hidden xl:inline">{t('ui.common.dashboard')}</span>
                        </NavLink>

                        {/* Dynamic categories */}
                        {categories.map((cat) => {
                            const CatIcon = resolveIcon(cat.categoryIcon ?? "Home");
                            const badge = categoryBadges[cat.label];
                            return (
                                <button key={cat.label} type="button"
                                    className={cn(
                                        "relative flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium transition-colors whitespace-nowrap",
                                        openMenu === cat.label
                                            ? "text-primary-foreground bg-primary-foreground/10"
                                            : "text-primary-foreground/70 hover:text-primary-foreground hover:bg-primary-foreground/5",
                                    )}
                                    onClick={() => setOpenMenu(openMenu === cat.label ? null : cat.label)}
                                    onMouseEnter={() => handleMouseEnter(cat.label)}
                                    title={cat.label}>
                                    <CatIcon className="h-4 w-4 xl:hidden flex-shrink-0" />
                                    <span className="hidden xl:inline">{cat.label}</span>
                                    {badge && (
                                        <span className={cn(
                                            "text-[10px] font-bold min-w-[18px] text-center px-1 py-0.5 rounded-full leading-none",
                                            getSolidBadgeClasses(badge.severity),
                                        )}>
                                            {badge.count}
                                        </span>
                                    )}
                                    <ChevronDown className={cn(
                                        "h-3.5 w-3.5 transition-transform duration-200 flex-shrink-0",
                                        openMenu === cat.label && "rotate-180",
                                    )} />
                                </button>
                            );
                        })}
                    </div>

                    {/* Right side */}
                    <div className="flex items-center gap-2 ml-auto">
                        <NavLink to={APP_OBSERVATION_PATH}
                            className={({ isActive }) => cn(
                                "inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors",
                                isActive
                                    ? "bg-accent/90 text-accent-foreground ring-1 ring-accent-foreground/20"
                                    : "bg-accent text-accent-foreground hover:bg-accent/90",
                            )}>
                            <AlertTriangle className="h-3.5 w-3.5" />
                            <span className="hidden md:inline">{t('ui.nav.observation')}</span>
                        </NavLink>

                        <NavLink to={APP_SETTINGS_PATH}
                            className={({ isActive }) => cn(
                                "relative p-2 rounded-md transition-colors",
                                isActive
                                    ? "text-primary-foreground bg-primary-foreground/10"
                                    : "text-primary-foreground/70 hover:text-primary-foreground hover:bg-primary-foreground/5",
                            )}
                            title={t('ui.nav.system_settings')}>
                            <Settings className="h-4 w-4" />
                        </NavLink>

                        <div className="w-px h-6 bg-primary-foreground/15 mx-1 hidden sm:block" />

                        <button type="button"
                            className="relative p-2 rounded-md text-primary-foreground/70 hover:text-primary-foreground hover:bg-primary-foreground/5 transition-colors">
                            <Bell className="h-4 w-4" />
                            <span className="absolute top-1 right-1 w-2 h-2 bg-accent rounded-full" />
                        </button>

                        <div className="flex items-center gap-2 pl-1">
                            <div className="hidden md:block text-right">
                                <div className="text-xs font-medium text-primary-foreground/90">{t('ui.nav.profile_name_placeholder')}</div>
                                <div className="text-[10px] text-primary-foreground/50">{t('ui.nav.company_name_placeholder')}</div>
                            </div>
                            <div className="h-8 w-8 rounded-full bg-accent/20 border border-accent/30 flex items-center justify-center">
                                <User className="h-4 w-4 text-accent" />
                            </div>
                        </div>
                    </div>
                </div>

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
            />
        </>
    );
}
