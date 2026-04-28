import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import Cookies from 'js-cookie';
import {
  DashboardDataState,
  DashboardGoalItem,
  DashboardGoalStatus,
  DashboardProcessOption,
  DashboardProcessStep,
  DashboardRiskItem,
  DashboardRiskMatrixMeta,
  DashboardTaskItem,
} from '@/types/dashboard';

interface CrudResponse<T> {
  data?: T[];
}

interface ActivityRecord {
  id: number;
  name: string;
  due: string | null;
  completed_at: string | null;
}

interface ControlActionRecord {
  id: number;
  name: string;
  due: string | null;
  finished_at: string | null;
}

interface ObjectiveRecord {
  id: number;
  name: string;
  due: string | null;
  archived_at: string | null;
}

interface RiskRecord {
  id: number;
  name: string;
  probability_id: number | null;
  consequence_id: number | null;
  post_probability_id: number | null;
  post_consequence_id: number | null;
}

interface ProcessRecord {
  id: number;
  name: string;
  department_id: number | null;
  isstartprocess: boolean;
  publishedbpmn: string | null;
}

interface DepartmentRecord {
  id: number;
  name: string;
}

interface ProcessActivityRecord {
  id: number;
  name: string;
  ordinal: number | null;
  process_id: number;
}

interface ProbabilityLevelRecord {
  id: number;
  name: string;
  ordinal: number;
}

interface ConsequenceLevelRecord {
  id: number;
  name: string;
  ordinal: number;
}

interface RiskLevelRecord {
  id: number;
  name: string;
  ordinal: number;
  color: string;
}

interface RiskLevelMappingRecord {
  probability_level_id: number;
  consequence_level_id: number;
  risk_level_id: number;
}

const PREFERRED_PROCESS_COOKIE = 'dashboard_preferred_process_id';

function readPreferredProcessIdFromCookie(): number | null {
  const rawValue = Cookies.get(PREFERRED_PROCESS_COOKIE);
  if (!rawValue) {
    return null;
  }

  const parsedValue = Number(rawValue);
  return Number.isFinite(parsedValue) ? parsedValue : null;
}

const EMPTY_STATE: DashboardDataState = {
  stats: {
    openActivities: 0,
    overdueControls: 0,
    completedGoals: 0,
    totalGoals: 0,
    averageRiskScore: 0,
  },
  tasks: [],
  goals: [],
  topRisks: [],
  riskMatrix: [],
  riskMatrixMeta: {
    probabilities: [],
    consequences: [],
    cellMeta: [],
  },
  processOptions: [],
  processSteps: [],
  selectedProcessId: null,
  selectedProcessName: null,
  selectedProcessBpmn: null,
};

function normalizeBpmnXml(value: string | null | undefined): string | null {
  const trimmed = value?.trim();

  return trimmed ? trimmed : null;
}

function buildProcessState(
  processes: ProcessRecord[],
  processActivities: ProcessActivityRecord[],
  departments: DepartmentRecord[],
  requestedProcessId: number | null,
) {
  const departmentsById = new Map<number, string>(departments.map((department) => [department.id, department.name]));

  const processOptions: DashboardProcessOption[] = processes.map((process) => ({
    id: process.id,
    name: process.name,
    isStartProcess: process.isstartprocess,
    departmentId: process.department_id,
    departmentName: process.department_id ? departmentsById.get(process.department_id) ?? null : null,
  }));

  const startProcessId = processes.find((process) => process.isstartprocess)?.id ?? null;
  const hasRequestedProcess = requestedProcessId !== null && processes.some((process) => process.id === requestedProcessId);
  const effectiveProcessId = hasRequestedProcess
    ? requestedProcessId
    : startProcessId ?? processOptions[0]?.id ?? null;

  const selectedProcess = processes.find((process) => process.id === effectiveProcessId) ?? null;

  const processSteps: DashboardProcessStep[] = processActivities
    .filter((step) => step.process_id === effectiveProcessId)
    .sort((a, b) => (a.ordinal ?? Number.MAX_SAFE_INTEGER) - (b.ordinal ?? Number.MAX_SAFE_INTEGER))
    .map((step) => ({
      id: step.id,
      name: step.name,
      ordinal: step.ordinal ?? 0,
    }));

  return {
    processOptions,
    processSteps,
    selectedProcessId: effectiveProcessId,
    selectedProcessName: selectedProcess?.name ?? null,
    selectedProcessBpmn: normalizeBpmnXml(selectedProcess?.publishedbpmn),
  };
}

function toArray<T>(payload: T[] | CrudResponse<T>): T[] {
  if (Array.isArray(payload)) {
    return payload;
  }

  return Array.isArray(payload.data) ? payload.data : [];
}

function parseDate(value: string | null): Date | null {
  if (!value) {
    return null;
  }

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function toTaskStatus(dateValue: string | null, doneAt: string | null): DashboardTaskItem['status'] {
  if (doneAt) {
    return 'done';
  }

  const date = parseDate(dateValue);
  if (!date) {
    return 'upcoming';
  }

  return date.getTime() < Date.now() ? 'overdue' : 'upcoming';
}

function toGoalStatus(goal: ObjectiveRecord): DashboardGoalStatus {
  if (goal.archived_at) {
    return 'achieved';
  }

  const dueDate = parseDate(goal.due);
  if (dueDate && dueDate.getTime() < Date.now()) {
    return 'unacceptable';
  }

  return 'acceptable';
}

function calculateRiskScore(risk: RiskRecord): number {
  const probability = risk.post_probability_id ?? risk.probability_id ?? 0;
  const consequence = risk.post_consequence_id ?? risk.consequence_id ?? 0;
  return probability * consequence;
}

function normalizeHexColor(color: string | null | undefined): string | null {
  if (!color) {
    return null;
  }

  const trimmed = color.trim();
  if (/^#[0-9a-fA-F]{6}$/.test(trimmed)) {
    return trimmed;
  }

  if (/^[0-9a-fA-F]{6}$/.test(trimmed)) {
    return `#${trimmed}`;
  }

  return null;
}

function buildRiskMatrixMeta(
  probabilityLevels: ProbabilityLevelRecord[],
  consequenceLevels: ConsequenceLevelRecord[],
  riskLevels: RiskLevelRecord[],
  mappings: RiskLevelMappingRecord[],
): DashboardRiskMatrixMeta {
  const sortedProbabilities = [...probabilityLevels].sort((a, b) => b.ordinal - a.ordinal);
  const sortedConsequences = [...consequenceLevels].sort((a, b) => a.ordinal - b.ordinal);

  const riskLevelsById = new Map<number, RiskLevelRecord>(riskLevels.map((level) => [level.id, level]));
  const mappingByCell = new Map<string, RiskLevelRecord>();

  mappings.forEach((mapping) => {
    const riskLevel = riskLevelsById.get(mapping.risk_level_id);
    if (!riskLevel) {
      return;
    }

    mappingByCell.set(`${mapping.probability_level_id}:${mapping.consequence_level_id}`, riskLevel);
  });

  const cellMeta = sortedProbabilities.map((probability) =>
    sortedConsequences.map((consequence) => {
      const riskLevel = mappingByCell.get(`${probability.id}:${consequence.id}`);
      return {
        className: 'risk-cell-green',
        backgroundColor: normalizeHexColor(riskLevel?.color),
        riskLevelName: riskLevel?.name ?? null,
      };
    }),
  );

  return {
    probabilities: sortedProbabilities,
    consequences: sortedConsequences,
    cellMeta,
  };
}

export function useDashboardData() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<DashboardDataState>(EMPTY_STATE);
  const selectedProcessIdRef = useRef<number | null>(null);
  const processDataRef = useRef<{
    processes: ProcessRecord[];
    processActivities: ProcessActivityRecord[];
    departments: DepartmentRecord[];
  }>({
    processes: [],
    processActivities: [],
    departments: [],
  });

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const [
        activitiesResponse,
        controlActionsResponse,
        objectivesResponse,
        risksResponse,
        processesResponse,
        departmentsResponse,
        processActivitiesResponse,
        probabilityLevelsResponse,
        consequenceLevelsResponse,
        riskLevelsResponse,
        riskLevelMappingsResponse,
      ] = await Promise.all([
        axios.get<ActivityRecord[] | CrudResponse<ActivityRecord>>('/api/crud/activities', {
          params: { '$select': 'id,name,due', sort: 'due', 'filter': {'completed_at': 'is_null'} },
        }),
        axios.get<ControlActionRecord[] | CrudResponse<ControlActionRecord>>('/api/crud/control-actions', {
          params: { '$select': 'id,name,due', sort: 'due', 'filter': {'finished_at': 'is_null'} },
        }),
        axios.get<ObjectiveRecord[] | CrudResponse<ObjectiveRecord>>('/api/crud/objectives', {
          params: { '$select': 'id,name,due,archived_at', sort: 'due' },
        }),
        axios.get<RiskRecord[] | CrudResponse<RiskRecord>>('/api/crud/risks', {
          params: {
            '$select': 'id,name,probability_id,consequence_id,post_probability_id,post_consequence_id',
          },
        }),
        axios.get<ProcessRecord[] | CrudResponse<ProcessRecord>>('/api/crud/processes', {
          params: { '$select': 'id,name,department_id,isstartprocess,publishedbpmn', sort: 'name' },
        }),
        axios.get<DepartmentRecord[] | CrudResponse<DepartmentRecord>>('/api/crud/departments', {
          params: { '$select': 'id,name', sort: 'name' },
        }).catch(() => ({ data: [] as DepartmentRecord[] })),
        axios.get<ProcessActivityRecord[] | CrudResponse<ProcessActivityRecord>>('/api/crud/process-activities', {
          params: { '$select': 'id,name,ordinal,process_id', sort: 'ordinal' },
        }),
        axios.get<ProbabilityLevelRecord[] | CrudResponse<ProbabilityLevelRecord>>('/api/crud/probability-levels', {
          params: { '$select': 'id,name,ordinal', sort: '-ordinal' },
        }),
        axios.get<ConsequenceLevelRecord[] | CrudResponse<ConsequenceLevelRecord>>('/api/crud/consequence-levels', {
          params: { '$select': 'id,name,ordinal', sort: 'ordinal' },
        }),
        axios.get<RiskLevelRecord[] | CrudResponse<RiskLevelRecord>>('/api/crud/risk-levels', {
          params: { '$select': 'id,name,ordinal,color', sort: 'ordinal' },
        }),
        axios.get<RiskLevelMappingRecord[] | CrudResponse<RiskLevelMappingRecord>>('/api/crud/risk-level-mappings', {
          params: { '$select': 'probability_level_id,consequence_level_id,risk_level_id' },
        }),
      ]);

      const activities = toArray(activitiesResponse.data);
      const controlActions = toArray(controlActionsResponse.data);
      const objectives = toArray(objectivesResponse.data);
      const risks = toArray(risksResponse.data);
      const processes = toArray(processesResponse.data);
      const departments = toArray(departmentsResponse.data);
      const processActivities = toArray(processActivitiesResponse.data);
      processDataRef.current = { processes, processActivities, departments };
      const probabilityLevels = toArray(probabilityLevelsResponse.data);
      const consequenceLevels = toArray(consequenceLevelsResponse.data);
      const riskLevels = toArray(riskLevelsResponse.data);
      const riskLevelMappings = toArray(riskLevelMappingsResponse.data);

      const riskMatrixMeta = buildRiskMatrixMeta(probabilityLevels, consequenceLevels, riskLevels, riskLevelMappings);
      const probabilityIndexById = new Map<number, number>(
        riskMatrixMeta.probabilities.map((level, index) => [level.id, index]),
      );
      const consequenceIndexById = new Map<number, number>(
        riskMatrixMeta.consequences.map((level, index) => [level.id, index]),
      );
      const riskLevelByCell = new Map<string, RiskLevelRecord>();
      const riskLevelsById = new Map<number, RiskLevelRecord>(riskLevels.map((level) => [level.id, level]));
      riskLevelMappings.forEach((mapping) => {
        const riskLevel = riskLevelsById.get(mapping.risk_level_id);
        if (!riskLevel) {
          return;
        }

        riskLevelByCell.set(`${mapping.probability_level_id}:${mapping.consequence_level_id}`, riskLevel);
      });

      const tasks: DashboardTaskItem[] = [
        ...activities.map((item) => ({
          id: `activity-${item.id}`,
          title: item.name,
          date: item.due,
          status: toTaskStatus(item.due, item.completed_at),
          type: 'activity' as const,
        })),
        ...controlActions.map((item) => ({
          id: `control-${item.id}`,
          title: item.name,
          date: item.due,
          status: toTaskStatus(item.due, item.finished_at),
          type: 'control' as const,
        })),
      ]
        .sort((a, b) => {
          const aDate = parseDate(a.date)?.getTime() ?? Number.MAX_SAFE_INTEGER;
          const bDate = parseDate(b.date)?.getTime() ?? Number.MAX_SAFE_INTEGER;
          return aDate - bDate;
        })
        .slice(0, 10);

      const goals: DashboardGoalItem[] = objectives.slice(0, 10).map((goal) => ({
        id: String(goal.id),
        title: goal.name,
        status: toGoalStatus(goal),
      }));

      const completedGoals = goals.filter((goal) => goal.status === 'achieved').length;

      const risksWithScore: DashboardRiskItem[] = risks
        .map((risk) => {
          const probabilityId = risk.post_probability_id ?? risk.probability_id;
          const consequenceId = risk.post_consequence_id ?? risk.consequence_id;
          const mappedRiskLevel = probabilityId && consequenceId
            ? riskLevelByCell.get(`${probabilityId}:${consequenceId}`)
            : null;

          return {
            id: String(risk.id),
            title: risk.name,
            score: mappedRiskLevel?.ordinal ?? calculateRiskScore(risk),
          };
        })
        .sort((a, b) => b.score - a.score);

      const riskMatrix = riskMatrixMeta.probabilities.map(() => riskMatrixMeta.consequences.map(() => 0));
      risks.forEach((risk) => {
        const probability = risk.post_probability_id ?? risk.probability_id;
        const consequence = risk.post_consequence_id ?? risk.consequence_id;

        if (!probability || !consequence) {
          return;
        }

        const row = probabilityIndexById.get(probability);
        const col = consequenceIndexById.get(consequence);
        if (row !== undefined && col !== undefined) {
          riskMatrix[row][col] += 1;
        }
      });

      const averageRiskScore =
        risksWithScore.length > 0
          ? Number((risksWithScore.reduce((sum, risk) => sum + risk.score, 0) / risksWithScore.length).toFixed(1))
          : 0;

      const requestedProcessId = selectedProcessIdRef.current ?? readPreferredProcessIdFromCookie();
      const processState = buildProcessState(processes, processActivities, departments, requestedProcessId);
      selectedProcessIdRef.current = processState.selectedProcessId;
      setData({
        stats: {
          openActivities: activities.filter((activity) => !activity.completed_at).length,
          overdueControls: controlActions.filter((action) => toTaskStatus(action.due, action.finished_at) === 'overdue').length,
          completedGoals,
          totalGoals: goals.length,
          averageRiskScore,
        },
        tasks,
        goals,
        topRisks: risksWithScore.slice(0, 10),
        riskMatrix,
        riskMatrixMeta,
        ...processState,
      });
    } catch {
      setError('dashboard_data_fetch_failed');
      processDataRef.current = { processes: [], processActivities: [], departments: [] };
      selectedProcessIdRef.current = null;
      setData(EMPTY_STATE);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleSetSelectedProcessId = useCallback((processId: number | null) => {
    selectedProcessIdRef.current = processId;

    setData((current) => ({
      ...current,
      ...buildProcessState(
        processDataRef.current.processes,
        processDataRef.current.processActivities,
        processDataRef.current.departments,
        processId,
      ),
    }));
  }, []);

  const actions = useMemo(
    () => ({
      setSelectedProcessId: handleSetSelectedProcessId,
      refresh: fetchData,
    }),
    [fetchData, handleSetSelectedProcessId],
  );

  return {
    loading,
    error,
    data,
    ...actions,
  };
}

