import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ListTree, Sparkles } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface CompentencesPageProps {
    route: AppSectionRoute;
}

export default function CompentencesPage({ route }: CompentencesPageProps) {
    const { t } = useTranslations();
    const [activeCompetence, setActiveCompetence] = useState<Record<string, any> | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.competences.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const config: CrudModuleConfig = {
        apiUrl: '/api/crud/competences',
        perPage: 25,
        defaultSort: 'name',
        selectFields: ['id', 'name', 'description'],
        createTitle: t('pages.competences.create_title'),
        editTitle: t('pages.competences.edit_title'),
        fields: [
            {
                key: 'name',
                label: t('pages.competences.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.competences.category_general'),
            },
            {
                key: 'description',
                label: t('pages.competences.column_description'),
                type: 'textarea',
                editable: true,
                masterDescription: true,
                category: t('pages.competences.category_general'),
            },
            {
                key: 'competence_levels',
                label: t('pages.competences.column_competence_levels'),
                type: 'text',
                sortable: false,
                editable: false,
                category: t('pages.competences.category_levels'),
                renderCell: (_, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveCompetence(row)}>
                        <ListTree className="h-4 w-4" />
                        {t('pages.competences.open_levels_button')}
                    </Button>
                ),
                renderDetail: (_, row) => (
                    <Button type="button" variant="outline" size="sm" className="gap-1" onClick={() => setActiveCompetence(row)}>
                        <ListTree className="h-4 w-4" />
                        {t('pages.competences.open_levels_button')}
                    </Button>
                ),
            },
        ],
    };

    const levelsConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeCompetence?.id) {
            return null;
        }

        return {
            apiUrl: '/api/crud/competence-levels',
            perPage: 50,
            defaultSort: 'ordinal',
            fixedFilters: {
                competence_id: activeCompetence.id,
            },
            createDefaults: {
                competence_id: activeCompetence.id,
            },
            selectFields: ['id', 'competence_id', 'name', 'description', 'ordinal'],
            createTitle: t('pages.competences.levels.create_title'),
            editTitle: t('pages.competences.levels.edit_title'),
            fields: [
                {
                    key: 'name',
                    label: t('pages.competences.levels.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.competences.levels.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.competences.levels.column_description'),
                    type: 'textarea',
                    editable: true,
                    masterDescription: true,
                    category: t('pages.competences.levels.category_general'),
                },
                {
                    key: 'ordinal',
                    label: t('pages.competences.levels.column_ordinal'),
                    type: 'number',
                    sortable: true,
                    editable: false,
                    hidden: true,
                    hiddenInTable: true,
                },
                {
                    key: 'competence_id',
                    label: t('pages.competences.levels.column_competence'),
                    type: 'number',
                    editable: false,
                    hidden: true,
                },
            ],
        };
    }, [activeCompetence?.id, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.competences.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <Sparkles className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.competences.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.competences.description')}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {levelsConfig && (
                <Dialog open={Boolean(activeCompetence)} onOpenChange={(open) => !open && setActiveCompetence(null)}>
                    <DialogContent className="max-w-5xl">
                        <DialogHeader>
                            <DialogTitle>
                                {t('pages.competences.levels.panel_title', {
                                    competence: String(activeCompetence?.name || ''),
                                })}
                            </DialogTitle>
                        </DialogHeader>
                        <div className="mt-2">
                            <CrudModule key={`competence-levels-${activeCompetence?.id}`} config={levelsConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
