import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface SuppliersPageProps {
    route: AppSectionRoute;
}
export default function SuppliersPage({ route }: SuppliersPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
