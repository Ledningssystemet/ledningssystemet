import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface EmployeesPageProps {
    route: AppSectionRoute;
}
export default function EmployeesPage({ route }: EmployeesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
