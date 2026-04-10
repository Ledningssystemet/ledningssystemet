import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface DocumentsPageProps {
    route: AppSectionRoute;
}
export default function DocumentsPage({ route }: DocumentsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
