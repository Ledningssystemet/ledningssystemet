import React, { useState } from 'react';
import { confirmAction, create, dateValue, nameOf, remove, t, todayDate, update, type Action, type Configuration, type Project, type Risk, type Run, type WorkPackage } from './api';
import { Badge, Btn, Card, Input, Label, Options, SectionTitle, Select, Textarea } from '../ui';
import { RecurringActivities } from './RecurringActivities';

export const activityValues = (item: Action | undefined, currentUserId: number) => {
    const today = todayDate();
    return {
        name: item?.name ?? '',
        description: item?.description ?? '',
        responsible_user_id: String(item ? item.responsible_user_id ?? '' : currentUserId),
        startdate: item ? dateValue(item.startdate) : today,
        due: item ? dateValue(item.due) : today,
        estimated_cost: String(item?.estimated_cost ?? ''),
        estimated_hours: String(item?.estimated_hours ?? ''),
        risks: item?.risks.map(risk => risk.id) ?? [],
    };
};
export type ActivityValues = ReturnType<typeof activityValues>;

type ActivityPayload = Omit<ActivityValues, 'responsible_user_id' | 'startdate' | 'due' | 'estimated_cost' | 'estimated_hours'> & {
    project_id: number;
    responsible_user_id: number | null;
    startdate: string | null;
    due: string | null;
    estimated_cost: number | null;
    estimated_hours: number | null;
};

export function activityPayload(form: ActivityValues, projectId: number): ActivityPayload;
export function activityPayload(form: ActivityValues, projectId: number, includeRisks: false): Omit<ActivityPayload, 'risks'>;
export function activityPayload(form: ActivityValues, projectId: number, includeRisks = true) {
    const { risks, ...fields } = form;
    return {
        ...fields,
        ...(includeRisks ? { risks } : {}),
        name: form.name.trim(),
        project_id: projectId,
        responsible_user_id: Number(form.responsible_user_id) || null,
        startdate: form.startdate || null,
        due: form.due || null,
        estimated_cost: form.estimated_cost === '' ? null : Number(form.estimated_cost),
        estimated_hours: form.estimated_hours === '' ? null : Number(form.estimated_hours),
    };
}

export function ActivityFields({ form, onChange, config, risks, dates = true }: {
    form: ActivityValues; onChange: (form: ActivityValues) => void; config: Configuration; risks?: Risk[]; dates?: boolean;
}) {
    const field = (key: Exclude<keyof ActivityValues, 'risks'>, value: string) => onChange({ ...form, [key]: value });
    return <div className="row">
        <div className="col-md-6"><Label text={t('Name')}><Input required maxLength={255} value={form.name} onChange={event => field('name', event.target.value)} /></Label></div>
        <div className="col-md-6"><Label text={t('Responsible')}><Select value={form.responsible_user_id} onChange={event => field('responsible_user_id', event.target.value)}><Options items={config.users} /></Select></Label></div>
        {dates && <div className="col-md-6"><Label text={t('Start date')}><Input type="date" value={form.startdate} onChange={event => field('startdate', event.target.value)} /></Label></div>}
        {dates && <div className="col-md-6"><Label text={t('Due')}><Input type="date" min={form.startdate || undefined} value={form.due} onChange={event => field('due', event.target.value)} /></Label></div>}
        <div className="col-md-6"><Label text={t('Estimated cost')}><Input type="number" min={0} max={99999999.99} step="0.01" value={form.estimated_cost} onChange={event => field('estimated_cost', event.target.value)} /></Label></div>
        <div className="col-md-6"><Label text={t('Estimated hours')}><Input type="number" min={0} max={4294967295} step={1} value={form.estimated_hours} onChange={event => field('estimated_hours', event.target.value)} /></Label></div>
        {risks && config.canReadRisks && <div className="col-12"><Label text={t('Linked risks')}><Select multiple value={form.risks.map(String)} onChange={event => onChange({ ...form, risks: Array.from(event.target.selectedOptions, option => Number(option.value)) })}><Options items={risks} empty={false} /></Select></Label></div>}
        <div className="col-12"><Label text={t('Description')}><Textarea rows={3} value={form.description} onChange={event => field('description', event.target.value)} /></Label></div>
    </div>;
}

function ActivityTable({ actions, config, blockedActivityIds, onEdit, onToggle, onRemove }: {
    actions: Action[]; config: Configuration; onEdit?: (item: Action) => void;
    blockedActivityIds: Set<number>;
    onToggle?: (item: Action) => void; onRemove?: (item: Action) => void;
}) {
    return <div className="table-responsive"><table className="table align-middle">
        <thead><tr>{['Name', 'Responsible', 'Start date', 'Due', 'Estimated cost', 'Estimated hours', 'Status', 'Actions'].map(label => <th key={label} scope="col">{t(label)}</th>)}</tr></thead>
        <tbody>{actions.map(item => <tr key={item.id}>
            <td className="text-break">{item.name}{item.recurring_project_activity_id && <Badge>{t('Recurring project activity')}</Badge>}<div className="text-muted small" style={{ whiteSpace: 'pre-wrap' }}>{item.description}</div><div className="d-flex flex-wrap gap-1 mt-1">{item.risks.map(risk => <Badge key={risk.id}>RISK-{risk.id}: {risk.name}</Badge>)}</div>{blockedActivityIds.has(item.id) && <p className="text-muted small mt-1">{t('Reopen all completed work packages containing this activity before reopening it')}</p>}</td>
            <td>{nameOf(config.users, item.responsible_user_id)}</td>
            <td className="text-nowrap">{dateValue(item.startdate)}</td>
            <td className="text-nowrap">{dateValue(item.due)}</td>
            <td>{item.estimated_cost ?? '—'}</td>
            <td>{item.estimated_hours ?? '—'}</td>
            <td><Badge tone={item.completed_at ? 'ok' : 'info'}>{t(item.completed_at ? 'Finished' : 'Planned')}</Badge></td>
            <td><div className="d-flex flex-wrap gap-2">
                {onEdit && item.access.update && !item.completed_at && <Btn variant="outline" onClick={() => onEdit(item)}>{t('Edit')}</Btn>}
                {onToggle && item.access.update && <Btn disabled={blockedActivityIds.has(item.id)} onClick={() => onToggle(item)}>{t(item.completed_at ? 'Reopen' : 'Finish')}</Btn>}
                {onRemove && item.access.delete && <Btn variant="ghost" disabled={blockedActivityIds.has(item.id)} onClick={() => onRemove(item)}>{t('Delete')}</Btn>}
            </div></td>
        </tr>)}</tbody>
    </table>{!actions.length && <p className="text-muted">{t('No actions')}</p>}</div>;
}

export function ActivityForm({ item, project, risks, config, run, onSaved, onCancel }: {
    item?: Action; project: Project; risks: Risk[]; config: Configuration; run: Run; onSaved: () => void; onCancel: () => void;
}) {
    const [form, setForm] = useState(activityValues(item, config.currentUserId));

    return <form className="border rounded p-3 my-3" onSubmit={async event => {
        event.preventDefault();
        const saved = item
            ? await run(() => update('ProjectActivity', item.id, activityPayload(form, project.id)))
            : await run(() => create('ProjectActivity', activityPayload(form, project.id, false)));
        if (saved) onSaved();
    }}>
        <ActivityFields form={form} onChange={setForm} config={config} risks={item ? risks : undefined} />
        <div className="d-flex gap-2"><Btn type="submit" disabled={!form.name.trim()}>{t('Save')}</Btn><Btn variant="ghost" onClick={onCancel}>{t('Cancel')}</Btn></div>
    </form>;
}

function toggleCompleted(item: Action, run: Run) {
    confirmAction(item.completed_at ? 'Are you sure that you want to reopen this activity?' : 'Are you sure that you want to mark this activity as completed?', () => {
        void run(() => update('ProjectActivity', item.id, {
            completed_at: item.completed_at ? null : new Date().toISOString().slice(0, 19).replace('T', ' '),
        }));
    }, item.completed_at ? 'Reopen action' : 'Finish action');
}

export function ProjectActions({ actions, project, risks, workPackages = [], config, run }: {
    actions: Action[]; project: Project; risks: Risk[]; workPackages?: WorkPackage[]; config: Configuration; run: Run;
}) {
    const [editing, setEditing] = useState<Action | 'new' | null>(null);
    const editable = !project.archived_at && project.access.write;
    const canUpdateActivities = !project.archived_at;
    return <><Card><SectionTitle title={t('Activities')} action={editable && config.canCreateAction && <Btn onClick={() => setEditing('new')}>{t('Add new activity')}</Btn>} />
        {editing && <ActivityForm key={editing === 'new' ? 'new' : editing.id} item={editing === 'new' ? undefined : editing} project={project} risks={risks} config={config} run={run} onSaved={() => setEditing(null)} onCancel={() => setEditing(null)} />}
        <ActivityTable
            actions={actions}
            blockedActivityIds={new Set(workPackages.filter(item => item.completed_at).flatMap(item => item.activities.map(activity => activity.id)))}
            config={config}
            onEdit={editable ? setEditing : undefined}
            onToggle={canUpdateActivities ? item => toggleCompleted(item, run) : undefined}
            onRemove={editable ? item => {
                confirmAction('Are you sure that you want to delete this activity?', () => { void run(() => remove('ProjectActivity', item.id)); }, 'Delete activity');
            } : undefined}
        />
    </Card><RecurringActivities project={project} config={config} run={run} /></>;
}
