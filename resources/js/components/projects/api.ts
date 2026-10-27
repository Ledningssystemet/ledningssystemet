import axios from 'axios';
import { t } from '../../lib/i18n.ts';
export { t } from '../../lib/i18n.ts';

declare global {
    interface Window {
        confirmDialog: (title: string, message: string, onConfirm: () => void, options: { warning: boolean }) => void;
        confirmCommentDialog: (title: string, message: string, comment: string | null, onConfirm: (comment: string) => void, options: { warning: boolean }) => void;
    }
}

export type Id = number;
export type Option = { id: Id; name: string; enabled?: boolean };
type UserOption = Option & { access_groups: Option[] };
type AccessGroupOption = { id: Id; risk_level_id: Id | null };
type RiskLevelOption = RiskLevel & { risk_mappings: { probability_level_id: Id; consequence_level_id: Id }[] };
export type Access = { create: boolean; read: boolean; update: boolean; delete: boolean };
export type Participant = Option & { readonly: boolean };
export type RiskLevel = Option & { color: string; ordinal: number };
export type Project = {
    id: Id;
    name: string;
    description: string | null;
    responsible_user_id: Id | null;
    responsible_user_name?: string | null;
    project_type_id: Id | null;
    start_date: string;
    end_date: string | null;
    archived_at: string | null;
    users: Participant[];
    riskcount: number;
    maxrisklevel: RiskLevel | null;
    riskdistribution: Record<string, Record<string, { count: number }>>;
    access: Access & { write: boolean; manageparticipants: boolean };
};
export type ProjectSummary = Pick<Project, 'id' | 'name' | 'description' | 'responsible_user_id' | 'responsible_user_name' | 'start_date' | 'end_date' | 'archived_at' | 'maxrisklevel' | 'users'>;
export type ProjectRisk = {
    id: Id;
    name: string;
    project_id: Id;
    riskowner_id: Id | null;
    probability_id: Id | null;
    consequence_id: Id | null;
    scenariodescription: string | null;
    consequencedescription: string | null;
    note: string | null;
    assessmentcomment?: string | null;
    risk_controls: Option[];
    assessed_at: string | null;
    risk_level: RiskLevel | null;
    access: Access;
};
export type Risk = ProjectRisk;
export type Action = {
    id: Id;
    recurring_project_activity_id?: Id | null;
    name: string;
    description: string | null;
    project_id: Id;
    responsible_user_id: Id | null;
    startdate: string | null;
    due: string | null;
    estimated_cost: number | string | null;
    estimated_hours: number | null;
    completed_at: string | null;
    assessmentcomment?: string | null;
    risks: Option[];
    access: Access;
};
export type WorkPackage = {
    id: Id;
    project_id: Id;
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    completed_at: string | null;
    activities: (Option & { completed_at: string | null })[];
    access: Access;
};
export type RecurringActivity = Pick<Action, 'id' | 'project_id' | 'name' | 'description' | 'responsible_user_id' | 'estimated_cost' | 'estimated_hours' | 'access'> & {
    frequency: 'daily' | 'weekly' | 'monthly';
    weekday: number | null;
    months: number[];
    day_of_month: number | null;
    last_generated_on: string | null;
};
export type Configuration = {
    currentUserId: Id;
    canCreateProject: boolean;
    canCreateRisk?: boolean;
    canReadRisks?: boolean;
    canReadActions?: boolean;
    canCreateAction?: boolean;
    canReadWorkPackages?: boolean;
    canCreateWorkPackage?: boolean;
    users: Option[];
    projectTypes: Option[];
    riskOwners: Option[];
    controls: Option[];
    probabilities: Option[];
    consequences: Option[];
    riskMappings: { probability_level_id: Id; consequence_level_id: Id; color: string; name: string }[];
};
export type ProjectPermissions = Pick<Configuration, 'currentUserId' | 'canCreateProject'>;
export function canWriteProject(project: Pick<ProjectSummary, 'responsible_user_id' | 'archived_at' | 'users'>, userId: Id): boolean {
    return !project.archived_at && (
        project.responsible_user_id === userId
        || project.users.some(user => user.id === userId && !user.readonly)
    );
}
export type Run = (operation: () => Promise<unknown>) => Promise<boolean>;
export type AgendaItem = {
    id: string;
    title: string;
    document: import('@tiptap/core').JSONContent;
    activity_ids: Id[];
    source?: 'custom' | 'template' | 'followup';
    description?: string | null;
    previous_meeting_id?: Id;
};
export type Meeting = {
    id: Id;
    project_id: Id;
    name: string;
    date: string;
    location: string | null;
    agenda: AgendaItem[];
    revision: number;
    completed_at: string | null;
    project_meeting_agenda_template_id: Id | null;
    previous_meeting_id: Id | null;
    access: Pick<Access, 'read' | 'update' | 'delete'>;
};
export type MeetingAgendaPreview = {
    templates: Option[];
    sections: AgendaItem[];
    previous_meeting: Pick<Meeting, 'id' | 'name' | 'date'> | null;
    follow_up_section: AgendaItem | null;
};
export type MeetingMention = {
    id: Id;
    project_id: Id;
    project_name: string;
    meeting_name: string;
    project_meeting_id: Id;
    user_id: Id;
    agenda_item_id: string;
    excerpt: string;
    acknowledged_at: string | null;
};

export type ProjectMeetingLink = { projectId: Id; meetingId: Id; mentionId: Id };

export function projectMeetingLink(search: string): ProjectMeetingLink | null {
    const params = new URLSearchParams(search);
    const ids = ['project', 'meeting', 'mention'].map(key => Number(params.get(key)));
    if (!ids.every(id => Number.isSafeInteger(id) && id > 0)) return null;
    return { projectId: ids[0], meetingId: ids[1], mentionId: ids[2] };
}

export function mentionUrl(mention: MeetingMention): string {
    const params = new URLSearchParams({
        project: String(mention.project_id), meeting: String(mention.project_meeting_id), mention: String(mention.id),
    });
    return `/projects?${params}#meeting-section-${encodeURIComponent(mention.agenda_item_id)}`;
}

export function confirmAction(message: string, onConfirm: () => void, title: string): void {
    window.confirmDialog(t(title), t(message), () => { onConfirm(); }, { warning: true });
}

export function confirmCommentAction(message: string, comment: string | null | undefined, onConfirm: (comment: string) => void, title: string): void {
    window.confirmCommentDialog(t(title), t(message), comment ?? '', onConfirm, { warning: true });
}

const base = '/api/v1/crud';

export async function configuration(
    permissions: ProjectPermissions,
    signal?: AbortSignal,
): Promise<Configuration> {
    const [users, projectTypes, controls, probabilities, consequences, accessGroups, riskLevels] = await Promise.all([
        collection<UserOption>('User', { 'fields[users]': 'id,name,enabled,access_groups', sort: 'name' }, signal),
        collection<Option>('ProjectType', { 'fields[project_types]': 'id,name', sort: 'name' }, signal),
        collection<Option>('Control', { 'fields[controls]': 'id,name', sort: 'name' }, signal),
        collection<Option & { ordinal: number }>('ProbabilityLevel', { 'fields[probability_levels]': 'id,name,ordinal', sort: '-ordinal' }, signal),
        collection<Option & { ordinal: number }>('ConsequenceLevel', { 'fields[consequence_levels]': 'id,name,ordinal', sort: 'ordinal' }, signal),
        collection<AccessGroupOption>('AccessGroup', { 'fields[access_groups]': 'id,risk_level_id' }, signal),
        collection<RiskLevelOption>('RiskLevel', { 'fields[risk_levels]': 'id,name,color,ordinal,risk_mappings' }, signal),
    ]);
    const riskLevelAccessGroups = new Set(accessGroups.filter(group => group.risk_level_id !== null).map(group => group.id));

    return {
        ...permissions,
        users: users.map(({ id, name, enabled }) => ({ id, name, enabled: Boolean(enabled) })),
        projectTypes,
        riskOwners: users
            .filter(user => user.access_groups.some(group => riskLevelAccessGroups.has(group.id)))
            .map(({ id, name }) => ({ id, name })),
        controls,
        probabilities,
        consequences,
        riskMappings: riskLevels.flatMap(level => level.risk_mappings.map(mapping => ({
            ...mapping,
            color: level.color,
            name: level.name,
        }))),
    };
}

export async function collection<T>(model: string, params: Record<string, string | number> = {}, signal?: AbortSignal): Promise<T[]> {
    const { data } = await axios.get(`${base}/${model}`, { params, signal });
    if (Array.isArray(data)) return data;
    // Gate filtering preserves collection keys, so Laravel may serialize an object.
    if (data && typeof data === 'object' && Object.keys(data).every(key => /^\d+$/.test(key))) {
        return Object.values(data);
    }
    throw new Error(t('Unexpected API response'));
}

export async function item<T>(model: string, id: Id, signal?: AbortSignal): Promise<T> {
    const { data } = await axios.get<T>(`${base}/${model}/${id}`, { signal });
    return data;
}

export function projectList(signal?: AbortSignal): Promise<ProjectSummary[]> {
    return collection<ProjectSummary>('Project', {
        'fields[projects]': 'id,name,description,responsible_user_id,responsible_user_name,start_date,end_date,archived_at,maxrisklevel,users',
        sort: 'name',
    }, signal);
}

export const create = (model: string, data: object) => axios.post(`${base}/${model}`, data);
export const update = (model: string, id: Id, data: object) => axios.patch(`${base}/${model}/${id}`, data);
export const remove = (model: string, id: Id, params: Record<string, number> = {}) => axios.delete(`${base}/${model}/${id}`, { params });
export const action = (model: string, id: Id, name: string, data?: object) => axios.post(`${base}/${model}/${id}/${name}`, data);

export function errorMessage(error: unknown): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data;
        if (data?.errors && typeof data.errors === 'object') {
            return Object.values(data.errors).flat().join(' ');
        }
        if (typeof data?.message === 'string' && data.message) return data.message;
    }
    return error instanceof Error ? error.message : t('Could not fetch data');
}

export const dateValue = (value: string | null) => value?.slice(0, 10) ?? '';
export const todayDate = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};
export const nameOf = (options: Option[], id: Id | null) => options.find(option => option.id === id)?.name ?? '';
