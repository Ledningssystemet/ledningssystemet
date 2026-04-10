import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface DepartmentsPageProps {
    route: AppSectionRoute;
}
export default function DepartmentsPage({ route }: DepartmentsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
