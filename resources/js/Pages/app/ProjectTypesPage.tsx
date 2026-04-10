import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ProjectTypesPageProps {
    route: AppSectionRoute;
}
export default function ProjectTypesPage({ route }: ProjectTypesPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
