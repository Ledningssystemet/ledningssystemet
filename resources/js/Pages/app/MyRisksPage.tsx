import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface MyRisksPageProps {
    route: AppSectionRoute;
}
export default function MyRisksPage({ route }: MyRisksPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
