import { ReactNode } from 'react';
import { useMenuLayoutPreference } from '@/hooks/useMenuLayoutPreference';
import SessionGuard from '@/components/auth/SessionGuard';
import AppHeader from '@/components/layout/AppHeader';
import MegaNav from '@/components/layout/MegaNav';
import SideMenu from '@/components/layout/SideMenu';

interface AppLayoutProps {
  children: ReactNode;
}

export default function AppLayout({ children }: AppLayoutProps) {
  const { menuLayout, isSideMenuLayout } = useMenuLayoutPreference();

  if (isSideMenuLayout) {
    return (
      <div className="flex h-screen flex-col overflow-hidden bg-[#fbfbfb]" data-menu-layout={menuLayout} data-testid="app-layout">
        <SessionGuard />

        <AppHeader
          testId="side-layout-header"
          className="hidden lg:flex"
          innerClassName="max-w-none px-3 lg:px-5"
        />

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
