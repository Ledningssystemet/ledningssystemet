import { useEffect, useMemo, useRef, useState } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import { Link, useNavigate, useParams } from 'react-router-dom';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import BpmnProcessEditor, { type BpmnProcessEditorHandle } from '@/Components/crud/BpmnProcessEditor';
import { APP_HOME_PATH, APP_PROCESSES_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import { useDirtyStateNavigation } from '@/hooks/useDirtyStateNavigation';
import type { AppSectionRoute } from '@/app/routes';

interface ProcessEditorPageProps {
    route: AppSectionRoute;
}

interface ProcessEditorRecord {
    id: number;
    bpmn: string | null;
}

interface NamedOption {
    id: number | string;
    name: string;
}

type CrudRowsPayload = {
    data?: Array<Record<string, unknown>>;
};

function toNamedOptions(payload: unknown): NamedOption[] {
    const rows = Array.isArray(payload)
        ? payload
        : Array.isArray((payload as CrudRowsPayload)?.data)
            ? (payload as CrudRowsPayload).data ?? []
            : [];

    return rows
        .map((row) => {
            const name = typeof row?.name === 'string' ? row.name.trim() : '';
            const id = typeof row?.id === 'number' || typeof row?.id === 'string' ? row.id : null;

            if (id === null || name === '') {
                return null;
            }

            return { id, name };
        })
        .filter((option): option is NamedOption => option !== null);
}

export default function ProcessEditorPage({ route }: ProcessEditorPageProps) {
    const { t } = useTranslations();
    const navigate = useNavigate();
    const { processId } = useParams<{ processId: string }>();

    const parsedProcessId = useMemo(() => Number(processId), [processId]);
    const [loading, setLoading] = useState(true);
    const [saveMode, setSaveMode] = useState<'save' | 'publish' | null>(null);
    const [pageErrorKey, setPageErrorKey] = useState<string | null>(null);
    const [publishValidationErrorKeys, setPublishValidationErrorKeys] = useState<string[]>([]);
    const [process, setProcess] = useState<ProcessEditorRecord | null>(null);
    const [informationTypeOptions, setInformationTypeOptions] = useState<NamedOption[]>([]);
    const [assetOptions, setAssetOptions] = useState<NamedOption[]>([]);
    const [isDirty, setIsDirty] = useState(false);
    const editorRef = useRef<BpmnProcessEditorHandle | null>(null);

    // Prevent navigation with unsaved changes
    useDirtyStateNavigation(isDirty && saveMode === null, t('pages.process_editor.unsaved_changes_confirm'));

    // Safe navigate that checks dirty state
    const safeNavigate = (path: string) => {
        if (isDirty && saveMode === null) {
            if (!window.confirm(t('pages.process_editor.unsaved_changes_confirm'))) {
                return;
            }
        }
        navigate(path);
    };


    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.process_editor.title') });
        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    useEffect(() => {
        if (!Number.isFinite(parsedProcessId)) {
            setPageErrorKey('pages.process_editor.invalid_process_id');
            setIsDirty(false);
            setLoading(false);
            return;
        }

        const controller = new AbortController();

        const load = async () => {
            setLoading(true);
            setPageErrorKey(null);
            setPublishValidationErrorKeys([]);
            setIsDirty(false);

            try {
                const query = new URLSearchParams({
                    $select: 'id,bpmn',
                });
                const [processResponse, informationTypesResponse, assetsResponse] = await Promise.all([
                    fetch(`/api/crud/processes/${parsedProcessId}?${query.toString()}`, {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    }),
                    fetch('/api/crud/information_types?paginate=0&%24select=id,name&sort=name', {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    }),
                    fetch('/api/crud/assets?paginate=0&%24select=id,name&sort=name', {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    }),
                ]);

                if (!processResponse.ok) {
                    setPageErrorKey('pages.process_editor.load_error');
                    setProcess(null);
                    setInformationTypeOptions([]);
                    setAssetOptions([]);
                    return;
                }

                const payload = await processResponse.json();
                setProcess(payload as ProcessEditorRecord);

                const informationTypesPayload = informationTypesResponse.ok ? await informationTypesResponse.json() : [];
                const assetsPayload = assetsResponse.ok ? await assetsResponse.json() : [];

                setInformationTypeOptions(toNamedOptions(informationTypesPayload));
                setAssetOptions(toNamedOptions(assetsPayload));
            } catch (loadError: unknown) {
                if ((loadError as { name?: string })?.name !== 'AbortError') {
                    setPageErrorKey('pages.process_editor.load_error');
                    setInformationTypeOptions([]);
                    setAssetOptions([]);
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

        if (publish && isDirty) {
            setPublishValidationErrorKeys(['pages.process_editor.validation.save_before_publish']);
            return;
        }

        setSaveMode(publish ? 'publish' : 'save');
        setPageErrorKey(null);
        setPublishValidationErrorKeys([]);

        try {
            const currentXml = await editorRef.current?.exportXml();
            if (!currentXml) {
                setPageErrorKey('pages.process_editor.bpmn_export_error');
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
                if (response.status === 422) {
                    const validationPayload = (await response.json()) as {
                        errors?: { bpmn?: string[]; publishedbpmn?: string[] };
                    };

                    const publishValidationErrors = validationPayload.errors?.publishedbpmn ?? [];
                    const bpmnValidationErrors = validationPayload.errors?.bpmn ?? [];
                    const allValidationErrors = [...new Set([...publishValidationErrors, ...bpmnValidationErrors])];

                    if (publish && allValidationErrors.length > 0) {
                        setPublishValidationErrorKeys(allValidationErrors);
                        return;
                    }

                    const firstError = allValidationErrors[0];
                    if (firstError) {
                        setPageErrorKey(firstError);
                        return;
                    }
                }

                setPageErrorKey(publish ? 'pages.process_editor.publish_error' : 'pages.process_editor.save_error');
                return;
            }

            setProcess((prev) => (prev ? { ...prev, bpmn: currentXml } : prev));
            editorRef.current?.markCurrentReferencesAsSaved();
            editorRef.current?.markCurrentStateAsSaved();
            setIsDirty(false);

            if (publish) {
                safeNavigate(APP_PROCESSES_PATH);
            }
        } catch {
            setPageErrorKey(publish ? 'pages.process_editor.publish_error' : 'pages.process_editor.save_error');
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

                {!loading && pageErrorKey && !process && (
                    <section className="rounded-2xl border border-border bg-card p-6 text-sm text-destructive shadow-sm">
                        {t(pageErrorKey)}
                    </section>
                )}

                {!loading && process && (
                    <section className="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm">
                        {pageErrorKey && (
                            <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                                {t(pageErrorKey)}
                            </div>
                        )}

                        <div className="space-y-2">
                            <BpmnProcessEditor
                                ref={editorRef}
                                xml={process.bpmn}
                                invalidMessage={t('pages.dashboard.process.invalid_bpmn')}
                                fitButtonLabel={t('pages.dashboard.process.fit_to_screen')}
                                editorLabels={{
                                    activateHandTool: t('pages.process_editor.editor.activate_hand_tool'),
                                    activateLassoTool: t('pages.process_editor.editor.activate_lasso_tool'),
                                    activateSpaceTool: t('pages.process_editor.editor.activate_space_tool'),
                                    activateGlobalConnectTool: t('pages.process_editor.editor.activate_global_connect_tool'),
                                    createStartEvent: t('pages.process_editor.editor.create_start_event'),
                                    createEndEvent: t('pages.process_editor.editor.create_end_event'),
                                    createExclusiveGateway: t('pages.process_editor.editor.create_exclusive_gateway'),
                                    createTask: t('pages.process_editor.editor.create_task'),
                                    createDataObjectReference: t('pages.process_editor.editor.create_data_object_reference'),
                                    createDataStoreReference: t('pages.process_editor.editor.create_data_store_reference'),
                                    createSubProcess: t('pages.process_editor.editor.create_sub_process'),
                                    appendTask: t('pages.process_editor.editor.append_task'),
                                    appendEndEvent: t('pages.process_editor.editor.append_end_event'),
                                    appendExclusiveGateway: t('pages.process_editor.editor.append_exclusive_gateway'),
                                    appendDataObjectReference: t('pages.process_editor.editor.append_data_object_reference'),
                                    appendDataStoreReference: t('pages.process_editor.editor.append_data_store_reference'),
                                    appendSubProcess: t('pages.process_editor.editor.append_sub_process'),
                                    connect: t('pages.process_editor.editor.connect'),
                                    delete: t('pages.process_editor.editor.delete'),
                                }}
                                propertyEditorLabels={{
                                    panelTitle: t('pages.process_editor.property_editor.panel_title'),
                                    appearanceGroup: t('pages.process_editor.property_editor.appearance_group'),
                                    sizeGroup: t('pages.process_editor.property_editor.size_group'),
                                    textGroup: t('pages.process_editor.property_editor.text_group'),
                                    width: t('pages.process_editor.property_editor.width'),
                                    height: t('pages.process_editor.property_editor.height'),
                                    fontSize: t('pages.process_editor.property_editor.font_size'),
                                    textColor: t('pages.process_editor.property_editor.text_color'),
                                    taskBackgroundImage: t('pages.process_editor.property_editor.task_background_image'),
                                    taskBackgroundImageFit: t('pages.process_editor.property_editor.task_background_image_fit'),
                                    taskBackgroundImageFitCrop: t('pages.process_editor.property_editor.task_background_image_fit_crop'),
                                    taskBackgroundImageFitContain: t('pages.process_editor.property_editor.task_background_image_fit_contain'),
                                    taskBackgroundImageFitStretch: t('pages.process_editor.property_editor.task_background_image_fit_stretch'),
                                    taskBackgroundImagePadding: t('pages.process_editor.property_editor.task_background_image_padding'),
                                    clearTaskBackgroundImage: t('pages.process_editor.property_editor.clear_task_background_image'),
                                    name: t('pages.process_editor.property_editor.name'),
                                    fillColor: t('pages.process_editor.property_editor.fill_color'),
                                    strokeColor: t('pages.process_editor.property_editor.stroke_color'),
                                    invalidHexColor: t('pages.process_editor.property_editor.invalid_hex_color'),
                                    invalidNumber: t('pages.process_editor.property_editor.invalid_number'),
                                    invalidTextValue: t('pages.process_editor.property_editor.invalid_text_value'),
                                     lockedReferenceNameMessage: t('pages.process_editor.property_editor.locked_reference_name_message'),
                                }}
                                informationTypeOptions={informationTypeOptions}
                                assetOptions={assetOptions}
                                creationDialogLabels={{
                                    informationTypeTitle: t('pages.process_editor.creation_dialog.information_type_title'),
                                    assetTitle: t('pages.process_editor.creation_dialog.asset_title'),
                                    selectExistingLabel: t('pages.process_editor.creation_dialog.select_existing_label'),
                                    selectExistingPlaceholder: t('pages.process_editor.creation_dialog.select_existing_placeholder'),
                                    customNameLabel: t('pages.process_editor.creation_dialog.custom_name_label'),
                                    customNamePlaceholder: t('pages.process_editor.creation_dialog.custom_name_placeholder'),
                                    applyName: t('pages.process_editor.creation_dialog.apply_name'),
                                    cancel: t('pages.process_editor.creation_dialog.cancel'),
                                    nameRequired: t('pages.process_editor.creation_dialog.name_required'),
                                    invalidName: t('pages.process_editor.creation_dialog.invalid_name'),
                                }}
                                onDirtyStateChange={setIsDirty}
                            />
                        </div>

                        <div className="flex items-center justify-between gap-3">
                            <Button variant="outline" onClick={() => safeNavigate(APP_PROCESSES_PATH)}>
                                <MaterialSymbol name="arrow_back" className="mr-2 h-4 w-4" />
                                {t('pages.process_editor.back_to_processes')}
                            </Button>
                            <div className="flex items-center gap-2">
                                <Button onClick={() => void persistProcess(false)} disabled={saveMode !== null}>
                                    <MaterialSymbol name="save" className="mr-2 h-4 w-4" />
                                    {saveMode === 'save' ? t('pages.process_editor.saving') : t('pages.process_editor.save')}
                                </Button>
                                <Button
                                    onClick={() => void persistProcess(true)}
                                    disabled={saveMode !== null || isDirty}
                                    title={isDirty ? t('pages.process_editor.publish_requires_saved_changes') : undefined}
                                >
                                    <MaterialSymbol name="upload" className="mr-2 h-4 w-4" />
                                    {saveMode === 'publish' ? t('pages.process_editor.publishing') : t('pages.process_editor.publish')}
                                </Button>
                            </div>
                        </div>

                        {isDirty && (
                            <p className="text-sm text-muted-foreground">
                                {t('pages.process_editor.unsaved_changes_notice')}
                            </p>
                        )}
                    </section>
                )}

                <Dialog
                    open={publishValidationErrorKeys.length > 0}
                    onOpenChange={(open) => {
                        if (!open) {
                            setPublishValidationErrorKeys([]);
                        }
                    }}
                >
                    <DialogContent className="max-w-2xl" resizable={false}>
                        <DialogHeader>
                            <DialogTitle>{t('pages.process_editor.publish_validation_dialog_title')}</DialogTitle>
                            <DialogDescription>{t('pages.process_editor.publish_validation_dialog_description')}</DialogDescription>
                        </DialogHeader>

                        <ul className="space-y-2 text-sm text-foreground">
                            {publishValidationErrorKeys.map((errorKey) => (
                                <li key={errorKey} className="rounded-md border border-destructive/20 bg-destructive/5 px-3 py-2 text-destructive">
                                    {t(errorKey)}
                                </li>
                            ))}
                        </ul>

                        <DialogFooter>
                            <Button type="button" onClick={() => setPublishValidationErrorKeys([])}>
                                {t('pages.process_editor.publish_validation_dialog_close')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}

