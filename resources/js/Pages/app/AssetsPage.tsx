import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface AssetsPageProps {
    route: AppSectionRoute;
}
export default function AssetsPage({ route }: AssetsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
