import { ReactNode } from 'react';
import SessionGuard from '@/components/auth/SessionGuard';
import MegaNav from '@/components/layout/MegaNav';

interface AppLayoutProps {
  children: ReactNode;
}

export default function AppLayout({ children }: AppLayoutProps) {
  return (
    <div className="flex flex-col h-screen overflow-hidden bg-background">
      <SessionGuard />
      <MegaNav />
      <main className="flex-1 overflow-y-auto">
        <div className="p-4 lg:p-6 max-w-[1600px] mx-auto">
          {children}
        </div>
      </main>
    </div>
  );
}
