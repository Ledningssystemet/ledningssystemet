import { useEffect, useState, useMemo, useRef } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { Link, useNavigate, useParams } from 'react-router-dom';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Select2Field } from '@/components/crud/Select2Field';
import { DocumentEditor, type EditorContent } from '@/components/crud/DocumentEditor';
import { APP_HOME_PATH, APP_DOCUMENTS_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface DocumentEditorPageProps {
    route: AppSectionRoute;
}

interface DocumentVersion {
    id: number;
    library_document_id: number;
    major_version: number;
    minor_version: number;
    contents: string | null;
    approver_id: number | null;
    approved_at: string | null;
    finished_at: string | null;
    created_at: string;
    updated_at: string;
}

interface LibraryDocument {
    id: number;
    name: string;
}

export default function DocumentEditorPage({ route }: DocumentEditorPageProps) {
    const { t } = useTranslations();
    const navigate = useNavigate();
    const { libraryDocumentId } = useParams<{ libraryDocumentId: string }>();

    const parsedDocId = useMemo(() => Number(libraryDocumentId), [libraryDocumentId]);

    const editorRef = useRef<any>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);
    const [errorKey, setErrorKey] = useState<string | null>(null);
    const [libraryDocument, setLibraryDocument] = useState<LibraryDocument | null>(null);
    const [version, setVersion] = useState<DocumentVersion | null>(null);
    const [content, setContent] = useState<EditorContent>({ blocks: [] });
    const [approverId, setApproverId] = useState<number | null>(null);
    const [approverOptions, setApproverOptions] = useState<Array<{ id: number; name: string }>>([]);

    const canEdit = version && !version.finished_at && version.id !== undefined;
    const canApprove = version && !version.approved_at && version.finished_at && true; // Check user is approver
    const canFinish = version && !version.finished_at && canEdit;

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.document_editor.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    // Load approvers
    useEffect(() => {
        const loadApprovers = async () => {
            try {
                const query = new URLSearchParams({ paginate: '0', $select: 'id,name', sort: 'name' });
                const response = await fetch(`/api/crud/users?${query.toString()}`, {
                    headers: { Accept: 'application/json' },
                });

                if (response.ok) {
                    const data = await response.json();
                    setApproverOptions(Array.isArray(data) ? data : []);
                }
            } catch (err) {
                console.error('Failed to load approvers:', err);
            }
        };

        void loadApprovers();
    }, []);

    // Load document and version
    useEffect(() => {
        if (!Number.isFinite(parsedDocId)) {
            setErrorKey('pages.document_editor.invalid_document_id');
            setLoading(false);
            return;
        }

        const controller = new AbortController();

        const load = async () => {
            setLoading(true);
            setErrorKey(null);

            try {
                // Load document info
                const docQuery = new URLSearchParams({ $select: 'id,name' });
                const docResponse = await fetch(`/api/crud/library_documents/${parsedDocId}?${docQuery.toString()}`, {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                });

                if (!docResponse.ok) {
                    setErrorKey('pages.document_editor.load_error');
                    return;
                }

                const docData = await docResponse.json();
                setLibraryDocument(docData);

                // Load latest version
                const versionQuery = new URLSearchParams({
                    paginate: '1',
                    per_page: '1',
                    sort: '-id',
                    $select: 'id,library_document_id,major_version,minor_version,contents,approver_id,approved_at,finished_at,created_at,updated_at',
                });
                versionQuery.set('filter[library_document_id]', String(parsedDocId));
                const versionResponse = await fetch(
                    `/api/crud/document-versions?${versionQuery.toString()}`,
                    {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    }
                );

                if (versionResponse.ok) {
                    const versionData = await versionResponse.json();
                    const versions = versionData.data || [versionData];
                    const latestVersion = versions[0];

                    if (latestVersion) {
                        setVersion(latestVersion);
                        setApproverId(latestVersion.approver_id);

                        if (latestVersion.contents) {
                            try {
                                const parsed = JSON.parse(latestVersion.contents);
                                setContent(parsed);
                            } catch {
                                setContent({ blocks: [] });
                            }
                        }
                    }
                }
            } catch (err) {
                if ((err as { name?: string })?.name !== 'AbortError') {
                    setErrorKey('pages.document_editor.load_error');
                }
            } finally {
                setLoading(false);
            }
        };

        void load();

        return () => controller.abort();
    }, [parsedDocId]);

    const handleSave = async () => {
        if (!version) return;

        setSaving(true);
        try {
            // Get current content from editor
            let currentContent = content;
            if (editorRef.current?.__saveContent) {
                const saved = await editorRef.current.__saveContent();
                if (saved) {
                    currentContent = saved;
                }
            }

            const payload = {
                approver_id: approverId || null,
                contents: JSON.stringify(currentContent),
            };

            const response = await fetch(`/api/crud/document-versions/${version.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                setErrorKey('pages.document_editor.save_error');
                return;
            }

            setIsDirty(false);
        } catch (err) {
            setErrorKey('pages.document_editor.save_error');
        } finally {
            setSaving(false);
        }
    };

    const handleFinish = async () => {
        if (!version) return;

        if (!window.confirm(t('pages.document_editor.finish_confirm'))) return;

        setSaving(true);
        try {
            // Save current content first
            let currentContent = content;
            if (editorRef.current?.__saveContent) {
                const saved = await editorRef.current.__saveContent();
                if (saved) {
                    currentContent = saved;
                }
            }

            // Save version
            await fetch(`/api/crud/document-versions/${version.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    contents: JSON.stringify(currentContent),
                }),
            });

            // Then finish it
            const response = await fetch(`/api/document-versions/${version.id}/finish`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                setErrorKey('pages.document_editor.save_error');
                return;
            }

            const updatedVersion = await response.json();
            setVersion(updatedVersion);
            setIsDirty(false);
        } catch (err) {
            setErrorKey('pages.document_editor.save_error');
        } finally {
            setSaving(false);
        }
    };

    const handleApprove = async () => {
        if (!version) return;

        if (!window.confirm(t('pages.document_editor.approve_confirm'))) return;

        setSaving(true);
        try {
            const response = await fetch(`/api/document-versions/${version.id}/approve`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                setErrorKey('pages.document_editor.save_error');
                return;
            }

            navigate(APP_DOCUMENTS_PATH);
        } catch (err) {
            setErrorKey('pages.document_editor.save_error');
        } finally {
            setSaving(false);
        }
    };

    const handleReject = async () => {
        if (!version) return;

        if (!window.confirm(t('pages.document_editor.reject_confirm'))) return;

        setSaving(true);
        try {
            const response = await fetch(`/api/document-versions/${version.id}/reject`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                setErrorKey('pages.document_editor.save_error');
                return;
            }

            navigate(APP_DOCUMENTS_PATH);
        } catch (err) {
            setErrorKey('pages.document_editor.save_error');
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <AppLayout>
                <div className="flex items-center justify-center py-12">
                    <MaterialSymbol name="progress_activity" className="h-8 w-8 animate-spin text-primary" />
                </div>
            </AppLayout>
        );
    }

    if (errorKey || !libraryDocument || !version) {
        return (
            <AppLayout>
                <div className="space-y-4">
                    <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                        <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                            {t('ui.app.breadcrumb_home')}
                        </Link>
                        <span>/</span>
                        <Link to={APP_DOCUMENTS_PATH} className="transition-colors hover:text-foreground">
                            {t('pages.documents.title')}
                        </Link>
                    </nav>

                    <div className="flex items-center gap-3 rounded-md border border-destructive/50 bg-destructive/10 p-4">
                        <MaterialSymbol name="error" className="h-5 w-5 text-destructive" />
                        <p className="text-sm text-destructive">{t(errorKey || 'pages.document_editor.load_error')}</p>
                    </div>

                    <Link to={APP_DOCUMENTS_PATH}>
                        <Button variant="outline">
                            <MaterialSymbol name="arrow_back" className="h-4 w-4 mr-2" />
                            {t('pages.document_editor.back')}
                        </Button>
                    </Link>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <Link to={APP_DOCUMENTS_PATH} className="transition-colors hover:text-foreground">
                        {t('pages.documents.title')}
                    </Link>
                    <span>/</span>
                    <span>{libraryDocument.name}</span>
                </nav>

                {/* Header */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                                <MaterialSymbol name="description" className="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    {t('pages.document_editor.title')} - {libraryDocument.name} v
                                    {version.major_version}.{version.minor_version}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('pages.document_editor.description')}
                                </p>
                            </div>
                        </div>

                        <Link to={APP_DOCUMENTS_PATH}>
                            <Button variant="outline">
                                <MaterialSymbol name="arrow_back" className="h-4 w-4 mr-2" />
                                {t('pages.document_editor.back')}
                            </Button>
                        </Link>
                    </div>
                </section>

                {/* Error message */}
                {errorKey && (
                    <div className="flex items-center gap-3 rounded-md border border-destructive/50 bg-destructive/10 p-4">
                        <MaterialSymbol name="error" className="h-5 w-5 text-destructive" />
                        <p className="text-sm text-destructive">{t(errorKey)}</p>
                    </div>
                )}

                {/* Approver selection */}
                {canEdit && (
                    <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                        <div className="grid gap-4">
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    {t('pages.document_editor.approver')}
                                </label>
                            </div>
                            <Select2Field
                                options={approverOptions.map((user) => ({
                                    value: user.id,
                                    label: user.name,
                                }))}
                                value={approverId}
                                onChange={(value) => {
                                    setApproverId(typeof value === 'number' ? value : null);
                                    setIsDirty(true);
                                }}
                                placeholder={t('pages.document_editor.select_approver')}
                            />
                        </div>
                    </section>
                )}

                {/* Editor content */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div>
                        <DocumentEditor
                            ref={editorRef}
                            initialContent={JSON.stringify(content)}
                            readOnly={!canEdit}
                            onChange={(newContent) => {
                                setContent(newContent);
                                setIsDirty(true);
                            }}
                        />
                    </div>
                </section>

                {/* Actions */}
                <section className="flex flex-wrap gap-3">
                    {canEdit && (
                        <>
                            <Button
                                onClick={handleSave}
                                disabled={!isDirty || saving}
                                className="gap-2"
                            >
                                {saving && <MaterialSymbol name="progress_activity" className="h-4 w-4 animate-spin" />}
                                <MaterialSymbol name="save" className="h-4 w-4" />
                                {t('pages.document_editor.save')}
                            </Button>

                            <Button
                                onClick={handleFinish}
                                disabled={saving}
                                variant="destructive"
                                className="gap-2"
                            >
                                {saving && <MaterialSymbol name="progress_activity" className="h-4 w-4 animate-spin" />}
                                {t('pages.document_editor.finish')}
                            </Button>
                        </>
                    )}

                    {canApprove && (
                        <>
                            <Button
                                onClick={handleApprove}
                                disabled={saving}
                                className="gap-2 bg-green-600 hover:bg-green-700"
                            >
                                {saving && <MaterialSymbol name="progress_activity" className="h-4 w-4 animate-spin" />}
                                {t('pages.document_editor.approve')}
                            </Button>

                            <Button
                                onClick={handleReject}
                                disabled={saving}
                                variant="destructive"
                                className="gap-2"
                            >
                                {saving && <MaterialSymbol name="progress_activity" className="h-4 w-4 animate-spin" />}
                                {t('pages.document_editor.reject')}
                            </Button>
                        </>
                    )}

                    <Button
                        onClick={() => {
                            // TODO: Implement PDF preview
                        }}
                        variant="outline"
                        className="gap-2"
                    >
                        <MaterialSymbol name="visibility" className="h-4 w-4" />
                        {t('pages.document_editor.preview_pdf')}
                    </Button>

                    {version.approved_at && (
                        <a
                            href={`/api/v1/items/DocumentVersion/${version.id}/download`}
                            className="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            <MaterialSymbol name="download" className="h-4 w-4" />
                            {t('pages.document_editor.download_pdf')}
                        </a>
                    )}
                </section>

                {/* Version info */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p className="text-muted-foreground">{t('pages.document_editor.status')}</p>
                            <p className="font-medium">
                                {version.approved_at
                                    ? t('pages.document_editor.status_published')
                                    : version.finished_at
                                      ? t('pages.document_editor.status_pending_approval')
                                      : t('pages.document_editor.status_draft')}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">{t('pages.document_editor.version')}</p>
                            <p className="font-medium">
                                {version.major_version}.{version.minor_version}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">{t('pages.document_editor.created_at')}</p>
                            <p className="font-medium">{new Date(version.created_at).toLocaleString()}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">{t('pages.document_editor.updated_at')}</p>
                            <p className="font-medium">{new Date(version.updated_at).toLocaleString()}</p>
                        </div>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

