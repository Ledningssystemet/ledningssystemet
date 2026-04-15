import type { CrudModuleConfig } from '@/components/crud';

type TranslateFn = (key: string, replacements?: Record<string, string | number>) => string;

interface BuildControlActionsCrudConfigOptions {
    controlId?: number;
    lockControlId?: boolean;
}

export function buildControlActionsCrudConfig(
    t: TranslateFn,
    options: BuildControlActionsCrudConfigOptions = {}
): CrudModuleConfig {
    const lockControlId = options.lockControlId === true && typeof options.controlId === 'number';

    return {
        apiUrl: '/api/crud/control-actions',
        perPage: 25,
        defaultSort: 'due',
        fixedFilters: lockControlId
            ? {
                  control_id: options.controlId,
              }
            : undefined,
        createDefaults: lockControlId
            ? {
                  control_id: options.controlId,
              }
            : undefined,
        selectFields: [
            'id',
            'control_id',
            'name',
            'description',
            'due',
            'finished_at',
            'responsible_id',
            'estimated_cost',
        ],
        createTitle: t('pages.controls.actions.create_title'),
        editTitle: t('pages.controls.actions.edit_title'),
        fields: [
            {
                key: 'name',
                label: t('pages.controls.actions.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.controls.actions.category_general'),
            },
            {
                key: 'description',
                label: t('pages.controls.actions.column_description'),
                type: 'textarea',
                editable: true,
                required: true,
                masterDescription: true,
                category: t('pages.controls.actions.category_general'),
            },
            {
                key: 'control_id',
                label: t('pages.controls.actions.column_control_id'),
                type: 'select',
                sortable: true,
                editable: !lockControlId,
                required: !lockControlId,
                hidden: lockControlId,
                filterable: !lockControlId,
                optionsUrl: '/api/crud/controls?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.control_actions.control_placeholder'),
                category: t('pages.controls.actions.category_general'),
            },
            {
                key: 'responsible_id',
                label: t('pages.controls.actions.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.controls.none_assigned'),
                category: t('pages.controls.actions.category_general'),
            },
            {
                key: 'due',
                label: t('pages.controls.actions.column_due'),
                type: 'date',
                sortable: true,
                editable: true,
                category: t('pages.controls.actions.category_schedule'),
            },
            {
                key: 'finished_at',
                label: t('pages.controls.actions.column_finished_at'),
                type: 'date',
                sortable: true,
                editable: true,
                category: t('pages.controls.actions.category_schedule'),
            },
            {
                key: 'estimated_cost',
                label: t('pages.controls.actions.column_estimated_cost'),
                type: 'number',
                sortable: true,
                editable: true,
                category: t('pages.controls.actions.category_finance'),
            },
            {
                key: 'status',
                label: t('pages.controls.actions.column_status'),
                type: 'text',
                editable: false,
                sortable: false,
                renderCell: (_, row) =>
                    row.finished_at
                        ? t('pages.controls.actions.status_finished')
                        : t('pages.controls.actions.status_planned'),
                renderDetail: (_, row) =>
                    row.finished_at
                        ? t('pages.controls.actions.status_finished')
                        : t('pages.controls.actions.status_planned'),
                category: t('pages.controls.actions.category_status'),
            },
        ],
    };
}

