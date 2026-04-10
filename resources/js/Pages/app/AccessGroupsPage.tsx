import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface AccessGroupsPageProps {
    route: AppSectionRoute;
}
export default function AccessGroupsPage({ route }: AccessGroupsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
