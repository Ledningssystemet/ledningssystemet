import AppLayout from '@/Layouts/AppLayout';
import DashboardGrid from "@/Components/dashboard/DashboardGrid";

export default function UserDashboard() {
    // Export the user dashboard using the DashboardGrid Component
    return (
        <AppLayout>
            <DashboardGrid />
        </AppLayout>
    )
}
