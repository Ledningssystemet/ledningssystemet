import { useEffect, useMemo, useState } from 'react';
import { SlidersHorizontal } from 'lucide-react';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout';
import { type AppSectionRoute } from '@/app/routes';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';

interface CustomPropertyContext {
    context: string;
    label: string;
    resource: string;
}

interface CustomPropertyContextResponse {
    data: CustomPropertyContext[];
}
interface CustomPropertiesPageProps {
    route: AppSectionRoute;
}
export default function CustomPropertiesPage({ route }: CustomPropertiesPageProps) {
    const { t, locale } = useTranslations();
    const [contexts, setContexts] = useState<CustomPropertyContext[]>([]);
    const [activeContext, setActiveContext] = useState<string>('');
    const [loadingContexts, setLoadingContexts] = useState(true);
    const [contextError, setContextError] = useState<string | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.custom_properties.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [locale]);

    useEffect(() => {
        let mounted = true;

        const loadContexts = async () => {
            setLoadingContexts(true);
            setContextError(null);

            try {
                const response = await axios.get<CustomPropertyContextResponse>('/api/custom-properties/contexts');
                if (!mounted) {
                    return;
                }

                const nextContexts = Array.isArray(response.data?.data) ? response.data.data : [];
                setContexts(nextContexts);
                setActiveContext((current) => {
                    if (current !== '' && nextContexts.some((entry) => entry.context === current)) {
                        return current;
                    }

                    return nextContexts[0]?.context ?? '';
                });
            } catch {
                if (!mounted) {
                    return;
                }

                setContextError(t('pages.custom_properties.load_contexts_failed'));
                setContexts([]);
                setActiveContext('');
            } finally {
                if (mounted) {
                    setLoadingContexts(false);
                }
            }
        };

        void loadContexts();

        return () => {
            mounted = false;
        };
    }, [locale]);

    const activeContextEntry = useMemo(
        () => contexts.find((entry) => entry.context === activeContext) ?? null,
        [contexts, activeContext]
    );

    const config: CrudModuleConfig | null = useMemo(() => {
        if (activeContext === '') {
            return null;
        }

        const encodedContext = encodeURIComponent(activeContext);

        return {
            apiUrl: `/api/custom-properties/contexts/${encodedContext}`,
            perPage: 25,
            defaultSort: 'ordinal',
            selectFields: ['id', 'name', 'description', 'type', 'options', 'ordinal', 'display_on_card', 'user_editable', 'required'],
            createTitle: t('pages.custom_properties.create_title'),
            editTitle: t('pages.custom_properties.edit_title'),
            fields: [
                {
                    key: 'name',
                    label: t('pages.custom_properties.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                },
                {
                    key: 'type',
                    label: t('pages.custom_properties.column_type'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    editableOnUpdate: false,
                    required: true,
                    options: [
                        { value: 'string', label: t('pages.custom_properties.types.string') },
                        { value: 'textarea', label: t('pages.custom_properties.types.textarea') },
                        { value: 'boolean', label: t('pages.custom_properties.types.boolean') },
                        { value: 'user', label: t('pages.custom_properties.types.user') },
                        { value: 'department', label: t('pages.custom_properties.types.department') },
                        { value: 'supplier', label: t('pages.custom_properties.types.supplier') },
                        { value: 'customer', label: t('pages.custom_properties.types.customer') },
                        { value: 'asset', label: t('pages.custom_properties.types.asset') },
                        { value: 'process', label: t('pages.custom_properties.types.process') },
                    ],
                },
                {
                    key: 'description',
                    label: t('pages.custom_properties.column_description'),
                    type: 'textarea',
                    editable: true,
                    required: true,
                    masterDescription: true,
                },
                {
                    key: 'ordinal',
                    label: t('pages.custom_properties.column_ordinal'),
                    type: 'number',
                    sortable: true,
                    editable: true,
                    required: true,
                },
                {
                    key: 'display_on_card',
                    label: t('pages.custom_properties.column_display_on_card'),
                    type: 'boolean',
                    sortable: true,
                    editable: true,
                    required: true,
                },
                {
                    key: 'user_editable',
                    label: t('pages.custom_properties.column_user_editable'),
                    type: 'boolean',
                    sortable: true,
                    editable: true,
                    required: true,
                },
                {
                    key: 'required',
                    label: t('pages.custom_properties.column_required'),
                    type: 'boolean',
                    sortable: true,
                    editable: true,
                    required: true,
                },
                {
                    key: 'options',
                    label: t('pages.custom_properties.column_options'),
                    type: 'textarea',
                    editable: true,
                    hiddenInTable: true,
                },
            ],
        };
    }, [activeContext, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.custom_properties.title')}
                    description={t('pages.custom_properties.description')}
                    icon={<SlidersHorizontal className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="space-y-4">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold text-foreground">{t('pages.custom_properties.context_title')}</h2>
                            {activeContextEntry ? (
                                <span className="text-xs text-muted-foreground">
                                    {t('pages.custom_properties.context_value', { context: activeContextEntry.context })}
                                </span>
                            ) : null}
                        </div>

                        {loadingContexts ? <p className="text-sm text-muted-foreground">{t('pages.custom_properties.loading_contexts')}</p> : null}
                        {!loadingContexts && contextError ? <p className="text-sm text-destructive">{contextError}</p> : null}

                        {!loadingContexts && !contextError && contexts.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {contexts.map((entry) => {
                                    const isActive = entry.context === activeContext;

                                    return (
                                        <Button
                                            key={entry.context}
                                            variant={isActive ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => setActiveContext(entry.context)}
                                        >
                                            {entry.label}
                                        </Button>
                                    );
                                })}
                            </div>
                        ) : null}

                        {!loadingContexts && !contextError && contexts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('pages.custom_properties.no_contexts')}</p>
                        ) : null}
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    {config ? (
                        <CrudModule config={config} />
                    ) : (
                        <p className="text-sm text-muted-foreground">{t('pages.custom_properties.select_context_hint')}</p>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
