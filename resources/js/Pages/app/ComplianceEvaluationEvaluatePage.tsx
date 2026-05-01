import { useEffect, useState, useCallback } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { Link, useParams } from 'react-router-dom';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ComplianceEvaluationEvaluatePageProps {
    route: AppSectionRoute;
}

interface EvaluationData {
    id: number;
    name: string;
    summary: string | null;
    finished: string | null;
    archived: string | null;
}

interface RequirementSource {
    id: number;
    requirement_source_id: number;
    note: string | null;
    reference: string;
    name: string;
}

interface RequirementRow {
    id: number;
    reference: string;
    name: string;
    description: string | null;
    governance: string | null;
    note: string | null;
    evaluated: boolean;
    applicable: boolean;
}

function RequirementCard({
    req,
    onRefresh,
    t,
}: {
    req: RequirementRow;
    onRefresh: () => void;
    t: (key: string) => string;
}) {
    const [note, setNote] = useState(req.note ?? '');
    const [showFindings, setShowFindings] = useState(false);

    const patch = async (data: Record<string, unknown>) => {
        await fetch(`/api/crud/compliance_evaluation_requirements/${req.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(data),
        });
        onRefresh();
    };

    const saveNote = () => patch({ note });

    const toggleComplete = () => {
        if (req.evaluated && req.applicable) {
            patch({ evaluated: false, applicable: true, note });
        } else {
            patch({ evaluated: true, applicable: true, note });
        }
    };

    const toggleNA = () => {
        if (req.evaluated && !req.applicable) {
            patch({ evaluated: false, applicable: false, note });
        } else {
            patch({ evaluated: true, applicable: false, note });
        }
    };

    const findingsConfig: CrudModuleConfig = {
        apiUrl: '/api/crud/compliance_evaluation_requirement_findings',
        perPage: 50,
        createTitle: t('pages.compliance_evaluation_evaluate.new_finding'),
        editTitle: t('pages.compliance_evaluation_evaluate.new_finding'),
        createDefaults: { compliance_evaluation_requirement_id: req.id },
        fixedFilters: { compliance_evaluation_requirement_id: req.id },
        selectFields: ['id', 'isnc', 'department_id', 'name', 'description', 'compliance_evaluation_requirement_id'],
        fields: [
            {
                key: 'isnc',
                label: t('pages.compliance_evaluation_evaluate.column_category'),
                type: 'select',
                sortable: false,
                editable: true,
                required: true,
                options: [
                    { value: '0', label: t('pages.compliance_evaluation_evaluate.finding_category_observation') },
                    { value: '1', label: t('pages.compliance_evaluation_evaluate.finding_category_nc') },
                ],
                renderCell: (value) => value ? t('pages.compliance_evaluation_evaluate.finding_category_nc') : t('pages.compliance_evaluation_evaluate.finding_category_observation'),
            },
            {
                key: 'department_id',
                label: t('pages.compliance_evaluation_evaluate.column_department'),
                type: 'select',
                sortable: false,
                editable: true,
                optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
            },
            {
                key: 'name',
                label: t('pages.compliance_evaluation_evaluate.column_finding_name'),
                type: 'text',
                sortable: false,
                editable: true,
                required: true,
                masterLabel: true,
            },
            {
                key: 'description',
                label: t('pages.compliance_evaluation_evaluate.column_finding_description'),
                type: 'textarea',
                sortable: false,
                editable: true,
                masterDescription: true,
            },
            {
                key: 'compliance_evaluation_requirement_id',
                label: '',
                type: 'text',
                sortable: false,
                editable: false,
                hiddenInTable: true,
            },
        ],
    };

    const isComplete = req.evaluated && req.applicable;
    const isNA = req.evaluated && !req.applicable;

    return (
        <div className="rounded-lg border border-border bg-background p-4 space-y-3">
            <div className="font-medium text-sm">
                {req.reference} {req.name}
            </div>

            {req.description && (
                <div className="text-sm text-muted-foreground">
                    <span className="font-semibold block">{t('pages.compliance_evaluation_evaluate.description_label')}</span>
                    {req.description}
                </div>
            )}

            {req.governance && (
                <div className="text-sm text-muted-foreground">
                    <span className="font-semibold block">{t('pages.compliance_evaluation_evaluate.governance_label')}</span>
                    {req.governance}
                </div>
            )}

            <Textarea
                value={note}
                onChange={(e) => setNote(e.target.value)}
                placeholder={t('pages.compliance_evaluation_evaluate.note_placeholder')}
                rows={2}
                className="text-sm"
            />

            <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" onClick={saveNote}>
                    <MaterialSymbol name="save" className="h-4 w-4 mr-1" />
                    {t('pages.compliance_evaluation_evaluate.save_note')}
                </Button>
                <Button
                    size="sm"
                    variant={isComplete ? 'default' : 'outline'}
                    onClick={toggleComplete}
                >
                    <MaterialSymbol name="check_box" className="h-4 w-4 mr-1" />
                    {t('pages.compliance_evaluation_evaluate.evaluation_complete')}
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setShowFindings((v) => !v)}
                >
                    <MaterialSymbol name="warning" className="h-4 w-4 mr-1" />
                    {t('pages.compliance_evaluation_evaluate.findings')}
                </Button>
            </div>

            {showFindings && (
                <div className="rounded border border-border p-3 mt-2">
                    <CrudModule config={findingsConfig} />
                </div>
            )}
        </div>
    );
}

function RequirementSourceSection({
    cers,
    evaluationId,
    t,
}: {
    cers: RequirementSource;
    evaluationId: number;
    t: (key: string) => string;
}) {
    const [open, setOpen] = useState(false);
    const [requirements, setRequirements] = useState<RequirementRow[]>([]);
    const [note, setNote] = useState(cers.note ?? '');
    const [hideEvaluated, setHideEvaluated] = useState(true);
    const [loading, setLoading] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    const loadRequirements = useCallback(() => {
        setLoading(true);
        fetch(`/api/crud/compliance_evaluation_requirements?paginate=0&filter[compliance_evaluation_id]=${evaluationId}&filter[cers_id]=${cers.id}`)
            .then((r) => r.json())
            .then((data: RequirementRow[]) => {
                setRequirements(data);
                setLoading(false);
            });
    }, [evaluationId, cers.id]);

    useEffect(() => {
        if (open) loadRequirements();
    }, [open, refreshKey, loadRequirements]);

    const saveNote = async () => {
        await fetch(`/api/crud/compliance_evaluation_requirement_sources/${cers.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ note }),
        });
    };

    const approveAll = async () => {
        const pending = requirements.filter((r) => !r.evaluated);
        await Promise.all(
            pending.map((r) =>
                fetch(`/api/crud/compliance_evaluation_requirements/${r.id}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ evaluated: true, applicable: true, note: r.note ?? '' }),
                })
            )
        );
        setRefreshKey((k) => k + 1);
    };

    const displayed = hideEvaluated ? requirements.filter((r) => !r.evaluated) : requirements;

    return (
        <div className="rounded-2xl border border-border bg-card shadow-sm overflow-hidden">
            <button
                className="w-full flex items-center justify-between p-4 text-left font-medium hover:bg-muted/50 transition-colors"
                onClick={() => setOpen((v) => !v)}
            >
                <span>{cers.reference} {cers.name}</span>
                {open ? <MaterialSymbol name="keyboard_arrow_down" className="h-4 w-4" /> : <MaterialSymbol name="keyboard_arrow_right" className="h-4 w-4" />}
            </button>

            {open && (
                <div className="border-t border-border p-4 space-y-4">
                    {/* Requirement source notes */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">{t('pages.compliance_evaluation_evaluate.notes_label')}</label>
                        <Textarea
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            rows={3}
                            placeholder={t('pages.compliance_evaluation_evaluate.notes_placeholder')}
                        />
                        <div className="flex gap-2">
                            <Button size="sm" variant="outline" onClick={saveNote}>
                                <MaterialSymbol name="save" className="h-4 w-4 mr-1" />
                                {t('pages.compliance_evaluation_evaluate.save_summary')}
                            </Button>
                            <Button size="sm" variant="outline" onClick={approveAll}>
                                <MaterialSymbol name="check_box" className="h-4 w-4 mr-1" />
                                {t('pages.compliance_evaluation_evaluate.approve_all_button')}
                            </Button>
                        </div>
                    </div>

                    {/* Hide evaluated filter */}
                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input
                            type="checkbox"
                            checked={hideEvaluated}
                            onChange={(e) => setHideEvaluated(e.target.checked)}
                            className="rounded"
                        />
                        {t('pages.compliance_evaluation_evaluate.hide_evaluated')}
                    </label>

                    {/* Requirements */}
                    {loading ? (
                        <p className="text-sm text-muted-foreground">{t('pages.compliance_evaluation_evaluate.loading')}</p>
                    ) : (
                        <div className="space-y-3">
                            {displayed.map((req) => (
                                <RequirementCard
                                    key={req.id}
                                    req={req}
                                    onRefresh={() => setRefreshKey((k) => k + 1)}
                                    t={t}
                                />
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function ComplianceEvaluationEvaluatePage({ route }: ComplianceEvaluationEvaluatePageProps) {
    const { t } = useTranslations();
    const { evaluationId } = useParams<{ evaluationId: string }>();
    const [evaluation, setEvaluation] = useState<EvaluationData | null>(null);
    const [requirementSources, setRequirementSources] = useState<RequirementSource[]>([]);
    const [summary, setSummary] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);

    useEffect(() => {
        if (!evaluationId) return;

        Promise.all([
            fetch(`/api/crud/compliance_evaluations/${evaluationId}`).then((r) => r.json()),
            fetch(`/api/compliance-evaluations/${evaluationId}/requirement-sources`).then((r) => r.json()),
        ])
            .then(([evalData, sourcesData]) => {
                setEvaluation(evalData);
                setSummary(evalData.summary ?? '');
                setRequirementSources(sourcesData);
                setLoading(false);
            })
            .catch(() => {
                setError(true);
                setLoading(false);
            });
    }, [evaluationId]);

    const saveSummary = async () => {
        if (!evaluationId) return;
        await fetch(`/api/crud/compliance_evaluations/${evaluationId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ summary }),
        });
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={evaluation?.name ?? t('pages.compliance_evaluations.title')}
                    description={t('pages.compliance_evaluation_evaluate.back_to_list')}
                    icon={<MaterialSymbol name="checklist" className="h-6 w-6 text-primary" />}
                    route={route}
                    actions={
                        <Link to="/compliance-evaluation">
                            <Button variant="outline" size="sm">
                                <MaterialSymbol name="arrow_back" className="h-4 w-4 mr-1" />
                                {t('pages.compliance_evaluation_evaluate.back_to_list')}
                            </Button>
                        </Link>
                    }
                />

                {loading && (
                    <p className="text-sm text-muted-foreground">{t('pages.compliance_evaluation_evaluate.loading')}</p>
                )}

                {error && (
                    <p className="text-sm text-destructive">{t('pages.compliance_evaluation_evaluate.error_load')}</p>
                )}

                {!loading && !error && evaluation && (
                    <>
                        {/* Executive summary */}
                        <section className="rounded-2xl border border-border bg-card p-6 shadow-sm space-y-3">
                            <label className="text-sm font-medium" htmlFor="executive-summary">
                                {t('pages.compliance_evaluation_evaluate.executive_summary_label')}
                            </label>
                            <Textarea
                                id="executive-summary"
                                value={summary}
                                onChange={(e) => setSummary(e.target.value)}
                                placeholder={t('pages.compliance_evaluation_evaluate.executive_summary_placeholder')}
                                rows={3}
                            />
                            <Button size="sm" variant="outline" onClick={saveSummary}>
                                <MaterialSymbol name="save" className="h-4 w-4 mr-1" />
                                {t('pages.compliance_evaluation_evaluate.save_summary')}
                            </Button>
                        </section>

                        {/* Requirement sources */}
                        <div className="space-y-3">
                            {requirementSources.map((cers) => (
                                <RequirementSourceSection
                                    key={cers.id}
                                    cers={cers}
                                    evaluationId={evaluation.id}
                                    t={t}
                                />
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

