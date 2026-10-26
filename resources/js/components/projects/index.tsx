import React, { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { canWriteProject, collection, configuration, confirmAction, errorMessage, item, nameOf, projectList, projectMeetingLink, t, type Action, type Configuration, type Project, type ProjectPermissions, type ProjectSummary, type Risk, type Run, type WorkPackage } from './api';
import { Overview, ProjectForm, ProjectHeader, Risks, Settings, Team } from './Views';
import { ProjectActions } from './ActionPlanPicker';
import { WorkPackages } from './WorkPackages';
import { Gantt } from './Gantt';
import { PersonalGantt } from './PersonalGantt';
import { AnnualWheel } from './AnnualWheel';
import { Meetings } from './Meetings';
import { Badge, Btn, Card, Icon, Input, Label, Options, SectionTitle, Select } from '../ui';
// @ts-ignore
import './projects.css';

export function Projects(permissions: ProjectPermissions) {
    return <ProjectsView config={permissions} />;
}

export function ProjectsView({ config: initialConfig }: { config: Configuration | ProjectPermissions }) {
    const initialLink = projectMeetingLink(typeof window !== 'undefined' ? window.location?.search ?? '' : '');
    const [projects, setProjects] = useState<ProjectSummary[]>([]);
    const [selectedId, setSelectedId] = useState<number | null>(() => initialLink?.projectId ?? null);
    const [projectDetail, setProjectDetail] = useState<Project | null>(null);
    const [risks, setRisks] = useState<Risk[]>([]);
    const [actions, setActions] = useState<Action[]>([]);
    const [tab, setTab] = useState(initialLink ? 'meetings' : 'overview');
    const [creating, setCreating] = useState(false);
    const [search, setSearch] = useState('');
    const [responsible, setResponsible] = useState('');
    const [archived, setArchived] = useState(false);
    const [mine, setMine] = useState(false);
    const [loading, setLoading] = useState(true);
    const [detailLoading, setDetailLoading] = useState(false);
    const detailProjectRef = useRef<number | null>(null);
    const [busy, setBusy] = useState(false);
    const busyRef = useRef(false);
    const [error, setError] = useState<string | null>(null);
    const [detailError, setDetailError] = useState<string | null>(null);
    const [revision, setRevision] = useState(0);
    const [workPackages, setWorkPackages] = useState<WorkPackage[]>([]);
    const [detailConfig, setDetailConfig] = useState<Configuration | null>('users' in initialConfig ? initialConfig : null);
    const [configError, setConfigError] = useState<string | null>(null);
    const [configAttempt, setConfigAttempt] = useState(0);
    const [meetingLink, setMeetingLink] = useState(initialLink);
    const [visualization, setVisualization] = useState('gantt');
    const onMeetingLinkOpened = useCallback(() => setMeetingLink(null), []);
    const meetingDirty = useRef(false);
    const onMeetingDirty = useCallback((dirty: boolean) => { meetingDirty.current = dirty; }, []);
    const navigate = (operation: () => void) => {
        if (meetingDirty.current) confirmAction('Discard unsaved meeting changes?', () => { meetingDirty.current = false; operation(); }, 'Unsaved changes');
        else operation();
    };
    const responsibleUsers = Array.from(new Map(projects
        .flatMap(project => project.responsible_user_id !== null && project.responsible_user_name
            ? [[project.responsible_user_id, { id: project.responsible_user_id, name: project.responsible_user_name }] as const]
            : [])).values())
        .sort((a, b) => a.name.localeCompare(b.name));
    const config: Configuration = {
        ...initialConfig,
        ...(detailConfig ?? {}),
        users: detailConfig?.users ?? responsibleUsers,
        projectTypes: detailConfig?.projectTypes ?? [],
        riskOwners: detailConfig?.riskOwners ?? [],
        controls: detailConfig?.controls ?? [],
        probabilities: detailConfig?.probabilities ?? [],
        consequences: detailConfig?.consequences ?? [],
        riskMappings: detailConfig?.riskMappings ?? [],
        canReadRisks: true, canCreateRisk: true,
        canReadActions: true, canCreateAction: true,
        canReadWorkPackages: true, canCreateWorkPackage: true,
    };

    useEffect(() => {
        if ((!selectedId && !creating) || detailConfig) return;
        const controller = new AbortController();
        setConfigError(null);
        configuration(initialConfig, controller.signal)
            .then(result => { if (!controller.signal.aborted) setDetailConfig(result); })
            .catch(error => { if (!axios.isCancel(error)) setConfigError(errorMessage(error)); });
        return () => controller.abort();
    }, [selectedId, creating, detailConfig, initialConfig, configAttempt]);

    const reload = useCallback(async (signal?: AbortSignal) => {
        const items = await projectList(signal);
        setProjects(items);
        setSelectedId(id => id && items.some(item => item.id === id) ? id : null);
    }, []);

    useEffect(() => {
        const controller = new AbortController();
        reload(controller.signal).catch(error => { if (!axios.isCancel(error)) setError(errorMessage(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [reload]);

    useEffect(() => {
        if (!loading && meetingLink && !projects.some(project => project.id === meetingLink.projectId)) {
            setError(t('The mentioned project is no longer available'));
        }
    }, [loading, projects, meetingLink]);

    useEffect(() => {
        const controller = new AbortController();
        if (detailProjectRef.current !== selectedId) {
            setRisks([]);
            setActions([]);
            setWorkPackages([]);
            setProjectDetail(null);
            detailProjectRef.current = selectedId;
        }
        setDetailError(null);
        if (!selectedId) {
            setDetailLoading(false);
            return () => controller.abort();
        }
        setDetailLoading(true);
        Promise.all([
            item<Project>('Project', selectedId, controller.signal),
            config.canReadRisks ? collection<Risk>('ProjectRisk', { 'filter[project_id]': selectedId }, controller.signal) : Promise.resolve([]),
            config.canReadActions ? collection<Action>('ProjectActivity', { 'filter[project_id]': selectedId, sort: 'due' }, controller.signal) : Promise.resolve([]),
            config.canReadWorkPackages ? collection<WorkPackage>('ProjectWorkPackage', { 'filter[project_id]': selectedId, sort: 'name' }, controller.signal) : Promise.resolve([]),
        ]).then(([projectItem, riskItems, actionItems, packageItems]) => {
            setProjectDetail(projectItem);
            setRisks(riskItems);
            setActions(actionItems);
            setWorkPackages(packageItems);
        }).catch(error => { if (!axios.isCancel(error)) setDetailError(errorMessage(error)); })
            .finally(() => { if (!controller.signal.aborted) setDetailLoading(false); });
        return () => controller.abort();
    }, [selectedId, revision, config.canReadRisks, config.canReadActions, config.canReadWorkPackages]);

    const refresh = async () => {
        setLoading(true);
        setError(null);
        try {
            await reload();
            setRevision(value => value + 1);
        } catch (error) {
            setError(errorMessage(error));
        } finally {
            setLoading(false);
        }
    };
    const run: Run = async operation => {
        if (busyRef.current) return false;
        busyRef.current = true;
        setBusy(true);
        setError(null);
        try {
            await operation();
        } catch (error) {
            setError(errorMessage(error));
            busyRef.current = false;
            setBusy(false);
            return false;
        }
        try {
            await reload();
        } catch (error) {
            setError(`${t('Changes saved, but the view could not be refreshed.')} ${errorMessage(error)}`);
        } finally {
            setRevision(value => value + 1);
            busyRef.current = false;
            setBusy(false);
        }
        return true;
    };
    const selectedProject = projects.find(item => item.id === selectedId);
    const project = projectDetail?.id === selectedId ? {
        ...projectDetail,
        archived_at: selectedProject ? selectedProject.archived_at : projectDetail.archived_at,
        users: selectedProject?.users ?? projectDetail.users,
        access: {
            ...projectDetail.access,
            write: selectedProject ? canWriteProject(selectedProject, config.currentUserId) : false,
        },
    } : null;
    const headerProject = project ?? projects.find(item => item.id === selectedId);
    const visible = projects.filter(item => (archived || !item.archived_at)
        && (!mine || item.responsible_user_id === config.currentUserId)
        && (!responsible || item.responsible_user_id === Number(responsible))
        && [item.name, item.description].some(value => value?.toLocaleLowerCase().includes(search.toLocaleLowerCase())));
    return <div className="projects-module">
        <fieldset disabled={busy} className="border-0 p-0 m-0">
            <ProjectHeader project={headerProject} projects={projects} config={config} tab={tab} loading={loading || detailLoading} onSelect={id => navigate(() => { setSelectedId(id); setTab('overview'); setCreating(false); })} onTab={next => { if (next !== tab) navigate(() => setTab(next)); }} />
            <div className="pm-body">
                {error && <div className="alert alert-danger" role="alert">{error} <Btn variant="outline" disabled={busy} onClick={() => void refresh()}>{t('Retry')}</Btn></div>}
                {(selectedId || creating) && configError && <div className="alert alert-danger" role="alert">{configError} <Btn variant="outline" onClick={() => setConfigAttempt(value => value + 1)}>{t('Retry')}</Btn></div>}
                {busy && <div className="text-muted mb-3" role="status">{t('Saving…')}</div>}
                {loading ? <p role="status">{t('Loading data…')}</p> : selectedId ? <>
                {(detailLoading || (!project && !detailError) || (!detailConfig && !configError)) && <p role="status">{t('Loading data…')}</p>}
                {detailError && <div className="alert alert-danger" role="alert">{detailError} <Btn variant="outline" onClick={() => setRevision(value => value + 1)}>{t('Retry')}</Btn></div>}
                {project && detailConfig && !detailLoading && !detailError && <div>
                    {tab === 'overview' && <Overview project={project} risks={risks} actions={actions} config={config} />}
                    {tab === 'risks' && <Risks key={project.id} project={project} risks={risks} actions={actions} config={config} run={run} />}
                    {tab === 'actions' && <ProjectActions key={project.id} project={project} actions={actions} risks={risks} workPackages={workPackages} config={config} run={run} />}
                    {tab === 'work-packages' && config.canReadWorkPackages && <WorkPackages key={project.id} project={project} workPackages={workPackages} actions={actions} config={config} run={run} />}
                    {tab === 'gantt' && (config.canReadWorkPackages || config.canReadActions) && <Gantt project={project} workPackages={workPackages} actions={actions} config={config} />}
                    {tab === 'meetings' && <Meetings key={project.id} project={project} config={config} onDirty={onMeetingDirty}
                        initialLink={meetingLink?.projectId === project.id ? meetingLink : null} onLinkOpened={onMeetingLinkOpened}
                        onActivityChanged={activity => setActions(previous => previous.some(item => item.id === activity.id)
                            ? previous.map(item => item.id === activity.id ? activity : item) : [...previous, activity])} />}
                    {tab === 'team' && <Team key={project.id} project={project} config={config} run={run} />}
                    {tab === 'settings' && <Settings key={project.id} project={project} config={config} run={run} />}
                </div>}
                </> : <>
                <div className="d-flex justify-content-end mb-2">
                    <div className="pm-visualization-buttons" role="group" aria-label={t('Project visualization')}>
                        <Btn variant="ghost" aria-pressed={visualization === 'none'} onClick={() => setVisualization('none')}>{t('No overview')}</Btn>
                        <Btn variant="ghost" aria-pressed={visualization === 'gantt'} onClick={() => setVisualization('gantt')}>{t('Gantt chart')}</Btn>
                        <Btn variant="ghost" aria-pressed={visualization === 'year-wheel'} onClick={() => setVisualization('year-wheel')}>{t('Year wheel')}</Btn>
                    </div>
                </div>
                {visualization === 'gantt' && <PersonalGantt projects={projects} config={config} />}
                {visualization === 'year-wheel' && <AnnualWheel projects={projects} config={config} />}
                <Card className="pm-project-list">
                    <div className="pm-list-toolbar">
                        <div className="pm-eyebrow">{t('Overview')}</div>
                        <SectionTitle title={t('Projects')} action={config.canCreateProject && <Btn onClick={() => setCreating(!creating)}><Icon name="add" />{t('Add new project')}</Btn>} />
                        {creating && (detailConfig
                            ? <ProjectForm config={config} run={run} onSaved={() => setCreating(false)} onCancel={() => setCreating(false)} />
                            : !configError && <p role="status">{t('Loading data…')}</p>)}
                        <div className="row mt-3">
                            <div className="col-md-6"><Label text={t('Search')}><Input type="search" value={search} onChange={event => setSearch(event.target.value)} /></Label></div>
                            <div className="col-md-6"><Label text={t('Responsible user')}><Select value={responsible} onChange={event => setResponsible(event.target.value)}><option value="">{t('Show all')}</option><Options empty={false} items={config.users.filter(user => projects.some(item => item.responsible_user_id === user.id))} /></Select></Label></div>
                        </div>
                        <div className="pm-list-toggles">
                            <label><input className="form-check-input me-2" type="checkbox" checked={archived} onChange={event => setArchived(event.target.checked)} />{t('Show archived')}</label>
                            <label><input className="form-check-input me-2" type="checkbox" checked={mine} onChange={event => setMine(event.target.checked)} />{t('Show only my projects')}</label>
                        </div>
                    </div>
                    <ul className="pm-project-rows" aria-label={t('Projects')}>
                    {visible.map(item => <li key={item.id} className="pm-project-row">
                        <div className="pm-project-row-icon"><Icon name="folder_open" /></div>
                        <div className="pm-project-row-content">
                            <h2 className="pm-project-row-title"><button className="pm-project-link" type="button" onClick={() => { setSelectedId(item.id); setTab('overview'); }}>{item.name}<Icon name="arrow_forward" /></button></h2>
                            <div className="pm-project-row-meta">{nameOf(config.users, item.responsible_user_id)} · {item.start_date}{item.end_date ? ` – ${item.end_date}` : ''}</div>
                            {item.description && <div className="pm-project-row-description text-break" style={{ whiteSpace: 'pre-wrap' }}>{item.description}</div>}
                        </div>
                        {(item.archived_at || item.maxrisklevel) && <div className="pm-project-row-badges">{item.archived_at && <Badge>{t('Archived')}</Badge>}{item.maxrisklevel && <span className="ls-badge" style={{ backgroundColor: `color-mix(in srgb, #${item.maxrisklevel.color} 25%, white)` }}>{item.maxrisklevel.name}</span>}</div>}
                    </li>)}
                    </ul>
                    {!visible.length && <p className="pm-empty-state">{t('No projects')}</p>}
                </Card>
                </>}
            </div>
        </fieldset>
    </div>;
}
