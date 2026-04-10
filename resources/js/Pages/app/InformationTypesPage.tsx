import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface InformationTypesPageProps {
    route: AppSectionRoute;
}
export default function InformationTypesPage({ route }: InformationTypesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
