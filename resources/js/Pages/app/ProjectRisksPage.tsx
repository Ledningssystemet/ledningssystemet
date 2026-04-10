import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ProjectRisksPageProps {
    route: AppSectionRoute;
}
export default function ProjectRisksPage({ route }: ProjectRisksPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
