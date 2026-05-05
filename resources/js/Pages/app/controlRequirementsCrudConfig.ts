import type { CrudModuleConfig } from '@/components/crud';

type TranslateFn = (key: string, replacements?: Record<string, string | number>) => string;

interface BuildControlRequirementsCrudConfigOptions {
    controlId?: number;
    lockControlId?: boolean;
}

export function buildControlRequirementsCrudConfig(
    t: TranslateFn,
    options: BuildControlRequirementsCrudConfigOptions = {}
): CrudModuleConfig {
    const lockControlId = options.lockControlId === true && typeof options.controlId === 'number';

    return {
        apiUrl: '/api/crud/control-requirements',
        perPage: 25,
        defaultSort: '-id',
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
            'requirement_id',
        ],
        createTitle: t('pages.controls.requirements.create_title'),
        editTitle: t('pages.controls.requirements.edit_title'),
        fields: [
            {
                key: 'requirement_id',
                label: t('pages.controls.requirements.column_requirement'),
                type: 'select',
                sortable: true,
                editable: true,
                required: true,
                optionsUrl: '/api/crud/requirements?paginate=0&filter[iscontrol]=1&%24select=id,reference,name&sort=reference',
                optionValueKey: 'id',
                optionLabelKey: 'reference',
                category: t('pages.controls.requirements.category_general'),
            },
            {
                key: 'control_id',
                label: t('pages.controls.requirements.column_control'),
                type: 'select',
                sortable: true,
                editable: !lockControlId,
                required: !lockControlId,
                hidden: lockControlId,
                filterable: !lockControlId,
                optionsUrl: '/api/crud/controls?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                category: t('pages.controls.requirements.category_general'),
            },
        ],
    };
}

