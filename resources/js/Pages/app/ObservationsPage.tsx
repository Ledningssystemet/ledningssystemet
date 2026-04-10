import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ObservationsPageProps {
    route: AppSectionRoute;
}
export default function ObservationsPage({ route }: ObservationsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
