import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ProcessPerformancePageProps {
    route: AppSectionRoute;
}
export default function ProcessPerformancePage({ route }: ProcessPerformancePageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
