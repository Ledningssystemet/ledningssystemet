import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface TagsPageProps {
    route: AppSectionRoute;
}
export default function TagsPage({ route }: TagsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
