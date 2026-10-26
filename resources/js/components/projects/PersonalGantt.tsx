import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Gantt, Btn, Card, type GanttGroup } from '../ui';
import { collection, errorMessage, t, type Action, type Configuration, type ProjectSummary } from './api';

type TimelineActivity = Pick<Action, 'id' | 'name' | 'project_id' | 'responsible_user_id' | 'startdate' | 'due' | 'completed_at'>;

export const participatingProjects = (projects: ProjectSummary[], userId: number) =>
    projects.filter(project => !project.archived_at
        && (project.responsible_user_id === userId || project.users.some(user => user.id === userId)));

export function personalGanttGroups(projects: ProjectSummary[], activities: TimelineActivity[], userId: number): GanttGroup[] {
    return participatingProjects(projects, userId).map(project => ({
        id: project.id,
        label: project.name,
        emptyMessage: t('No activities assigned to you'),
        rows: activities.filter(activity => activity.project_id === project.id && activity.responsible_user_id === userId)
            .map(activity => ({
                id: activity.id, name: activity.name, start: activity.startdate, end: activity.due,
                completed: Boolean(activity.completed_at), kind: 'activity',
            })),
    }));
}

export function personalActivities(projects: ProjectSummary[], userId: number, signal?: AbortSignal): Promise<TimelineActivity[]> {
    const ids = participatingProjects(projects, userId).map(project => project.id);
    if (!ids.length) return Promise.resolve([]);
    return collection<TimelineActivity>('ProjectActivity', {
        'fields[project_activities]': 'id,name,project_id,responsible_user_id,startdate,due,completed_at',
        'filter[project_id]': ids.join(','),
        'filter[responsible_user_id]': userId,
        sort: 'due',
    }, signal);
}

export function PersonalGantt({ projects, config }: { projects: ProjectSummary[]; config: Configuration }) {
    const [activities, setActivities] = useState<TimelineActivity[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);
    useEffect(() => {
        const controller = new AbortController();
        setActivities(null);
        setError(null);
        if (config.canReadActions) {
            personalActivities(projects, config.currentUserId, controller.signal)
                .then(rows => { if (!controller.signal.aborted) setActivities(rows); })
                .catch(error => { if (!axios.isCancel(error)) setError(errorMessage(error)); });
        }
        return () => controller.abort();
    }, [projects, config.currentUserId, config.canReadActions, attempt]);

    if (!config.canReadActions) return null;
    if (error) return <Card><div className="alert alert-danger mb-0" role="alert">
        {error} <Btn variant="outline" onClick={() => setAttempt(value => value + 1)}>{t('Retry')}</Btn>
    </div></Card>;
    if (!activities) return <Card><p className="mb-0" role="status">{t('Loading your project timeline…')}</p></Card>;
    const groups = personalGanttGroups(projects, activities, config.currentUserId);
    if (!groups.length) return <Card><p className="mb-0 text-muted">{t('You are not participating in any ongoing projects')}</p></Card>;
    return <Gantt title={t('My project activities')} description={t('Only planned project activities are shown. Recurring activities are shown in the year wheel.')} groups={groups} ariaLabel={t('My project activities')} />;
}
