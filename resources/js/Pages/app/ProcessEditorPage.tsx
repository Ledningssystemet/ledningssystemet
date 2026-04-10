import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Save, Upload } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import BpmnProcessEditor, { type BpmnProcessEditorHandle } from '@/components/crud/BpmnProcessEditor';
import { APP_HOME_PATH, APP_PROCESSES_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ProcessEditorPageProps {
    route: AppSectionRoute;
}

interface ProcessEditorRecord {
    id: number;
    bpmn: string | null;
}

export default function ProcessEditorPage({ route }: ProcessEditorPageProps) {
    const { t } = useTranslations();
    const navigate = useNavigate();
    const { processId } = useParams<{ processId: string }>();

    const parsedProcessId = useMemo(() => Number(processId), [processId]);
    const [loading, setLoading] = useState(true);
    const [saveMode, setSaveMode] = useState<'save' | 'publish' | null>(null);
    const [errorKey, setErrorKey] = useState<string | null>(null);
    const [process, setProcess] = useState<ProcessEditorRecord | null>(null);
    const editorRef = useRef<BpmnProcessEditorHandle | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.process_editor.title') });
        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    useEffect(() => {
        if (!Number.isFinite(parsedProcessId)) {
            setErrorKey('pages.process_editor.invalid_process_id');
            setLoading(false);
            return;
        }

        const controller = new AbortController();

        const load = async () => {
            setLoading(true);
            setErrorKey(null);

            try {
                const query = new URLSearchParams({
                    $select: 'id,bpmn',
                });
                const response = await fetch(`/api/crud/processes/${parsedProcessId}?${query.toString()}`, {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    setErrorKey('pages.process_editor.load_error');
                    setProcess(null);
                    return;
                }

                const payload = await response.json();
                setProcess(payload as ProcessEditorRecord);
            } catch (loadError: unknown) {
                if ((loadError as { name?: string })?.name !== 'AbortError') {
                    setErrorKey('pages.process_editor.load_error');
                }
            } finally {
                setLoading(false);
            }
        };

        void load();

        return () => {
            controller.abort();
        };
    }, [parsedProcessId]);

    const persistProcess = async (publish: boolean) => {
        if (!process) {
            return;
        }

        setSaveMode(publish ? 'publish' : 'save');
        setErrorKey(null);

        try {
            const currentXml = await editorRef.current?.exportXml();
            if (!currentXml) {
                setErrorKey('pages.process_editor.bpmn_export_error');
                return;
            }

            const payload: { bpmn: string } = { bpmn: currentXml };

            const response = await fetch(
                publish ? `/api/processes/${process.id}/publish` : `/api/crud/processes/${process.id}`,
                {
                method: publish ? 'POST' : 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                if (publish && response.status === 422) {
                    const validationPayload = (await response.json()) as {
                        errors?: { publishedbpmn?: string[] };
                    };
                    const firstError = validationPayload.errors?.publishedbpmn?.[0];
                    if (firstError) {
                        setErrorKey(firstError);
                        return;
                    }
                }

                setErrorKey(publish ? 'pages.process_editor.publish_error' : 'pages.process_editor.save_error');
                return;
            }

            setProcess((prev) => (prev ? { ...prev, bpmn: currentXml } : prev));

            navigate(APP_PROCESSES_PATH);
        } catch {
            setErrorKey(publish ? 'pages.process_editor.publish_error' : 'pages.process_editor.save_error');
        } finally {
            setSaveMode(null);
        }
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <Link to={APP_PROCESSES_PATH} className="transition-colors hover:text-foreground">
                        {t('pages.processes.title')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.process_editor.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">{t('pages.process_editor.title')}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {route.description ?? t('pages.process_editor.description')}
                    </p>
                </section>

                {loading && (
                    <section className="rounded-2xl border border-border bg-card p-6 text-sm text-muted-foreground shadow-sm">
                        {t('pages.process_editor.loading')}
                    </section>
                )}

                {!loading && errorKey && (
                    <section className="rounded-2xl border border-border bg-card p-6 text-sm text-destructive shadow-sm">
                        {t(errorKey)}
                    </section>
                )}

                {!loading && !errorKey && process && (
                    <section className="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm">
                        <div className="space-y-2">
                            <div className="text-sm font-medium text-foreground">
                                {t('pages.process_editor.bpmn_label')}
                            </div>
                            <BpmnProcessEditor
                                ref={editorRef}
                                xml={process.bpmn}
                                invalidMessage={t('pages.dashboard.process.invalid_bpmn')}
                                fitButtonLabel={t('pages.dashboard.process.fit_to_screen')}
                            />
                        </div>

                        <div className="flex items-center justify-between gap-3">
                            <Button variant="outline" onClick={() => navigate(APP_PROCESSES_PATH)}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                {t('pages.process_editor.back_to_processes')}
                            </Button>
                            <div className="flex items-center gap-2">
                                <Button onClick={() => void persistProcess(false)} disabled={saveMode !== null}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {saveMode === 'save' ? t('pages.process_editor.saving') : t('pages.process_editor.save')}
                                </Button>
                                <Button onClick={() => void persistProcess(true)} disabled={saveMode !== null}>
                                    <Upload className="mr-2 h-4 w-4" />
                                    {saveMode === 'publish' ? t('pages.process_editor.publishing') : t('pages.process_editor.publish')}
                                </Button>
                            </div>
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

