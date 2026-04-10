import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ComplianceEvaluationPageProps {
    route: AppSectionRoute;
}
export default function ComplianceEvaluationPage({ route }: ComplianceEvaluationPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
