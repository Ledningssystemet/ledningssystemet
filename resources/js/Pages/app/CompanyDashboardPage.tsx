import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface CompanyDashboardPageProps {
    route: AppSectionRoute;
}
export default function CompanyDashboardPage({ route }: CompanyDashboardPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
