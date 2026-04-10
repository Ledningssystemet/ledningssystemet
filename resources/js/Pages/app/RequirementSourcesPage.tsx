import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface RequirementSourcesPageProps {
    route: AppSectionRoute;
}
export default function RequirementSourcesPage({ route }: RequirementSourcesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
