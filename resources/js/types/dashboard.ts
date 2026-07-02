export type DashboardTaskStatus = 'overdue' | 'upcoming' | 'done';
export type DashboardTaskType = 'activity' | 'control';
export type DashboardGoalStatus = 'achieved' | 'acceptable' | 'unacceptable';

export interface DashboardStatItem {
  openActivities: number;
  overdueControls: number;
  completedGoals: number;
  totalGoals: number;
  averageRiskScore: number;
}

export interface DashboardTaskItem {
  id: string;
  title: string;
  date: string | null;
  status: DashboardTaskStatus;
  type: DashboardTaskType;
}

export interface DashboardGoalItem {
  id: string;
  title: string;
  status: DashboardGoalStatus;
}

export interface DashboardRiskItem {
  id: string;
  title: string;
  score: number;
}

export interface DashboardRiskMatrixProbabilityLevel {
  id: number;
  name: string;
  ordinal: number;
}

export interface DashboardRiskMatrixConsequenceLevel {
  id: number;
  name: string;
  ordinal: number;
}

export interface DashboardRiskMatrixCellMeta {
  className: string;
  backgroundColor: string | null;
  riskLevelName: string | null;
}

export interface DashboardRiskMatrixMeta {
  probabilities: DashboardRiskMatrixProbabilityLevel[];
  consequences: DashboardRiskMatrixConsequenceLevel[];
  cellMeta: DashboardRiskMatrixCellMeta[][];
}

export interface DashboardProcessOption {
  id: number;
  name: string;
  isStartProcess: boolean;
  departmentId: number | null;
  departmentName: string | null;
}

export interface DashboardProcessStep {
  id: number;
  name: string;
  ordinal: number;
}

export interface DashboardDataState {
  stats: DashboardStatItem;
  tasks: DashboardTaskItem[];
  goals: DashboardGoalItem[];
  topRisks: DashboardRiskItem[];
  riskMatrix: number[][];
  riskMatrixMeta: DashboardRiskMatrixMeta;
  processOptions: DashboardProcessOption[];
  processSteps: DashboardProcessStep[];
  selectedProcessId: number | null;
  selectedProcessName: string | null;
  selectedProcessBpmn: string | null;
}

export interface DashboardWidgetProps {
  data: DashboardDataState;
  loading: boolean;
  error: string | null;
  setSelectedProcessId: (processId: number | null) => void;
}

