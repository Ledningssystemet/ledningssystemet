import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface IncidentsPageProps {
    route: AppSectionRoute;
}
export default function IncidentsPage({ route }: IncidentsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
