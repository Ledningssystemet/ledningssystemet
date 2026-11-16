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

For `Gantt` usage and date-range behavior, see [Project features](projects.md#reusable-gantt-component).

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
