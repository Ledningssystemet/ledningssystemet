/**
 * CRUD Table Module Types
 */

export type ViewMode = 'table' | 'accordion' | 'master-detail';
export type ColumnType = 'string' | 'number' | 'boolean' | 'date' | 'datetime' | 'custom';

/**
 * Single CRUD Record
 */
export interface CrudRecord {
  [key: string]: any;
  id: string | number;
}

/**
 * Column Definition
 */
export interface ColumnDef {
  key: string;
  label: string;
  type?: ColumnType;
  sortable?: boolean;
  filterable?: boolean;
  editable?: boolean;
  hidden?: boolean;
  width?: string;
  format?: (value: any) => string | React.ReactNode;
  render?: (record: CrudRecord) => React.ReactNode;
  headerRender?: () => React.ReactNode;
}

/**
 * CRUD Table Configuration
 */
export interface CrudTableConfig {
  resource: string;
  columns: ColumnDef[];
  title?: string;
  description?: string;
  creatable?: boolean;
  editable?: boolean;
  deletable?: boolean;
  paginate?: boolean;
  perPage?: number;
  actions?: {
    edit?: boolean;
    delete?: boolean;
    custom?: Array<{
      label: string;
      onClick: (record: CrudRecord) => void;
      icon?: React.ReactNode;
    }>;
  };
}

/**
 * Filter Configuration
 */
export interface CrudFilter {
  field: string;
  operator?: 'equals' | 'gt' | 'gte' | 'lt' | 'lte';
  value: any;
}

/**
 * Sort Configuration
 */
export interface CrudSort {
  field: string;
  direction: 'asc' | 'desc';
}

/**
 * API Response for Index
 */
export interface CrudIndexResponse {
  data: CrudRecord[];
  meta?: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

/**
 * CRUD Table State
 */
export interface CrudTableState {
  data: CrudRecord[];
  loading: boolean;
  error: string | null;
  selectedRows: Set<string | number>;
  filters: CrudFilter[];
  search: string;
  sort: CrudSort | null;
  pagination: {
    page: number;
    perPage: number;
    total: number;
  };
}

/**
 * Modal State
 */
export interface ModalState {
  type: 'create' | 'edit' | 'delete' | null;
  record?: CrudRecord;
  isOpen: boolean;
}

/**
 * Bulk Edit Payload
 */
export interface BulkEditPayload {
  ids: (string | number)[];
  data: Record<string, any>;
}

/**
 * API Error Response
 */
export interface ApiErrorResponse {
  message: string;
  errors?: Record<string, string[]>;
}

