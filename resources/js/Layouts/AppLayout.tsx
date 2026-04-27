import { ReactNode, useEffect, useRef, useState } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import { Link, useLocation } from 'react-router-dom';
import { APP_HOME_PATH, APP_MY_PROFILE_PATH } from '@/app/routes';
import logoWhite from '@/assets/logo_se_white.svg';
import { useTranslations } from '@/hooks/useTranslations';
import { useMenuLayoutPreference } from '@/hooks/useMenuLayoutPreference';
import SessionGuard from '@/components/auth/SessionGuard';
import MegaNav from '@/components/layout/MegaNav';
import SideMenu from '@/components/layout/SideMenu';

interface AppLayoutProps {
  children: ReactNode;
}

interface SharedProps extends PageProps {
  auth?: {
    user?: {
      name?: string | null;
      email?: string | null;
    } | null;
  };
}

export default function AppLayout({ children }: AppLayoutProps) {
  const { t } = useTranslations();
  const { menuLayout, isSideMenuLayout } = useMenuLayoutPreference();
  const location = useLocation();
  const page = usePage<SharedProps>();
  const [profileMenuOpen, setProfileMenuOpen] = useState(false);
  const profileMenuRef = useRef<HTMLDivElement>(null);

  const user = page.props.auth?.user;
  const profileName = user?.name?.trim() || user?.email || t('ui.nav.profile_name_placeholder');
  const profileEmail = user?.email;

  useEffect(() => {
    const handlePointerDown = (event: MouseEvent) => {
      if (!profileMenuRef.current?.contains(event.target as Node)) {
        setProfileMenuOpen(false);
      }
    };

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setProfileMenuOpen(false);
      }
    };

    document.addEventListener('mousedown', handlePointerDown);
    document.addEventListener('keydown', handleEscape);

    return () => {
      document.removeEventListener('mousedown', handlePointerDown);
      document.removeEventListener('keydown', handleEscape);
    };
  }, []);

  useEffect(() => {
    setProfileMenuOpen(false);
  }, [location.pathname]);

  if (isSideMenuLayout) {
    return (
      <div className="flex h-screen flex-col overflow-hidden bg-[#fbfbfb]" data-menu-layout={menuLayout} data-testid="app-layout">
        <SessionGuard />

        {/* Full-width header */}
        <header
          className="hidden h-14 shrink-0 items-center justify-between border-b border-border/10 topbar-gradient px-3 text-white lg:flex lg:px-5"
          data-testid="side-layout-header"
        >
          <Link to={APP_HOME_PATH} className="flex items-center">
            <img src={logoWhite} alt={t('ui.nav.app_name')} className="h-7" />
          </Link>

          <div ref={profileMenuRef} className="relative">
            <button
              type="button"
              data-testid="account-menu-trigger"
              onClick={() => setProfileMenuOpen((previous) => !previous)}
              aria-label={t('ui.nav.account_menu_label')}
              aria-expanded={profileMenuOpen}
              className="flex items-center gap-2 rounded-md px-2 py-1.5 transition-colors hover:bg-white/10"
            >
              <div className="hidden text-right md:block">
                <div className="text-xs font-semibold text-white/90">{profileName}</div>
                {profileEmail && <div className="text-[10px] text-white/60">{profileEmail}</div>}
              </div>
              <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[#288c98]/35 text-[#d4eff2]">
                <MaterialSymbol name="person" className="h-4 w-4" />
              </span>
              <MaterialSymbol name="keyboard_arrow_down" className={`h-4 w-4 transition-transform ${profileMenuOpen ? 'rotate-180' : ''}`} />
            </button>

            {profileMenuOpen && (
              <div className="absolute right-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-md border border-[#e6e6e6] bg-white shadow-xl">
                <Link
                  to={APP_MY_PROFILE_PATH}
                  className="flex items-center gap-2 px-3 py-2 text-sm text-black transition-colors hover:bg-[#fbfbfb]"
                >
                  <MaterialSymbol name="tune" className="h-4 w-4 text-[#6c757d]" />
                  {t('ui.nav.my_preferences')}
                </Link>
                <a
                  href="/logout"
                  className="flex items-center gap-2 px-3 py-2 text-sm text-black transition-colors hover:bg-[#fbfbfb]"
                >
                  <MaterialSymbol name="logout" className="h-4 w-4 text-[#6c757d]" />
                  {t('ui.nav.log_out')}
                </a>
              </div>
            )}
          </div>
        </header>

        {/* Body: sidemenu + content */}
        <div className="lg:hidden">
          <MegaNav />
        </div>
        <div className="flex min-h-0 flex-1 overflow-hidden">
          <SideMenu mobileOpen={false} onCloseMobile={() => {}} />
          <main className="flex-1 overflow-y-auto">
            <div className="mx-auto max-w-[1600px] p-4 lg:p-6">
              {children}
            </div>
          </main>
        </div>
      </div>
    );
  }

  return (
    <div className="flex h-screen flex-col overflow-hidden bg-background" data-menu-layout={menuLayout} data-testid="app-layout">
      <SessionGuard />
      <MegaNav />
      <main className="flex-1 overflow-y-auto">
        <div className="mx-auto max-w-[1600px] p-4 lg:p-6">
          {children}
        </div>
      </main>
    </div>
  );
}
