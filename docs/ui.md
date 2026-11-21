# Shared React UI

Reusable React components are exported from `resources/js/components/ui/index.tsx`:
`Icon`, `Card`, `SectionTitle`, `Btn`, `Input`, `Select`, `Textarea`, `Options`,
`Label`, `Badge`, `MultiSelect2`, `Dropzone` and `Gantt`. Import them from `../ui` in sibling
component folders. Their CSS is imported automatically and works without a
`projects-module` wrapper. Bootstrap utility/form classes and Material Symbols
are provided by the application; `MultiSelect2` additionally requires its existing
jQuery/Select2 integration.

Shared UI components use the `ls-*` class prefix; project-specific classes retain
the `pm-*` prefix. Shared colors can be overridden with `--ui-primary`,
`--ui-foreground`, `--ui-muted` and `--ui-border`; project theme variables remain
supported as fallbacks. Project-specific layout and status logic stay in the
project module. Translation uses the application's `window.translateString` through
`resources/js/lib/i18n.ts`.

JSX source files include a runtime `React` import so that direct npm package
imports work with both classic and automatic JSX transforms, independently of
the consuming application's compiler configuration.

For `Gantt` usage and date-range behavior, see [Project features](projects.md#reusable-gantt-component).

## Table module permissions

`TableModule` imports its own scoped styles, including styles for dialog and
select portals. Consumers do not need to configure Tailwind. The checked-in
utility stylesheet is generated with `npm run build:table-css` after changing
table utility classes or the Tailwind configuration. Theme overrides use
`--ls-table-*` variables (HSL components, for example `--ls-table-primary:
183 83% 27%`); set them on the document or body so portal content inherits
them too. `--ls-table-radius` is a CSS length.

`TableModule` accepts `permissions.create` as a boolean and
`permissions.update` and `permissions.delete` as booleans or per-item
functions. Boolean values can also be supplied through JSON `data-props` on a
`table-module` element. Per-item `access` and `accessFlags` restrictions still
apply; server-side authorization remains required.
`retainCreatedItem` keeps the create response in the current table instead of
reloading the list, which is useful for response-only values that cannot be
retrieved later.

### Available views

Configure `viewModes` on the `TableModule` config to disable individual views:

```ts
{
  defaultView: "table",
  viewModes: { masterDetail: false, table: true, list: false },
}
```

Omitted settings default to `true`, so existing configurations retain all three
views. At least one view must be enabled; disabling all three raises a
configuration error. `reorder` requires the table view and raises an error if
it is disabled.

In list view, item actions are hidden while collapsed and appear above the
details when an item is expanded.

The saved `md_view` cookie is used only if its view is enabled. Otherwise the
module selects `defaultView` when enabled, or the first enabled view in the
order master-detail, table, list. `persistState: false` skips the saved
view. The same rules apply when the configuration changes after mounting.
The option works in React configs, JSON `data-props`, and `data-config` for
`mountMasterDetail`.

When inline editing has started, view switching is disabled until the edit is
cancelled or saved successfully. A failed save keeps the current view and edit
open.

### Sortable columns

Set `sortable: true` on a column to offer it in the sorting dialog and make its
table header clickable. Columns with `sortable: false` or no `sortable` setting
are not sortable through either control. In API mode, the server must also
allow the requested sort field.

For fixed select options, `type: "select"` with `multiple: true` displays a
checkbox multiselect in create and edit forms. Values are submitted as an array
of strings; existing object values such as `{ id, name }` are normalized to
their IDs. Column and filter options accept either strings or `{ value, label }`
objects, so stored values can have human-readable labels.

For local data, `searchKeys` selects the fields searched for a substring. With
`dataSource`, the table sends the trimmed search text as a Spatie filter:
`searchKeys: ["name"]` produces `filter[name]`, and `["name", "email"]`
produces `filter[name_or_email]`. Other filters remain separate and combine with
this filter. The backend must explicitly allow the resulting filter and implement
the intended OR behavior; Spatie does not create it automatically. Commas in
search text follow the server's filter-value delimiter configuration. A search
requires at least one key and cannot share its resulting filter name with an
active filter or a fixed data-source parameter.

Consumer regression coverage should include all three views, responsive layout,
selection and pagination, filter and edit dialogs, selects and multiselects,
delete confirmation, nested tables, theme overrides, and unrelated Bootstrap
controls. In list view, verify actions are hidden while collapsed and appear
above details when expanded. Exercise both application registration and direct
npm imports. Cover
each view disabled individually and in combinations, disabled saved and default
views, configuration changes, all views disabled, and the reorder conflict. Cover
attempted view switching during inline edit, cancellation, successful save, and
failed save. Cover
sortable and non-sortable columns in both the table and sorting dialog, including
API-backed sorting. Cover
single- and multi-field API search, combining search with filters and pagination,
filter name collisions, and any server-specific delimiter and OR behavior.

The table implementation is split by responsibility: `TableModule.tsx` owns
state and data operations; reusable controls and views are in `table/components/`,
the form is in `table/forms/`, dialog components are in `table/dialogs/`, and
configuration/persistence helpers are in `table/helpers/`. Keep API and
permission decisions in the module rather than duplicating them in presentation
components.

Table-owned labels, accessibility text, validation and fallback errors use the
shared translation function with English keys and Swedish translations in
`lang/sv.json`. Configuration-provided labels and server messages are supplied
by the consumer and are not translated automatically. Consumer regression
coverage should check English fallback and Swedish translations across views,
dialogs, pagination, validation, bulk actions and API errors, including
interpolated values and custom property tabs.

## Dropzone

`Dropzone` is a reusable React upload control, exported with `DropzoneProps`
from `resources/js/components/ui` and the `ui/Dropzone` package entry. It has
its own styles and works outside the project module. It accepts multiple files
through drag-and-drop or an accessible file picker and shows status, individual
errors and retry actions. Each file is uploaded separately; successful files
are not retried when another file fails.

```tsx
<Dropzone
    onUpload={(file, signal) => {
        const data = new FormData();
        data.append('file', file);
        return axios.post('/upload', data, { signal });
    }}
    onUploaded={() => refreshFiles()}
    errorMessage={formatError}
    disabled={!canUpload}
/>
```

`onUpload` must reject on failure and should pass the supplied `AbortSignal` to
the transport. `onUploaded` runs once for each successful upload; `errorMessage`
optionally formats rejected errors. Unmounting aborts active uploads; disabling
the control prevents new uploads and retries but does not cancel active ones.
Use a React `key` tied to the owning object when changing upload context.
The control does not provide authorization or choose an API endpoint: callers
must enforce permissions on the server and supply their own upload and refresh
logic. Regression coverage should exercise drag-and-drop, keyboard file
selection, multiple files, mixed successes and failures, retry, disabled state,
unmount cancellation and use outside the projects module.
