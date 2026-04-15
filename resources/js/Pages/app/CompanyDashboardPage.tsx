import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import AppLayout from '@/layouts/AppLayout';
import { APP_HOME_PATH, type AppSectionRoute } from '@/app/routes';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslations } from '@/hooks/useTranslations';
import { useDashboardData } from '@/hooks/useDashboardData';
import StatsRow from '@/components/dashboard/StatsRow';
import TaskList from '@/components/dashboard/TaskList';
import GoalsCard from '@/components/dashboard/GoalsCard';
import TopRisks from '@/components/dashboard/TopRisks';
import RiskOverview from '@/components/dashboard/RiskOverview';
import ProcessCard from '@/components/dashboard/ProcessCard';

const TAB_KEYS = ['todo', 'risk_overview', 'process_metrics', 'system_utilization'] as const;

type CompanyDashboardTab = (typeof TAB_KEYS)[number];

function isCompanyDashboardTab(value: string): value is CompanyDashboardTab {
    return TAB_KEYS.includes(value as CompanyDashboardTab);
}

function getTabFromHash(hash: string): CompanyDashboardTab {
    const normalized = hash.replace(/^#/, '');
    return isCompanyDashboardTab(normalized) ? normalized : 'todo';
}

interface CompanyDashboardPageProps {
    route: AppSectionRoute;
}

export default function CompanyDashboardPage({ route }: CompanyDashboardPageProps) {
    const { t } = useTranslations();
    const [activeTab, setActiveTab] = useState<CompanyDashboardTab>(() => getTabFromHash(window.location.hash));
    const { data, loading, error, setSelectedProcessId } = useDashboardData();

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: route.label });

        return () => {
            document.title = previousTitle;
        };
    }, [route.label, t]);

    useEffect(() => {
        const onHashChange = () => setActiveTab(getTabFromHash(window.location.hash));
        window.addEventListener('hashchange', onHashChange);

        return () => {
            window.removeEventListener('hashchange', onHashChange);
        };
    }, []);

    const widgetError = error ? t('pages.dashboard.shared.load_error') : null;
    const widgetProps = { data, loading, error: widgetError, setSelectedProcessId };

    const taskSummary = useMemo(() => {
        const overdue = data.tasks.filter((task) => task.status === 'overdue').length;
        const upcoming = data.tasks.filter((task) => task.status === 'upcoming').length;
        const done = data.tasks.filter((task) => task.status === 'done').length;

        return { overdue, upcoming, done };
    }, [data.tasks]);

    const goalSummary = useMemo(() => {
        const achieved = data.goals.filter((goal) => goal.status === 'achieved').length;
        const acceptable = data.goals.filter((goal) => goal.status === 'acceptable').length;
        const unacceptable = data.goals.filter((goal) => goal.status === 'unacceptable').length;

        return { achieved, acceptable, unacceptable };
    }, [data.goals]);

    const tabs = useMemo(() => [
        { key: 'todo' as const, title: t('pages.company_dashboard.tabs.todo') },
        { key: 'risk_overview' as const, title: t('pages.company_dashboard.tabs.risk_overview') },
        { key: 'process_metrics' as const, title: t('pages.company_dashboard.tabs.process_metrics') },
        { key: 'system_utilization' as const, title: t('pages.company_dashboard.tabs.system_utilization') },
    ], [t]);

    const handleTabChange = (tab: string) => {
        if (!isCompanyDashboardTab(tab)) {
            return;
        }

        setActiveTab(tab);
        window.history.replaceState(null, '', `#${tab}`);
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{route.label}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">{route.label}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {route.description ?? t('pages.company_dashboard.description')}
                    </p>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <Tabs value={activeTab} onValueChange={handleTabChange}>
                        <TabsList className="h-auto w-full flex-wrap justify-start gap-1 bg-muted/60 p-1">
                            {tabs.map((tab) => (
                                <TabsTrigger key={tab.key} value={tab.key}>
                                    {tab.title}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        <TabsContent value="todo" className="mt-4 space-y-4">
                            <StatsRow {...widgetProps} />
                            <div className="grid gap-4 lg:grid-cols-3">
                                <div className="lg:col-span-1">
                                    <TaskList {...widgetProps} />
                                </div>
                                <div className="lg:col-span-1">
                                    <GoalsCard {...widgetProps} />
                                </div>
                                <div className="lg:col-span-1">
                                    <TopRisks {...widgetProps} />
                                </div>
                            </div>
                        </TabsContent>

                        <TabsContent value="risk_overview" className="mt-4">
                            <div className="grid gap-4 lg:grid-cols-2">
                                <RiskOverview {...widgetProps} />
                                <TopRisks {...widgetProps} />
                            </div>
                        </TabsContent>

                        <TabsContent value="process_metrics" className="mt-4 space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div className="rounded-xl border border-border bg-card p-4">
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {t('pages.company_dashboard.metrics.avg_risk_score')}
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold text-foreground">{data.stats.averageRiskScore}</p>
                                </div>
                                <div className="rounded-xl border border-border bg-card p-4">
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {t('pages.company_dashboard.metrics.completed_goals')}
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold text-foreground">
                                        {data.stats.completedGoals}/{data.stats.totalGoals}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-border bg-card p-4">
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {t('pages.company_dashboard.metrics.visible_processes')}
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold text-foreground">{data.processOptions.length}</p>
                                </div>
                            </div>
                            <div className="min-h-[28rem]">
                                <ProcessCard {...widgetProps} />
                            </div>
                        </TabsContent>

                        <TabsContent value="system_utilization" className="mt-4">
                            <div className="grid gap-4 lg:grid-cols-2">
                                <section className="rounded-xl border border-border bg-card p-4">
                                    <h2 className="text-base font-semibold text-foreground">
                                        {t('pages.company_dashboard.system.workload_title')}
                                    </h2>
                                    <div className="mt-3 grid grid-cols-3 gap-3">
                                        <div className="rounded-lg bg-muted/40 px-3 py-2 text-center">
                                            <div className="text-xl font-semibold text-destructive">{taskSummary.overdue}</div>
                                            <div className="text-xs text-muted-foreground">{t('pages.company_dashboard.system.overdue')}</div>
                                        </div>
                                        <div className="rounded-lg bg-muted/40 px-3 py-2 text-center">
                                            <div className="text-xl font-semibold text-foreground">{taskSummary.upcoming}</div>
                                            <div className="text-xs text-muted-foreground">{t('pages.company_dashboard.system.upcoming')}</div>
                                        </div>
                                        <div className="rounded-lg bg-muted/40 px-3 py-2 text-center">
                                            <div className="text-xl font-semibold text-emerald-600">{taskSummary.done}</div>
                                            <div className="text-xs text-muted-foreground">{t('pages.company_dashboard.system.done')}</div>
                                        </div>
                                    </div>
                                </section>

                                <section className="rounded-xl border border-border bg-card p-4">
                                    <h2 className="text-base font-semibold text-foreground">
                                        {t('pages.company_dashboard.system.improvement_title')}
                                    </h2>
                                    <div className="mt-3 grid grid-cols-3 gap-3">
                                        <div className="rounded-lg bg-muted/40 px-3 py-2 text-center">
                                            <div className="text-xl font-semibold text-emerald-600">{goalSummary.achieved}</div>
                                            <div className="text-xs text-muted-foreground">{t('pages.company_dashboard.system.achieved')}</div>
                                        </div>
                                        <div className="rounded-lg bg-muted/40 px-3 py-2 text-center">
                                            <div className="text-xl font-semibold text-amber-600">{goalSummary.acceptable}</div>
                                            <div className="text-xs text-muted-foreground">{t('pages.company_dashboard.system.acceptable')}</div>
                                        </div>
                                        <div className="rounded-lg bg-muted/40 px-3 py-2 text-center">
                                            <div className="text-xl font-semibold text-destructive">{goalSummary.unacceptable}</div>
                                            <div className="text-xs text-muted-foreground">{t('pages.company_dashboard.system.unacceptable')}</div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </TabsContent>
                    </Tabs>
                </section>
            </div>
        </AppLayout>
    );
}
