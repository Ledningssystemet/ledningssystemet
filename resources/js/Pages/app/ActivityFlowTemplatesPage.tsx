import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ActivityFlowTemplatesPageProps {
    route: AppSectionRoute;
}
export default function ActivityFlowTemplatesPage({ route }: ActivityFlowTemplatesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
