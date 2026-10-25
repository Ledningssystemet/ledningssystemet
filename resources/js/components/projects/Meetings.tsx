import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { action, collection, confirmAction, create, errorMessage, item, remove, t, todayDate, update, type Action, type Configuration, type Meeting, type MeetingAgendaPreview, type MeetingMention, type Option, type Project, type ProjectMeetingLink } from './api';
import { MeetingAgenda, newAgendaItem } from './MeetingAgenda';
import { ActivityFields, activityPayload, activityValues } from './ActionPlanPicker';
import { Badge, Btn, Card, Input, Label, Options, SectionTitle, Select } from '../ui';

type MeetingValues = Pick<Meeting, 'name' | 'date' | 'location' | 'agenda'>;
type MeetingDraft = {
    values: MeetingValues;
    agenda_template_id: number | null;
    include_follow_up: boolean;
    follow_up_meeting_id: number | null;
};

export function NewMeetingSetup({ project, onPrepared, onCancel }: {
    project: Project; onPrepared: (draft: MeetingDraft) => void; onCancel: () => void;
}) {
    const [templates, setTemplates] = useState<Option[]>([]);
    const [templateId, setTemplateId] = useState('');
    const [date, setDate] = useState(todayDate());
    const [preview, setPreview] = useState<MeetingAgendaPreview | null>(null);
    const [busy, setBusy] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);
    useEffect(() => {
        let active = true;
        setBusy(true); setError(null);
        action('Project', project.id, 'meetingagenda', { date: todayDate() })
            .then(response => { if (active) setTemplates(response.data.templates); })
            .catch(error => { if (active) setError(errorMessage(error)); })
            .finally(() => { if (active) setBusy(false); });
        return () => { active = false; };
    }, [project.id, attempt]);
    const prepare = (agenda: MeetingAgendaPreview, include: boolean) => onPrepared({
        values: { name: '', date, location: '', agenda: [
            ...(include && agenda.follow_up_section ? [agenda.follow_up_section] : []),
            ...(templateId ? agenda.sections : [newAgendaItem()]),
        ] },
        agenda_template_id: Number(templateId) || null,
        include_follow_up: include,
        follow_up_meeting_id: agenda.previous_meeting?.id ?? null,
    });
    return <Card><SectionTitle title={t('New meeting')} action={<Btn variant="ghost" disabled={busy} onClick={onCancel}>{t('Cancel')}</Btn>} />
        {error && <div role="alert" className="alert alert-danger">{error} <Btn variant="outline" onClick={() => setAttempt(value => value + 1)}>{t('Retry')}</Btn></div>}
        {preview?.previous_meeting ? <div role="dialog" aria-label={t('Follow-up from previous meeting')}>
            <p>{t('Include a mandatory follow-up item with open activities from the previous meeting?')}</p>
            <p><strong>{preview.previous_meeting.name}</strong> · {preview.previous_meeting.date}</p>
            <div className="d-flex flex-wrap gap-2">
                <Btn onClick={() => prepare(preview, true)}>{t('Include follow-up')}</Btn>
                <Btn variant="outline" onClick={() => prepare(preview, false)}>{t('Continue without follow-up')}</Btn>
                <Btn variant="ghost" onClick={() => setPreview(null)}>{t('Back')}</Btn>
            </div>
        </div> : <form onSubmit={async event => {
            event.preventDefault(); setBusy(true); setError(null);
            try {
                const response = await action('Project', project.id, 'meetingagenda', { date, agenda_template_id: Number(templateId) || null });
                const agenda: MeetingAgendaPreview = response.data;
                if (agenda.previous_meeting) setPreview(agenda);
                else prepare(agenda, false);
            } catch (error) { setError(errorMessage(error)); }
            finally { setBusy(false); }
        }}><fieldset disabled={busy || !!error}>
            <Label text={t('Date')}><Input type="date" required value={date} onChange={event => setDate(event.target.value)} /></Label>
            <Label text={t('Standard agenda')}><Select value={templateId} onChange={event => setTemplateId(event.target.value)}>
                <option value="">{t('Without standard agenda')}</option><Options items={templates} empty={false} />
            </Select></Label>
            <Btn type="submit">{t('Continue')}</Btn>
        </fieldset></form>}
    </Card>;
}

export function MeetingEditor({ meeting, initialValues, project, config, actions, busy, onSave, onCancel, onCreateActivity, onDirty, onOpenMeeting, onToggleActivity, targetMention, onMentionAcknowledged }: {
    meeting: Meeting | null; project: Project; config: Configuration; actions: Action[]; busy: boolean;
    initialValues?: MeetingValues;
    onSave: (values: MeetingValues, complete: boolean) => Promise<boolean>; onCancel: () => void;
    onCreateActivity: (payload: object) => Promise<Action | null>; onDirty: (dirty: boolean) => void;
    onOpenMeeting: (id: number) => void;
    onToggleActivity: (activity: Action) => void;
    targetMention?: MeetingMention | null; onMentionAcknowledged?: (mention: MeetingMention) => void;
}) {
    const [form, setForm] = useState<MeetingValues>(initialValues ?? {
        name: meeting?.name ?? '', date: meeting?.date ?? todayDate(), location: meeting?.location ?? '',
        agenda: meeting?.agenda ?? [newAgendaItem()],
    });
    const [activitySection, setActivitySection] = useState<string | null>(null);
    const [activityForm, setActivityForm] = useState(activityValues(undefined, config.currentUserId));
    const [readySection, setReadySection] = useState<string | null>(null);
    const [mentionError, setMentionError] = useState<string | null>(null);
    const [mentionAttempt, setMentionAttempt] = useState(0);
    const dirty = useRef(false);
    const locked = !!project.archived_at || (!!meeting && !meeting.access.update);
    const members = [...project.users, ...config.users.filter(user => user.id === project.responsible_user_id)]
        .filter((member, index, all) => all.findIndex(other => other.id === member.id) === index);
    const change = (values: MeetingValues) => { setForm(values); dirty.current = true; onDirty(true); };
    useEffect(() => {
        const beforeUnload = (event: BeforeUnloadEvent) => { if (dirty.current) event.preventDefault(); };
        window.addEventListener('beforeunload', beforeUnload);
        return () => { window.removeEventListener('beforeunload', beforeUnload); onDirty(false); };
    }, [onDirty]);
    useEffect(() => {
        if (!targetMention || readySection !== targetMention.agenda_item_id || targetMention.project_meeting_id !== meeting?.id) return;
        const section = document.getElementById(`meeting-section-${targetMention.agenda_item_id}`);
        if (!section) {
            setMentionError(t('The mentioned meeting or notes are no longer available'));
            return;
        }
        section.scrollIntoView({ block: 'center' });
        section.focus({ preventScroll: true });
        if (targetMention.acknowledged_at) return;
        let active = true;
        setMentionError(null);
        action('ProjectMeetingMention', targetMention.id, 'acknowledge')
            .then(response => {
                if (active) {
                    onMentionAcknowledged?.(response.data);
                    window.dispatchEvent(new Event('project-mentions-changed'));
                }
            })
            .catch(error => { if (active) setMentionError(errorMessage(error)); });
        return () => { active = false; };
    }, [targetMention, readySection, meeting?.id, mentionAttempt, onMentionAcknowledged]);
    const cancel = () => {
        if (dirty.current) confirmAction('Discard unsaved meeting changes?', onCancel, 'Unsaved changes');
        else onCancel();
    };
    const save = async (complete: boolean) => {
        if (await onSave({ ...form, name: form.name.trim(), location: form.location?.trim() || null }, complete)) {
            dirty.current = false;
            onDirty(false);
            onCancel();
        }
    };
    const valid = !!form.name.trim() && !!form.date && form.agenda.every(section => !!section.title.trim());
    return <Card>
        <SectionTitle title={meeting ? meeting.name : t('New meeting')} action={<Btn variant="ghost" onClick={cancel}>{t('Close')}</Btn>} />
        {mentionError && <div role="alert" className="alert alert-danger">{t('Could not acknowledge mention')} {mentionError}{' '}
            <Btn variant="outline" onClick={() => setMentionAttempt(value => value + 1)}>{t('Retry')}</Btn>
        </div>}
        {meeting?.completed_at && <p role="status"><Badge tone="ok">{t('The meeting is completed and locked')}</Badge></p>}
        <fieldset disabled={busy || locked}>
            <div className="row">
                <div className="col-md-6"><Label text={t('Name')}><Input required maxLength={255} value={form.name} onChange={event => change({ ...form, name: event.target.value })} /></Label></div>
                <div className="col-md-6"><Label text={t('Date')}><Input required type="date" value={form.date} onChange={event => change({ ...form, date: event.target.value })} /></Label></div>
                <div className="col-12"><Label text={t('Location')}><Input maxLength={255} value={form.location ?? ''} onChange={event => change({ ...form, location: event.target.value })} /></Label></div>
            </div>
        </fieldset>
        <fieldset disabled={busy}>
            <MeetingAgenda items={form.agenda} members={members} actions={actions} config={config} locked={locked || busy}
                targetSectionId={targetMention?.agenda_item_id} onSectionReady={setReadySection}
                onToggleActivity={onToggleActivity}
                onOpenMeeting={id => {
                    if (dirty.current) confirmAction('Discard unsaved meeting changes?', () => onOpenMeeting(id), 'Unsaved changes');
                    else onOpenMeeting(id);
                }}
                onChange={agenda => change({ ...form, agenda })} onCreateActivity={id => { setActivitySection(id); setActivityForm(activityValues(undefined, config.currentUserId)); }} />
            {activitySection && <form className="border rounded p-3 mb-3" onSubmit={async event => {
                event.preventDefault();
                const activity = await onCreateActivity(activityPayload(activityForm, project.id));
                if (activity) {
                    change({ ...form, agenda: form.agenda.map(section => section.id === activitySection ? { ...section, activity_ids: [...section.activity_ids, activity.id] } : section) });
                    setActivitySection(null);
                }
            }}>
                <h3 className="h5">{t('Add new activity')}</h3>
                <p className="text-muted">{t('The activity is saved immediately. Save the meeting to keep the link.')}</p>
                <ActivityFields form={activityForm} onChange={setActivityForm} config={config} />
                <Btn type="submit" disabled={!activityForm.name.trim()}>{t('Create')}</Btn>{' '}
                <Btn variant="ghost" onClick={() => setActivitySection(null)}>{t('Cancel')}</Btn>
            </form>}
            {!locked && <div className="d-flex flex-wrap gap-2">
                <Btn disabled={!valid || !!activitySection} onClick={() => void save(false)}>{t('Save')}</Btn>
                <Btn variant="outline" disabled={!valid || !!activitySection} onClick={() => confirmAction('Save and complete this meeting? It will be locked for editing.', () => { void save(true); }, 'Complete meeting')}>{t('Save and complete meeting')}</Btn>
            </div>}
        </fieldset>
    </Card>;
}

export function Meetings({ project, config, onDirty, onActivityChanged, initialLink, onLinkOpened }: {
    project: Project; config: Configuration; onDirty: (dirty: boolean) => void;
    onActivityChanged: (activity: Action) => void;
    initialLink?: ProjectMeetingLink | null; onLinkOpened?: () => void;
}) {
    const [meetings, setMeetings] = useState<Meeting[]>([]);
    const [actions, setActions] = useState<Action[]>([]);
    const [selected, setSelected] = useState<Meeting | 'new' | null>(null);
    const [draft, setDraft] = useState<MeetingDraft | null>(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const busyRef = useRef(false);
    const [error, setError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [targetMention, setTargetMention] = useState<MeetingMention | null>(null);
    const load = async (signal?: AbortSignal) => {
        const [meetingRows, activityRows] = await Promise.all([
            collection<Meeting>('ProjectMeeting', { 'filter[project_id]': project.id, sort: '-date' }, signal),
            config.canReadActions ? collection<Action>('ProjectActivity', { 'filter[project_id]': project.id }, signal) : Promise.resolve([]),
        ]);
        if (!signal?.aborted) { setMeetings(meetingRows); setActions(activityRows); }
    };
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true); setError(null);
        load(controller.signal).catch(error => { if (!axios.isCancel(error)) setError(errorMessage(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [project.id, config.canReadActions, attempt]);
    useEffect(() => {
        if (loading || error || !initialLink || initialLink.projectId !== project.id) return;
        const controller = new AbortController();
        const meeting = meetings.find(meeting => meeting.id === initialLink.meetingId);
        if (!meeting) {
            setError(t('The mentioned meeting or notes are no longer available'));
            return;
        }
        item<MeetingMention>('ProjectMeetingMention', initialLink.mentionId, controller.signal)
            .then(mention => {
                if (controller.signal.aborted) return;
                if (mention.project_meeting_id !== meeting.id || !meeting.agenda.some(section => section.id === mention.agenda_item_id)) {
                    throw new Error(t('The mentioned meeting or notes are no longer available'));
                }
                setTargetMention(mention);
                setSelected(meeting);
                onLinkOpened?.();
            })
            .catch(error => { if (!axios.isCancel(error)) setError(errorMessage(error)); });
        return () => controller.abort();
    }, [loading, error, meetings, initialLink, project.id, onLinkOpened]);
    const run = async (operation: () => Promise<unknown>, refresh = true) => {
        if (busyRef.current) return false;
        busyRef.current = true; setBusy(true); setError(null);
        try {
            await operation();
        } catch (error) {
            setError(errorMessage(error));
            busyRef.current = false; setBusy(false);
            return false;
        } finally {
            if (!refresh) { busyRef.current = false; setBusy(false); }
        }
        if (refresh) {
            try { await load(); }
            catch (error) { setError(`${t('Changes saved, but the view could not be refreshed.')} ${errorMessage(error)}`); }
            finally { busyRef.current = false; setBusy(false); }
        }
        return true;
    };
    return <div>
        {error && <div className="alert alert-danger" role="alert">{error} <Btn variant="outline" disabled={busy} onClick={() => {
            const reload = () => { setSelected(null); setAttempt(value => value + 1); };
            if (selected) confirmAction('Discard unsaved meeting changes?', reload, 'Unsaved changes');
            else reload();
        }}>{t('Reload meetings')}</Btn></div>}
        {loading ? <p role="status">{t('Loading data…')}</p> : <fieldset disabled={busy}>
            {busy && <p role="status">{t('Saving…')}</p>}
            {selected === 'new' && !draft ? <NewMeetingSetup project={project} onPrepared={setDraft} onCancel={() => setSelected(null)} />
                : selected ? <MeetingEditor key={selected === 'new' ? 'new' : selected.id} meeting={selected === 'new' ? null : selected}
                initialValues={selected === 'new' ? draft?.values : undefined}
                project={project} config={config} actions={actions} busy={busy} onDirty={onDirty}
                targetMention={targetMention} onMentionAcknowledged={setTargetMention}
                onToggleActivity={activity => confirmAction(activity.completed_at ? 'Are you sure that you want to reopen this activity?' : 'Are you sure that you want to mark this activity as completed?', () => {
                    void run(async () => {
                        const response = await update('ProjectActivity', activity.id, {
                            completed_at: activity.completed_at ? null : new Date().toISOString().slice(0, 19).replace('T', ' '),
                        });
                        setActions(previous => previous.map(item => item.id === activity.id ? response.data : item));
                        onActivityChanged(response.data);
                    }, false);
                }, activity.completed_at ? 'Reopen action' : 'Finish action')}
                onCancel={() => { setSelected(null); setDraft(null); }}
                onOpenMeeting={id => {
                    const previous = meetings.find(meeting => meeting.id === id);
                    if (previous) { setSelected(previous); setDraft(null); }
                    else setError(t('Previous meeting is no longer available'));
                }}
                onSave={(values, complete) => run(() => {
                    const payload = { ...values, project_id: project.id, ...(complete ? { completed_at: new Date().toISOString() } : {}) };
                    return selected === 'new' ? create('ProjectMeeting', {
                        ...payload, agenda_template_id: draft?.agenda_template_id ?? null,
                        include_follow_up: draft?.include_follow_up ?? false, follow_up_meeting_id: draft?.follow_up_meeting_id ?? null,
                    }) : update('ProjectMeeting', selected.id, { ...payload, revision: selected.revision });
                })}
                onCreateActivity={async payload => {
                    let created: Action | null = null;
                    await run(async () => {
                        const response = await create('ProjectActivity', payload);
                        created = response.data;
                        setActions(previous => [...previous, response.data]);
                        onActivityChanged(response.data);
                    }, false);
                    return created;
                }} />
                : <Card><SectionTitle title={t('Meetings')} action={!project.archived_at && project.access.write && <Btn onClick={() => { setDraft(null); setSelected('new'); }}>{t('New meeting')}</Btn>} />
                    {!meetings.length && <p className="text-muted">{t('No meetings')}</p>}
                    <ul className="list-unstyled">{meetings.map(meeting => <li key={meeting.id} className="d-flex flex-wrap align-items-center justify-content-between gap-3 border-top py-3">
                        <div><h3 className="h5">{meeting.name}</h3><span className="text-muted">{meeting.date} · {meeting.location}</span>{' '}<Badge tone={meeting.completed_at ? 'ok' : 'info'}>{t(meeting.completed_at ? 'Finished' : 'Planned')}</Badge></div>
                        <div className="d-flex gap-2"><Btn variant="outline" onClick={() => setSelected(meeting)}>{t('Open meeting')}</Btn>
                            {meeting.access.delete && <Btn variant="ghost" onClick={() => confirmAction('Delete this meeting and its mentions?', () => { void run(() => remove('ProjectMeeting', meeting.id)); }, 'Delete meeting')}>{t('Delete')}</Btn>}
                        </div>
                    </li>)}</ul>
                </Card>}
        </fieldset>}
    </div>;
}
