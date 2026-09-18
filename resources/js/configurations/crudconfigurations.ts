import type { CrudModuleConfig } from "@components/crudmodule/types";

export type CrudConfigurationDataset = Record<string, CrudModuleConfig>;

/**
 * Register shared CRUD configurations here and reference them via
 * <crud-module data-config-dataset="your-key"></crud-module>
 * or data-props: { configDataset: "your-key" }.
 */
const idNameListUrl = (model: string): string =>
  `/api/v1/items/${model}?paginate=0&sort=name&%24select=id,name`;

export const crudConfigurations: CrudConfigurationDataset = {
  "systemadmin-sites": {
    apiUrl: "/api/v1/items/Site",
    title: "Sites",
    createTitle: "Create site",
    editTitle: "Edit",
    primaryKey: "id",
    defaultSort: "name",
    perPage: 25,
    fields: [
      {
        key: "id",
        label: "ID",
        type: "number",
        hidden: true,
        editable: false,
        sortable: false,
      },
      {
        key: "status",
        label: "Status",
        type: "text",
        hidden: true,
        editable: false,
      },
      {
        key: "name",
        label: "Name",
        type: "text",
        required: true,
        sortable: true,
        masterLabel: true,
        list: true,
      },
      {
        key: "description",
        label: "Description",
        type: "textarea",
        sortable: true,
        masterDescription: true,
        list: true,
      },
      {
        key: "responsible_user_id",
        label: "Responsible",
        type: "select",
        required: true,
        sortable: true,
        optionsUrl: idNameListUrl("User"),
      },
      {
        key: "tags",
        label: "Tag",
        type: "multiselect",
        editable: false,
        optionsUrl: idNameListUrl("Tag"),
      },
      {
        key: "users",
        label: "Users",
        type: "multiselect",
        editable: false,
        optionsUrl: idNameListUrl("User"),
      },
      {
        key: "departments",
        label: "Departments",
        type: "multiselect",
        editable: false,
        optionsUrl: idNameListUrl("Department"),
      },
      {
        key: "assets",
        label: "Assets",
        type: "multiselect",
        editable: false,
        optionsUrl: idNameListUrl("Asset"),
      },
    ],
    filterFields: [
      {
        key: "tag_id",
        label: "Tag",
        type: "select",
        optionsUrl: idNameListUrl("Tag"),
      },
      {
        key: "responsible_user_id",
        label: "Responsible user",
        type: "select",
        optionsUrl: idNameListUrl("User"),
      },
      {
        key: "hidechecked",
        label: "Hide items without issues",
        type: "boolean",
        options: [
          { value: 0, label: "Show all" },
          { value: 1, label: "Hide items without issues" },
        ],
        placeholderValue: 0,
      },
    ],
  },
};

export function getCrudConfiguration(datasetKey: string): CrudModuleConfig | undefined {
  return crudConfigurations[datasetKey];
}