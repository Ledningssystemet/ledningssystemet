import { useEffect, useMemo, useState } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ApiTokensPageProps {
    route: AppSectionRoute;
}

export default function ApiTokensPage({ route }: ApiTokensPageProps) {
    const { t } = useTranslations();
    const [tokenDialogOpen, setTokenDialogOpen] = useState(false);
    const [plainToken, setPlainToken] = useState<string | null>(null);
    const [copyStatus, setCopyStatus] = useState<'idle' | 'success' | 'error'>('idle');

    const apiBaseUrl = useMemo(() => {
        if (typeof window === 'undefined') {
            return '/api';
        }

        return `${window.location.origin}/api`;
    }, []);

    const swaggerDocsUrl = '/api/docs';
    const embeddedSwaggerDocsUrl = '/api/docs?embedded=1';
    const openApiSpecUrl = '/openapi.json';

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/admin/api-tokens',
            perPage: 25,
            defaultSort: '-created_at',
            selectFields: ['id', 'name', 'user_id', 'created_at'],
            createTitle: t('pages.api_tokens.create_title'),
            editTitle: t('pages.api_tokens.edit_title'),
            onSaveSuccess: async (item, context) => {
                if (!context.isNew) {
                    return;
                }

                const value = typeof item.plain_text_token === 'string' ? item.plain_text_token : null;
                setPlainToken(value);
                setTokenDialogOpen(true);
            },
            fields: [
                {
                    key: 'name',
                    label: t('pages.api_tokens.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                },
                {
                    key: 'user_id',
                    label: t('pages.api_tokens.column_user'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    editableOnUpdate: false,
                    required: true,
                    optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.api_tokens.select_user_placeholder'),
                },
                {
                    key: 'created_at',
                    label: t('pages.api_tokens.column_created_at'),
                    type: 'date',
                    sortable: true,
                    editable: false,
                    renderCell: (value) => {
                        if (!value) return '—';
                        const date = new Date(String(value));
                        return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
                    },
                },
            ],
        }),
        [t]
    );

    useEffect(() => {
        if (tokenDialogOpen) {
            setCopyStatus('idle');
        }
    }, [tokenDialogOpen, plainToken]);

    useEffect(() => {
        if (copyStatus !== 'success') {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            setCopyStatus('idle');
        }, 2000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [copyStatus]);

    const handleCopyToken = async () => {
        if (!plainToken) {
            setCopyStatus('error');
            return;
        }

        try {
            await navigator.clipboard.writeText(plainToken);
            setCopyStatus('success');
        } catch {
            setCopyStatus('error');
        }
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.api_tokens.title')}
                    description={t('pages.api_tokens.description')}
                    icon={<MaterialSymbol name="vpn_key" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="space-y-4">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold text-foreground">{t('pages.api_tokens.api_docs_title')}</h2>
                            <div className="flex flex-wrap items-center gap-3 text-sm">
                                <a
                                    href={swaggerDocsUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-primary hover:underline"
                                >
                                    {t('pages.api_tokens.api_docs_link')}
                                    <MaterialSymbol name="open_in_new" className="h-4 w-4" />
                                </a>
                                <a
                                    href={openApiSpecUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-primary hover:underline"
                                >
                                    {t('pages.api_tokens.api_docs_spec_link')}
                                    <MaterialSymbol name="open_in_new" className="h-4 w-4" />
                                </a>
                            </div>
                        </div>

                        <p className="text-sm text-muted-foreground">
                            {t('pages.api_tokens.api_docs_intro', { baseUrl: apiBaseUrl })}
                        </p>

                        <div className="rounded-xl border border-border bg-muted/10 p-3 text-xs text-muted-foreground">
                            {t('pages.api_tokens.api_docs_swagger_hint')}
                        </div>

                        <iframe
                            src={embeddedSwaggerDocsUrl}
                            title={t('pages.api_tokens.api_docs_embed_title')}
                            className="h-[900px] w-full rounded-xl border border-border bg-background"
                        />
                    </div>
                </section>

                <Dialog open={tokenDialogOpen} onOpenChange={setTokenDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('pages.api_tokens.token_dialog_title')}</DialogTitle>
                            <DialogDescription>{t('pages.api_tokens.token_dialog_description')}</DialogDescription>
                        </DialogHeader>

                        <div className="rounded-md border bg-muted/30 p-3 font-mono text-xs break-all">
                            {plainToken ?? t('pages.api_tokens.token_missing')}
                        </div>

                        <DialogFooter className="sm:justify-between">
                            <div className="text-xs text-muted-foreground">
                                {copyStatus === 'success' && t('pages.api_tokens.copy_success_feedback')}
                                {copyStatus === 'error' && t('pages.api_tokens.copy_error_feedback')}
                            </div>
                            <div className="flex items-center gap-2">
                                <Button variant="outline" onClick={() => void handleCopyToken()} disabled={!plainToken}>
                                    {copyStatus === 'success' ? <MaterialSymbol name="check" className="mr-2 h-4 w-4" /> : <MaterialSymbol name="content_copy" className="mr-2 h-4 w-4" />}
                                    {copyStatus === 'success'
                                        ? t('pages.api_tokens.copy_button_copied')
                                        : t('pages.api_tokens.copy_button')}
                                </Button>
                                <Button onClick={() => setTokenDialogOpen(false)}>{t('pages.api_tokens.close_button')}</Button>
                            </div>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
