import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ActivitiesPageProps {
    route: AppSectionRoute;
}
export default function ActivitiesPage({ route }: ActivitiesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
