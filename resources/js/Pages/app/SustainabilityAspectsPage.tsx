import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface SustainabilityAspectsPageProps {
    route: AppSectionRoute;
}
export default function SustainabilityAspectsPage({ route }: SustainabilityAspectsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
