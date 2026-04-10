import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface SupplierCategoriesPageProps {
    route: AppSectionRoute;
}
export default function SupplierCategoriesPage({ route }: SupplierCategoriesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
