import {ReactNode} from "react";

export interface SelectOption {
    value: string | number;
    label: string;
    imageUrl?: string;
}

export type FieldType =
    | "text"
    | "color"
    | "number"
    | "select"
    | "multiselect"
    | "tags"
    | "inline-tags"
    | "date"
    | "boolean"
    | "textarea"
    | "email"
    | "url"
    | "file"
    | "datetime"
    | "pictogram-multiselect";

export interface FieldConfig {
    key: string;
    label: string;
    type: FieldType;
    category?: string;
    helpText?: string;
    filterable?: boolean;
    sortable?: boolean;
    editable?: boolean;
    editableOnCreate?: boolean;
    editableOnUpdate?: boolean;
    required?: boolean;
    placeholder?: string;
    accept?: string;
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
    hiddenInDetails?: boolean;
    width?: string;
    masterLabel?: boolean;
    masterDescription?: boolean;
}

export type ItemStatus = "info" | "warning" | "danger";

export interface ItemBadgeConfig {
    label: string;
    variant?: "default" | "secondary" | "destructive" | "outline";
}

export interface RowActionConfig {
    key: string;
    label: string;
    icon?: ReactNode;
    variant?: "default" | "secondary" | "destructive" | "outline" | "ghost";
    isVisible?: (item: Record<string, any>) => boolean;
    onClick: (item: Record<string, any>) => void | Promise<void>;
    /** Whether to call refetch after onClick completes. Defaults to true. */
    refreshOnComplete?: boolean;
}

/**
 * A row action that opens a Dialog containing a nested CrudModule.
 * The CrudModule state is managed automatically – no manual useState needed in the page.
 */
export interface SubTableActionConfig {
    key: string;
    label: string;
    icon?: ReactNode;
    variant?: "default" | "secondary" | "destructive" | "outline" | "ghost";
    isVisible?: (item: Record<string, any>) => boolean;
    /** Dialog max-width Tailwind class, e.g. "max-w-3xl". Defaults to "max-w-4xl". */
    dialogMaxWidth?: string;
    dialogTitle: string | ((item: Record<string, any>) => string);
    dialogDescription?: string | ((item: Record<string, any>) => string);
    /**
     * Returns the CrudModuleConfig for the child table, given the parent row item.
     * Called only when the dialog is open.
     */
    getConfig: (item: Record<string, any>) => CrudModuleConfig;
    /** Forwarded to the inner CrudModule's onEditFormDataChange prop. */
    onEditFormDataChange?: (data: Record<string, any>) => void;
}

/**
 * A field that is only visible in the filter bar – never in the table or edit dialog.
 * Equivalent to defining a FieldConfig with hidden:true, editable:false, filterable:true.
 */
export interface FilterFieldConfig {
    key: string;
    label: string;
    type: "boolean" | "select";
    options?: SelectOption[];
    optionsUrl?: string;
    optionValueKey?: string;
    optionLabelKey?: string;
    placeholder?: string;
}

export interface CrudModuleConfig {
    apiUrl: string;
    title?: string;
    fields: FieldConfig[];
    /** Locked sort direction for tables that include an ordinal field. Defaults to asc. */
    ordinalSortDirection?: "asc" | "desc";
    /** Static API filters always sent as filter[field]=value. Useful for child tables. */
    fixedFilters?: Record<string, any>;
    /** Additional top-level query params derived from current filter state. */
    customQueryParams?: (filters: Record<string, any>) => Record<string, any>;
    /** Disable automatic filter[field]=value serialization and rely on customQueryParams/fixedFilters instead. */
    serializeFilters?: boolean;
    /** Values merged into payload when creating a new record. */
    createDefaults?: Record<string, any>;
    /** Explicit list of API fields to request via $select for index fetches. */
    selectFields?: string[];
    primaryKey?: string;
    searchable?: boolean;
    includes?: string[];
    defaultSort?: string;
    perPage?: number;
    createTitle?: string;
    editTitle?: string;
    /** Hide the create button and prevent opening a create dialog when false. */
    canCreate?: boolean;
    /** Hide edit actions across all items when false. */
    canEdit?: boolean;
    /** Hide delete actions across all items when false. */
    canDelete?: boolean;
    /** Hide table row selection and mass actions when false. */
    selectable?: boolean;
    /** Function that returns a status for each item, shown as a colored indicator */
    getItemStatus?: (item: Record<string, any>) => ItemStatus | null;
    /** Optional badge shown next to item name/label */
    getItemBadge?: (item: Record<string, any>) => ItemBadgeConfig | null;
    /** Key on each item indicating if it can be edited (default: always true) */
    editableKey?: string;
    /** Key on each item indicating if it can be deleted (default: always true) */
    deletableKey?: string;
    /** Callback invoked after a successful create/update save. */
    onSaveSuccess?: (item: Record<string, any>, context: {
        isNew: boolean;
        payload: Record<string, any>
    }) => void | Promise<void>;
    /** Optional row-level custom actions shown next to edit/delete actions. */
    rowActions?: RowActionConfig[];
    /**
     * Row actions that open a Dialog containing a nested CrudModule.
     * The dialog state is managed by CrudModule – no manual useState needed in the page.
     */
    subTableActions?: SubTableActionConfig[];
    /**
     * Shorthand for filter-only fields. These are merged into `fields` as
     * hidden:true, editable:false, filterable:true entries.
     */
    filterFields?: FilterFieldConfig[];
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

export interface OrdinalReorderResult {
    id: string | number;
    ordinal: number;
}

export interface EditDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: Record<string, any> | null;
    fields: FieldConfig[];
    title?: string;
    onSave: (data: Record<string, any>) => Promise<void>;
    onFormDataChange?: (data: Record<string, any>) => void;
}
