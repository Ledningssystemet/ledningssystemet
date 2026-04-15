import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Users } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

// ─── Tab keys ────────────────────────────────────────────────────────────────

const TAB_KEYS = [
    'general_info',
    'roles_and_tasks',
    'qualifications',
    'competences',
    'responsibilities',
] as const;

type EmployeeTab = (typeof TAB_KEYS)[number];

// ─── API types ────────────────────────────────────────────────────────────────

interface EmployeeData {
    id: number;
    name: string;
    email: string;
    title: string | null;
    enabled: boolean;
    manager: { id: number; name: string } | null;
    departments: { id: number; name: string }[];
    direct_reports: { id: number; name: string }[];
}

interface ProcessActivity {
    id: number;
    name: string;
    process_id: number;
    process_name: string;
}

interface RoleData {
    id: number;
    name: string;
    description: string | null;
    authorities: string | null;
    accountable_activities: ProcessActivity[];
    responsible_activities: ProcessActivity[];
}

interface QualificationEntry {
    id: number;
    name: string;
    finished_at: string | null;
    planned_at: string | null;
    expires_at: string | null;
}

interface QualificationsData {
    achieved: QualificationEntry[];
    missing: { id: number; name: string }[];
}

interface CompetenceEntry {
    id: number;
    name: string;
    description: string;
    is_mandatory: boolean;
    achieved_level_name: string | null;
    acceptable_level_name: string | null;
    desired_level_name: string | null;
    acceptable_ok: boolean;
    desired_ok: boolean;
    evaluated: boolean;
    note: string | null;
    updated_by: string | null;
    updated_at: string | null;
}

interface ResponsibilitiesData {
    processes: { id: number; name: string }[];
    information_types: { id: number; name: string }[];
    assets: { id: number; name: string }[];
    customers: { id: number; name: string }[];
    suppliers: { id: number; name: string }[];
    controls: { id: number; name: string }[];
}

// ─── Helper: expiry status ────────────────────────────────────────────────────

function expiryStatus(expiresAt: string | null): 'expired' | 'soon' | 'ok' | null {
    if (!expiresAt) return null;
    const ms = new Date(expiresAt).getTime() - Date.now();
    if (ms < 0) return 'expired';
    if (ms < 30 * 24 * 60 * 60 * 1000) return 'soon';
    return 'ok';
}

// ─── Shared sub-components ────────────────────────────────────────────────────

function InfoRow({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5 rounded-xl bg-muted/50 px-4 py-3">
            <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{label}</span>
            <span className="text-sm font-medium text-foreground">{children}</span>
        </div>
    );
}

function SectionCard({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="rounded-xl border border-border p-4">
            <h3 className="mb-3 text-base font-semibold text-foreground">{title}</h3>
            {children}
        </section>
    );
}

function LoadingPlaceholder({ label }: { label: string }) {
    return <p className="text-sm text-muted-foreground">{label}</p>;
}

function ErrorPlaceholder({ label }: { label: string }) {
    return (
        <p className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {label}
        </p>
    );
}

// ─── Tab: General information ─────────────────────────────────────────────────

function GeneralInfoTab({ data, loading, error, t }: {
    data: EmployeeData | null;
    loading: boolean;
    error: string | null;
    t: (key: string) => string;
}) {
    if (loading) return <LoadingPlaceholder label={t('pages.employees.loading')} />;
    if (error) return <ErrorPlaceholder label={error} />;
    if (!data) return null;

    return (
        <div className="space-y-3">
            <InfoRow label={t('pages.employees.column_name')}>{data.name}</InfoRow>
            <InfoRow label="Email">{data.email || '—'}</InfoRow>
            <InfoRow label={t('pages.employees.general.label_title')}>{data.title || '—'}</InfoRow>
            <InfoRow label={t('pages.employees.general.label_active')}>
                {data.enabled ? '✓' : '✗'}
            </InfoRow>
            <InfoRow label={t('pages.employees.general.label_departments')}>
                {data.departments.length > 0
                    ? data.departments.map((d) => d.name).join(', ')
                    : t('pages.employees.general.no_departments')}
            </InfoRow>
            <InfoRow label={t('pages.employees.general.label_manager')}>
                {data.manager ? data.manager.name : t('pages.employees.general.no_manager')}
            </InfoRow>
            {data.direct_reports.length > 0 && (
                <InfoRow label={t('pages.employees.general.label_direct_reports')}>
                    {data.direct_reports.map((u) => u.name).join(', ')}
                </InfoRow>
            )}
        </div>
    );
}

// ─── Tab: Roles and tasks ─────────────────────────────────────────────────────

function ActivityGroup({ activities }: { activities: ProcessActivity[] }) {
    const byProcess = useMemo(() => {
        const map = new Map<number, { processName: string; items: ProcessActivity[] }>();
        for (const a of activities) {
            if (!map.has(a.process_id)) {
                map.set(a.process_id, { processName: a.process_name, items: [] });
            }
            map.get(a.process_id)!.items.push(a);
        }
        return Array.from(map.values());
    }, [activities]);

    if (byProcess.length === 0) return null;

    return (
        <ul className="mt-1 space-y-2">
            {byProcess.map((group) => (
                <li key={group.processName}>
                    <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {group.processName}
                    </span>
                    <ul className="mt-0.5 space-y-0.5 pl-3">
                        {group.items.map((a) => (
                            <li key={a.id} className="text-sm text-foreground">{a.name}</li>
                        ))}
                    </ul>
                </li>
            ))}
        </ul>
    );
}

function RolesTab({ data, loading, error, t }: {
    data: RoleData[] | null;
    loading: boolean;
    error: string | null;
    t: (key: string) => string;
}) {
    if (loading) return <LoadingPlaceholder label={t('pages.employees.loading')} />;
    if (error) return <ErrorPlaceholder label={error} />;
    if (!data || data.length === 0)
        return <p className="text-sm text-muted-foreground">{t('pages.employees.roles.no_roles')}</p>;

    return (
        <div className="space-y-4">
            {data.map((role) => (
                <SectionCard key={role.id} title={role.name}>
                    {role.description && (
                        <p className="mb-3 text-sm text-muted-foreground">{role.description}</p>
                    )}
                    {role.authorities && (
                        <div className="mb-3 rounded-xl bg-muted/50 px-4 py-3">
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                {t('pages.employees.roles.label_authorities')}
                            </p>
                            <p className="mt-0.5 text-sm text-foreground">{role.authorities}</p>
                        </div>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                {t('pages.employees.roles.label_accountable_for')}
                            </p>
                            {role.accountable_activities.length > 0
                                ? <ActivityGroup activities={role.accountable_activities} />
                                : <p className="mt-1 text-sm text-muted-foreground">{t('pages.employees.roles.no_activities')}</p>}
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                {t('pages.employees.roles.label_responsible_for')}
                            </p>
                            {role.responsible_activities.length > 0
                                ? <ActivityGroup activities={role.responsible_activities} />
                                : <p className="mt-1 text-sm text-muted-foreground">{t('pages.employees.roles.no_activities')}</p>}
                        </div>
                    </div>
                </SectionCard>
            ))}
        </div>
    );
}

// ─── Tab: Qualifications ──────────────────────────────────────────────────────

function QualificationsTab({ data, loading, error, t }: {
    data: QualificationsData | null;
    loading: boolean;
    error: string | null;
    t: (key: string) => string;
}) {
    if (loading) return <LoadingPlaceholder label={t('pages.employees.loading')} />;
    if (error) return <ErrorPlaceholder label={error} />;
    if (!data) return null;

    return (
        <div className="space-y-6">
            <SectionCard title={t('pages.employees.qualifications.my_qualifications')}>
                {data.achieved.length === 0
                    ? <p className="text-sm text-muted-foreground">{t('pages.employees.qualifications.no_qualifications')}</p>
                    : (
                        <ul className="space-y-3">
                            {data.achieved.map((q) => {
                                const status = expiryStatus(q.expires_at);
                                return (
                                    <li key={q.id} className="rounded-xl border border-border bg-card px-4 py-3">
                                        <p className="text-sm font-semibold text-foreground">{q.name}</p>
                                        <div className="mt-1 flex flex-wrap gap-2 text-xs">
                                            {q.finished_at ? (
                                                <span className="rounded bg-emerald-100 px-2 py-0.5 text-emerald-700">
                                                    ✓ {t('pages.employees.qualifications.label_achieved')} {q.finished_at}
                                                </span>
                                            ) : (
                                                <span className="rounded bg-destructive/10 px-2 py-0.5 text-destructive">
                                                    ✗ {t('pages.employees.qualifications.label_not_achieved')}
                                                </span>
                                            )}
                                            {q.planned_at && (
                                                <span className="rounded bg-blue-100 px-2 py-0.5 text-blue-700">
                                                    {t('pages.employees.qualifications.label_planned')} {q.planned_at}
                                                </span>
                                            )}
                                            {q.expires_at && (
                                                <span className={
                                                    status === 'expired'
                                                        ? 'rounded bg-destructive/10 px-2 py-0.5 text-destructive'
                                                        : status === 'soon'
                                                            ? 'rounded bg-amber-100 px-2 py-0.5 text-amber-700'
                                                            : 'rounded bg-muted px-2 py-0.5 text-muted-foreground'
                                                }>
                                                    {t('pages.employees.qualifications.label_expires')} {q.expires_at}
                                                </span>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
            </SectionCard>

            <SectionCard title={t('pages.employees.qualifications.missing_qualifications')}>
                {data.missing.length === 0
                    ? <p className="text-sm text-muted-foreground">{t('pages.employees.qualifications.no_missing')}</p>
                    : (
                        <ul className="space-y-2">
                            {data.missing.map((q) => (
                                <li key={q.id} className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                                    {q.name}
                                </li>
                            ))}
                        </ul>
                    )}
            </SectionCard>
        </div>
    );
}

// ─── Tab: Competences ─────────────────────────────────────────────────────────

function CompetencesTab({ data, loading, error, t }: {
    data: CompetenceEntry[] | null;
    loading: boolean;
    error: string | null;
    t: (key: string) => string;
}) {
    const [mandatoryOnly, setMandatoryOnly] = useState(true);

    if (loading) return <LoadingPlaceholder label={t('pages.employees.loading')} />;
    if (error) return <ErrorPlaceholder label={error} />;
    if (!data || data.length === 0)
        return <p className="text-sm text-muted-foreground">{t('pages.employees.competences.no_competences')}</p>;

    const visible = mandatoryOnly ? data.filter((c) => c.is_mandatory) : data;

    return (
        <div className="space-y-4">
            <label className="flex cursor-pointer items-center gap-2 text-sm font-medium text-foreground">
                <input
                    type="checkbox"
                    checked={mandatoryOnly}
                    onChange={(e) => setMandatoryOnly(e.target.checked)}
                    className="h-4 w-4 rounded border-border"
                />
                {t('pages.employees.competences.show_mandatory_only')}
            </label>

            {visible.length === 0
                ? <p className="text-sm text-muted-foreground">{t('pages.employees.competences.no_competences')}</p>
                : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full border-collapse text-sm">
                            <thead>
                                <tr className="bg-muted/60">
                                    <th className="border border-border p-2 text-left font-semibold">{t('pages.employees.column_name')}</th>
                                    <th className="border border-border p-2 text-left font-semibold">{t('pages.employees.competences.label_acceptable_level')}</th>
                                    <th className="border border-border p-2 text-left font-semibold">{t('pages.employees.competences.label_desired_level')}</th>
                                    <th className="border border-border p-2 text-left font-semibold">{t('pages.employees.competences.label_achieved_level')}</th>
                                    <th className="border border-border p-2 text-left font-semibold">{t('pages.employees.competences.label_evaluated')}</th>
                                    <th className="border border-border p-2 text-left font-semibold">{t('pages.employees.competences.label_evaluation_notes')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((c) => (
                                    <tr key={c.id} className="hover:bg-muted/30">
                                        <td className="border border-border p-2 font-medium">
                                            {c.name}
                                            {c.description && (
                                                <p className="mt-0.5 text-xs font-normal text-muted-foreground">{c.description}</p>
                                            )}
                                        </td>
                                        <td className={`border border-border p-2 ${!c.acceptable_ok ? 'text-destructive' : ''}`}>
                                            {c.acceptable_level_name ?? '—'}
                                        </td>
                                        <td className={`border border-border p-2 ${c.evaluated && !c.desired_ok ? 'text-amber-600' : ''}`}>
                                            {c.desired_level_name ?? '—'}
                                        </td>
                                        <td className={`border border-border p-2 ${!c.acceptable_ok ? 'text-destructive' : (c.evaluated && !c.desired_ok ? 'text-amber-600' : '')}`}>
                                            {c.achieved_level_name ?? '—'}
                                        </td>
                                        <td className={`border border-border p-2 ${!c.evaluated ? 'text-destructive' : 'text-emerald-700'}`}>
                                            {c.evaluated
                                                ? (c.updated_at ? `${c.updated_at}${c.updated_by ? ` (${c.updated_by})` : ''}` : '✓')
                                                : t('pages.employees.competences.label_not_evaluated')}
                                        </td>
                                        <td className="border border-border p-2 text-muted-foreground">{c.note ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
        </div>
    );
}

// ─── Tab: Responsibilities ────────────────────────────────────────────────────

function ResponsibilityGroup({ title, items }: { title: string; items: { id: number; name: string }[] }) {
    if (items.length === 0) return null;
    return (
        <div>
            <h4 className="mb-1 text-sm font-semibold text-foreground">{title}</h4>
            <ul className="space-y-1">
                {items.map((item) => (
                    <li key={item.id} className="text-sm text-foreground">{item.name}</li>
                ))}
            </ul>
        </div>
    );
}

function ResponsibilitiesTab({ data, loading, error, t }: {
    data: ResponsibilitiesData | null;
    loading: boolean;
    error: string | null;
    t: (key: string) => string;
}) {
    if (loading) return <LoadingPlaceholder label={t('pages.employees.loading')} />;
    if (error) return <ErrorPlaceholder label={error} />;
    if (!data) return null;

    const hasAny = (
        data.processes.length > 0 ||
        data.information_types.length > 0 ||
        data.assets.length > 0 ||
        data.customers.length > 0 ||
        data.suppliers.length > 0 ||
        data.controls.length > 0
    );

    if (!hasAny)
        return <p className="text-sm text-muted-foreground">{t('pages.employees.responsibilities.no_responsibilities')}</p>;

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <ResponsibilityGroup title={t('pages.employees.responsibilities.processes')} items={data.processes} />
            <ResponsibilityGroup title={t('pages.employees.responsibilities.customers')} items={data.customers} />
            <ResponsibilityGroup title={t('pages.employees.responsibilities.suppliers')} items={data.suppliers} />
            <ResponsibilityGroup title={t('pages.employees.responsibilities.information_types')} items={data.information_types} />
            <ResponsibilityGroup title={t('pages.employees.responsibilities.assets')} items={data.assets} />
            <ResponsibilityGroup title={t('pages.employees.responsibilities.controls')} items={data.controls} />
        </div>
    );
}

// ─── Employee detail dialog ───────────────────────────────────────────────────

interface EmployeeDialogProps {
    employeeId: number | null;
    onClose: () => void;
    t: (key: string) => string;
}

function EmployeeDialog({ employeeId, onClose, t }: EmployeeDialogProps) {
    const [activeTab, setActiveTab] = useState<EmployeeTab>('general_info');

    const [profileData, setProfileData] = useState<EmployeeData | null>(null);
    const [profileLoading, setProfileLoading] = useState(false);
    const [profileError, setProfileError] = useState<string | null>(null);

    const [rolesData, setRolesData] = useState<RoleData[] | null>(null);
    const [rolesLoading, setRolesLoading] = useState(false);
    const [rolesError, setRolesError] = useState<string | null>(null);

    const [qualificationsData, setQualificationsData] = useState<QualificationsData | null>(null);
    const [qualificationsLoading, setQualificationsLoading] = useState(false);
    const [qualificationsError, setQualificationsError] = useState<string | null>(null);

    const [competencesData, setCompetencesData] = useState<CompetenceEntry[] | null>(null);
    const [competencesLoading, setCompetencesLoading] = useState(false);
    const [competencesError, setCompetencesError] = useState<string | null>(null);

    const [responsibilitiesData, setResponsibilitiesData] = useState<ResponsibilitiesData | null>(null);
    const [responsibilitiesLoading, setResponsibilitiesLoading] = useState(false);
    const [responsibilitiesError, setResponsibilitiesError] = useState<string | null>(null);

    // Reset all data when a different employee is opened
    useEffect(() => {
        setActiveTab('general_info');
        setProfileData(null);
        setProfileError(null);
        setRolesData(null);
        setRolesError(null);
        setQualificationsData(null);
        setQualificationsError(null);
        setCompetencesData(null);
        setCompetencesError(null);
        setResponsibilitiesData(null);
        setResponsibilitiesError(null);
    }, [employeeId]);

    const loadProfile = useCallback(async () => {
        if (!employeeId || profileData) return;
        setProfileLoading(true);
        setProfileError(null);
        try {
            const res = await fetch(`/api/employees/${employeeId}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(t('pages.employees.error_loading'));
            setProfileData(await res.json() as EmployeeData);
        } catch (e) {
            setProfileError(e instanceof Error ? e.message : t('pages.employees.error_loading'));
        } finally {
            setProfileLoading(false);
        }
    }, [employeeId, profileData, t]);

    const loadRoles = useCallback(async () => {
        if (!employeeId || rolesData) return;
        setRolesLoading(true);
        setRolesError(null);
        try {
            const res = await fetch(`/api/employees/${employeeId}/roles`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(t('pages.employees.error_loading'));
            setRolesData(await res.json() as RoleData[]);
        } catch (e) {
            setRolesError(e instanceof Error ? e.message : t('pages.employees.error_loading'));
        } finally {
            setRolesLoading(false);
        }
    }, [employeeId, rolesData, t]);

    const loadQualifications = useCallback(async () => {
        if (!employeeId || qualificationsData) return;
        setQualificationsLoading(true);
        setQualificationsError(null);
        try {
            const res = await fetch(`/api/employees/${employeeId}/qualifications`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(t('pages.employees.error_loading'));
            setQualificationsData(await res.json() as QualificationsData);
        } catch (e) {
            setQualificationsError(e instanceof Error ? e.message : t('pages.employees.error_loading'));
        } finally {
            setQualificationsLoading(false);
        }
    }, [employeeId, qualificationsData, t]);

    const loadCompetences = useCallback(async () => {
        if (!employeeId || competencesData) return;
        setCompetencesLoading(true);
        setCompetencesError(null);
        try {
            const res = await fetch(`/api/employees/${employeeId}/competences`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(t('pages.employees.error_loading'));
            setCompetencesData(await res.json() as CompetenceEntry[]);
        } catch (e) {
            setCompetencesError(e instanceof Error ? e.message : t('pages.employees.error_loading'));
        } finally {
            setCompetencesLoading(false);
        }
    }, [employeeId, competencesData, t]);

    const loadResponsibilities = useCallback(async () => {
        if (!employeeId || responsibilitiesData) return;
        setResponsibilitiesLoading(true);
        setResponsibilitiesError(null);
        try {
            const res = await fetch(`/api/employees/${employeeId}/responsibilities`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(t('pages.employees.error_loading'));
            setResponsibilitiesData(await res.json() as ResponsibilitiesData);
        } catch (e) {
            setResponsibilitiesError(e instanceof Error ? e.message : t('pages.employees.error_loading'));
        } finally {
            setResponsibilitiesLoading(false);
        }
    }, [employeeId, responsibilitiesData, t]);

    useEffect(() => {
        if (!employeeId) return;
        if (activeTab === 'general_info') void loadProfile();
        if (activeTab === 'roles_and_tasks') void loadRoles();
        if (activeTab === 'qualifications') void loadQualifications();
        if (activeTab === 'competences') void loadCompetences();
        if (activeTab === 'responsibilities') void loadResponsibilities();
    }, [activeTab, employeeId, loadProfile, loadRoles, loadQualifications, loadCompetences, loadResponsibilities]);

    const tabs = useMemo(() => [
        { key: 'general_info' as const,     title: t('pages.employees.tabs.general_info') },
        { key: 'roles_and_tasks' as const,  title: t('pages.employees.tabs.roles_and_tasks') },
        { key: 'qualifications' as const,   title: t('pages.employees.tabs.qualifications') },
        { key: 'competences' as const,      title: t('pages.employees.tabs.competences') },
        { key: 'responsibilities' as const, title: t('pages.employees.tabs.responsibilities') },
    ], [t]);

    return (
        <Dialog open={employeeId !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {profileData?.name ?? t('pages.employees.dialog_title')}
                        {profileData?.title ? (
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                — {profileData.title}
                            </span>
                        ) : null}
                    </DialogTitle>
                </DialogHeader>

                <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as EmployeeTab)} className="mt-2">
                    <TabsList className="h-auto w-full flex-wrap justify-start gap-1 bg-muted/60 p-1">
                        {tabs.map((tab) => (
                            <TabsTrigger key={tab.key} value={tab.key}>
                                {tab.title}
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="general_info" className="mt-4 rounded-xl border border-border p-4">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">{t('pages.employees.tabs.general_info')}</h2>
                        <GeneralInfoTab data={profileData} loading={profileLoading} error={profileError} t={t} />
                    </TabsContent>

                    <TabsContent value="roles_and_tasks" className="mt-4 rounded-xl border border-border p-4">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">{t('pages.employees.tabs.roles_and_tasks')}</h2>
                        <RolesTab data={rolesData} loading={rolesLoading} error={rolesError} t={t} />
                    </TabsContent>

                    <TabsContent value="qualifications" className="mt-4 rounded-xl border border-border p-4">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">{t('pages.employees.tabs.qualifications')}</h2>
                        <QualificationsTab data={qualificationsData} loading={qualificationsLoading} error={qualificationsError} t={t} />
                    </TabsContent>

                    <TabsContent value="competences" className="mt-4 rounded-xl border border-border p-4">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">{t('pages.employees.tabs.competences')}</h2>
                        <CompetencesTab data={competencesData} loading={competencesLoading} error={competencesError} t={t} />
                    </TabsContent>

                    <TabsContent value="responsibilities" className="mt-4 rounded-xl border border-border p-4">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">{t('pages.employees.tabs.responsibilities')}</h2>
                        <ResponsibilitiesTab data={responsibilitiesData} loading={responsibilitiesLoading} error={responsibilitiesError} t={t} />
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

interface EmployeesPageProps {
    route: AppSectionRoute;
}

export default function EmployeesPage({ route }: EmployeesPageProps) {
    const { t } = useTranslations();
    const [selectedEmployeeId, setSelectedEmployeeId] = useState<number | null>(null);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.employees.title') });
        return () => { document.title = previousTitle; };
    }, [t]);

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/users',
        perPage: 25,
        defaultSort: 'name',
        canCreate: false,
        canEdit: false,
        canDelete: false,
        selectable: false,
        selectFields: ['id', 'name', 'title', 'enabled'],
        fields: [
            {
                key: 'name',
                label: t('pages.employees.column_name'),
                type: 'text',
                sortable: true,
                editable: false,
                masterLabel: true,
            },
            {
                key: 'title',
                label: t('pages.employees.column_title'),
                type: 'text',
                sortable: true,
                editable: false,
            },
            {
                key: 'enabled',
                label: t('pages.employees.column_enabled'),
                type: 'boolean',
                sortable: true,
                editable: false,
            },
        ],
        rowActions: [
            {
                key: 'view_details',
                label: t('pages.employees.action_open'),
                icon: <Users className="h-4 w-4" />,
                variant: 'outline',
                refreshOnComplete: false,
                onClick: (item) => setSelectedEmployeeId(item.id as number),
            },
        ],
    }), [t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                {/* Breadcrumb */}
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.employees.title')}</span>
                </nav>

                {/* Page header */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <Users className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.employees.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.employees.description')}
                            </p>
                        </div>
                    </div>
                </section>

                {/* Employee list */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {/* Employee detail dialog */}
            <EmployeeDialog
                employeeId={selectedEmployeeId}
                onClose={() => setSelectedEmployeeId(null)}
                t={t}
            />
        </AppLayout>
    );
}
