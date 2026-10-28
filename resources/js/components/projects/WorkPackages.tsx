import React, { useState } from 'react';
import { action, confirmAction, create, dateValue, remove, t, todayDate, update, type Action, type Configuration, type Project, type Run, type WorkPackage } from './api';
import { Badge, Btn, Card, Icon, Input, Label, MultiSelect2, SectionTitle, Textarea } from '../ui';
import { ActivityFields, activityPayload, activityValues } from './ActionPlanPicker';

export const canCompleteWorkPackage = (item: WorkPackage) =>
    item.activities.length > 0 && item.activities.every(activity => Boolean(activity.completed_at));

const newPackageDates = () => {
    const today = todayDate();
    return { start_date: today, end_date: today };
};

export function WorkPackageForm({ item, project, actions, run, onSaved, onCancel }: {
    item?: WorkPackage; project: Project; actions: Action[]; run: Run; onSaved: () => void; onCancel: () => void;
}) {
    const [form, setForm] = useState({
        name: item?.name ?? '',
        description: item?.description ?? '',
        activities: item?.activities.map(activity => activity.id) ?? [],
        ...(item ? { start_date: dateValue(item.start_date), end_date: dateValue(item.end_date) } : newPackageDates()),
    });

    return <form className="border rounded p-3 my-3" onSubmit={async event => {
        event.preventDefault();
        const payload = { ...form, name: form.name.trim(), project_id: project.id, start_date: form.start_date || null, end_date: form.end_date || null };
        if (await run(() => item ? update('ProjectWorkPackage', item.id, payload) : create('ProjectWorkPackage', payload))) onSaved();
    }}>
        <Label text={t('Name')}><Input required maxLength={255} value={form.name} onChange={event => setForm({ ...form, name: event.target.value })} /></Label>
        <Label text={t('Description')}><Textarea rows={3} value={form.description} onChange={event => setForm({ ...form, description: event.target.value })} /></Label>
        <Label text={t('Linked activities')}><MultiSelect2 items={actions} values={form.activities} onChange={activities => setForm({ ...form, activities })} label={t('Linked activities')} /></Label>
        <div className="row">
            <div className="col-md-6"><Label text={t('Start date')}><Input type="date" value={form.start_date} onChange={event => setForm({ ...form, start_date: event.target.value })} /></Label></div>
            <div className="col-md-6"><Label text={t('End date')}><Input type="date" min={form.start_date || undefined} value={form.end_date} onChange={event => setForm({ ...form, end_date: event.target.value })} /></Label></div>
        </div>
        <p className="text-muted small">{t('A work package is completed manually after all linked activities have been completed.')}</p>
        <div className="d-flex gap-2"><Btn type="submit" disabled={!form.name.trim()}>{t('Save')}</Btn><Btn variant="ghost" onClick={onCancel}>{t('Cancel')}</Btn></div>
    </form>;
}

export function WorkPackageQuickAdd({ project, run }: { project: Project; run: Run }) {
    const [form, setForm] = useState(() => ({ name: '', ...newPackageDates() }));

    return <form className="pm-wp-quick-add" aria-label={t('Add work package')} onSubmit={async event => {
        event.preventDefault();
        if (await run(() => create('ProjectWorkPackage', {
            name: form.name.trim(), project_id: project.id, start_date: form.start_date || null, end_date: form.end_date || null,
        }))) setForm({ name: '', ...newPackageDates() });
    }}>
        <Input required maxLength={255} aria-label={t('New work package')} placeholder={t('New work package')} value={form.name} onChange={event => setForm({ ...form, name: event.target.value })} />
        <Input type="date" aria-label={t('Start date')} value={form.start_date} onChange={event => setForm({ ...form, start_date: event.target.value })} />
        <Input type="date" aria-label={t('End date')} min={form.start_date || undefined} value={form.end_date} onChange={event => setForm({ ...form, end_date: event.target.value })} />
        <Btn type="submit" disabled={!form.name.trim()}>{t('Add')}</Btn>
    </form>;
}

export function WorkPackageActivityForm({ item, project, config, run, onSaved, onCancel }: {
    item: WorkPackage; project: Project; config: Configuration; run: Run; onSaved: () => void; onCancel: () => void;
}) {
    const [form, setForm] = useState(activityValues(undefined, config.currentUserId));

    return <form className="pm-wp-editor" onSubmit={async event => {
        event.preventDefault();
        if (await run(() => action('ProjectWorkPackage', item.id, 'createactivity', activityPayload(form, project.id)))) onSaved();
    }}>
        <h4 className="h6">{t('Add new activity')}</h4>
        <ActivityFields form={form} onChange={setForm} config={config} />
        <div className="d-flex gap-2"><Btn type="submit" disabled={!form.name.trim()}>{t('Save')}</Btn><Btn variant="ghost" onClick={onCancel}>{t('Cancel')}</Btn></div>
    </form>;
}

export function WorkPackageLinksForm({ item, actions, run, onSaved, onCancel }: {
    item: WorkPackage; actions: Action[]; run: Run; onSaved: () => void; onCancel: () => void;
}) {
    const [ids, setIds] = useState(item.activities.map(activity => activity.id));

    return <form className="pm-wp-editor" onSubmit={async event => {
        event.preventDefault();
        if (await run(() => update('ProjectWorkPackage', item.id, { activities: ids }))) onSaved();
    }}>
        <Label text={t('Linked activities')}><MultiSelect2 items={actions} values={ids} onChange={setIds} label={t('Linked activities')} /></Label>
        <div className="d-flex gap-2"><Btn type="submit">{t('Save')}</Btn><Btn variant="ghost" onClick={onCancel}>{t('Cancel')}</Btn></div>
    </form>;
}

export function WorkPackageRow({ item, project, actions, config, run }: {
    item: WorkPackage; project: Project; actions: Action[]; config: Configuration; run: Run;
}) {
    const [editor, setEditor] = useState<'details' | 'links' | 'activity' | null>(null);
    const editable = !project.archived_at && project.access.write;
    const canChange = editable && item.access.update;
    const canEditContents = canChange && !item.completed_at;
    const finished = item.activities.filter(activity => activity.completed_at).length;
    const close = () => setEditor(null);

    return <li className={`pm-wp-row${item.completed_at ? ' pm-wp-finished' : ''}`}>
        <section aria-label={item.name}>
            <div className="pm-wp-heading">
                <div className="pm-wp-title"><h3>{item.name}</h3><Badge tone={item.completed_at ? 'ok' : 'muted'}>{t(item.completed_at ? 'Finished' : 'Not completed')}</Badge></div>
                {(item.start_date || item.end_date) && <span className="pm-wp-dates">{dateValue(item.start_date) || '—'} – {dateValue(item.end_date) || '—'}</span>}
                <div className="pm-wp-actions">
                    {canEditContents && <Btn variant="ghost" onClick={() => setEditor(editor === 'details' ? null : 'details')}>{t('Edit')}</Btn>}
                    {editable && item.access.delete && <Btn variant="ghost" onClick={() => {
                        confirmAction('Are you sure that you want to delete this work package? Its activities will not be deleted.', () => { void run(() => remove('ProjectWorkPackage', item.id)); }, 'Delete work package');
                    }}>{t('Delete')}</Btn>}
                </div>
            </div>
            {item.description && <p className="pm-wp-description">{item.description}</p>}
            <div className="pm-wp-progress">{item.activities.length ? <>{t('Completed activities')}: {finished} / {item.activities.length}</> : t('No linked activities')}</div>
            {canEditContents && editor === 'details' && <WorkPackageForm item={item} project={project} actions={actions} run={run} onSaved={close} onCancel={close} />}
            <div className="pm-wp-content">
                {item.activities.length ? <ul className="pm-wp-chips" aria-label={t('Linked activities')}>
                    {item.activities.map(activity => <li className="pm-wp-chip" key={activity.id}>
                        <span>{activity.name}</span><Badge tone={activity.completed_at ? 'ok' : 'muted'}>{t(activity.completed_at ? 'Finished' : 'Planned')}</Badge>
                        {canEditContents && <Btn variant="ghost" aria-label={`${t('Unlink activity')}: ${activity.name}`} onClick={() => {
                            void run(() => update('ProjectWorkPackage', item.id, { activities: item.activities.filter(link => link.id !== activity.id).map(link => link.id) }));
                        }}><Icon name="close" /></Btn>}
                    </li>)}
                </ul> : <p className="pm-wp-empty">{t('No linked activities')}</p>}
                {canEditContents && <div className="d-flex flex-wrap gap-2">
                    <Btn variant="outline" onClick={() => setEditor(editor === 'links' ? null : 'links')}>{t('Link existing activities')}</Btn>
                    {config.canCreateAction && <Btn variant="outline" onClick={() => setEditor(editor === 'activity' ? null : 'activity')}><Icon name="add" />{t('New activity')}</Btn>}
                </div>}
                {canEditContents && editor === 'links' && <WorkPackageLinksForm item={item} actions={actions} run={run} onSaved={close} onCancel={close} />}
                {canEditContents && config.canCreateAction && editor === 'activity' && <WorkPackageActivityForm item={item} project={project} config={config} run={run} onSaved={close} onCancel={close} />}
                {item.activities.length > 0 && <ul className="pm-wp-activity-list">
                    {item.activities.map(activity => <li key={activity.id}>
                        <span className={activity.completed_at ? 'pm-wp-activity-done' : undefined}>{activity.name}</span>
                        <span className="pm-wp-activity-date">{dateValue(actions.find(action => action.id === activity.id)?.due)}</span>
                        <Badge tone={activity.completed_at ? 'ok' : 'muted'}>{t(activity.completed_at ? 'Finished' : 'Planned')}</Badge>
                    </li>)}
                </ul>}
                <div className="pm-wp-footer">
                    {item.completed_at ? <span className="pm-wp-completed-at">{t('Completed at')}: {item.completed_at}</span> : !canCompleteWorkPackage(item) && <span className="pm-wp-hint">{t('A work package can only be completed when it contains activities and all of them are completed')}</span>}
                    {canChange && <Btn variant="outline" disabled={!item.completed_at && !canCompleteWorkPackage(item)} onClick={() => {
                        confirmAction(item.completed_at ? 'Are you sure that you want to reopen this work package?' : 'Are you sure that you want to complete this work package?', () => {
                            void run(() => update('ProjectWorkPackage', item.id, {
                                completed_at: item.completed_at ? null : new Date().toISOString().slice(0, 19).replace('T', ' '),
                            }));
                        }, item.completed_at ? 'Reopen work package' : 'Complete work package');
                    }}>{t(item.completed_at ? 'Reopen' : 'Finish')}</Btn>}
                </div>
            </div>
        </section>
    </li>;
}

export function WorkPackages({ project, workPackages, actions, config, run }: {
    project: Project; workPackages: WorkPackage[]; actions: Action[]; config: Configuration; run: Run;
}) {
    const editable = !project.archived_at && project.access.write;

    return <Card className="pm-work-packages">
        <SectionTitle title={t('Work packages')} />
        <p className="pm-wp-intro">{t('Work packages have start and end dates and are completed manually after all linked activities are finished.')}</p>
        {editable && config.canCreateWorkPackage && <WorkPackageQuickAdd project={project} run={run} />}
        {!workPackages.length && <p className="text-muted">{t('No work packages')}</p>}
        <ol className="pm-wp-timeline">{workPackages.map(item => <WorkPackageRow key={item.id} item={item} project={project} actions={actions} config={config} run={run} />)}</ol>
    </Card>;
}
