import { useEffect, useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/hooks/useTranslations';
import { APP_HOME_PATH } from '@/app/routes';
import { Link } from 'react-router-dom';
import { FolderOpen, Search, Upload } from 'lucide-react';
import type { AppSectionRoute } from '@/app/routes';

interface MyDocumentsPageProps {
    route: AppSectionRoute;
}

export default function MyDocumentsPage({ route }: MyDocumentsPageProps) {
    const { t } = useTranslations();
    const [searchQuery, setSearchQuery] = useState('');

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.my_documents.title') });
        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                {/* Breadcrumb */}
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.my_documents.title')}</span>
                </nav>

                {/* Page header */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                                <FolderOpen className="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    {t('pages.my_documents.title')}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {route.description ?? t('pages.my_documents.description')}
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            <Upload className="h-4 w-4" />
                            {t('pages.my_documents.upload_document')}
                        </button>
                    </div>
                </section>

                {/* Search */}
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder={t('pages.my_documents.search_placeholder')}
                        className="w-full rounded-xl border border-border bg-card py-2.5 pl-10 pr-4 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40"
                    />
                </div>

                {/* Document list */}
                <section className="rounded-2xl border border-border bg-card shadow-sm">
                    <div className="border-b border-border px-6 py-4">
                        <h2 className="text-sm font-semibold text-foreground">
                            {t('pages.my_documents.recent_documents')}
                        </h2>
                    </div>
                    <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                        <div className="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                            <FolderOpen className="h-7 w-7 text-muted-foreground" />
                        </div>
                        <p className="text-sm text-muted-foreground">{t('pages.my_documents.no_documents')}</p>
                        <button
                            type="button"
                            className="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            <Upload className="h-4 w-4" />
                            {t('pages.my_documents.upload_document')}
                        </button>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

