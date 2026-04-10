import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ControlsPageProps {
    route: AppSectionRoute;
}
export default function ControlsPage({ route }: ControlsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
