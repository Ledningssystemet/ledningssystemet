import { useEffect, useMemo, useState } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import type { ProcessSustainabilityAspectItem, SustainabilityMetricItem } from '@/types/sustainabilityAspects';
import type { AppSectionRoute } from '@/app/routes';

interface SustainabilityAspectsPageProps {
    route: AppSectionRoute;
}

export default function SustainabilityAspectsPage({ route }: SustainabilityAspectsPageProps) {
    const { t } = useTranslations();
    const [renderKey, setRenderKey] = useState(0);
    const [activeAssessmentItem, setActiveAssessmentItem] = useState<ProcessSustainabilityAspectItem | null>(null);
    const [assessmentMetrics, setAssessmentMetrics] = useState<SustainabilityMetricItem[]>([]);
    const [assessmentValues, setAssessmentValues] = useState<Record<number, string>>({});
    const [assessmentLoading, setAssessmentLoading] = useState(false);
    const [assessmentSaving, setAssessmentSaving] = useState(false);

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/process_sustainability_aspects',
            perPage: 25,
            defaultSort: 'name',
            createTitle: t('pages.sustainability_aspects.create_title'),
            editTitle: t('pages.sustainability_aspects.edit_title'),
            selectFields: [
                'id',
                'name',
                'description',
                'impact_description',
                'governance_description',
                'monitoring_description',
                'process_id',
                'process_name',
                'sustainability_aspect_id',
                'sustainability_aspect_name',
                'tags',
                'metric_sum',
                'significant',
                'sustainability_metrics',
                'process_performance_metrics',
                'objectives',
            ],
            customQueryParams: (filters) => ({
                tag_id: filters.tag_id || undefined,
            }),
            rowActions: [
                {
                    key: 'assess-metrics',
                    label: t('pages.sustainability_aspects.assessment.open_button'),
                    icon: <MaterialSymbol name="checklist" className="h-4 w-4" />,
                    variant: 'outline',
                    refreshOnComplete: false,
                    onClick: (item) => {
                        setActiveAssessmentItem(item as ProcessSustainabilityAspectItem);
                    },
                },
            ],
            fields: [
                {
                    key: 'name',
                    label: t('pages.sustainability_aspects.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    renderCell: (value, row) => {
                        const processName = String(row.process_name || '');
                        const aspectName = String(row.sustainability_aspect_name || '');
                        const localName = String(value || '');
                        const chunks = [processName, aspectName, localName].filter((chunk) => chunk !== '');

                        return chunks.join(' - ');
                    },
                    category: t('pages.sustainability_aspects.category_general'),
                },
                {
                    key: 'tags',
                    label: t('pages.sustainability_aspects.column_tags'),
                    type: 'inline-tags',
                    editable: true,
                    sortable: false,
                    tags: true,
                    optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                    createOptionUrl: '/api/crud/tags',
                    optionValueKey: 'name',
                    optionLabelKey: 'name',
                    createOptionPayloadKey: 'name',
                    category: t('pages.sustainability_aspects.category_general'),
                },
                {
                    key: 'process_id',
                    label: t('pages.sustainability_aspects.column_process'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    required: true,
                    optionsUrl: '/api/crud/processes?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.sustainability_aspects.category_general'),
                },
                {
                    key: 'sustainability_aspect_id',
                    label: t('pages.sustainability_aspects.column_sustainability_aspect'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    editableOnUpdate: false,
                    required: true,
                    optionsUrl: '/api/crud/sustainability-aspects?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    helpText: t('pages.sustainability_aspects.sustainability_aspect_help'),
                    category: t('pages.sustainability_aspects.category_general'),
                },
                {
                    key: 'description',
                    label: t('pages.sustainability_aspects.column_description'),
                    type: 'textarea',
                    editable: true,
                    required: true,
                    masterDescription: true,
                    category: t('pages.sustainability_aspects.category_general'),
                },
                {
                    key: 'metric_sum',
                    label: t('pages.sustainability_aspects.column_metric_sum'),
                    type: 'number',
                    editable: false,
                    sortable: false,
                    renderCell: (value) =>
                        value == null ? (
                            <span className="text-muted-foreground">{t('pages.sustainability_aspects.not_assessed')}</span>
                        ) : (
                            String(value)
                        ),
                    renderDetail: (value) =>
                        value == null ? (
                            <span className="text-muted-foreground">{t('pages.sustainability_aspects.not_assessed')}</span>
                        ) : (
                            String(value)
                        ),
                    category: t('pages.sustainability_aspects.category_assessment'),
                },
                {
                    key: 'significant',
                    label: t('pages.sustainability_aspects.column_significant'),
                    type: 'text',
                    editable: false,
                    sortable: false,
                    renderCell: (value) => renderSignificant(value, t),
                    renderDetail: (value) => renderSignificant(value, t),
                    category: t('pages.sustainability_aspects.category_assessment'),
                },
                {
                    key: 'sustainability_metrics_view',
                    label: t('pages.sustainability_aspects.column_sustainability_metrics'),
                    type: 'text',
                    editable: false,
                    sortable: false,
                    renderCell: (_value, row) => renderMetricSummary(row.sustainability_metrics, t),
                    renderDetail: (_value, row) => renderMetricSummary(row.sustainability_metrics, t),
                    category: t('pages.sustainability_aspects.category_assessment'),
                },
                {
                    key: 'impact_description',
                    label: t('pages.sustainability_aspects.column_impact_description'),
                    type: 'textarea',
                    editable: true,
                    category: t('pages.sustainability_aspects.category_descriptions'),
                },
                {
                    key: 'governance_description',
                    label: t('pages.sustainability_aspects.column_governance_description'),
                    type: 'textarea',
                    editable: true,
                    category: t('pages.sustainability_aspects.category_descriptions'),
                },
                {
                    key: 'monitoring_description',
                    label: t('pages.sustainability_aspects.column_monitoring_description'),
                    type: 'textarea',
                    editable: true,
                    category: t('pages.sustainability_aspects.category_descriptions'),
                },
                {
                    key: 'process_performance_metrics',
                    label: t('pages.sustainability_aspects.column_process_performance_metrics'),
                    type: 'multiselect',
                    editable: true,
                    sortable: false,
                    optionsUrl: '/api/crud/process_performance_metrics?paginate=0&filter[quantitative]=1&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.sustainability_aspects.category_relations'),
                },
                {
                    key: 'objectives',
                    label: t('pages.sustainability_aspects.column_objectives'),
                    type: 'multiselect',
                    editable: true,
                    sortable: false,
                    optionsUrl: '/api/crud/objectives?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    category: t('pages.sustainability_aspects.category_relations'),
                },
                {
                    key: 'tag_id',
                    label: t('pages.sustainability_aspects.filter_tag'),
                    type: 'select',
                    hidden: true,
                    editable: false,
                    filterable: true,
                    optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                },
            ],
        }),
        [t]
    );

    useEffect(() => {
        const loadAssessment = async () => {
            if (!activeAssessmentItem?.id) {
                return;
            }

            setAssessmentLoading(true);

            try {
                const response = await fetch(
                    `/api/crud/process_sustainability_aspects/${activeAssessmentItem.id}?%24select=id,name,sustainability_metrics`,
                    {
                        headers: { Accept: 'application/json' },
                    }
                );

                if (!response.ok) {
                    setAssessmentMetrics([]);
                    setAssessmentValues({});
                    return;
                }

                const payload = (await response.json()) as ProcessSustainabilityAspectItem;
                const metrics = Array.isArray(payload.sustainability_metrics) ? payload.sustainability_metrics : [];

                setAssessmentMetrics(metrics);
                const nextValues: Record<number, string> = {};
                metrics.forEach((metric) => {
                    nextValues[metric.id] = metric.level?.sustainability_metric_level_id
                        ? String(metric.level.sustainability_metric_level_id)
                        : '';
                });
                setAssessmentValues(nextValues);
            } catch {
                setAssessmentMetrics([]);
                setAssessmentValues({});
            } finally {
                setAssessmentLoading(false);
            }
        };

        void loadAssessment();
    }, [activeAssessmentItem?.id]);

    const closeAssessmentDialog = () => {
        setActiveAssessmentItem(null);
        setAssessmentMetrics([]);
        setAssessmentValues({});
        setAssessmentSaving(false);
    };

    const saveAssessment = async () => {
        if (!activeAssessmentItem?.id) {
            return;
        }

        setAssessmentSaving(true);

        try {
            const payload: Record<number, number> = {};
            Object.entries(assessmentValues).forEach(([metricId, levelId]) => {
                const parsedMetricId = Number(metricId);
                const parsedLevelId = Number(levelId);
                if (Number.isFinite(parsedMetricId) && parsedMetricId > 0 && Number.isFinite(parsedLevelId) && parsedLevelId > 0) {
                    payload[parsedMetricId] = parsedLevelId;
                }
            });

            const response = await fetch(`/api/crud/process_sustainability_aspects/${activeAssessmentItem.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ sustainability_metrics: payload }),
            });

            if (!response.ok) {
                return;
            }

            closeAssessmentDialog();
            setRenderKey((previous) => previous + 1);
        } finally {
            setAssessmentSaving(false);
        }
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.sustainability_aspects.title')}
                    description={t('pages.sustainability_aspects.description')}
                    icon={<MaterialSymbol name="eco" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule
                        key={`sustainability-aspects-${renderKey}`}
                        config={config}
                    />
                </section>
            </div>

            <Dialog open={Boolean(activeAssessmentItem)} onOpenChange={(open) => !open && closeAssessmentDialog()}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <MaterialSymbol name="checklist" className="h-5 w-5" />
                            {t('pages.sustainability_aspects.assessment.panel_title', {
                                aspect: String(activeAssessmentItem?.name || ''),
                            })}
                        </DialogTitle>
                        <DialogDescription>{t('pages.sustainability_aspects.assessment.panel_description')}</DialogDescription>
                    </DialogHeader>

                    {assessmentLoading ? (
                        <div className="text-sm text-muted-foreground">{t('pages.sustainability_aspects.assessment.loading')}</div>
                    ) : (
                        <div className="space-y-3">
                            {assessmentMetrics.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('pages.sustainability_aspects.assessment.no_metrics')}
                                </p>
                            ) : (
                                assessmentMetrics.map((metric) => (
                                    <div key={metric.id} className="grid gap-1">
                                        <label className="text-sm font-medium text-foreground" htmlFor={`metric-${metric.id}`}>
                                            {metric.name}
                                        </label>
                                        <select
                                            id={`metric-${metric.id}`}
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={assessmentValues[metric.id] ?? ''}
                                            onChange={(event) => {
                                                const value = event.target.value;
                                                setAssessmentValues((previous) => ({
                                                    ...previous,
                                                    [metric.id]: value,
                                                }));
                                            }}
                                        >
                                            <option value="">{t('pages.sustainability_aspects.not_assessed')}</option>
                                            {metric.levels.map((level) => (
                                                <option key={`metric-${metric.id}-level-${level.id}`} value={String(level.id)}>
                                                    {`${level.name} (${level.multiplier})`}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                ))
                            )}
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={closeAssessmentDialog} disabled={assessmentSaving}>
                            {t('pages.sustainability_aspects.assessment.close_panel_button')}
                        </Button>
                        <Button type="button" onClick={saveAssessment} disabled={assessmentSaving || assessmentLoading}>
                            {assessmentSaving
                                ? t('pages.sustainability_aspects.assessment.saving_button')
                                : t('pages.sustainability_aspects.assessment.save_button')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function renderMetricSummary(
    value: unknown,
    t: (key: string, replacements?: Record<string, string | number>) => string
) {
    if (!Array.isArray(value) || value.length === 0) {
        return <span className="text-muted-foreground">{t('pages.sustainability_aspects.not_assessed')}</span>;
    }

    const metrics = value as SustainabilityMetricItem[];

    return (
        <div className="space-y-1">
            {metrics.map((metric) => (
                <div key={metric.id} className="text-sm">
                    <span className="font-medium">{metric.name}</span>
                    <span className="text-muted-foreground">
                        {' - '}
                        {metric.level ? `${metric.level.name} (${metric.level.multiplier})` : t('pages.sustainability_aspects.not_assessed')}
                    </span>
                </div>
            ))}
        </div>
    );
}

function renderSignificant(
    value: unknown,
    t: (key: string, replacements?: Record<string, string | number>) => string
) {
    if (value === null || value === undefined) {
        return <span className="text-muted-foreground">{t('pages.sustainability_aspects.not_assessed')}</span>;
    }

    return value ? t('pages.sustainability_aspects.option_yes') : t('pages.sustainability_aspects.option_no');
}
