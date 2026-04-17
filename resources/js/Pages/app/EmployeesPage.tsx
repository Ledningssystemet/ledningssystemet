import { useMemo, useState } from 'react';
import { Users } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import { EmployeeDialog } from '@/components/employees/EmployeeDialog';
import type { AppSectionRoute } from '@/app/routes';

// ─── Page ─────────────────────────────────────────────────────────────────────
interface EmployeesPageProps { route: AppSectionRoute }

export default function EmployeesPage({ route }: EmployeesPageProps) {
    const { t } = useTranslations();
    const [selectedEmployeeId, setSelectedEmployeeId] = useState<number | null>(null);

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/users',
        perPage: 25,
        defaultSort: 'name',
        canCreate: false,
        canEdit: false,
        canDelete: false,
        selectable: false,
        selectFields: ['id', 'name', 'title', 'enabled'],
        fields: [
            { key: 'name', label: t('pages.employees.column_name'), type: 'text', sortable: true, editable: false, masterLabel: true },
            { key: 'title', label: t('pages.employees.column_title'), type: 'text', sortable: true, editable: false },
            { key: 'enabled', label: t('pages.employees.column_enabled'), type: 'boolean', sortable: true, editable: false },
        ],
        rowActions: [
            {
                key: 'view_details',
                label: t('pages.employees.action_open'),
                icon: <Users className="h-4 w-4" />,
                variant: 'outline',
                refreshOnComplete: false,
                onClick: (item: Record<string, any>) => {
                    const employeeId = typeof item.id === 'number' ? item.id : Number(item.id);

                    if (!Number.isNaN(employeeId)) {
                        setSelectedEmployeeId(employeeId);
                    }
                },
            },
        ],
    }), [t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.employees.title')}
                    description={t('pages.employees.description')}
                    icon={<Users className="h-6 w-6 text-primary" />}
                    route={route}
                />
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
            <EmployeeDialog
                employeeId={selectedEmployeeId}
                onClose={() => setSelectedEmployeeId(null)}
                t={t}
            />
        </AppLayout>
    );
}
