import { useEffect, useMemo, useState } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { useNavigate } from 'react-router-dom';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { buildComplianceEvaluationEvaluatePath } from '@/app/routes';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ComplianceEvaluationPageProps {
    route: AppSectionRoute;
}

interface RequirementSourceOption {
    id: number;
    reference: string;
    name: string;
}

interface GenerateChecklistDialogProps {
    evaluation: Record<string, any> | null;
    onClose: () => void;
    onGenerated: () => void;
}

function GenerateChecklistDialog({ evaluation, onClose, onGenerated }: GenerateChecklistDialogProps) {
    const { t } = useTranslations();
    const [sources, setSources] = useState<RequirementSourceOption[]>([]);
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!evaluation) return;
        setLoading(true);

        fetch('/api/crud/requirement_sources?paginate=0&%24select=id,reference,name&sort=reference')
            .then((r) => r.json())
            .then((data: RequirementSourceOption[]) => {
                setSources(data);
                const currentIds: number[] = (evaluation.requirement_sources ?? []).map((s: any) => Number(s.requirement_source_id ?? s.id));
                setSelected(new Set(currentIds));
                setLoading(false);
            });
    }, [evaluation]);

    const toggle = (id: number) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const handleGenerate = async () => {
        if (!evaluation) return;
        setSaving(true);
        await fetch(`/api/compliance-evaluations/${evaluation.id}/generate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ reqsources: Array.from(selected) }),
        });
        setSaving(false);
        onGenerated();
        onClose();
    };

    return (
        <Dialog open={Boolean(evaluation)} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('pages.compliance_evaluations.action_generate_checklist')}</DialogTitle>
                    <DialogDescription>
                        {t('pages.compliance_evaluations.generate_checklist_note')}
                    </DialogDescription>
                </DialogHeader>
                <div className="max-h-96 overflow-y-auto space-y-2 py-2">
                    {loading ? (
                        <p className="text-sm text-muted-foreground">{t('pages.compliance_evaluation_evaluate.loading')}</p>
                    ) : (
                        sources.map((src) => (
                            <label key={src.id} className="flex items-center gap-2 cursor-pointer text-sm">
                                <input
                                    type="checkbox"
                                    checked={selected.has(src.id)}
                                    onChange={() => toggle(src.id)}
                                    className="rounded"
                                />
                                {src.reference} {src.name}
                            </label>
                        ))
                    )}
                </div>
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="outline" onClick={onClose}>{t('ui.app.cancel')}</Button>
                    <Button onClick={handleGenerate} disabled={saving}>
                        {t('pages.compliance_evaluations.action_generate_checklist')}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function ProgressBar({ statistics }: { statistics?: Record<string, number> }) {
    if (!statistics || !statistics.requirements) return null;

    const { requirements, pass, na, open } = statistics;
    const passPercent = Math.round((pass / requirements) * 100);
    const naPercent = Math.round((na / requirements) * 100);
    const openPercent = Math.round((open / requirements) * 100);

    return (
        <div className="space-y-1">
            <div className="flex h-2 w-full overflow-hidden rounded-full bg-muted">
                <div className="bg-green-500 transition-all" style={{ width: `${passPercent}%` }} />
                <div className="bg-sky-400 transition-all" style={{ width: `${naPercent}%` }} />
                <div className="bg-muted-foreground/30 transition-all" style={{ width: `${openPercent}%` }} />
            </div>
            <div className="flex gap-3 text-xs text-muted-foreground">
                <span className="flex items-center gap-1">
                    <span className="inline-block h-2 w-2 rounded-full bg-green-500" />{pass}
                </span>
                <span className="flex items-center gap-1">
                    <span className="inline-block h-2 w-2 rounded-full bg-sky-400" />{na}
                </span>
                <span className="flex items-center gap-1">
                    <span className="inline-block h-2 w-2 rounded-full bg-muted-foreground/40" />{open}
                </span>
            </div>
        </div>
    );
}

export default function ComplianceEvaluationPage({ route }: ComplianceEvaluationPageProps) {
    const { t } = useTranslations();
    const navigate = useNavigate();
    const [generateTarget, setGenerateTarget] = useState<Record<string, any> | null>(null);
    const [reloadKey, setReloadKey] = useState(0);

    const apiAction = async (url: string) => {
        await fetch(url, { method: 'POST', headers: { Accept: 'application/json' } });
        setReloadKey((k) => k + 1);
    };

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/compliance_evaluations',
            perPage: 25,
            defaultSort: 'name',
            createTitle: t('pages.compliance_evaluations.create_title'),
            editTitle: t('pages.compliance_evaluations.edit_title'),
            selectFields: [
                'id', 'name', 'startdate', 'description', 'participants',
                'finished', 'archived', 'statistics', 'requirement_sources',
            ],
            getItemStatus: (item) => {
                if (item.archived) return 'info';
                if (item.finished) return null;
                const stats = item.statistics as Record<string, number> | undefined;
                if (stats && stats.requirements > 0 && stats.open === 0) return 'warning';
                return null;
            },
            rowActions: [
                {
                    key: 'open-tool',
                    label: t('pages.compliance_evaluations.action_open_tool'),
                    variant: 'outline',
                    refreshOnComplete: false,
                    isVisible: (item) => !item.finished && !item.archived,
                    onClick: (item) => {
                        navigate(buildComplianceEvaluationEvaluatePath(item.id));
                    },
                },
                {
                    key: 'generate-checklist',
                    label: t('pages.compliance_evaluations.action_generate_checklist'),
                    variant: 'outline',
                    refreshOnComplete: false,
                    isVisible: (item) => !item.finished && !item.archived,
                    onClick: (item) => {
                        setGenerateTarget(item);
                    },
                },
                {
                    key: 'finish',
                    label: t('pages.compliance_evaluations.action_finish'),
                    variant: 'outline',
                    isVisible: (item) => {
                        if (item.finished || item.archived) return false;
                        const stats = item.statistics as Record<string, number> | undefined;
                        return Boolean(stats && stats.requirements > 0 && stats.open === 0);
                    },
                    onClick: async (item) => {
                        if (!window.confirm(t('pages.compliance_evaluations.confirm_finish'))) return;
                        await apiAction(`/api/compliance-evaluations/${item.id}/finish`);
                    },
                },
                {
                    key: 'reopen',
                    label: t('pages.compliance_evaluations.action_reopen'),
                    variant: 'outline',
                    isVisible: (item) => Boolean(item.finished && !item.archived),
                    onClick: async (item) => {
                        if (!window.confirm(t('pages.compliance_evaluations.confirm_reopen'))) return;
                        await apiAction(`/api/compliance-evaluations/${item.id}/reopen`);
                    },
                },
                {
                    key: 'archive',
                    label: t('pages.compliance_evaluations.action_archive'),
                    variant: 'outline',
                    isVisible: (item) => Boolean(item.finished && !item.archived),
                    onClick: async (item) => {
                        if (!window.confirm(t('pages.compliance_evaluations.confirm_archive'))) return;
                        await apiAction(`/api/compliance-evaluations/${item.id}/archive`);
                    },
                },
                {
                    key: 'report',
                    label: t('pages.compliance_evaluations.action_download_report'),
                    variant: 'outline',
                    refreshOnComplete: false,
                    onClick: (item) => {
                        window.open(`/api/v1/ReportCentral/ComplianceEvaluation/${item.id}`, '_blank', 'noopener,noreferrer');
                    },
                },
            ],
            fields: [
                {
                    key: 'name',
                    label: t('pages.compliance_evaluations.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.compliance_evaluations.category_general'),
                },
                {
                    key: 'startdate',
                    label: t('pages.compliance_evaluations.column_startdate'),
                    type: 'date',
                    sortable: true,
                    editable: true,
                    required: true,
                    category: t('pages.compliance_evaluations.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.compliance_evaluations.column_description'),
                    type: 'textarea',
                    sortable: false,
                    editable: true,
                    masterDescription: true,
                    category: t('pages.compliance_evaluations.category_general'),
                },
                {
                    key: 'participants',
                    label: t('pages.compliance_evaluations.column_participants'),
                    type: 'textarea',
                    sortable: false,
                    editable: true,
                    hiddenInTable: true,
                    category: t('pages.compliance_evaluations.category_general'),
                },
                {
                    key: 'summary',
                    label: t('pages.compliance_evaluations.column_summary'),
                    type: 'textarea',
                    sortable: false,
                    editable: true,
                    hiddenInTable: true,
                    category: t('pages.compliance_evaluations.category_general'),
                },
                {
                    key: 'requirement_sources',
                    label: t('pages.compliance_evaluations.column_scope'),
                    type: 'text',
                    sortable: false,
                    editable: false,
                    renderCell: (_, row) => {
                        const srcs = row.requirement_sources as Array<{ reference: string; name: string }> | undefined;
                        if (!srcs || srcs.length === 0) return '-';
                        return srcs.map((s) => `${s.reference ?? ''} ${s.name ?? ''}`.trim()).join(', ');
                    },
                    category: t('pages.compliance_evaluations.category_general'),
                },
                {
                    key: 'statistics',
                    label: t('pages.compliance_evaluations.column_progress'),
                    type: 'text',
                    sortable: false,
                    editable: false,
                    renderCell: (_, row) => (
                        <ProgressBar statistics={row.statistics as Record<string, number>} />
                    ),
                    category: t('pages.compliance_evaluations.category_status'),
                },
                {
                    key: 'finished',
                    label: t('pages.compliance_evaluations.column_finished'),
                    type: 'date',
                    sortable: true,
                    editable: false,
                    hiddenInTable: true,
                    category: t('pages.compliance_evaluations.category_status'),
                },
                {
                    key: 'archived',
                    label: t('pages.compliance_evaluations.column_archived'),
                    type: 'date',
                    sortable: true,
                    editable: false,
                    hiddenInTable: true,
                    category: t('pages.compliance_evaluations.category_status'),
                },
            ],
        }),
        [t, navigate]
    );

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.compliance_evaluations.title')}
                    description={t('pages.compliance_evaluations.description')}
                    icon={<MaterialSymbol name="checklist" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule key={reloadKey} config={config} />
                </section>
            </div>

            <GenerateChecklistDialog
                evaluation={generateTarget}
                onClose={() => setGenerateTarget(null)}
                onGenerated={() => setReloadKey((k) => k + 1)}
            />
        </AppLayout>
    );
}
