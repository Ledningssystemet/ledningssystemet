import type { CrudModuleConfig } from '@/Components/crud';

type TranslateFn = (key: string, replacements?: Record<string, string | number>) => string;

interface BuildRequirementSourceRequirementsCrudConfigOptions {
    requirementSourceId?: number;
    lockRequirementSourceId?: boolean;
    readOnly?: boolean;
}

export function buildRequirementSourceRequirementsCrudConfig(
    t: TranslateFn,
    options: BuildRequirementSourceRequirementsCrudConfigOptions = {}
): CrudModuleConfig {
    const lockRequirementSourceId = options.lockRequirementSourceId === true && typeof options.requirementSourceId === 'number';
    const readOnly = options.readOnly === true;

    return {
        apiUrl: '/api/crud/requirements',
        perPage: 25,
        defaultSort: 'ordinal',
        fixedFilters: lockRequirementSourceId
            ? {
                  requirement_source_id: options.requirementSourceId,
              }
            : undefined,
        createDefaults: lockRequirementSourceId
            ? {
                  requirement_source_id: options.requirementSourceId,
                  applicable: true,
                  ordinal: 0,
              }
            : {
                  applicable: true,
                  ordinal: 0,
              },
        selectFields: [
            'id',
            'requirement_source_id',
            'reference',
            'name',
            'description',
            'applicable',
            'governance',
            'controls',
            'ordinal',
        ],
        createTitle: t('pages.requirement_sources.requirements.create_title'),
        editTitle: t('pages.requirement_sources.requirements.edit_title'),
        canCreate: !readOnly,
        canEdit: !readOnly,
        canDelete: !readOnly,
        fields: [
            {
                key: 'reference',
                label: t('pages.requirement_sources.requirements.column_reference'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.requirement_sources.requirements.category_general'),
            },
            {
                key: 'name',
                label: t('pages.requirement_sources.requirements.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                category: t('pages.requirement_sources.requirements.category_general'),
            },
            {
                key: 'description',
                label: t('pages.requirement_sources.requirements.column_description'),
                type: 'textarea',
                sortable: false,
                editable: true,
                masterDescription: true,
                category: t('pages.requirement_sources.requirements.category_general'),
            },
            {
                key: 'applicable',
                label: t('pages.requirement_sources.requirements.column_applicable'),
                type: 'select',
                sortable: true,
                editable: true,
                options: [
                    { value: 1, label: t('pages.requirement_sources.option_yes') },
                    { value: 0, label: t('pages.requirement_sources.option_no') },
                ],
                renderCell: (value) => {
                    if (value === null || value === undefined || value === '') {
                        return '?';
                    }

                    const isYes = value === true || value === 1 || value === '1' || value === 'true';
                    return isYes
                        ? t('pages.requirement_sources.option_yes')
                        : t('pages.requirement_sources.option_no');
                },
                renderDetail: (value) => {
                    if (value === null || value === undefined || value === '') {
                        return '?';
                    }

                    const isYes = value === true || value === 1 || value === '1' || value === 'true';
                    return isYes
                        ? t('pages.requirement_sources.option_yes')
                        : t('pages.requirement_sources.option_no');
                },
                category: t('pages.requirement_sources.requirements.category_status'),
            },
            {
                key: 'governance',
                label: t('pages.requirement_sources.requirements.column_governance'),
                type: 'textarea',
                sortable: false,
                editable: true,
                category: t('pages.requirement_sources.requirements.category_status'),
            },
            {
                key: 'controls',
                label: t('pages.requirement_sources.requirements.column_controls'),
                type: 'multiselect',
                sortable: false,
                editable: true,
                optionsUrl: '/api/crud/controls?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.requirement_sources.requirements.select_controls'),
                category: t('pages.requirement_sources.requirements.category_links'),
            },
            {
                key: 'requirement_source_id',
                label: t('pages.requirement_sources.requirements.column_requirement_source'),
                type: 'select',
                sortable: true,
                editable: !lockRequirementSourceId,
                required: !lockRequirementSourceId,
                hidden: lockRequirementSourceId,
                optionsUrl: '/api/crud/requirement_sources?paginate=0&%24select=id,reference,name&sort=reference',
                optionValueKey: 'id',
                optionLabelKey: 'reference',
                category: t('pages.requirement_sources.requirements.category_links'),
            },
            {
                key: 'ordinal',
                label: t('pages.requirement_sources.requirements.column_ordinal'),
                type: 'number',
                sortable: true,
                editable: false,
                hidden: true,
                hiddenInTable: true,
            },
        ],
    };
}

