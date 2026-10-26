import React, { useEffect, useRef } from 'react';
import { t } from '@/lib/i18n';
import { Card } from './index';
// @ts-ignore
import './gantt.css';

export type GanttRow = {
    id: string | number;
    name: string;
    meta?: string;
    start?: string | null;
    end?: string | null;
    completed?: boolean;
    kind: 'bar' | 'activity';
};
export type GanttGroup = {
    id: string | number;
    label: string;
    rows: GanttRow[];
    emptyMessage?: string;
    summary?: GanttRow;
};
export type GanttProps = {
    title: string;
    groups: GanttGroup[];
    startDate?: string | null;
    endDate?: string | null;
    today?: string;
    locale?: string;
    ariaLabel?: string;
};

const dateValue = (value?: string | null) => value?.slice(0, 10) ?? '';
const todayDate = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

const dayMs = 86400000;
const dayNumber = (value: string) => Date.parse(`${dateValue(value)}T00:00:00Z`) / dayMs;
const monthStart = (day: number) => {
    const date = new Date(day * dayMs);
    return Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1) / dayMs;
};
const nextMonth = (day: number) => {
    const date = new Date(day * dayMs);
    return Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 1) / dayMs;
};

export function ganttScale(dates: (string | null | undefined)[], today = todayDate()) {
    const days = dates.filter((date): date is string => Boolean(date)).map(dayNumber);
    if (!days.length) days.push(dayNumber(today));
    const start = monthStart(Math.min(...days));
    const end = nextMonth(nextMonth(monthStart(Math.max(...days))));
    const months: { start: number; end: number }[] = [];
    for (let cursor = start; cursor < end; cursor = nextMonth(cursor)) {
        const boundary = nextMonth(cursor);
        months.push({ start: cursor, end: boundary });
    }
    const position = (day: number) => (day - start) / (end - start) * 100;
    return { start, end, months, position };
}

export function ganttSpan(start: string | null, end: string | null, scale: ReturnType<typeof ganttScale>, inclusive = false) {
    if (!start || !end) return null;
    const first = dayNumber(start);
    const last = dayNumber(end);
    if (last < first) return null;
    return { left: `${scale.position(first)}%`, width: `${(last - first + (inclusive ? 1 : 0)) / (scale.end - scale.start) * 100}%` };
}

export function Gantt({ title, groups, startDate, endDate, today = todayDate(), locale, ariaLabel = t('Gantt chart') }: GanttProps) {
    const scrollRef = useRef<HTMLDivElement>(null);
    const todayRef = useRef<HTMLDivElement>(null);
    const labelRef = useRef<HTMLDivElement>(null);
    const visibleDates = groups.flatMap(group => [...(group.summary ? [group.summary] : []), ...group.rows].flatMap(row => [row.start, row.end]));
    const scale = ganttScale(visibleDates.some(Boolean) ? visibleDates : [startDate, endDate], today);
    const todayPosition = scale.position(dayNumber(today));
    useEffect(() => {
        const scroll = scrollRef.current;
        const marker = todayRef.current;
        if (!scroll || !marker) return;
        const focusToday = () => {
            const labelWidth = labelRef.current?.getBoundingClientRect().width ?? 0;
            const visibleWidth = scroll.clientWidth - labelWidth;
            if (visibleWidth <= 0) return;
            const markerPosition = marker.getBoundingClientRect().left - scroll.getBoundingClientRect().left + scroll.scrollLeft;
            scroll.scrollLeft = Math.max(0, Math.min(scroll.scrollWidth - scroll.clientWidth, markerPosition - labelWidth - visibleWidth / 2));
        };
        focusToday();
        const observer = new ResizeObserver(focusToday);
        observer.observe(scroll);
        return () => observer.disconnect();
    }, [today, scale.start, scale.end, title]);
    const language = locale || (typeof document === 'undefined' ? 'en' : document.documentElement.lang || 'en');
    const monthFormat = new Intl.DateTimeFormat(language, { month: 'short', year: '2-digit', timeZone: 'UTC' });
    const grid = <div className="ls-gantt-grid" aria-hidden="true">
        {scale.months.map(month => <span key={month.start} style={{ left: `${scale.position(month.start)}%` }} />)}
    </div>;
    const rangeText = (start: string | null, end: string | null) => `${dateValue(start) || '—'} – ${dateValue(end) || '—'}`;
    const emptyRange = (start: string | null, end: string | null) => <span className="ls-gantt-undated">{rangeText(start, end)} · {t('Incomplete dates')}</span>;
    const overdue = (row: GanttRow) => !row.completed && !!row.end && dayNumber(row.end) < dayNumber(today);
    const bar = (row: GanttRow) => {
        const span = ganttSpan(row.start ?? null, row.end ?? null, scale, true);
        const description = `${row.name}: ${rangeText(row.start ?? null, row.end ?? null)} · ${t(row.completed ? 'Finished' : overdue(row) ? 'Overdue' : 'Not completed')}`;
        return span ? <span role="img" aria-label={description} title={description} className={`ls-gantt-bar${row.completed ? ' ls-gantt-completed' : overdue(row) ? ' ls-gantt-overdue-bar' : ''}`} style={span} />
            : emptyRange(row.start ?? null, row.end ?? null);
    };

    return <Card className="ls-gantt-card">
        <div ref={scrollRef} className="ls-gantt-scroll" role="region" aria-label={ariaLabel} tabIndex={0}>
            <div className="ls-gantt" style={{ minWidth: Math.max(760, 224 + scale.months.length * 112) }}>
                <div className="ls-gantt-header">
                    <div ref={labelRef} className="ls-gantt-label" title={title}>{title}</div>
                    <div className="ls-gantt-months">{scale.months.map(month => <span key={month.start} style={{ width: `${(month.end - month.start) / (scale.end - scale.start) * 100}%` }}>
                        {monthFormat.format(new Date(month.start * dayMs))}
                    </span>)}</div>
                </div>
                {todayPosition >= 0 && todayPosition < 100 && <div className="ls-gantt-overlay" aria-hidden="true">
                    <div ref={todayRef} className="ls-gantt-today" style={{ left: `${todayPosition}%` }}><span>{t('Today')}</span></div>
                </div>}
                {groups.map(group => <React.Fragment key={group.id}>
                    <div className={`ls-gantt-group${group.summary && overdue(group.summary) ? ' ls-gantt-overdue' : ''}`}><h2 className="ls-gantt-label">{group.label}</h2><div className="ls-gantt-track">{grid}{group.summary && bar(group.summary)}</div></div>
                    {!group.rows.length && group.emptyMessage && <div className="ls-gantt-row"><div className="ls-gantt-label ls-gantt-muted">{group.emptyMessage}</div><div className="ls-gantt-track">{grid}</div></div>}
                    {group.rows.map(row => {
                        const span = ganttSpan(row.start ?? null, row.end ?? null, scale, row.kind === 'bar');
                        const status = t(row.completed ? 'Finished' : overdue(row) ? 'Overdue' : row.kind === 'bar' ? 'Not completed' : 'Planned');
                        const description = `${row.name}: ${rangeText(row.start ?? null, row.end ?? null)} · ${status}`;
                        return <div className={`ls-gantt-row${overdue(row) ? ' ls-gantt-overdue' : ''}`} key={row.id}>
                            <div className="ls-gantt-label"><span className="ls-gantt-name" title={row.name}>{row.name}</span>{row.meta && <span className="ls-gantt-meta">{row.meta}</span>}</div>
                            <div className={`ls-gantt-track${row.completed ? ' ls-gantt-activity-completed' : ''}`}>{grid}
                                {row.kind === 'bar' ? bar(row)
                                    : row.end ? <span role="img" aria-label={description} title={description} className="ls-gantt-activity" style={span ?? { left: `${scale.position(dayNumber(row.end))}%`, width: 0 }}>
                                    {span && <span className="ls-gantt-activity-line" />}
                                    <span className="ls-gantt-dot" />
                                </span> : <span className="ls-gantt-undated">{t('No due date')}</span>}
                            </div>
                        </div>;
                    })}
                </React.Fragment>)}
            </div>
        </div>
        <div className="ls-gantt-legend" aria-label={t('Chart legend')}>
            <span><i className="ls-gantt-key-package" />{t('Not completed')}</span>
            <span><i className="ls-gantt-key-completed" />{t('Finished')}</span>
            <span><i className="ls-gantt-key-activity" />{t('Activity period and due date')}</span>
            <span><i className="ls-gantt-key-overdue" />{t('Overdue')}</span>
            <span><i className="ls-gantt-key-today" />{t('Today')}</span>
        </div>
    </Card>;
}
