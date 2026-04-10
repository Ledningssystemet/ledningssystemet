import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ApiTokensPageProps {
    route: AppSectionRoute;
}
export default function ApiTokensPage({ route }: ApiTokensPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
