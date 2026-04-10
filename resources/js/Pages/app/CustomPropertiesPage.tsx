import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface CustomPropertiesPageProps {
    route: AppSectionRoute;
}
export default function CustomPropertiesPage({ route }: CustomPropertiesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
