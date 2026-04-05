import { ReactNode } from "react";

export interface SelectOption {
  value: string | number;
  label: string;
}

export type FieldType =
  | "text"
  | "number"
  | "select"
  | "multiselect"
  | "tags"
  | "inline-tags"
  | "date"
  | "boolean"
  | "textarea"
  | "email"
  | "url";

export interface FieldConfig {
  key: string;
  label: string;
  type: FieldType;
  category?: string;
  filterable?: boolean;
  sortable?: boolean;
  editable?: boolean;
  required?: boolean;
  placeholder?: string;
  options?: SelectOption[];
  optionsUrl?: string;
  createOptionUrl?: string;
  optionValueKey?: string;
  optionLabelKey?: string;
  createOptionPayloadKey?: string;
  tags?: boolean;
  renderCell?: (value: any, row: Record<string, any>) => ReactNode;
  renderDetail?: (value: any, row: Record<string, any>) => ReactNode;
  hidden?: boolean;
  hiddenInTable?: boolean;
  width?: string;
  masterLabel?: boolean;
  masterDescription?: boolean;
}

export type ItemStatus = "info" | "warning" | "danger";

export interface ItemBadgeConfig {
  label: string;
  variant?: "default" | "secondary" | "destructive" | "outline";
}

export interface CrudModuleConfig {
  apiUrl: string;
  title?: string;
  fields: FieldConfig[];
  /** Explicit list of API fields to request via $select for index fetches. */
  selectFields?: string[];
  primaryKey?: string;
  searchable?: boolean;
  includes?: string[];
  defaultSort?: string;
  perPage?: number;
  createTitle?: string;
  editTitle?: string;
  /** Function that returns a status for each item, shown as a colored indicator */
  getItemStatus?: (item: Record<string, any>) => ItemStatus | null;
  /** Optional badge shown next to item name/label */
  getItemBadge?: (item: Record<string, any>) => ItemBadgeConfig | null;
  /** Key on each item indicating if it can be edited (default: always true) */
  editableKey?: string;
  /** Key on each item indicating if it can be deleted (default: always true) */
  deletableKey?: string;
}

export type ViewMode = "master-detail" | "table" | "accordion";

export interface CrudState {
  items: Record<string, any>[];
  selectedItems: Set<string | number>;
  activeItem: Record<string, any> | null;
  search: string;
  filters: Record<string, any>;
  sort: string;
  sortDirection: "asc" | "desc";
  page: number;
  perPage: number;
  totalPages: number;
  total: number;
  loading: boolean;
  viewMode: ViewMode;
}

export interface EditDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: Record<string, any> | null;
  fields: FieldConfig[];
  title?: string;
  onSave: (data: Record<string, any>) => Promise<void>;
}
