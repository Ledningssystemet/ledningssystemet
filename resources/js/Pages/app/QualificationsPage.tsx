import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface QualificationsPageProps {
    route: AppSectionRoute;
}
export default function QualificationsPage({ route }: QualificationsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
