import AppSectionPlaceholderPage from '@/Pages/app/AppSectionPlaceholderPage';
import type { AppSectionRoute } from '@/app/routes';
interface UserNotificationSettingsPageProps {
    route: AppSectionRoute;
}
export default function UserNotificationSettingsPage({ route }: UserNotificationSettingsPageProps) {
    return <AppSectionPlaceholderPage route={route} />;
}
