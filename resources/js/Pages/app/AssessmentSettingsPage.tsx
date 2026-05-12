import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslations } from '@/hooks/useTranslations';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import type { AppSectionRoute } from '@/app/routes';

const TAB_KEYS = [
    'risk_assessment',
    'information_classification',
    'data_privacy',
    'sustainability_settings',
    'archival_settings',
] as const;

type AssessmentSettingsTab = (typeof TAB_KEYS)[number];

interface RiskLevelMatrixItem {
    id: number;
    name: string;
    ordinal: number;
    color?: string | null;
    description?: string | null;
    reassessment_days_withoutplans?: number | null;
    reassessment_days_withplans?: number | null;
}

interface RiskMappingRow {
    probability_level_id: number;
    consequence_level_id: number;
    risk_level_id: number;
}

function normalizeHexColor(color?: string | null): string | null {
    if (!color) {
        return null;
    }

    const normalized = color.startsWith('#') ? color.slice(1) : color;
    return /^[0-9a-fA-F]{6}$/.test(normalized) ? `#${normalized}` : null;
}

function isAssessmentSettingsTab(value: string): value is AssessmentSettingsTab {
    return TAB_KEYS.includes(value as AssessmentSettingsTab);
}

function getTabFromHash(hash: string): AssessmentSettingsTab {
    const normalized = hash.replace(/^#/, '');
    if (isAssessmentSettingsTab(normalized)) {
        return normalized;
    }

    return 'risk_assessment';
}

interface AssessmentSettingsPageProps {
    route: AppSectionRoute;
}

export default function AssessmentSettingsPage({ route }: AssessmentSettingsPageProps) {
    const { t } = useTranslations();
    const [activeTab, setActiveTab] = useState<AssessmentSettingsTab>(() => getTabFromHash(window.location.hash));
    const previousTabRef = useRef<AssessmentSettingsTab | null>(null);
    const [riskMatrixLoading, setRiskMatrixLoading] = useState(false);
    const [riskMatrixError, setRiskMatrixError] = useState<string | null>(null);
    const [riskMatrixSuccess, setRiskMatrixSuccess] = useState<string | null>(null);
    const [savingRiskMappings, setSavingRiskMappings] = useState(false);
    const [probabilities, setProbabilities] = useState<RiskLevelMatrixItem[]>([]);
    const [consequences, setConsequences] = useState<RiskLevelMatrixItem[]>([]);
    const [riskLevels, setRiskLevels] = useState<RiskLevelMatrixItem[]>([]);
    const [matrixValues, setMatrixValues] = useState<Record<string, string>>({});

    useEffect(() => {
        const onHashChange = () => {
            setActiveTab(getTabFromHash(window.location.hash));
        };

        window.addEventListener('hashchange', onHashChange);
        return () => {
            window.removeEventListener('hashchange', onHashChange);
        };
    }, []);

    const tabs = useMemo(
        () => [
            {
                key: 'risk_assessment' as const,
                title: t('pages.assessment_settings.tabs.risk_assessment'),
                sections: [
                    t('pages.assessment_settings.sections.probability_levels'),
                    t('pages.assessment_settings.sections.consequence_levels'),
                    t('pages.assessment_settings.sections.risk_levels'),
                    t('pages.assessment_settings.sections.risk_level_mapping'),
                ],
            },
            {
                key: 'information_classification' as const,
                title: t('pages.assessment_settings.tabs.information_classification'),
                sections: [
                    t('pages.assessment_settings.sections.confidentiality_levels'),
                    t('pages.assessment_settings.sections.integrity_levels'),
                    t('pages.assessment_settings.sections.availability_levels'),
                ],
            },
            {
                key: 'data_privacy' as const,
                title: t('pages.assessment_settings.tabs.data_privacy'),
                sections: [
                    t('pages.assessment_settings.sections.data_categories'),
                    t('pages.assessment_settings.sections.subject_categories'),
                    t('pages.assessment_settings.sections.recipient_categories'),
                    t('pages.assessment_settings.sections.legal_basis'),
                ],
            },
            {
                key: 'sustainability_settings' as const,
                title: t('pages.assessment_settings.tabs.sustainability_settings'),
                sections: [
                    t('pages.assessment_settings.sections.sustainability_aspects'),
                    t('pages.assessment_settings.sections.sustainability_metrics'),
                ],
            },
            {
                key: 'archival_settings' as const,
                title: t('pages.assessment_settings.tabs.archival_settings'),
                sections: [
                    t('pages.assessment_settings.sections.confidentiality_grounds'),
                    t('pages.assessment_settings.sections.diaries'),
                ],
            },
        ],
        [t],
    );

    const probabilityConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/probability-levels',
        defaultSort: 'ordinal',
        ordinalSortDirection: 'desc',
        createTitle: t('pages.assessment_settings.create_probability_level'),
        editTitle: t('pages.assessment_settings.edit_probability_level'),
        fields: [
            { key: 'ordinal', label: t('pages.assessment_settings.column_ordinal'), type: 'number', sortable: true, editable: false, hiddenInTable: true },
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true, required: true, masterDescription: true },
        ],
        onSaveSuccess: async () => {
            await loadRiskMatrix();
        },
    }), [t]);

    const consequenceConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/consequence-levels',
        defaultSort: 'ordinal',
        ordinalSortDirection: 'desc',
        createTitle: t('pages.assessment_settings.create_consequence_level'),
        editTitle: t('pages.assessment_settings.edit_consequence_level'),
        fields: [
            { key: 'ordinal', label: t('pages.assessment_settings.column_ordinal'), type: 'number', sortable: true, editable: false, hiddenInTable: true },
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true, required: true, masterDescription: true },
        ],
        onSaveSuccess: async () => {
            await loadRiskMatrix();
        },
    }), [t]);

    const riskLevelConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/risk-levels',
        defaultSort: 'ordinal',
        ordinalSortDirection: 'desc',
        createTitle: t('pages.assessment_settings.create_risk_level'),
        editTitle: t('pages.assessment_settings.edit_risk_level'),
        fields: [
            { key: 'ordinal', label: t('pages.assessment_settings.column_ordinal'), type: 'number', sortable: true, editable: false, hiddenInTable: true },
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true, required: true },
            { key: 'color', label: t('pages.assessment_settings.column_color'), type: 'color', editable: true, required: true },
            { key: 'reassessment_days_withoutplans', label: t('pages.assessment_settings.column_reassessment_without_actions'), type: 'number', sortable: true, editable: true, required: true },
            { key: 'reassessment_days_withplans', label: t('pages.assessment_settings.column_reassessment_with_actions'), type: 'number', sortable: true, editable: true, required: true },
        ],
        onSaveSuccess: async () => {
            await loadRiskMatrix();
        },
    }), [t]);

    const confidentialityConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/confidentiality-classes',
        defaultSort: 'ordinal',
        ordinalSortDirection: 'desc',
        createTitle: t('pages.assessment_settings.create_confidentiality_level'),
        editTitle: t('pages.assessment_settings.edit_confidentiality_level'),
        fields: [
            { key: 'ordinal', label: t('pages.assessment_settings.column_ordinal'), type: 'number', sortable: true, editable: false, hiddenInTable: true },
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true, required: true, masterDescription: true },
        ],
    }), [t]);

    const integrityConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/integrity-classes',
        defaultSort: 'ordinal',
        ordinalSortDirection: 'desc',
        createTitle: t('pages.assessment_settings.create_integrity_level'),
        editTitle: t('pages.assessment_settings.edit_integrity_level'),
        fields: [
            { key: 'ordinal', label: t('pages.assessment_settings.column_ordinal'), type: 'number', sortable: true, editable: false, hiddenInTable: true },
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true, required: true, masterDescription: true },
        ],
    }), [t]);

    const availabilityConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/availability-classes',
        defaultSort: 'ordinal',
        ordinalSortDirection: 'desc',
        createTitle: t('pages.assessment_settings.create_availability_level'),
        editTitle: t('pages.assessment_settings.edit_availability_level'),
        fields: [
            { key: 'ordinal', label: t('pages.assessment_settings.column_ordinal'), type: 'number', sortable: true, editable: false, hiddenInTable: true },
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true, required: true, masterDescription: true },
        ],
    }), [t]);

    const dataCategoryConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/data-categories',
        defaultSort: 'name',
        createTitle: t('pages.assessment_settings.create_data_category'),
        editTitle: t('pages.assessment_settings.edit_data_category'),
        fields: [
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true, required: true },
            { key: 'sensitive', label: t('pages.assessment_settings.column_sensitive'), type: 'boolean', sortable: true, editable: true, required: true },
        ],
    }), [t]);

    const subjectCategoryConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/subject-categories',
        defaultSort: 'name',
        createTitle: t('pages.assessment_settings.create_subject_category'),
        editTitle: t('pages.assessment_settings.edit_subject_category'),
        fields: [
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true, required: true },
        ],
    }), [t]);

    const recipientCategoryConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/recipient-categories',
        defaultSort: 'name',
        createTitle: t('pages.assessment_settings.create_recipient_category'),
        editTitle: t('pages.assessment_settings.edit_recipient_category'),
        fields: [
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true, required: true },
        ],
    }), [t]);

    const legalBasisConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/legal-bases',
        defaultSort: 'name',
        createTitle: t('pages.assessment_settings.create_legal_basis'),
        editTitle: t('pages.assessment_settings.edit_legal_basis'),
        fields: [
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true, required: true },
            { key: 'sensitive', label: t('pages.assessment_settings.column_allow_sensitive'), type: 'boolean', sortable: true, editable: true, required: true },
            { key: 'consent', label: t('pages.assessment_settings.column_consent_based'), type: 'boolean', sortable: true, editable: true, required: true },
        ],
    }), [t]);

    const sustainabilityAspectConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/sustainability-aspects',
        defaultSort: 'name',
        createTitle: t('pages.assessment_settings.create_sustainability_aspect'),
        editTitle: t('pages.assessment_settings.edit_sustainability_aspect'),
        fields: [
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true },
            { key: 'threshold', label: t('pages.assessment_settings.column_significant_threshold'), type: 'number', sortable: true, editable: true },
        ],
    }), [t]);

    const sustainabilityMetricConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/sustainability-metrics',
        defaultSort: 'name',
        createTitle: t('pages.assessment_settings.create_sustainability_metric'),
        editTitle: t('pages.assessment_settings.edit_sustainability_metric'),
        fields: [
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true },
        ],
    }), [t]);

    const sustainabilityMetricLevelConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/sustainability-metric-levels',
        defaultSort: 'id',
        createTitle: t('pages.assessment_settings.create_sustainability_metric_level'),
        editTitle: t('pages.assessment_settings.edit_sustainability_metric_level'),
        fields: [
            { key: 'sustainability_metric_id', label: t('pages.assessment_settings.column_sustainability_metric'), type: 'number', sortable: true, editable: true, required: true },
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true },
            { key: 'multiplier', label: t('pages.assessment_settings.column_multiplier'), type: 'number', sortable: true, editable: true, required: true },
        ],
    }), [t]);

    const confidentialityGroundConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/confidentiality-grounds',
        defaultSort: 'name',
        createTitle: t('pages.assessment_settings.create_confidentiality_ground'),
        editTitle: t('pages.assessment_settings.edit_confidentiality_ground'),
        fields: [
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true },
        ],
    }), [t]);

    const diariesConfig: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/diaries',
        defaultSort: 'name',
        createTitle: t('pages.assessment_settings.create_diary'),
        editTitle: t('pages.assessment_settings.edit_diary'),
        fields: [
            { key: 'name', label: t('pages.assessment_settings.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true },
            { key: 'description', label: t('pages.assessment_settings.column_description'), type: 'textarea', editable: true },
        ],
    }), [t]);

    const loadRiskMatrix = useCallback(async () => {
        setRiskMatrixLoading(true);
        setRiskMatrixError(null);

        try {
            const [probResponse, consResponse, levelResponse, mappingResponse] = await Promise.all([
                fetch('/api/crud/probability-levels?paginate=0&sort=-ordinal&%24select=id,name,ordinal', { headers: { Accept: 'application/json' } }),
                fetch('/api/crud/consequence-levels?paginate=0&sort=ordinal&%24select=id,name,ordinal', { headers: { Accept: 'application/json' } }),
                fetch('/api/crud/risk-levels?paginate=0&sort=ordinal&%24select=id,name,ordinal,color', { headers: { Accept: 'application/json' } }),
                fetch('/api/assessment-settings/risk-mappings', { headers: { Accept: 'application/json' } }),
            ]);

            if (!probResponse.ok || !consResponse.ok || !levelResponse.ok || !mappingResponse.ok) {
                throw new Error(t('pages.assessment_settings.risk_mapping_load_failed'));
            }

            const probabilityRows = (await probResponse.json()) as RiskLevelMatrixItem[];
            const consequenceRows = (await consResponse.json()) as RiskLevelMatrixItem[];
            const riskLevelRows = (await levelResponse.json()) as RiskLevelMatrixItem[];
            const mappingJson = (await mappingResponse.json()) as { mappings?: RiskMappingRow[] };
            const mappingRows = mappingJson.mappings ?? [];

            setProbabilities(probabilityRows);
            setConsequences(consequenceRows);
            setRiskLevels(riskLevelRows);

            const nextValues: Record<string, string> = {};
            for (const row of mappingRows) {
                nextValues[`${row.probability_level_id}:${row.consequence_level_id}`] = String(row.risk_level_id);
            }

            for (const probability of probabilityRows) {
                for (const consequence of consequenceRows) {
                    const key = `${probability.id}:${consequence.id}`;
                    if (nextValues[key] == null) {
                        nextValues[key] = '';
                    }
                }
            }

            setMatrixValues(nextValues);
        } catch (error) {
            setRiskMatrixError(error instanceof Error ? error.message : t('pages.assessment_settings.risk_mapping_load_failed'));
        } finally {
            setRiskMatrixLoading(false);
        }
    }, [t]);

    const hasAllRiskMappings = useMemo(() => {
        if (probabilities.length === 0 || consequences.length === 0 || riskLevels.length === 0) {
            return false;
        }

        for (const probability of probabilities) {
            for (const consequence of consequences) {
                const value = matrixValues[`${probability.id}:${consequence.id}`];
                if (value == null || value === '') {
                    return false;
                }
            }
        }

        return true;
    }, [consequences, matrixValues, probabilities, riskLevels.length]);

    const riskLevelColorById = useMemo(() => {
        const map = new Map<number, string>();
        for (const riskLevel of riskLevels) {
            const normalized = normalizeHexColor(riskLevel.color);
            if (normalized) {
                map.set(riskLevel.id, normalized);
            }
        }

        return map;
    }, [riskLevels]);

    const hasMatrixData = probabilities.length > 0 && consequences.length > 0 && riskLevels.length > 0;

    const saveRiskMappings = useCallback(async () => {
        setRiskMatrixError(null);
        setRiskMatrixSuccess(null);

        if (!hasAllRiskMappings) {
            setRiskMatrixError(t('pages.assessment_settings.risk_mapping_incomplete'));
            return;
        }

        const payload: RiskMappingRow[] = [];
        for (const probability of probabilities) {
            for (const consequence of consequences) {
                const key = `${probability.id}:${consequence.id}`;
                const riskLevelId = Number(matrixValues[key]);
                payload.push({
                    probability_level_id: probability.id,
                    consequence_level_id: consequence.id,
                    risk_level_id: riskLevelId,
                });
            }
        }

        setSavingRiskMappings(true);
        try {
            const response = await fetch('/api/assessment-settings/risk-mappings', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ mappings: payload }),
            });

            const json = await response.json().catch(() => ({} as { message?: string }));

            if (!response.ok) {
                throw new Error(typeof json?.message === 'string' ? json.message : t('pages.assessment_settings.risk_mapping_save_failed'));
            }

            setRiskMatrixSuccess(t('pages.assessment_settings.risk_mapping_saved'));
        } catch (error) {
            setRiskMatrixError(error instanceof Error ? error.message : t('pages.assessment_settings.risk_mapping_save_failed'));
        } finally {
            setSavingRiskMappings(false);
        }
    }, [consequences, hasAllRiskMappings, matrixValues, probabilities, t]);

    useEffect(() => {
        const previousTab = previousTabRef.current;
        previousTabRef.current = activeTab;

        // Avoid repeated fetches caused by rerenders while staying on the same tab.
        if (activeTab !== 'risk_assessment' || previousTab === activeTab) {
            return;
        }

        void loadRiskMatrix();
    }, [activeTab, loadRiskMatrix]);

    const handleTabChange = (tab: string) => {
        if (!isAssessmentSettingsTab(tab)) {
            return;
        }

        setActiveTab(tab);
        window.history.replaceState(null, '', `#${tab}`);
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={route.label}
                    description={t('pages.assessment_settings.description')}
                    icon={<MaterialSymbol name="settings" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <Tabs value={activeTab} onValueChange={handleTabChange}>
                        <TabsList className="h-auto w-full flex-wrap justify-start gap-1 bg-muted/60 p-1">
                            {tabs.map((tab) => (
                                <TabsTrigger key={tab.key} value={tab.key}>
                                    {tab.title}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {tabs.map((tab) => (
                            <TabsContent key={tab.key} value={tab.key} className="mt-4 rounded-xl border border-border p-4">
                                <h2 className="text-lg font-semibold text-foreground">{tab.title}</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('pages.assessment_settings.tab_description')}
                                </p>

                                <div className="mt-4 space-y-6">
                                    {tab.key === 'risk_assessment' && (
                                        <>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.probability_levels')}</h3>
                                                <CrudModule config={probabilityConfig} />
                                            </section>

                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.consequence_levels')}</h3>
                                                <CrudModule config={consequenceConfig} />
                                            </section>

                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.risk_levels')}</h3>
                                                <CrudModule config={riskLevelConfig} />
                                            </section>

                                            <section className="rounded-xl border border-border p-4">
                                                <div className="mb-3 flex items-center justify-between gap-2">
                                                    <h3 className="text-base font-semibold">{t('pages.assessment_settings.sections.risk_level_mapping')}</h3>
                                                    <button
                                                        type="button"
                                                        className="rounded-md border border-border px-3 py-1 text-sm hover:bg-muted"
                                                        onClick={() => {
                                                            void loadRiskMatrix();
                                                        }}
                                                    >
                                                        {t('pages.assessment_settings.reload_matrix')}
                                                    </button>
                                                </div>

                                                {riskMatrixLoading && !hasMatrixData && (
                                                    <p className="text-sm text-muted-foreground">{t('pages.assessment_settings.loading_matrix')}</p>
                                                )}

                                                {riskMatrixError && (
                                                    <p className="mb-3 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                                        {riskMatrixError}
                                                    </p>
                                                )}

                                                {riskMatrixSuccess && (
                                                    <p className="mb-3 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                                                        {riskMatrixSuccess}
                                                    </p>
                                                )}

                                                {!hasMatrixData && !riskMatrixLoading ? (
                                                    <p className="text-sm text-muted-foreground">
                                                        {t('pages.assessment_settings.risk_mapping_prerequisites')}
                                                    </p>
                                                ) : hasMatrixData ? (
                                                    <div className="space-y-3 overflow-x-auto">
                                                        <table className="min-w-full border-collapse text-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th className="border border-border bg-muted p-2 text-left">{t('pages.assessment_settings.matrix_probability')}</th>
                                                                    {consequences.map((cons) => (
                                                                        <th key={cons.id} className="border border-border bg-muted p-2 text-left">
                                                                            {cons.name}
                                                                        </th>
                                                                    ))}
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {probabilities.map((prob) => (
                                                                    <tr key={prob.id}>
                                                                        <th className="border border-border bg-muted/60 p-2 text-left font-medium">{prob.name}</th>
                                                                        {consequences.map((cons) => {
                                                                            const key = `${prob.id}:${cons.id}`;
                                                                            const selectedRiskLevelId = Number(matrixValues[key]);
                                                                            const selectedColor = riskLevelColorById.get(selectedRiskLevelId);
                                                                            return (
                                                                                <td
                                                                                    key={key}
                                                                                    className="border border-border p-2"
                                                                                    style={selectedColor ? { backgroundColor: `${selectedColor}33` } : undefined}
                                                                                >
                                                                                    <select
                                                                                        className="w-full rounded-md border border-border bg-background px-2 py-1"
                                                                                        style={selectedColor ? { backgroundColor: `${selectedColor}33` } : undefined}
                                                                                        value={matrixValues[key] ?? ''}
                                                                                        onChange={(event) => {
                                                                                            const nextValue = event.target.value;
                                                                                            setMatrixValues((prev) => ({ ...prev, [key]: nextValue }));
                                                                                            setRiskMatrixSuccess(null);
                                                                                        }}
                                                                                    >
                                                                                        <option value="">{t('pages.assessment_settings.select_option')}</option>
                                                                                        {riskLevels.map((riskLevel) => (
                                                                                            <option key={riskLevel.id} value={String(riskLevel.id)}>
                                                                                                {riskLevel.name}
                                                                                            </option>
                                                                                        ))}
                                                                                    </select>
                                                                                </td>
                                                                            );
                                                                        })}
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>

                                                        {!hasAllRiskMappings && (
                                                            <p className="text-sm text-amber-600">{t('pages.assessment_settings.risk_mapping_incomplete')}</p>
                                                        )}

                                                        <button
                                                            type="button"
                                                            className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                                            disabled={savingRiskMappings || !hasAllRiskMappings}
                                                            onClick={() => {
                                                                void saveRiskMappings();
                                                            }}
                                                        >
                                                            {savingRiskMappings
                                                                ? t('pages.assessment_settings.saving')
                                                                : t('pages.assessment_settings.save_risk_mapping')}
                                                        </button>
                                                    </div>
                                                ) : null}
                                            </section>
                                        </>
                                    )}

                                    {tab.key === 'information_classification' && (
                                        <>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.confidentiality_levels')}</h3>
                                                <CrudModule config={confidentialityConfig} />
                                            </section>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.integrity_levels')}</h3>
                                                <CrudModule config={integrityConfig} />
                                            </section>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.availability_levels')}</h3>
                                                <CrudModule config={availabilityConfig} />
                                            </section>
                                        </>
                                    )}

                                    {tab.key === 'data_privacy' && (
                                        <>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.data_categories')}</h3>
                                                <CrudModule config={dataCategoryConfig} />
                                            </section>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.subject_categories')}</h3>
                                                <CrudModule config={subjectCategoryConfig} />
                                            </section>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.recipient_categories')}</h3>
                                                <CrudModule config={recipientCategoryConfig} />
                                            </section>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.legal_basis')}</h3>
                                                <CrudModule config={legalBasisConfig} />
                                            </section>
                                        </>
                                    )}

                                    {tab.key === 'sustainability_settings' && (
                                        <>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.sustainability_aspects')}</h3>
                                                <CrudModule config={sustainabilityAspectConfig} />
                                            </section>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.sustainability_metrics')}</h3>
                                                <CrudModule config={sustainabilityMetricConfig} />
                                            </section>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.sustainability_metric_levels')}</h3>
                                                <CrudModule config={sustainabilityMetricLevelConfig} />
                                            </section>
                                        </>
                                    )}

                                    {tab.key === 'archival_settings' && (
                                        <>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.confidentiality_grounds')}</h3>
                                                <CrudModule config={confidentialityGroundConfig} />
                                            </section>
                                            <section className="rounded-xl border border-border p-4">
                                                <h3 className="mb-3 text-base font-semibold">{t('pages.assessment_settings.sections.diaries')}</h3>
                                                <CrudModule config={diariesConfig} />
                                            </section>
                                        </>
                                    )}
                                </div>
                            </TabsContent>
                        ))}
                    </Tabs>
                </section>
            </div>
        </AppLayout>
    );
}
