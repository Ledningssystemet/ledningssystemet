import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface AssessmentSettingsPageProps {
    route: AppSectionRoute;
}
export default function AssessmentSettingsPage({ route }: AssessmentSettingsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
