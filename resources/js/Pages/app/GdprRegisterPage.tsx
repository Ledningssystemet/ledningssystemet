import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface GdprRegisterPageProps {
    route: AppSectionRoute;
}
export default function GdprRegisterPage({ route }: GdprRegisterPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
