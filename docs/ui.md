# Shared React UI

Reusable React components are exported from `resources/js/components/ui/index.tsx`:
`Icon`, `Card`, `SectionTitle`, `Btn`, `Input`, `Select`, `Textarea`, `Options`,
`Label`, `Badge`, `MultiSelect2` and `Gantt`. Import them from `../ui` in sibling
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
