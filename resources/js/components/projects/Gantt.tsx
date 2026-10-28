import React from 'react';
import { Gantt as GanttChart, type GanttGroup, type GanttRow } from '../ui/Gantt';
import { nameOf, t, type Action, type Configuration, type Project, type WorkPackage } from './api';

export function Gantt({ project, workPackages, actions, config, today }: {
    project: Project; workPackages: WorkPackage[]; actions: Action[]; config: Configuration; today?: string;
}) {
    const groups: GanttGroup[] = [];
    const activityRow = (item: Action): GanttRow => ({
        id: item.id, name: item.name, meta: nameOf(config.users, item.responsible_user_id),
        start: item.startdate, end: item.due, completed: Boolean(item.completed_at), kind: 'activity',
    });
    const linkedIds = new Set<number>();
    if (config.canReadWorkPackages) {
        for (const item of workPackages) {
            const ids = new Set(item.activities.map(activity => activity.id));
            ids.forEach(id => linkedIds.add(id));
            groups.push({
                id: `work-package-${item.id}`, label: item.name,
                summary: { id: item.id, name: item.name, start: item.start_date, end: item.end_date, completed: Boolean(item.completed_at), kind: 'bar' },
                emptyMessage: config.canReadActions ? t('No actions') : undefined,
                rows: config.canReadActions ? actions.filter(activity => ids.has(activity.id)).map(activityRow) : [],
            });
        }
        if (!workPackages.length) groups.push({ id: 'work-packages', label: t('Work packages'), emptyMessage: t('No work packages'), rows: [] });
    }
    if (config.canReadActions) groups.push({
        id: 'activities',
        label: t('Other project activities'),
        emptyMessage: t('No actions'),
        rows: actions.filter(item => !linkedIds.has(item.id)).map(activityRow),
    });

    return <GanttChart title={project.name} groups={groups} today={today} ariaLabel={t('Project Gantt chart')} />;
}
