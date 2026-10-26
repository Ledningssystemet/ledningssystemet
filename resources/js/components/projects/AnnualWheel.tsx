import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Btn, Card, Icon, Input } from '../ui';
import { collection, errorMessage, t, type Configuration, type Id, type ProjectSummary, type RecurringActivity } from './api';
import { participatingProjects } from './PersonalGantt';
import { monthNames as monthKeys, weekdays } from './RecurringActivities';

export type WheelActivity = Pick<RecurringActivity, 'id' | 'project_id' | 'name' | 'description' | 'responsible_user_id' | 'frequency' | 'weekday' | 'months' | 'day_of_month'>
    & { responsible_user_name?: string | null };
export type WheelDetails = { project: string; responsible: string; description: string | null; schedule: string };

export function scheduleText(activity: Pick<WheelActivity, 'frequency' | 'weekday' | 'months' | 'day_of_month'>): string {
    if (activity.frequency === 'daily') return t('Daily');
    if (activity.frequency === 'weekly') return activity.weekday ? `${t('Weekly')}, ${t(weekdays[activity.weekday - 1]).toLocaleLowerCase()}` : t('Weekly');
    const months = [...(activity.months ?? [])].map(Number).sort((a, b) => a - b);
    const when = months.length === 12 ? t('Every month') : months.map(month => t(monthKeys[month - 1])).join(', ');
    const day = activity.day_of_month ? `${t('Day of month')} ${activity.day_of_month}` : t('Last day of the month');
    return `${t('Monthly')}: ${when}. ${day}`;
}
export type WheelEntry = { id: Id; name: string; project_id: Id; month: number; count: number };

export const wheelColors = ['#d0587e', '#3ccf3c', '#2f8fb3', '#9bbb2a', '#5b3fc0', '#2ec4b6', '#2a4fc0', '#e040c8', '#d9825b', '#e3b927', '#7a4fd6', '#c94f4f'];
const maxLabelLength = 32;

export function occurrencesInMonth(activity: WheelActivity, year: number, month: number): number {
    const days = new Date(Date.UTC(year, month, 0)).getUTCDate();
    if (activity.frequency === 'daily') return days;
    if (activity.frequency === 'weekly') {
        let count = 0;
        for (let day = 1; day <= days; day++) {
            if ((new Date(Date.UTC(year, month - 1, day)).getUTCDay() + 6) % 7 + 1 === activity.weekday) count++;
        }
        return count;
    }
    return (activity.months ?? []).map(Number).includes(month) ? 1 : 0;
}

export function wheelEntries(activities: WheelActivity[], year: number, projectOrder: Id[] = []): WheelEntry[][] {
    const order = (id: Id) => { const index = projectOrder.indexOf(id); return index < 0 ? projectOrder.length : index; };
    const sorted = [...activities].sort((a, b) => order(a.project_id) - order(b.project_id) || a.name.localeCompare(b.name) || a.id - b.id);
    return Array.from({ length: 12 }, (_, index) => sorted.flatMap(activity => {
        const count = occurrencesInMonth(activity, year, index + 1);
        return count ? [{ id: activity.id, name: activity.name, project_id: activity.project_id, month: index + 1, count }] : [];
    }));
}

export const wheelLabel = (entry: Pick<WheelEntry, 'name' | 'count'>) => {
    const name = entry.name.length > maxLabelLength ? `${entry.name.slice(0, maxLabelLength - 1)}…` : entry.name;
    return entry.count > 1 ? `${name} (${entry.count})` : name;
};

const point = (center: number, degrees: number, radius: number) => {
    const radians = degrees * Math.PI / 180;
    return [center + radius * Math.sin(radians), center - radius * Math.cos(radians)] as const;
};
const round = (value: number) => Math.round(value * 100) / 100;
const xy = ([x, y]: readonly [number, number]) => `${round(x)} ${round(y)}`;

function sectorPath(center: number, from: number, to: number, inner: number, outer: number) {
    return `M ${xy(point(center, from, inner))} L ${xy(point(center, from, outer))} A ${outer} ${outer} 0 0 1 ${xy(point(center, to, outer))} `
        + `L ${xy(point(center, to, inner))} A ${inner} ${inner} 0 0 0 ${xy(point(center, from, inner))} Z`;
}

function monthNames(locale?: string) {
    const language = locale || (typeof document === 'undefined' ? 'en' : document.documentElement.lang || 'en');
    const format = new Intl.DateTimeFormat(language, { month: 'short', timeZone: 'UTC' });
    const long = new Intl.DateTimeFormat(language, { month: 'long', timeZone: 'UTC' });
    return Array.from({ length: 12 }, (_, index) => {
        const date = new Date(Date.UTC(2000, index, 1));
        return { short: format.format(date).replace('.', '').slice(0, 3).toLocaleUpperCase(language), long: long.format(date) };
    });
}

export function Wheel({ entries, projects, year, today = new Date(), locale, details }: {
    entries: WheelEntry[][]; projects: Pick<ProjectSummary, 'id' | 'name'>[]; year: number; today?: Date; locale?: string;
    details?: (entry: WheelEntry) => WheelDetails | null;
}) {
    const [active, setActive] = useState<{ key: string; entry: WheelEntry; x: number; y: number } | null>(null);
    useEffect(() => {
        if (!active) return;
        const onPointer = (event: MouseEvent) => {
            const target = event.target as Element;
            if (!target.closest?.('.pm-wheel-info, .pm-wheel-entry')) setActive(null);
        };
        const onKey = (event: KeyboardEvent) => { if (event.key === 'Escape') setActive(null); };
        document.addEventListener('mousedown', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [active]);
    const months = monthNames(locale);
    const colorOf = (id: Id) => wheelColors[Math.max(0, projects.findIndex(project => project.id === id)) % wheelColors.length];
    const nameOf = (id: Id) => projects.find(project => project.id === id)?.name ?? '';
    const maxPerMonth = Math.max(1, ...entries.map(month => month.length));
    const ring = Math.max(220, Math.ceil(maxPerMonth * 12 * 15 / (2 * Math.PI)));
    const inner = Math.round(ring * 0.42);
    const labelRadius = ring + 30;
    const size = 2 * (labelRadius + 210);
    const center = size / 2;
    const total = entries.reduce((sum, month) => sum + month.length, 0);
    const currentMonth = today.getFullYear() === year ? today.getMonth() : -1;
    const info = active && details ? details(active.entry) : null;
    return <div className="pm-wheel">
        <div className="pm-wheel-canvas" style={{ maxWidth: size }}>
        <svg className="pm-wheel-svg" viewBox={`0 0 ${size} ${size}`} role="group" aria-label={`${t('Year wheel')} ${year}`}>
            {months.map((month, index) => {
                const [x, y] = point(center, index * 30 + 15, (inner + ring) / 2);
                return <g key={index}>
                    <path className={`pm-wheel-sector${index % 2 ? ' pm-wheel-sector-shaded' : ''}`}
                        d={sectorPath(center, index * 30, (index + 1) * 30, inner, ring)} />
                    <text className="pm-wheel-month" x={round(x)} y={round(y)} textAnchor="middle" dominantBaseline="middle">{month.short}</text>
                </g>;
            })}
            {currentMonth >= 0 && <path className="pm-wheel-sector-current" d={sectorPath(center, currentMonth * 30, (currentMonth + 1) * 30, inner, ring)} />}
            <circle className="pm-wheel-ring" cx={center} cy={center} r={ring} />
            <circle className="pm-wheel-center" cx={center} cy={center} r={inner} />
            <text className="pm-wheel-total" x={center} y={center} textAnchor="middle" dominantBaseline="middle">
                {`${total} ${t(total === 1 ? 'activity' : 'activities')}`}
            </text>
            {entries.flatMap((month, monthIndex) => month.map((entry, index) => {
                const angle = monthIndex * 30 + (index + 0.5) * 30 / month.length;
                const color = colorOf(entry.project_id);
                const [dotX, dotY] = point(center, angle, ring);
                const [fromX, fromY] = point(center, angle, ring + 5);
                const [toX, toY] = point(center, angle, labelRadius - 4);
                const [textX, textY] = point(center, angle, labelRadius);
                const left = angle > 180;
                const label = wheelLabel(entry);
                const key = `${entry.id}-${entry.month}`;
                const summary = `${entry.name}${entry.count > 1 ? ` (${entry.count})` : ''} – ${nameOf(entry.project_id)}, ${months[monthIndex].long}`;
                const open = () => setActive({ key, entry, x: dotX / size * 100, y: dotY / size * 100 });
                return <g key={key} className={`pm-wheel-entry${active?.key === key ? ' pm-wheel-entry-active' : ''}`}
                    {...(details ? {
                        role: 'button', tabIndex: 0, 'aria-label': summary, 'aria-expanded': active?.key === key,
                        onClick: open,
                        onKeyDown: (event: React.KeyboardEvent) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); open(); } },
                    } : {})}>
                    <title>{summary}</title>
                    <line x1={round(fromX)} y1={round(fromY)} x2={round(toX)} y2={round(toY)} stroke={color} />
                    <circle cx={round(dotX)} cy={round(dotY)} r={4.5} fill={color} />
                    <text className="pm-wheel-label" x={round(textX)} y={round(textY)} textAnchor={left ? 'end' : 'start'} dominantBaseline="middle"
                        transform={`rotate(${round(left ? angle + 90 : angle - 90)} ${round(textX)} ${round(textY)})`}>{label}</text>
                </g>;
            }))}
        </svg>
        {active && info && <div className="pm-wheel-info" role="dialog" aria-label={active.entry.name} style={{
            left: `${active.x}%`, top: `${active.y}%`,
            transform: `translate(${active.x > 50 ? 'calc(-100% - 10px)' : '10px'}, ${active.y > 50 ? 'calc(-100% - 10px)' : '10px'})`,
        }}>
            <div className="pm-wheel-info-header">
                <h3 className="pm-wheel-info-title">{active.entry.name}</h3>
                <button type="button" className="pm-wheel-info-close" aria-label={t('Close')} onClick={() => setActive(null)}><Icon name="close" /></button>
            </div>
            <dl className="pm-wheel-info-details">
                <dt>{t('Project')}</dt><dd>{info.project}</dd>
                <dt>{t('Responsible')}</dt><dd>{info.responsible}</dd>
                <dt>{t('Description')}</dt><dd className={info.description ? 'pm-wheel-info-text' : 'text-muted'}>{info.description || t('No description')}</dd>
                <dt>{t('Schedule')}</dt><dd>{info.schedule}{active.entry.count > 1 && <div className="text-muted">{`${months[active.entry.month - 1].long}: ${active.entry.count} ${t('times')}`}</div>}</dd>
            </dl>
        </div>}
        </div>
        <ul className="visually-hidden">
            {entries.map((month, index) => <li key={index}>{months[index].long}: {month.length
                ? month.map(entry => `${wheelLabel(entry)} (${nameOf(entry.project_id)})`).join(', ') : t('No recurring activities')}</li>)}
        </ul>
    </div>;
}

export function ProjectPicker({ projects, counts, selected, onChange }: {
    projects: Pick<ProjectSummary, 'id' | 'name'>[]; counts: Record<Id, number>; selected: Id[] | null; onChange: (selected: Id[] | null) => void;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const ref = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (!open) return;
        const onPointer = (event: MouseEvent) => { if (!ref.current?.contains(event.target as Node)) setOpen(false); };
        const onKey = (event: KeyboardEvent) => { if (event.key === 'Escape') setOpen(false); };
        document.addEventListener('mousedown', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);
    const isSelected = (id: Id) => selected === null || selected.includes(id);
    const commit = (ids: Id[]) => onChange(projects.every(project => ids.includes(project.id)) ? null : ids);
    const toggle = (id: Id) => {
        const current = selected ?? projects.map(project => project.id);
        commit(current.includes(id) ? current.filter(item => item !== id) : [...current, id]);
    };
    const matches = projects.filter(project => project.name.toLocaleLowerCase().includes(query.trim().toLocaleLowerCase()));
    const label = selected === null ? t('All projects')
        : selected.length === 0 ? t('No projects selected')
        : selected.length === 1 ? projects.find(project => project.id === selected[0])?.name ?? t('All projects')
        : `${selected.length} ${t('projects selected')}`;
    return <div className="pm-wheel-picker" ref={ref}>
        <Btn variant="outline" aria-haspopup="true" aria-expanded={open} onClick={() => setOpen(!open)}>{label}<Icon name="expand_more" /></Btn>
        {open && <div className="pm-wheel-picker-menu">
            <div className="pm-wheel-picker-search">
                <Input type="search" autoFocus placeholder={t('Search projects…')} aria-label={t('Search projects…')} value={query} onChange={event => setQuery(event.target.value)} />
            </div>
            <ul className="pm-wheel-picker-list" aria-label={t('Projects')}>
                {matches.map(project => <li key={project.id}>
                    <button type="button" className="pm-wheel-picker-item" aria-pressed={isSelected(project.id)} onClick={() => toggle(project.id)}>
                        <span className="pm-wheel-picker-dot" style={{ backgroundColor: wheelColors[projects.indexOf(project) % wheelColors.length] }} />
                        <span className="pm-wheel-picker-name">{project.name}</span>
                        <span className="pm-wheel-picker-count">{counts[project.id] ?? 0}</span>
                    </button>
                </li>)}
                {!matches.length && <li className="pm-wheel-picker-empty">{t('No projects')}</li>}
            </ul>
            <div className="pm-wheel-picker-footer">
                <button type="button" onClick={() => onChange(null)}>{t('Show all')}</button>
                <button type="button" disabled={!matches.length} onClick={() => commit(matches.map(project => project.id))}>{t('Select search results')}</button>
            </div>
        </div>}
    </div>;
}

export function wheelActivities(projectIds: Id[], signal?: AbortSignal): Promise<WheelActivity[]> {
    if (!projectIds.length) return Promise.resolve([]);
    return collection<WheelActivity>('RecurringProjectActivity', {
        'fields[recurring_project_activities]': 'id,project_id,name,description,responsible_user_id,responsible_user_name,frequency,weekday,months,day_of_month',
        'filter[project_id]': projectIds.join(','),
        sort: 'name',
    }, signal);
}

export function AnnualWheel({ projects, config, today = new Date() }: { projects: ProjectSummary[]; config: Configuration; today?: Date }) {
    const [activities, setActivities] = useState<WheelActivity[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [selected, setSelected] = useState<Id[] | null>(null);
    const participating = participatingProjects(projects, config.currentUserId);
    const projectKey = participating.map(project => project.id).join(',');
    useEffect(() => {
        const controller = new AbortController();
        setActivities(null);
        setError(null);
        if (config.canReadActions) {
            wheelActivities(projectKey ? projectKey.split(',').map(Number) : [], controller.signal)
                .then(rows => { if (!controller.signal.aborted) setActivities(rows); })
                .catch(error => { if (!axios.isCancel(error)) setError(errorMessage(error)); });
        }
        return () => controller.abort();
    }, [projectKey, config.canReadActions, attempt]);

    if (!config.canReadActions) return null;
    if (error) return <Card><div className="alert alert-danger mb-0" role="alert">
        {error} <Btn variant="outline" onClick={() => setAttempt(value => value + 1)}>{t('Retry')}</Btn>
    </div></Card>;
    if (!activities) return <Card><p className="mb-0" role="status">{t('Loading the year wheel…')}</p></Card>;
    if (!participating.length) return <Card><p className="mb-0 text-muted">{t('You are not participating in any ongoing projects')}</p></Card>;
    const year = today.getFullYear();
    const visibleIds = selected ?? participating.map(project => project.id);
    const counts = activities.reduce<Record<Id, number>>((result, activity) => ({ ...result, [activity.project_id]: (result[activity.project_id] ?? 0) + 1 }), {});
    const entries = wheelEntries(activities.filter(activity => visibleIds.includes(activity.project_id)), year, participating.map(project => project.id));
    const details = (entry: WheelEntry): WheelDetails | null => {
        const activity = activities.find(item => item.id === entry.id);
        if (!activity) return null;
        return {
            project: participating.find(project => project.id === activity.project_id)?.name ?? '',
            responsible: activity.responsible_user_name ?? t('None'),
            description: activity.description,
            schedule: scheduleText(activity),
        };
    };
    return <Card className="pm-wheel-card">
        <p className="pm-wheel-description text-muted">{t('Only recurring project activities are shown, distributed over the months of the year according to their schedule. Planned activities are shown in the Gantt chart.')}</p>
        <div className="pm-wheel-toolbar">
            <ProjectPicker projects={participating} counts={counts} selected={selected} onChange={setSelected} />
            <span className="pm-wheel-year">{year}</span>
        </div>
        <Wheel entries={entries} projects={participating} year={year} today={today} details={details} />
    </Card>;
}
