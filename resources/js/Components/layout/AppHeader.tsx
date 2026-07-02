import { ReactNode, useEffect, useRef, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { usePage } from "@inertiajs/react";
import type { PageProps } from "@inertiajs/core";
import { cn } from "@/Lib/utils";
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import logoWhite from "@/Assets/logo_se_white.svg";
import { APP_HOME_PATH, APP_MY_PROFILE_PATH } from "@/app/routes";
import { useTranslations } from "@/hooks/useTranslations";

interface SharedProps extends PageProps {
    auth?: {
        user?: {
            name?: string | null;
            email?: string | null;
        } | null;
    };
}

interface AppHeaderProps {
    testId?: string;
    className?: string;
    innerClassName?: string;
    leftContent?: ReactNode;
    navigationContent?: ReactNode;
    logoPath?: string;
    onLogoClick?: () => void;
    onBeforeProfileToggle?: () => void;
    profilePreferencesPath?: string;
    logoutPath?: string;
}

function getProfileMenuItemClasses(isActive = false) {
    return cn(
        "flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-colors",
        isActive
            ? "bg-muted text-foreground"
            : "text-foreground hover:bg-muted",
    );
}

export default function AppHeader({
    testId,
    className,
    innerClassName,
    leftContent,
    navigationContent,
    logoPath = APP_HOME_PATH,
    onLogoClick,
    onBeforeProfileToggle,
    profilePreferencesPath = APP_MY_PROFILE_PATH,
    logoutPath = "/logout",
}: AppHeaderProps) {
    const { t } = useTranslations();
    const location = useLocation();
    const page = usePage<SharedProps>();
    const [profileMenuOpen, setProfileMenuOpen] = useState(false);
    const profileMenuRef = useRef<HTMLDivElement>(null);

    const user = page.props.auth?.user;
    const profileName =
        user?.name?.trim() || user?.email || t("ui.nav.profile_name_placeholder");
    const profileEmail = user?.email;

    useEffect(() => {
        const handlePointerDown = (event: MouseEvent) => {
            if (!profileMenuRef.current?.contains(event.target as Node)) {
                setProfileMenuOpen(false);
            }
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === "Escape") {
                setProfileMenuOpen(false);
            }
        };

        document.addEventListener("mousedown", handlePointerDown);
        document.addEventListener("keydown", handleKeyDown);

        return () => {
            document.removeEventListener("mousedown", handlePointerDown);
            document.removeEventListener("keydown", handleKeyDown);
        };
    }, []);

    useEffect(() => {
        setProfileMenuOpen(false);
    }, [location.pathname]);

    return (
        <div
            data-testid={testId}
            className={cn(
                "h-16 shrink-0 border-b border-border/10 topbar-gradient",
                className,
            )}
        >
            <div
                className={cn(
                    "mx-auto flex h-full w-full max-w-[1600px] items-center px-4 lg:px-6",
                    innerClassName,
                )}
            >
                {leftContent}

                <Link
                    to={logoPath}
                    className="mr-4 flex items-center"
                    onClick={onLogoClick}
                >
                    <img src={logoWhite} alt={t("ui.nav.app_name")} className="h-10" />
                </Link>

                {navigationContent}

                <div className="ml-auto flex items-center gap-2">
                    {navigationContent && (
                        <div className="mx-1 hidden h-6 w-px bg-primary-foreground/15 sm:block" />
                    )}

                    <div ref={profileMenuRef} className="relative pl-1">
                        <button
                            type="button"
                            data-testid="account-menu-trigger"
                            aria-haspopup="menu"
                            aria-expanded={profileMenuOpen}
                            aria-label={t("ui.nav.account_menu_label")}
                            onClick={() => {
                                onBeforeProfileToggle?.();
                                setProfileMenuOpen((prev) => !prev);
                            }}
                            className="flex items-center gap-2 rounded-lg px-2 py-1.5 transition-colors hover:bg-primary-foreground/10 focus:outline-none focus:ring-2 focus:ring-primary-foreground/30"
                        >
                            <div className="hidden text-right md:block">
                                <div className="text-xs font-medium text-primary-foreground/90">{profileName}</div>
                                <div className="text-[10px] text-primary-foreground/50">{profileEmail}</div>
                            </div>
                            <div className="flex h-8 w-8 items-center justify-center rounded-full border border-accent/30 bg-accent/20">
                                <MaterialSymbol name="person" className="h-4 w-4 text-accent" />
                            </div>
                            <MaterialSymbol
                                name="keyboard_arrow_down"
                                className={cn(
                                    "hidden h-4 w-4 text-primary-foreground/70 transition-transform sm:block",
                                    profileMenuOpen && "rotate-180",
                                )}
                            />
                        </button>

                        {profileMenuOpen && (
                            <div className="absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-xl border border-border bg-card p-1.5 shadow-xl">
                                <div className="border-b border-border px-3 py-2">
                                    <div className="truncate text-sm font-medium text-card-foreground">{profileName}</div>
                                    {profileEmail && (
                                        <div className="truncate text-xs text-muted-foreground">{profileEmail}</div>
                                    )}
                                </div>

                                <div className="pt-1">
                                    <Link
                                        to={profilePreferencesPath}
                                        onClick={() => setProfileMenuOpen(false)}
                                        className={getProfileMenuItemClasses(location.pathname === profilePreferencesPath)}
                                    >
                                        <MaterialSymbol name="tune" className="h-4 w-4 text-muted-foreground" />
                                        <span>{t("ui.nav.my_profile")}</span>
                                    </Link>

                                    <a
                                        href={logoutPath}
                                        onClick={() => setProfileMenuOpen(false)}
                                        className={getProfileMenuItemClasses()}
                                    >
                                        <MaterialSymbol name="logout" className="h-4 w-4 text-muted-foreground" />
                                        <span>{t("ui.nav.log_out")}</span>
                                    </a>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

