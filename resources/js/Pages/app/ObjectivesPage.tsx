import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ObjectivesPageProps {
    route: AppSectionRoute;
}
export default function ObjectivesPage({ route }: ObjectivesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
