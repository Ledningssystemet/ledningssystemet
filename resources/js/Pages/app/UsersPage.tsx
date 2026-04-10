import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface UsersPageProps {
    route: AppSectionRoute;
}
export default function UsersPage({ route }: UsersPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
