import AppSectionPlaceholderPage from '@/pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface ChemicalRegisterPageProps {
    route: AppSectionRoute;
}
export default function ChemicalRegisterPage({ route }: ChemicalRegisterPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
