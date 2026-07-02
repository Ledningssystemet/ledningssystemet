import { useEffect, useMemo, useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { useTabData } from '@/hooks/useTabData';

// ─── Tab keys ────────────────────────────────────────────────────────────────
const TAB_KEYS = ['general_info', 'roles_and_tasks', 'qualifications', 'competences', 'responsibilities'] as const;
type EmployeeTab = (typeof TAB_KEYS)[number];

// ─── API types ────────────────────────────────────────────────────────────────
export interface EmployeeData { id: number; name: string; email: string; title: string | null; enabled: boolean; manager: { id: number; name: string } | null; departments: { id: number; name: string }[]; direct_reports: { id: number; name: string }[] }
export interface ProcessActivity { id: number; name: string; process_id: number; process_name: string }
export interface RoleData { id: number; name: string; description: string | null; authorities: string | null; accountable_activities: ProcessActivity[]; responsible_activities: ProcessActivity[] }
export interface QualificationsData { achieved: { id: number; name: string; finished_at: string | null; planned_at: string | null; expires_at: string | null }[]; missing: { id: number; name: string }[] }
export interface CompetenceEntry { id: number; name: string; description: string; is_mandatory: boolean; achieved_level_name: string | null; acceptable_level_name: string | null; desired_level_name: string | null; acceptable_ok: boolean; desired_ok: boolean; evaluated: boolean; note: string | null; updated_by: string | null; updated_at: string | null }
export interface ResponsibilitiesData { processes: { id: number; name: string }[]; information_types: { id: number; name: string }[]; assets: { id: number; name: string }[]; customers: { id: number; name: string }[]; suppliers: { id: number; name: string }[]; controls: { id: number; name: string }[] }

// ─── Helpers ─────────────────────────────────────────────────────────────────
function expiryStatus(expiresAt: string | null): 'expired' | 'soon' | 'ok' | null {
    if (!expiresAt) return null;
    const ms = new Date(expiresAt).getTime() - Date.now();
    if (ms < 0) return 'expired';
    if (ms < 30 * 24 * 60 * 60 * 1000) return 'soon';
    return 'ok';
}

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
    return <p className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">{label}</p>;
}

// ─── Props ────────────────────────────────────────────────────────────────────
export interface EmployeeDialogProps {
    employeeId: number | null;
    onClose: () => void;
    t: (key: string) => string;
}

// ─── Component ───────────────────────────────────────────────────────────────
export function EmployeeDialog({ employeeId, onClose, t }: EmployeeDialogProps) {
    const [activeTab, setActiveTab] = useState<EmployeeTab>('general_info');

    const base = employeeId ? `/api/employees/${employeeId}` : null;
    const { data: profileData, loading: profileLoading, error: profileError } = useTabData<EmployeeData>(activeTab === 'general_info' ? base : null);
    const { data: rolesData, loading: rolesLoading, error: rolesError } = useTabData<RoleData[]>(activeTab === 'roles_and_tasks' && employeeId ? `${base}/roles` : null);
    const { data: qualData, loading: qualLoading, error: qualError } = useTabData<QualificationsData>(activeTab === 'qualifications' && employeeId ? `${base}/qualifications` : null);
    const { data: compData, loading: compLoading, error: compError } = useTabData<CompetenceEntry[]>(activeTab === 'competences' && employeeId ? `${base}/competences` : null);
    const { data: respData, loading: respLoading, error: respError } = useTabData<ResponsibilitiesData>(activeTab === 'responsibilities' && employeeId ? `${base}/responsibilities` : null);

    // Reset tab when switching employee
    useEffect(() => { setActiveTab('general_info'); }, [employeeId]);

    const tabs = useMemo(() => [
        { key: 'general_info' as const, title: t('pages.employees.tabs.general_info') },
        { key: 'roles_and_tasks' as const, title: t('pages.employees.tabs.roles_and_tasks') },
        { key: 'qualifications' as const, title: t('pages.employees.tabs.qualifications') },
        { key: 'competences' as const, title: t('pages.employees.tabs.competences') },
        { key: 'responsibilities' as const, title: t('pages.employees.tabs.responsibilities') },
    ], [t]);

    return (
        <Dialog open={employeeId !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {profileData?.name ?? t('pages.employees.dialog_title')}
                        {profileData?.title && (
                            <span className="ml-2 text-sm font-normal text-muted-foreground">— {profileData.title}</span>
                        )}
                    </DialogTitle>
                </DialogHeader>
                <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as EmployeeTab)} className="mt-2">
                    <TabsList className="h-auto w-full flex-wrap justify-start gap-1 bg-muted/60 p-1">
                        {tabs.map((tab) => <TabsTrigger key={tab.key} value={tab.key}>{tab.title}</TabsTrigger>)}
                    </TabsList>

                    {/* General Info */}
                    <TabsContent value="general_info" className="mt-4 rounded-xl border border-border p-4">
                        <h2 className="mb-4 text-lg font-semibold">{t('pages.employees.tabs.general_info')}</h2>
                        {profileLoading ? <LoadingPlaceholder label={t('pages.employees.loading')} />
                            : profileError ? <ErrorPlaceholder label={profileError} />
                            : profileData && (
                                <div className="space-y-3">
                                    <InfoRow label={t('pages.employees.column_name')}>{profileData.name}</InfoRow>
                                    <InfoRow label="Email">{profileData.email || '—'}</InfoRow>
                                    <InfoRow label={t('pages.employees.general.label_title')}>{profileData.title || '—'}</InfoRow>
                                    <InfoRow label={t('pages.employees.general.label_active')}>{profileData.enabled ? '✓' : '✗'}</InfoRow>
                                    <InfoRow label={t('pages.employees.general.label_departments')}>
                                        {profileData.departments.length > 0 ? profileData.departments.map((d) => d.name).join(', ') : t('pages.employees.general.no_departments')}
                                    </InfoRow>
                                    <InfoRow label={t('pages.employees.general.label_manager')}>
                                        {profileData.manager ? profileData.manager.name : t('pages.employees.general.no_manager')}
                                    </InfoRow>
                                    {profileData.direct_reports.length > 0 && (
                                        <InfoRow label={t('pages.employees.general.label_direct_reports')}>
                                            {profileData.direct_reports.map((u) => u.name).join(', ')}
                                        </InfoRow>
                                    )}
                                </div>
                            )}
                    </TabsContent>

                    {/* Roles & Tasks */}
                    <TabsContent value="roles_and_tasks" className="mt-4 rounded-xl border border-border p-4">
                        <h2 className="mb-4 text-lg font-semibold">{t('pages.employees.tabs.roles_and_tasks')}</h2>
                        {rolesLoading ? <LoadingPlaceholder label={t('pages.employees.loading')} />
                            : rolesError ? <ErrorPlaceholder label={rolesError} />
                            : !rolesData || rolesData.length === 0 ? <p className="text-sm text-muted-foreground">{t('pages.employees.roles.no_roles')}</p>
                            : (
                                <div className="space-y-4">
                                    {rolesData.map((role) => (
                                        <SectionCard key={role.id} title={role.name}>
                                            {role.description && <p className="mb-3 text-sm text-muted-foreground">{role.description}</p>}
                                            {role.authorities && (
                                                <div className="mb-3 rounded-xl bg-muted/50 px-4 py-3">
                                                    <p className="text-xs font-medium uppercase text-muted-foreground">{t('pages.employees.roles.label_authorities')}</p>
                                                    <p className="mt-0.5 text-sm">{role.authorities}</p>
                                                </div>
                                            )}
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <p className="text-xs font-medium uppercase text-muted-foreground">{t('pages.employees.roles.label_accountable_for')}</p>
                                                    {role.accountable_activities.length > 0
                                                        ? <ul className="mt-1 space-y-1 pl-3">{role.accountable_activities.map((a) => <li key={a.id} className="text-sm">{a.process_name}: {a.name}</li>)}</ul>
                                                        : <p className="mt-1 text-sm text-muted-foreground">{t('pages.employees.roles.no_activities')}</p>}
                                                </div>
                                                <div>
                                                    <p className="text-xs font-medium uppercase text-muted-foreground">{t('pages.employees.roles.label_responsible_for')}</p>
                                                    {role.responsible_activities.length > 0
                                                        ? <ul className="mt-1 space-y-1 pl-3">{role.responsible_activities.map((a) => <li key={a.id} className="text-sm">{a.process_name}: {a.name}</li>)}</ul>
                                                        : <p className="mt-1 text-sm text-muted-foreground">{t('pages.employees.roles.no_activities')}</p>}
                                                </div>
                                            </div>
                                        </SectionCard>
                                    ))}
                                </div>
                            )}
                    </TabsContent>

                    {/* Qualifications */}
                    <TabsContent value="qualifications" className="mt-4 rounded-xl border border-border p-4">
                        <h2 className="mb-4 text-lg font-semibold">{t('pages.employees.tabs.qualifications')}</h2>
                        {qualLoading ? <LoadingPlaceholder label={t('pages.employees.loading')} />
                            : qualError ? <ErrorPlaceholder label={qualError} />
                            : qualData && (
                                <div className="space-y-6">
                                    <SectionCard title={t('pages.employees.qualifications.my_qualifications')}>
                                        {qualData.achieved.length === 0
                                            ? <p className="text-sm text-muted-foreground">{t('pages.employees.qualifications.no_qualifications')}</p>
                                            : (
                                                <ul className="space-y-3">
                                                    {qualData.achieved.map((q) => {
                                                        const status = expiryStatus(q.expires_at);
                                                        return (
                                                            <li key={q.id} className="rounded-xl border border-border bg-card px-4 py-3">
                                                                <p className="text-sm font-semibold">{q.name}</p>
                                                                <div className="mt-1 flex flex-wrap gap-2 text-xs">
                                                                    {q.finished_at
                                                                        ? <span className="rounded bg-emerald-100 px-2 py-0.5 text-emerald-700">✓ {t('pages.employees.qualifications.label_achieved')} {q.finished_at}</span>
                                                                        : <span className="rounded bg-destructive/10 px-2 py-0.5 text-destructive">✗ {t('pages.employees.qualifications.label_not_achieved')}</span>}
                                                                    {q.planned_at && <span className="rounded bg-blue-100 px-2 py-0.5 text-blue-700">{t('pages.employees.qualifications.label_planned')} {q.planned_at}</span>}
                                                                    {q.expires_at && (
                                                                        <span className={
                                                                            status === 'expired' ? 'rounded bg-destructive/10 px-2 py-0.5 text-destructive'
                                                                            : status === 'soon' ? 'rounded bg-amber-100 px-2 py-0.5 text-amber-700'
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
                                        {qualData.missing.length === 0
                                            ? <p className="text-sm text-muted-foreground">{t('pages.employees.qualifications.no_missing')}</p>
                                            : <ul className="space-y-2">{qualData.missing.map((q) => <li key={q.id} className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive">{q.name}</li>)}</ul>}
                                    </SectionCard>
                                </div>
                            )}
                    </TabsContent>

                    {/* Competences */}
                    <TabsContent value="competences" className="mt-4 rounded-xl border border-border p-4">
                        <h2 className="mb-4 text-lg font-semibold">{t('pages.employees.tabs.competences')}</h2>
                        {compLoading ? <LoadingPlaceholder label={t('pages.employees.loading')} />
                            : compError ? <ErrorPlaceholder label={compError} />
                            : !compData || compData.length === 0 ? <p className="text-sm text-muted-foreground">{t('pages.employees.competences.no_competences')}</p>
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
                                            {compData.filter((c) => c.is_mandatory).map((c) => (
                                                <tr key={c.id} className="hover:bg-muted/30">
                                                    <td className="border border-border p-2 font-medium">
                                                        {c.name}
                                                        {c.description && <p className="mt-0.5 text-xs font-normal text-muted-foreground">{c.description}</p>}
                                                    </td>
                                                    <td className={`border border-border p-2 ${!c.acceptable_ok ? 'text-destructive' : ''}`}>{c.acceptable_level_name ?? '—'}</td>
                                                    <td className={`border border-border p-2 ${c.evaluated && !c.desired_ok ? 'text-amber-600' : ''}`}>{c.desired_level_name ?? '—'}</td>
                                                    <td className={`border border-border p-2 ${!c.acceptable_ok ? 'text-destructive' : c.evaluated && !c.desired_ok ? 'text-amber-600' : ''}`}>{c.achieved_level_name ?? '—'}</td>
                                                    <td className={`border border-border p-2 ${!c.evaluated ? 'text-destructive' : 'text-emerald-700'}`}>
                                                        {c.evaluated ? (c.updated_at ? `${c.updated_at}${c.updated_by ? ` (${c.updated_by})` : ''}` : '✓') : t('pages.employees.competences.label_not_evaluated')}
                                                    </td>
                                                    <td className="border border-border p-2 text-muted-foreground">{c.note ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                    </TabsContent>

                    {/* Responsibilities */}
                    <TabsContent value="responsibilities" className="mt-4 rounded-xl border border-border p-4">
                        <h2 className="mb-4 text-lg font-semibold">{t('pages.employees.tabs.responsibilities')}</h2>
                        {respLoading ? <LoadingPlaceholder label={t('pages.employees.loading')} />
                            : respError ? <ErrorPlaceholder label={respError} />
                            : respData && (
                                <div className="grid gap-6 sm:grid-cols-2">
                                    {respData.processes.length > 0 && <div><h4 className="mb-1 text-sm font-semibold">{t('pages.employees.responsibilities.processes')}</h4><ul className="space-y-1">{respData.processes.map((i) => <li key={i.id} className="text-sm">{i.name}</li>)}</ul></div>}
                                    {respData.customers.length > 0 && <div><h4 className="mb-1 text-sm font-semibold">{t('pages.employees.responsibilities.customers')}</h4><ul className="space-y-1">{respData.customers.map((i) => <li key={i.id} className="text-sm">{i.name}</li>)}</ul></div>}
                                    {respData.suppliers.length > 0 && <div><h4 className="mb-1 text-sm font-semibold">{t('pages.employees.responsibilities.suppliers')}</h4><ul className="space-y-1">{respData.suppliers.map((i) => <li key={i.id} className="text-sm">{i.name}</li>)}</ul></div>}
                                    {respData.information_types.length > 0 && <div><h4 className="mb-1 text-sm font-semibold">{t('pages.employees.responsibilities.information_types')}</h4><ul className="space-y-1">{respData.information_types.map((i) => <li key={i.id} className="text-sm">{i.name}</li>)}</ul></div>}
                                    {respData.assets.length > 0 && <div><h4 className="mb-1 text-sm font-semibold">{t('pages.employees.responsibilities.assets')}</h4><ul className="space-y-1">{respData.assets.map((i) => <li key={i.id} className="text-sm">{i.name}</li>)}</ul></div>}
                                    {respData.controls.length > 0 && <div><h4 className="mb-1 text-sm font-semibold">{t('pages.employees.responsibilities.controls')}</h4><ul className="space-y-1">{respData.controls.map((i) => <li key={i.id} className="text-sm">{i.name}</li>)}</ul></div>}
                                </div>
                            )}
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}

