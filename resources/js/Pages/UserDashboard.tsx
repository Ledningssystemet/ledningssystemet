import AppLayout from '@/layouts/AppLayout';
import DashboardGrid from "@/components/dashboard/DashboardGrid";

export default function UserDashboard() {
    // Export the user dashboard using the DashboardGrid Component
    return (
        <AppLayout>
            <DashboardGrid />
        </AppLayout>
    )
}
