import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface CompentencesPageProps {
    route: AppSectionRoute;
}
export default function CompentencesPage({ route }: CompentencesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
