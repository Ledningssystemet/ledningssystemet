import type { CrudModuleConfig } from '@/components/crud';

type TranslateFn = (key: string, replacements?: Record<string, string | number>) => string;

interface BuildIncidentLogsCrudConfigOptions {
    incidentId?: number;
    lockIncidentId?: boolean;
}

export function buildIncidentLogsCrudConfig(
    t: TranslateFn,
    options: BuildIncidentLogsCrudConfigOptions = {}
): CrudModuleConfig {
    const lockIncidentId = options.lockIncidentId === true && typeof options.incidentId === 'number';

    return {
        apiUrl: '/api/crud/incident-logs',
        perPage: 25,
        defaultSort: '-start_at',
        fixedFilters: lockIncidentId
            ? { incident_id: options.incidentId }
            : undefined,
        createDefaults: lockIncidentId
            ? { incident_id: options.incidentId }
            : undefined,
        selectFields: ['id', 'incident_id', 'start_at', 'description'],
        createTitle: t('pages.incidents.logs.create_title'),
        editTitle: t('pages.incidents.logs.edit_title'),
        fields: [
            {
                key: 'start_at',
                label: t('pages.incidents.logs.column_start_at'),
                type: 'date',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.incidents.logs.category_general'),
            },
            {
                key: 'description',
                label: t('pages.incidents.logs.column_description'),
                type: 'textarea',
                editable: true,
                required: true,
                masterDescription: true,
                category: t('pages.incidents.logs.category_general'),
            },
            {
                key: 'incident_id',
                label: t('pages.incidents.logs.column_incident'),
                type: 'select',
                sortable: false,
                editable: !lockIncidentId,
                required: !lockIncidentId,
                hidden: lockIncidentId,
                filterable: !lockIncidentId,
                optionsUrl: '/api/crud/incidents?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                category: t('pages.incidents.logs.category_general'),
            },
        ],
    };
}

