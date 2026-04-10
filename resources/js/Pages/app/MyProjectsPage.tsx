import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface MyProjectsPageProps {
    route: AppSectionRoute;
}
export default function MyProjectsPage({ route }: MyProjectsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
