import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ControlActionsPageProps {
    route: AppSectionRoute;
}
export default function ControlActionsPage({ route }: ControlActionsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
