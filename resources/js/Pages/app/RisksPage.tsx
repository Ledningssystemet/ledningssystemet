import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface RisksPageProps {
    route: AppSectionRoute;
}
export default function RisksPage({ route }: RisksPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
