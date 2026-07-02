import { useMemo } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';
import { buildControlActionsCrudConfig } from '@/Pages/app/controlActionsCrudConfig';

interface ControlActionsPageProps {
    route: AppSectionRoute;
}

export default function ControlActionsPage({ route }: ControlActionsPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = useMemo(() => buildControlActionsCrudConfig(t), [t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.control_actions.title')}
                    description={t('pages.control_actions.description')}
                    icon={<MaterialSymbol name="checklist" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
