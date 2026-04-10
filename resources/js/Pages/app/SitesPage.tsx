import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface SitesPageProps {
    route: AppSectionRoute;
}
export default function SitesPage({ route }: SitesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
