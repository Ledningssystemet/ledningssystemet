import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface AgreementsPageProps {
    route: AppSectionRoute;
}
export default function AgreementsPage({ route }: AgreementsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
