import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ActivityFlowsPageProps {
    route: AppSectionRoute;
}
export default function ActivityFlowsPage({ route }: ActivityFlowsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
