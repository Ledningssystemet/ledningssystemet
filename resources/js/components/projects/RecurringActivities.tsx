import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { collection, confirmAction, create, errorMessage, nameOf, remove, t, update, type Configuration, type Project, type RecurringActivity, type Run } from './api';
import { ActivityFields, activityPayload, activityValues } from './ActionPlanPicker';
import { Btn, Card, Label, SectionTitle, Select } from '../ui';

export const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
export const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

export function recurrenceValues(item?: RecurringActivity) {
    return {
        frequency: item?.frequency ?? 'monthly',
        weekday: String(item?.weekday ?? 1),
        months: item?.months ?? monthNames.map((_, index) => index + 1),
        day_of_month: String(item?.day_of_month ?? ''),
    };
}

export function recurrencePayload(form: ReturnType<typeof recurrenceValues>) {
    return {
        frequency: form.frequency,
        weekday: form.frequency === 'weekly' ? Number(form.weekday) : null,
        months: form.months,
        day_of_month: form.frequency === 'monthly' && form.day_of_month !== '' ? Number(form.day_of_month) : null,
    };
}

export function RecurringActivityForm({ item, project, config, run, onSaved, onCancel }: {
    item?: RecurringActivity; project: Project; config: Configuration; run: Run; onSaved: () => void; onCancel: () => void;
}) {
    const [activity, setActivity] = useState(() => ({
        ...activityValues(undefined, config.currentUserId),
        ...(item ? {
            name: item.name, description: item.description ?? '', responsible_user_id: String(item.responsible_user_id ?? ''),
            estimated_cost: String(item.estimated_cost ?? ''), estimated_hours: String(item.estimated_hours ?? ''),
        } : {}),
    }));
    const [form, setForm] = useState(() => recurrenceValues(item));
    return <form className="border rounded p-3 my-3" onSubmit={async event => {
        event.preventDefault();
        const { startdate, due, ...fields } = activityPayload(activity, project.id, false);
        const payload = { ...fields, ...recurrencePayload(form) };
        if (await run(() => item ? update('RecurringProjectActivity', item.id, payload) : create('RecurringProjectActivity', payload))) onSaved();
    }}>
        <ActivityFields form={activity} onChange={setActivity} config={config} dates={false} />
        <Label text={t('Recurrence')}><Select value={form.frequency} onChange={event => {
            const frequency = event.target.value;
            if (frequency === 'daily' || frequency === 'weekly' || frequency === 'monthly') setForm({ ...form, frequency });
        }}><option value="daily">{t('Daily')}</option><option value="weekly">{t('Weekly')}</option><option value="monthly">{t('Monthly')}</option></Select></Label>
        {form.frequency === 'weekly' && <Label text={t('Weekday')}><Select value={form.weekday} onChange={event => setForm({ ...form, weekday: event.target.value })}>
            {weekdays.map((day, index) => <option key={day} value={index + 1}>{t(day)}</option>)}
        </Select></Label>}
        {form.frequency === 'monthly' && <>
            <Label text={t('Months')}><Select required multiple value={form.months.map(String)} onChange={event => setForm({ ...form, months: Array.from(event.target.selectedOptions, option => Number(option.value)) })}>
                {monthNames.map((month, index) => <option key={month} value={index + 1}>{t(month)}</option>)}
            </Select></Label>
            <Label text={t('Day of month')}><Select value={form.day_of_month} onChange={event => setForm({ ...form, day_of_month: event.target.value })}>
                <option value="">{t('Last day of the month')}</option>
                {Array.from({ length: 31 }, (_, index) => <option key={index} value={index + 1}>{index + 1}</option>)}
            </Select></Label>
            <p className="text-muted">{t('Shorter months use the last day of the month')}</p>
        </>}
        <p className="text-muted">{t('One unfinished activity at a time. Missed occurrences are skipped. Start and due dates are the scheduled day.')}</p>
        <div className="d-flex gap-2"><Btn type="submit" disabled={!activity.name.trim() || !form.months.length}>{t('Save')}</Btn><Btn variant="ghost" onClick={onCancel}>{t('Cancel')}</Btn></div>
    </form>;
}

export function RecurringActivities({ project, config, run }: { project: Project; config: Configuration; run: Run }) {
    const [items, setItems] = useState<RecurringActivity[]>([]);
    const [editing, setEditing] = useState<RecurringActivity | 'new' | null>(null);
    const [revision, setRevision] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    useEffect(() => {
        const controller = new AbortController();
        setError(null);
        setLoading(true);
        collection<RecurringActivity>('RecurringProjectActivity', { 'filter[project_id]': project.id, sort: 'name' }, controller.signal)
            .then(rows => { if (!controller.signal.aborted) setItems(rows); })
            .catch(error => { if (!axios.isCancel(error)) setError(errorMessage(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [project.id, revision]);
    const editable = !project.archived_at && project.access.write;
    const mutate: Run = async operation => {
        const saved = await run(operation);
        if (saved) setRevision(value => value + 1);
        return saved;
    };
    return <Card className="mt-3"><SectionTitle title={t('Recurring project activities')} action={editable && config.canCreateAction && <Btn onClick={() => setEditing('new')}>{t('Add recurring activity')}</Btn>} />
        {error && <div className="alert alert-danger" role="alert">{error} <Btn variant="outline" onClick={() => setRevision(value => value + 1)}>{t('Retry')}</Btn></div>}
        {editing && editable && <RecurringActivityForm key={editing === 'new' ? 'new' : editing.id} item={editing === 'new' ? undefined : editing}
            project={project} config={config} run={mutate} onSaved={() => setEditing(null)} onCancel={() => setEditing(null)} />}
        {loading ? <p role="status">{t('Loading data…')}</p> : !error && <div className="table-responsive"><table className="table align-middle">
            <thead><tr>{['Name', 'Responsible', 'Recurrence', 'Actions'].map(label => <th key={label} scope="col">{t(label)}</th>)}</tr></thead>
            <tbody>{items.map(item => <tr key={item.id}>
                <td>{item.name}<div className="text-muted small">{item.description}</div></td>
                <td>{nameOf(config.users, item.responsible_user_id)}</td>
                <td>{t(item.frequency === 'daily' ? 'Daily' : item.frequency === 'weekly' ? 'Weekly' : 'Monthly')}
                    {item.frequency === 'weekly' && item.weekday && `: ${t(weekdays[item.weekday - 1])}`}
                    {item.frequency === 'monthly' && <><div>{item.months.map(month => t(monthNames[month - 1])).join(', ')}</div>{item.day_of_month ?? t('Last day of the month')}</>}
                </td>
                <td><div className="d-flex gap-2">
                    {editable && item.access.update && <Btn variant="outline" onClick={() => setEditing(item)}>{t('Edit')}</Btn>}
                    {editable && item.access.delete && <Btn variant="ghost" onClick={() => confirmAction('Delete this recurrence? Existing activities will be kept.', () => {
                        void mutate(() => remove('RecurringProjectActivity', item.id)).then(saved => { if (saved && editing !== 'new' && editing?.id === item.id) setEditing(null); });
                    }, 'Delete recurring activity')}>{t('Delete')}</Btn>}
                </div></td>
            </tr>)}</tbody>
        </table>{!items.length && <p className="text-muted">{t('No recurring activities')}</p>}</div>}
    </Card>;
}
