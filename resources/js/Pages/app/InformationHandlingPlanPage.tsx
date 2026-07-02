import AppSectionPlaceholderPage from '@/Pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface InformationHandlingPlanPageProps {
    route: AppSectionRoute;
}
export default function InformationHandlingPlanPage({ route }: InformationHandlingPlanPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
