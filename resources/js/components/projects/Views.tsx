import React, { useRef, useState } from 'react';
import { action, confirmAction, create, dateValue, errorMessage, nameOf, remove, t, update, type Action, type Configuration, type Id, type Participant, type Project, type ProjectSummary, type Risk, type Run } from './api';
import { Badge, Btn, Card, Icon, Input, Label, Options, SectionTitle, Select, Textarea } from '../ui';
import { ActivityFields, activityPayload, activityValues, type ActivityValues } from './ActionPlanPicker';

export function ProjectHeader({ project, projects, config, tab, loading, onSelect, onTab }: {
    project?: ProjectSummary; projects: ProjectSummary[]; config: Configuration; tab: string; loading: boolean;
    onSelect: (id: Id | null) => void; onTab: (tab: string) => void;
}) {
    const tabs = [
        ['overview', 'Overview', 'dashboard'], ['team', 'Team', 'group'],
        ...(config.canReadRisks ? [['risks', 'Risks', 'warning']] : []),
        ...(config.canReadActions ? [['actions', 'Activities', 'checklist']] : []),
        ...(config.canReadWorkPackages ? [['work-packages', 'Work packages', 'workspaces']] : []),
        ...(config.canReadWorkPackages || config.canReadActions ? [['gantt', 'Gantt', 'view_timeline']] : []),
        ['meetings', 'Meetings', 'event_note'],
        ['settings', 'Settings', 'settings'],
    ];
    return <header className="pm-header">
        <select className="pm-project-select" aria-label={t('Select project')} value={project?.id ?? ''} disabled={loading} onChange={event => onSelect(Number(event.target.value) || null)}>
            <option value="">{t('All projects')}</option>
            {projects.map(item => <option key={item.id} value={item.id}>{item.name}{item.archived_at ? ` (${t('Archived')})` : ''}</option>)}
        </select>
        <div className="pm-header-main">
            <div className="pm-header-copy">
                <div className="pm-eyebrow">{t('Project')}</div>
                <h1 className="pm-project-title">{project?.name ?? t('Projects')}</h1>
                {project && <div className="pm-header-meta">{nameOf(config.users, project.responsible_user_id)} · {dateValue(project.start_date)}{project.end_date && ` – ${dateValue(project.end_date)}`}{project.archived_at && <> · {t('Archived')}</>}</div>}
                {project?.description && <div className="pm-header-description text-break" style={{ whiteSpace: 'pre-wrap' }}>{project.description}</div>}
            </div>
        </div>
        {project && <nav className="pm-tabs" aria-label={t('Project')}>
            {tabs.map(([id, label, icon]) => <button key={id} type="button" className="pm-tab" aria-current={tab === id ? 'page' : undefined} onClick={() => onTab(id)}><Icon name={icon} />{t(label)}</button>)}
        </nav>}
    </header>;
}

export function ProjectForm({ project, config, run, onSaved, onCancel }: {
    project?: Project; config: Configuration; run: Run; onSaved: () => void; onCancel: () => void;
}) {
    const [form, setForm] = useState({
        name: project?.name ?? '',
        description: project?.description ?? '',
        responsible_user_id: String(project ? project.responsible_user_id ?? '' : config.currentUserId),
        start_date: dateValue(project?.start_date ?? new Date().toISOString()),
        end_date: dateValue(project?.end_date ?? null),
        project_type_id: String(project?.project_type_id ?? ''),
    });
    const field = (key: 'name' | 'description' | 'responsible_user_id' | 'start_date' | 'end_date' | 'project_type_id', value: string) => setForm(previous => ({ ...previous, [key]: value }));
    return <form onSubmit={async event => {
        event.preventDefault();
        const { project_type_id, ...values } = form;
        const payload = {
            ...values, name: form.name.trim(), end_date: form.end_date || null,
            responsible_user_id: Number(form.responsible_user_id) || null,
            ...(!project ? { project_type_id: Number(project_type_id) || null } : {}),
        };
        if (await run(() => project ? update('Project', project.id, payload) : create('Project', payload))) onSaved();
    }}>
        <div className="row">
            <div className="col-12"><Label text={t('Name')}><Input required maxLength={255} value={form.name} onChange={event => field('name', event.target.value)} /></Label></div>
            {!project && <div className="col-12"><Label text={t('Project type')}><Select value={form.project_type_id} onChange={event => field('project_type_id', event.target.value)}><Options items={config.projectTypes} /></Select></Label></div>}
            <div className="col-12"><Label text={t('Description')}><Textarea rows={5} value={form.description} onChange={event => field('description', event.target.value)} /></Label></div>
            <div className="col-12"><Label text={t('Responsible user')}><Select value={form.responsible_user_id} onChange={event => field('responsible_user_id', event.target.value)}><Options items={config.users} /></Select></Label></div>
            <div className="col-md-6"><Label text={t('Start date')}><Input required type="date" value={form.start_date} onChange={event => field('start_date', event.target.value)} /></Label></div>
            <div className="col-md-6"><Label text={t('End date')}><Input type="date" value={form.end_date} onChange={event => field('end_date', event.target.value)} /></Label></div>
        </div>
        <div className="d-flex gap-2"><Btn type="submit" disabled={!form.name.trim()}>{t('Save')}</Btn><Btn variant="ghost" onClick={onCancel}>{t('Cancel')}</Btn></div>
    </form>;
}

export function Overview({ project, risks, actions, config }: { project: Project; risks: Risk[]; actions: Action[]; config: Configuration }) {
    const stats = [
        ...(config.canReadRisks ? [{ label: t('Open risks'), value: risks.filter(risk => !risk.assessed_at).length, detail: `${risks.length} ${t('risks in total')}`, tone: 'warn', icon: 'warning' }] : []),
        ...(config.canReadActions ? [{ label: t('Activities'), value: actions.length, detail: `${actions.filter(item => item.completed_at).length} ${t('finished')}`, tone: 'info', icon: 'checklist' }] : []),
        { label: t('Participants'), value: project.users.length, detail: nameOf(config.users, project.responsible_user_id), tone: 'neutral', icon: 'group' },
        ...(config.canReadRisks ? [{ label: t('Approved risks'), value: risks.filter(risk => risk.assessed_at).length, detail: t('Approved'), tone: 'ok', icon: 'verified' }] : []),
    ];
    return <>
        <div className="pm-stats">{stats.map(stat => <Card className={`pm-stat pm-stat-${stat.tone}`} key={stat.label}><div className="pm-stat-label"><span>{stat.label}</span><Icon name={stat.icon} /></div><div className="pm-stat-value">{stat.value}</div><div className="pm-stat-detail">{stat.detail}</div></Card>)}</div>
        <div className="pm-overview-grid">
            <Card><SectionTitle title={t('Risk matrix')} />
                <div className="pm-matrix-layout">
                    <div className="pm-matrix-y">{t('Probability')} →</div>
                    <div className="pm-matrix-content">
                        <div className="pm-matrix" role="group" aria-label={t('Risk matrix')} style={{ gridTemplateColumns: `repeat(${Math.max(config.consequences.length, 1)}, minmax(0, 1fr))` }}>
                            {config.probabilities.flatMap(probability => config.consequences.map(consequence => {
                                const level = config.riskMappings.find(item => item.probability_level_id === probability.id && item.consequence_level_id === consequence.id);
                                const count = project.riskdistribution[probability.id]?.[consequence.id]?.count || 0;
                                return <div className="pm-matrix-cell" key={`${probability.id}-${consequence.id}`} title={`${probability.name} / ${consequence.name}${level ? ` · ${level.name}` : ''}`} aria-label={`${probability.name} / ${consequence.name}: ${count}`} style={{ backgroundColor: level ? `color-mix(in srgb, #${level.color} 25%, white)` : '#f3f5f7' }}>{count || ''}</div>;
                            }))}
                        </div>
                        <div className="pm-matrix-x">{t('Consequence')} →</div>
                    </div>
                </div>
            </Card>
            <Card><SectionTitle title={t(config.canReadActions ? 'Planned activities' : 'Project')} />
                {config.canReadActions ? <ul className="pm-activity-list">
                    {actions.map(item => <li key={item.id} className="pm-activity">
                        <div className="pm-activity-heading"><span>{item.name}</span><Badge tone={item.completed_at ? 'ok' : 'muted'}>{t(item.completed_at ? 'Finished' : 'Planned')}</Badge></div>
                        {item.description && <div className="pm-activity-detail">{item.description}</div>}
                        <div className="pm-activity-detail">{nameOf(config.users, item.responsible_user_id)} · {dateValue(item.due)}</div>
                    </li>)}
                    {!actions.length && <li className="text-muted">{t('No actions')}</li>}
                </ul> : <ProjectDetails project={project} config={config} />}
            </Card>
        </div>
    </>;
}

function ProjectDetails({ project, config }: { project: Project; config: Configuration }) {
    return <dl className="pm-project-details row mb-0">
        <div className="col-12"><dt>{t('Responsible user')}</dt><dd>{nameOf(config.users, project.responsible_user_id) || t('None')}</dd></div>
        <div className="col-12"><dt>{t('Description')}</dt><dd className="text-break" style={{ whiteSpace: 'pre-wrap' }}>{project.description || '—'}</dd></div>
        <div className="col-md-6"><dt>{t('Start date')}</dt><dd>{dateValue(project.start_date) || '—'}</dd></div>
        <div className="col-md-6"><dt>{t('End date')}</dt><dd>{dateValue(project.end_date) || '—'}</dd></div>
    </dl>;
}
export function RiskForm({ risk, project, actions = [], config, run, onSaved, onCancel }: {
    risk?: Risk; project: Project; actions?: Action[]; config: Configuration; run: Run; onSaved: () => void; onCancel: () => void;
}) {
    const projectParticipants = project.users ?? [];
    const riskOwners = [
        ...config.users.filter(user => user.id === project.responsible_user_id),
        ...projectParticipants.filter(participant => participant.id !== project.responsible_user_id),
    ];
    const defaultRiskOwnerId = risk ? risk.riskowner_id : project.responsible_user_id;
    const linkedActions = config.canReadActions ? actions.filter(item => item.risks.some(link => link.id === risk?.id)) : [];
    const [activities, setActivities] = useState<{ key: number; values: ActivityValues; dirty: boolean; editable: boolean; removed?: boolean }[]>(
        linkedActions.map(item => ({
            key: item.id, values: activityValues(item, config.currentUserId), dirty: false, editable: item.access.update && !item.completed_at,
        })),
    );
    const nextActivityKey = useRef(0);
    const savedRiskId = useRef(risk?.id);
    const savedActivityIds = useRef(new Map<number, number>());
    const availableActivities = config.canReadActions && project.access.write
        ? actions.filter(item => item.access.update && !activities.some(activity => activity.key === item.id && !activity.removed))
        : [];
    const [form, setForm] = useState({
        name: risk?.name ?? '',
        riskowner_id: String(defaultRiskOwnerId !== null && riskOwners.some(owner => owner.id === defaultRiskOwnerId) ? defaultRiskOwnerId : ''),
        probability_id: String(risk?.probability_id ?? ''), consequence_id: String(risk?.consequence_id ?? ''),
        scenariodescription: risk?.scenariodescription ?? '', consequencedescription: risk?.consequencedescription ?? '',
        note: risk?.note ?? '', risk_controls: risk?.risk_controls.map(control => control.id) ?? [],
    });
    const field = (key: Exclude<keyof typeof form, 'risk_controls'>, value: string) => setForm(previous => ({ ...previous, [key]: value }));
    return <form onSubmit={async event => {
        event.preventDefault();
        const payload = {
            ...form, name: form.name.trim(), project_id: project.id,
            riskowner_id: Number(form.riskowner_id) || null,
            probability_id: Number(form.probability_id) || null, consequence_id: Number(form.consequence_id) || null,
        };
        if (await run(async () => {
            if (savedRiskId.current) {
                await update('ProjectRisk', savedRiskId.current, payload);
            } else {
                const { data } = await create('ProjectRisk', payload);
                if (typeof data.id !== 'number') throw new Error(t('Unexpected API response'));
                savedRiskId.current = data.id;
            }
            const riskId = savedRiskId.current;
            if (typeof riskId !== 'number') throw new Error(t('Unexpected API response'));
            try {
                for (const activity of activities.filter(item => item.dirty)) {
                    const id = savedActivityIds.current.get(activity.key) ?? (activity.key > 0 ? activity.key : undefined);
                    if (activity.removed) {
                        if (id) await update('ProjectActivity', id, { risks: activity.values.risks.filter(link => link !== riskId) });
                        continue;
                    }
                    const values = activityPayload(activity.values, project.id);
                    values.risks = Array.from(new Set([...values.risks, riskId]));
                    if (id) {
                        await update('ProjectActivity', id, values);
                    } else {
                        const { data } = await create('ProjectActivity', values);
                        if (typeof data.id !== 'number') throw new Error(t('Unexpected API response'));
                        savedActivityIds.current.set(activity.key, data.id);
                    }
                }
            } catch (error) {
                throw new Error(`${t('Risk saved, but some activities could not be saved. Retry saving to finish.')} ${errorMessage(error)}`);
            }
        })) onSaved();
    }}>
        <fieldset className="pm-form-section"><legend>{t('Risk details')}</legend><div className="row">
            <div className="col-md-6"><Label text={t('Name')}><Input required maxLength={255} value={form.name} onChange={event => field('name', event.target.value)} /></Label></div>
            <div className="col-md-6"><Label text={t('Risk owner')}><Select value={form.riskowner_id} onChange={event => field('riskowner_id', event.target.value)}><Options items={riskOwners} /></Select></Label></div>
            <div className="col-md-6"><Label text={t('Risk scenario')}><Textarea required rows={3} value={form.scenariodescription} onChange={event => field('scenariodescription', event.target.value)} /></Label></div>
            <div className="col-md-6"><Label text={t('Consequence description')}><Textarea rows={3} value={form.consequencedescription} onChange={event => field('consequencedescription', event.target.value)} /></Label></div>
            <div className="col-md-6"><Label text={t('Controls')}><Select multiple value={form.risk_controls.map(String)} onChange={event => setForm({ ...form, risk_controls: Array.from(event.target.selectedOptions, option => Number(option.value)) })}><Options items={config.controls} empty={false} /></Select></Label></div>
        </div></fieldset>
        <fieldset className="pm-form-section"><legend>{t('Current risk assessment')}</legend><div className="row">
            <div className="col-md-6"><Label text={t('Probability')}><Select value={form.probability_id} onChange={event => field('probability_id', event.target.value)}><Options items={config.probabilities} /></Select></Label></div>
            <div className="col-md-6"><Label text={t('Consequence')}><Select value={form.consequence_id} onChange={event => field('consequence_id', event.target.value)}><Options items={config.consequences} /></Select></Label></div>
        </div></fieldset>
        {config.canReadActions && <fieldset className="pm-form-section"><legend>{t('Planned activities')}</legend>
            {project.access.write && <Label text={t('Link existing activity')}><Select aria-label={t('Link existing activity')} value="" disabled={!availableActivities.length} onChange={event => {
                const selected = availableActivities.find(item => item.id === Number(event.target.value));
                if (selected) setActivities(previous => previous.some(item => item.key === selected.id)
                    ? previous.map(item => item.key === selected.id ? { ...item, removed: false, dirty: true } : item) : [...previous, {
                    key: selected.id, values: activityValues(selected, config.currentUserId), dirty: true, editable: !selected.completed_at,
                }]);
            }}>
                <option value="">{t('Link existing activity')}</option>
                <Options items={availableActivities} empty={false} />
            </Select></Label>}
            {activities.filter(activity => !activity.removed).map(activity => {
                const canUnlink = !risk?.assessed_at && project.access.write
                    && (activity.key < 0 || actions.some(item => item.id === activity.key && item.access.update));
                return <fieldset className="border rounded p-3 mb-3" key={activity.key} disabled={!activity.editable && !canUnlink}>
                    <legend className="float-none w-auto px-2 h6">{activity.values.name || t('Add new activity')}</legend>
                    <fieldset className="border-0 p-0 m-0" disabled={!activity.editable}>
                        <ActivityFields form={activity.values} config={config} onChange={values => setActivities(previous => previous.map(item => item.key === activity.key ? { ...item, values, dirty: true } : item))} />
                    </fieldset>
                    {canUnlink && <Btn variant="ghost" onClick={() => setActivities(previous => previous.map(item => item.key === activity.key ? { ...item, removed: true, dirty: true } : item))}>{t('Unlink activity')}</Btn>}
                </fieldset>;
            })}
            {!activities.some(activity => !activity.removed) && <p className="text-muted">{t('No actions')}</p>}
            {project.access.write && config.canCreateAction && <Btn variant="outline" onClick={() => {
                const key = --nextActivityKey.current;
                setActivities(previous => [...previous, { key, values: activityValues(undefined, config.currentUserId), dirty: true, editable: true }]);
            }}><Icon name="add" />{t('Add new activity')}</Btn>}
        </fieldset>}
        <fieldset className="pm-form-section"><legend>{t('Notes')}</legend>
            <Label text={t('Note')}><Textarea rows={3} value={form.note} onChange={event => field('note', event.target.value)} /></Label>
        </fieldset>
        <div className="d-flex gap-2"><Btn type="submit" disabled={!form.name.trim()}>{t('Save')}</Btn><Btn variant="ghost" onClick={onCancel}>{t('Cancel')}</Btn></div>
    </form>;
}

export function Risks({ project, risks, actions, config, run }: { project: Project; risks: Risk[]; actions: Action[]; config: Configuration; run: Run }) {
    const [editing, setEditing] = useState<Id | 'new' | null>(null);
    const editable = !project.archived_at;
    return <>
        <Card><SectionTitle title={t('Risks')} action={editable && project.access.write && config.canCreateRisk && <Btn onClick={() => setEditing('new')}>{t('Add new risk')}</Btn>} />
            {editing === 'new' && <div className="mt-3"><RiskForm project={project} actions={actions} config={config} run={run} onSaved={() => setEditing(null)} onCancel={() => setEditing(null)} /></div>}
            {!risks.length && <p className="text-muted">{t('No risks')}</p>}
        </Card>
        {risks.map(risk => <Card key={risk.id} className={`pm-risk-card ${risk.assessed_at ? 'pm-risk-approved' : ''}`}>
            <details className="pm-risk-accordion">
            <summary className="pm-risk-header">
            <div className="pm-risk-summary-content">
            <SectionTitle title={risk.name} action={<div className="d-flex gap-2"><Badge tone={risk.assessed_at ? 'ok' : 'warn'}>{t(risk.assessed_at ? 'Approved' : 'Not assessed')}</Badge>{risk.risk_level && <span className="ls-badge" style={{ backgroundColor: `color-mix(in srgb, #${risk.risk_level.color} 25%, white)` }}>{risk.risk_level.name}</span>}</div>} />
            <div className="text-muted small">RISK-{risk.id} · {nameOf(config.users, risk.riskowner_id)}{risk.assessed_at && ` · ${dateValue(risk.assessed_at)}`}</div>
            <div className="d-flex flex-wrap gap-3 mt-2">
                <span>{t('Probability')}: <strong>{nameOf(config.probabilities, risk.probability_id) || t('Not assessed')}</strong></span>
                <span>{t('Consequence')}: <strong>{nameOf(config.consequences, risk.consequence_id) || t('Not assessed')}</strong></span>
            </div>
            </div>
            <span className="pm-risk-expand"><Icon name="expand_more" /></span>
            </summary>
            <div className="pm-risk-body">
            {editing === risk.id ? <RiskForm risk={risk} project={project} actions={actions} config={config} run={run} onSaved={() => setEditing(null)} onCancel={() => setEditing(null)} /> : <>
                <section className="pm-risk-section"><h3>{t('Risk details')}</h3>
                    <dl className="row mb-0">
                        <div className="col-md-6"><dt>{t('Name')}</dt><dd>{risk.name}</dd></div>
                        <div className="col-md-6"><dt>{t('Risk owner')}</dt><dd>{nameOf(config.users, risk.riskowner_id) || t('None')}</dd></div>
                        <div className="col-md-6"><dt>{t('Risk scenario')}</dt><dd className="pm-risk-text">{risk.scenariodescription || t('None')}</dd></div>
                        <div className="col-md-6"><dt>{t('Consequence description')}</dt><dd className="pm-risk-text">{risk.consequencedescription || t('None')}</dd></div>
                        <div className="col-12"><dt>{t('Controls')}</dt><dd className="d-flex flex-wrap gap-2">{risk.risk_controls.length ? risk.risk_controls.map(control => <Badge key={control.id}>{control.name}</Badge>) : t('None')}</dd></div>
                    </dl>
                </section>
                <section className="pm-risk-section"><h3>{t('Current risk assessment')}</h3>
                    <dl className="row mb-0">
                        <div className="col-md-6"><dt>{t('Probability')}</dt><dd>{nameOf(config.probabilities, risk.probability_id) || t('Not assessed')}</dd></div>
                        <div className="col-md-6"><dt>{t('Consequence')}</dt><dd>{nameOf(config.consequences, risk.consequence_id) || t('Not assessed')}</dd></div>
                    </dl>
                </section>
                {config.canReadActions && <section className="pm-risk-section"><h3>{t('Planned activities')}</h3>
                    <ul className="pm-activity-list">{actions.filter(item => item.risks.some(link => link.id === risk.id)).map(item => <li className="pm-activity" key={item.id}>
                        <div className="pm-activity-heading"><strong>{item.name}</strong><Badge tone={item.completed_at ? 'ok' : 'info'}>{t(item.completed_at ? 'Finished' : 'Planned')}</Badge></div>
                        {item.description && <p className="pm-risk-text mb-2">{item.description}</p>}
                        <div className="pm-activity-detail">{t('Responsible')}: {nameOf(config.users, item.responsible_user_id) || t('None')}{item.due && <> · {t('Due')}: {dateValue(item.due)}</>}</div>
                    </li>)}</ul>
                    {!actions.some(item => item.risks.some(link => link.id === risk.id)) && <p className="text-muted mb-0">{t('No actions')}</p>}
                </section>}
                <section className="pm-risk-section"><h3>{t('Notes')}</h3><p className="pm-risk-text mb-0">{risk.note || t('None')}</p></section>
                <div className="pm-risk-footer d-flex flex-wrap gap-2">
                    {editable && risk.access.update && !risk.assessed_at && <Btn variant="outline" onClick={() => setEditing(risk.id)}>{t('Edit')}</Btn>}
                    {editable && risk.access.update && !risk.assessed_at && risk.riskowner_id === config.currentUserId && <Btn disabled={!risk.probability_id || !risk.consequence_id} onClick={() => confirmAction('Are you sure that you want to approve this risk?', () => { void run(() => action('ProjectRisk', risk.id, 'approve')); }, 'Approve risk')}>{t('Approve')}</Btn>}
                    {editable && risk.access.update && risk.assessed_at && <Btn variant="outline" onClick={() => confirmAction('Are you sure that you want to reassess this risk? The risk will be reopened for assessment.', () => { void run(() => action('ProjectRisk', risk.id, 'replace')); }, 'Reassess risk')}>{t('Reassess risk')}</Btn>}
                    {editable && risk.access.delete && <Btn variant="ghost" onClick={() => confirmAction('Are you sure that you want to delete this risk?', () => { void run(() => remove('ProjectRisk', risk.id)); }, 'Delete risk')}>{t('Delete')}</Btn>}
                </div>
            </>}
            </div>
            </details>
        </Card>)}
    </>;
}

export function TeamForm({ project, config, run, onSaved, onCancel }: {
    project: Project; config: Configuration; run: Run; onSaved: () => void; onCancel: () => void;
}) {
    const [participants, setParticipants] = useState<Participant[]>(project.users.filter(user => user.id !== project.responsible_user_id));
    const availableUsers = config.users.filter(user => user.enabled && user.id !== project.responsible_user_id && !participants.some(member => member.id === user.id));
    return <form onSubmit={async event => {
        event.preventDefault();
        if (await run(() => action('Project', project.id, 'updateparticipants', {
            users: participants.map(({ id, readonly }) => ({ id, readonly })),
        }))) onSaved();
    }}>
        <Label text={t('Participants')}><Select aria-label={`${t('Add')}: ${t('Participants')}`} value="" disabled={!availableUsers.length} onChange={event => {
            const selected = availableUsers.find(user => user.id === Number(event.target.value));
            if (selected) setParticipants(previous => previous.some(user => user.id === selected.id) ? previous : [...previous, { id: selected.id, name: selected.name, readonly: false }]);
        }}>
            <option value="">{t('Add')}</option>
            <Options items={availableUsers} empty={false} />
        </Select></Label>
        <div className="table-responsive"><table className="table align-middle">
            <thead><tr><th scope="col">{t('Name')}</th><th scope="col">{t('Access')}</th><th scope="col">{t('Actions')}</th></tr></thead>
            <tbody>{participants.map(member => <tr key={member.id}><td>{member.name}</td><td>
                <Select aria-label={`${t('Access')}: ${member.name}`} value={member.readonly ? 'read' : 'write'} onChange={event => setParticipants(previous => previous.map(user => user.id === member.id ? { ...user, readonly: event.target.value === 'read' } : user))}>
                    <option value="write">{t('Read/write')}</option><option value="read">{t('Read only')}</option>
                </Select>
            </td><td><Btn variant="ghost" aria-label={`${t('Delete')}: ${member.name}`} onClick={() => setParticipants(previous => previous.filter(user => user.id !== member.id))}><Icon name="delete" />{t('Delete')}</Btn></td></tr>)}</tbody>
        </table></div>
        <div className="d-flex gap-2"><Btn type="submit">{t('Save')}</Btn><Btn variant="ghost" onClick={onCancel}>{t('Cancel')}</Btn></div>
    </form>;
}

export function Team({ project, config, run }: { project: Project; config: Configuration; run: Run }) {
    const [editing, setEditing] = useState(false);
    return <Card><SectionTitle title={t('Participants')} action={project.access.manageparticipants && !editing && <Btn variant="outline" onClick={() => setEditing(true)}>{t('Manage participants')}</Btn>} /><p className="text-muted">{t('Participants will be able to view the project and its related risks')}</p>
        {editing ? <TeamForm project={project} config={config} run={run} onSaved={() => setEditing(false)} onCancel={() => setEditing(false)} /> :
        <div className="table-responsive"><table className="table align-middle">
            <thead><tr><th scope="col">{t('Name')}</th><th scope="col">{t('Role')}</th><th scope="col">{t('Access')}</th></tr></thead>
            <tbody>
                {project.responsible_user_id && <tr><td>{nameOf(config.users, project.responsible_user_id)}</td><td><Badge tone="info">{t('Responsible user')}</Badge></td><td>{t('Read/write')}</td></tr>}
                {project.users.filter(user => user.id !== project.responsible_user_id).map(user => <tr key={user.id}><td>{user.name}</td><td><Badge>{t('Participant')}</Badge></td><td>{t(user.readonly ? 'Read only' : 'Read/write')}</td></tr>)}
            </tbody>
        </table></div>}
    </Card>;
}

export function Settings({ project, config, run }: { project: Project; config: Configuration; run: Run }) {
    const [editing, setEditing] = useState(false);
    return <>
        <Card><SectionTitle title={t('Project settings')} action={project.access.update && !editing && <Btn variant="outline" onClick={() => setEditing(true)}>{t('Edit')}</Btn>} />
            {editing ? <ProjectForm project={project} config={config} run={run} onSaved={() => setEditing(false)} onCancel={() => setEditing(false)} /> : <ProjectDetails project={project} config={config} />}
        </Card>
        <Card><SectionTitle title={t('Actions')} /><div className="d-flex flex-wrap gap-2">
            {project.access.update && <>
                <Btn variant={project.archived_at ? 'outline' : 'warning'} onClick={() => confirmAction(project.archived_at ? 'By unarchiving this project, risk management will be continued.' : 'By archiving this project, you claim that no more risk management is to be done regarding this project. Risks will not be re-opened for assessment.', () => { void run(() => action('Project', project.id, project.archived_at ? 'unarchive' : 'archive')); }, project.archived_at ? 'Unarchive project' : 'Archive project')}>{t(project.archived_at ? 'Unarchive project' : 'Archive project')}</Btn>
                {project.archived_at && <Btn variant="danger" onClick={() => confirmAction('The risks related to the project will also be deleted', () => { void run(() => remove('Project', project.id)); }, 'Delete project')}>{t('Delete project')}</Btn>}
            </>}
        </div></Card>
    </>;
}
