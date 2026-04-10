import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface RolesPageProps {
    route: AppSectionRoute;
}
export default function RolesPage({ route }: RolesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
