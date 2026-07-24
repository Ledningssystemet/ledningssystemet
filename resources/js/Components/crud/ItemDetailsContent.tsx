import {Badge} from "@/Components/ui/badge";
import {Button} from "@/Components/ui/button";
import {MaterialSymbol} from "@/Components/ui/material-symbol";
import {useTranslations} from "@/hooks/useTranslations";
import {InlineTagsEditor} from "./InlineTagsEditor";
import {CollapsedMultiValueBadges} from "./CollapsedMultiValueBadges";
import {resolveOptions, useAllSelectOptions} from "./optionsCache";
import {FieldConfig, RowActionConfig, SelectOption} from "./types";

interface ItemDetailsContentProps {
    item: Record<string, any>;
    fields: FieldConfig[];
    onInlineFieldUpdate?: (item: Record<string, any>, fieldKey: string, value: any) => Promise<void>;
    className?: string;
    primaryKey?: string;
    canEdit?: boolean;
    onEdit?: (item: Record<string, any>) => void;
    canDelete?: boolean;
    onDelete?: (id: string | number) => void;
    rowActions?: RowActionConfig[];
    onRowAction?: (action: RowActionConfig, item: Record<string, any>) => Promise<void>;
    deletableKey?: string;
    actionsContainerClassName?: string;
}

export function ItemDetailsContent({
    item,
    fields,
    onInlineFieldUpdate,
    className = "bg-muted/30 rounded-md p-4 space-y-4",
    primaryKey = "id",
    canEdit = true,
    onEdit,
    canDelete = true,
    onDelete,
    rowActions = [],
    onRowAction,
    deletableKey,
    actionsContainerClassName = "pt-2 flex justify-end gap-2",
}: ItemDetailsContentProps) {
    const {t} = useTranslations();
    const optionsMap = useAllSelectOptions(fields);
    const categories = groupByCategory(fields);
    const itemId = item[primaryKey];
    const canDeleteItem = Boolean(
        canDelete && itemId !== undefined && itemId !== null && (deletableKey ? item[deletableKey] !== false : true),
    );
    const visibleRowActions = rowActions.filter((action) => (action.isVisible ? action.isVisible(item) : true));
    const showActions = (canEdit && Boolean(onEdit)) || (canDeleteItem && Boolean(onDelete)) || visibleRowActions.length > 0;

    return (
        <div className={className}>
            {categories.map(({category, fields: categoryFields}) => (
                <div key={category}>
                    {category !== "__uncategorized__" && (
                        <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2 border-b pb-1">
                            {category}
                        </h4>
                    )}
                    <div className="grid gap-3">
                        {categoryFields.map((field) => {
                            const value = item[field.key];
                            return (
                                <div key={field.key} className="grid grid-cols-3 gap-2 text-sm">
                                    <span className="text-muted-foreground font-medium">
                                        {field.label}
                                    </span>
                                    <span className="col-span-2">
                                        {field.type === "inline-tags" && field.editable !== false && onInlineFieldUpdate
                                            ? (
                                                <InlineTagsEditor
                                                    item={item}
                                                    field={field}
                                                    value={value}
                                                    onSave={onInlineFieldUpdate}
                                                />
                                            )
                                            : field.renderDetail
                                                ? field.renderDetail(value, item)
                                                : field.renderCell
                                                    ? field.renderCell(value, item)
                                                    : renderDetailValue(value, field, optionsMap, t)}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            ))}
            {showActions && (
                <div className={actionsContainerClassName}>
                    {canEdit && onEdit && (
                        <Button variant="outline" size="sm" onClick={() => onEdit(item)}>
                            <MaterialSymbol name="edit" className="h-4 w-4 mr-1"/>
                            {t("ui.crud.action_edit")}
                        </Button>
                    )}
                    {canDeleteItem && onDelete && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive hover:text-destructive"
                            onClick={() => onDelete(itemId as string | number)}
                        >
                            <MaterialSymbol name="delete" className="h-4 w-4 mr-1"/>
                            {t("ui.crud.action_delete")}
                        </Button>
                    )}
                    {visibleRowActions.map((action) => (
                        <Button
                            key={action.key}
                            variant={action.variant || "outline"}
                            size="sm"
                            onClick={() => void onRowAction?.(action, item)}
                        >
                            {action.icon ? <span className="mr-1 inline-flex">{action.icon}</span> : null}
                            {action.label}
                        </Button>
                    ))}
                </div>
            )}
        </div>
    );
}

function groupByCategory(fields: FieldConfig[]) {
    const map = new Map<string, FieldConfig[]>();
    for (const field of fields) {
        if (field.hiddenInDetails) continue;
        const category = field.category || "__uncategorized__";
        if (!map.has(category)) map.set(category, []);
        map.get(category)!.push(field);
    }

    return Array.from(map.entries()).map(([category, categoryFields]) => ({
        category,
        fields: categoryFields,
    }));
}

function renderDetailValue(
    value: any,
    field: FieldConfig,
    optionsMap: Map<string, SelectOption[]>,
    t: (key: string, replacements?: Record<string, string | number>) => string,
) {
    if (value == null) return "-";

    if (field.type === "color" && typeof value === "string") {
        const normalized = normalizeColor(value);
        if (normalized === null) {
            return String(value);
        }

        return (
            <span className="inline-flex items-center gap-2">
                <span className="h-4 w-4 rounded-sm border border-border" style={{backgroundColor: normalized}}/>
                <span>{normalized}</span>
            </span>
        );
    }

    if (field.type === "boolean") {
        return value ? t("ui.crud.yes") : t("ui.crud.no");
    }

    if ((field.type === "multiselect" || field.type === "tags" || field.type === "inline-tags") && Array.isArray(value)) {
        const options = resolveOptions(field, optionsMap);
        return (
            <CollapsedMultiValueBadges
                values={value}
                options={options}
                moreLabel={(remaining) => t("ui.crud.multi_value_more", {count: remaining})}
            />
        );
    }

    if (Array.isArray(value)) {
        const options = resolveOptions(field, optionsMap);
        return (
            <div className="flex flex-wrap gap-1">
                {value.map((itemValue: any) => {
                    const option = options.find((opt) => String(opt.value) === String(itemValue));
                    return (
                        <Badge key={itemValue} variant="secondary" className="text-xs">
                            {option?.label || itemValue}
                        </Badge>
                    );
                })}
            </div>
        );
    }

    if (field.type === "select") {
        const options = resolveOptions(field, optionsMap);
        return options.find((option) => valuesAreEquivalent(option.value, value))?.label ?? String(value);
    }

    if (field.type === "textarea") {
        return <span className="whitespace-pre-line">{String(value)}</span>;
    }

    if (field.type === "date" && typeof value === "string") {
        return new Date(value).toLocaleDateString();
    }

    if (field.type === "datetime" && typeof value === "string") {
        return new Date(value).toLocaleString();
    }

    return String(value);
}

function normalizeColor(value: string): string | null {
    const trimmed = value.trim();
    const withoutHash = trimmed.startsWith("#") ? trimmed.slice(1) : trimmed;
    if (!/^[0-9a-fA-F]{6}$/.test(withoutHash)) {
        return null;
    }

    return `#${withoutHash.toLowerCase()}`;
}

function normalizeComparableValue(value: unknown): string {
    if (value === true || value === 1 || value === "1" || value === "true") {
        return "1";
    }

    if (value === false || value === 0 || value === "0" || value === "false") {
        return "0";
    }

    return String(value);
}

function valuesAreEquivalent(left: unknown, right: unknown): boolean {
    return normalizeComparableValue(left) === normalizeComparableValue(right);
}
