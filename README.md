# Ledningssystemet

Ledningssystemet is an open-source management system for documenting, organizing, and improving business processes and quality management work.
It is designed to be your tool for compliance with many regulations and standards, including ISO 9001, ISO 14001, ISO 45001, ISO/IEC 27001, GDPR and NIS2.

## Examples of Features

- Process and document management
- Role and permission management
- Activity logging
- Import and export support
- Risk management
- Incident management
- GDPR automation (e.g. Record of Processing Activities)

See our training videos for more examples: https://support.ledningssystemet.se/kb/faq.php?cid=3

## Getting started

See [INSTALL.md](INSTALL.md) for installation instructions.

## System settings

System administrators manage Staff, Processes, Findings, Suppliers, GDPR and
Archival, as well as the data protection contact's name and email address, under
**System settings > General settings** (`/systemadmin/settings`).
Settings apply to the entire installation and are persisted by Laravel Pennant
in `system_settings`, using its dedicated `ledningssystemet` store and null scope.
Disabling a module does not delete its data.

Run `php artisan migrate --force` during deployment, before serving requests.
The migration imports the installation's existing `DISABLE_STAFF`,
`DISABLE_PROCESSES`, `DISABLE_FINDING`, `DISABLE_SUPPLIER`, `DISABLE_GDPR` and
`DISABLE_ARCHIVAL` values from the environment / `.env`, including when
configuration is cached. Keep those values available until the migration has
run. Without explicit values, all modules except Archival start enabled.
The same migration imports `COMPANY_DATA_PRIVACY_CONTACT_NAME` and
`COMPANY_DATA_PRIVACY_CONTACT_EMAIL`, defaulting to empty strings if unset.
After migration, these environment variables and the old `disable_*` and
`data_privacy_contact_*` configuration keys are no longer used and can be removed.
The processing register reads its contact details from the database. Changes in the
administration page take effect without rebuilding configuration or route caches.

## API documentation

Authenticated API users can retrieve the Swagger 2.0 specification with
`GET /api/v1/swagger`, using `Authorization: Bearer <token>` and
`Accept: application/json`. No system administrator permission is required.
This endpoint also accepts the browser's login session, even without
`Referer` or `Origin` headers. This explicit session support is limited to Swagger;
authentication for other API endpoints is unchanged.
The Swagger UI remains available to system administrators at `/systemadmin/swagger`
and loads the specification from the API endpoint.
The specification documents `/api/v1/crud/{model}` and its item and custom-action
routes, not the legacy `/api/v1/items` API. List operations support
`filter[field]=value`, comma-separated `sort` and `include`, and
`fields[table]=field1,field2` (or `fields[_]`). Allowed values depend on the model;
explicit model filters are listed in the specification. Default filters are based
on visible database columns and visible relationship paths, not legacy `index`
parameters such as `search`. Without `per_page`, lists return an array. With
`per_page` (and optionally `page`), they return a pagination object with `data`
and pagination metadata.

## Shared React UI

Reusable React components are exported from `resources/js/components/ui/index.tsx`:
`Icon`, `Card`, `SectionTitle`, `Btn`, `Input`, `Select`, `Textarea`, `Options`,
`Label`, `Badge` and `MultiSelect2`. Import them from `../ui` in sibling component
folders. Their CSS is imported automatically and works without a
`projects-module` wrapper. Bootstrap utility/form classes and Material Symbols
are provided by the application; `MultiSelect2` additionally requires its existing
jQuery/Select2 integration.

Shared UI components use the `ls-*` class prefix; project-specific classes retain
the `pm-*` prefix. Shared colors can
be overridden with `--ui-primary`, `--ui-foreground`, `--ui-muted` and `--ui-border`;
project theme variables remain supported as fallbacks. Project-specific layout
and status logic stay in the project module. Translation uses the application's
`window.translateString` through `resources/js/lib/i18n.ts`.

## Project meetings

The Meetings tab stores project meetings through the `ProjectMeeting` CRUD API.
Run migrations before deployment to create `project_meetings` and
`project_meeting_mentions`. Meetings have a date, location and an ordered agenda;
each section stores a Tiptap JSON document and links to activities in the same
project. Sections can be added, removed and reordered. Activities created from
the editor are saved immediately; their section links are saved with the meeting.
Project owners and writable participants can edit meetings; read-only
participants can read them. Archived projects and completed meetings cannot be
modified, including through the API. Completing a meeting saves and locks it.
Updates must include the last received `revision`; stale saves return HTTP 409.

Under **Coordination > Meeting agendas**, administrators with
`managementtools.edit` can configure named meeting agendas with ordered
mandatory points and descriptions. Agendas are available to every project,
regardless of project type. New meetings can use one of these agendas or start
without a template. Template points are snapshotted
when the meeting is saved and cannot be removed, renamed or changed into custom
points, including via the API. Notes, activity links and point ordering remain
editable until completion. Later template edits/deletions do not modify existing
meeting documents.

Before preparing a meeting from a meeting agenda, the interface asks whether to
include follow-up when a previous meeting exists in the same project using that
same agenda. The previous meeting is the latest by date (then ID), no later than
the new meeting date. Accepting adds a mandatory follow-up point containing links
to its still-open project activities and a link to that meeting. Declining omits
the point. The server requires an explicit choice and rejects stale previews.
`Project/{id}/meetingagenda` returns the available agendas and, given `date` and
`project_meeting_agenda_template_id`, the proposed mandatory points and previous
meeting. Creating `ProjectMeeting` with `project_meeting_agenda_template_id` submits these points,
`include_follow_up` and the preview's `follow_up_meeting_id`. Templates and their
items are stored in `project_meeting_agenda_templates` and
`project_meeting_agenda_template_items`.

Type `@` in a section to mention a project participant or the project owner.
Type `/` to link an existing project activity or create a new one; already linked
activities are excluded. Both commands open a compact popup over the notes.
Keep typing to filter names, use Up/Down to navigate and Enter or Tab to select.
Escape, Close or clicking outside dismisses the popup without changing the notes.
The toolbar's `@` and `+` buttons open the same menus.
The server derives mention records from the saved document, not from a
client-supplied recipient list. The header mounts the `project-mentions` React
component using shared UI buttons, badges and an accessible popup. Its `@` button
counts unacknowledged mentions and lists them grouped by project, with meeting
names and note excerpts. Links open `/projects?project=…&meeting=…&mention=…`,
select the meeting and focus the relevant agenda section. A mention is acknowledged
only after that section's editor is ready; loading or acknowledgement failures
show an error with a retry action. The count refreshes when the popup opens,
the window regains focus or an acknowledgement succeeds. The API only exposes
the current user's mentions in accessible projects, including project and meeting
names. The `ProjectMeetingMention` action `acknowledge`
records an idempotent acknowledgement, also for completed meetings. Re-saving
unchanged notes preserves acknowledgements; changed excerpts reset them.
Removing a mention or its section removes its record. Deleting a meeting or
project cascades to its mentions. Mentions are in-app only; no email is sent.

## Project interface

The projects page (`/projects`) mounts `projects-module` through the
React component registry. Projects, project risks and project activities are read and saved
using `/api/v1/crud`; no mock data or browser-local persistence is used.
The project list requests only selected Project fields (including responsible
users' names) and filtered ProjectActivity timeline fields. Lookup lists for
forms and risk assessment are loaded on demand when opening or creating a
project, then reused while the module remains mounted. Project risks, work
packages and full activity details are fetched only after selecting a project.
The light dashboard-style project header, icon tabs, accented metric cards,
full-width project rows and risk matrix are styled with
module-scoped CSS (`resources/js/components/projects/projects.css`), without
changing the surrounding application's Bootstrap theme.
The module uses `ProjectRisk` (`project_risks`) and `ProjectActivity`
(`project_activities`), both scoped with `filter[project_id]`. Project activities
have start/due dates, estimated cost/hours and optional links to multiple risks
in the same project through `project_risk_project_activities`. They are separate
from general activities and control actions. Risk notes use `note`; the initial
and post-activity assessments use their respective probability/consequence IDs.
Risk forms group details, current assessment, planned activities, post-activity
assessment and notes into separate sections. Planned activities can be created
and edited in the risk form using the same fields as the Activities tab. Existing
activities can be linked through a dropdown without removing their other risk
links; linking is saved with the form. Activities can also be unlinked from
unassessed risks when saving, without deleting the activity or its other risk links.
The read-only risk display follows the
same section order, with labeled assessments and activity summaries.
The post-activity assessment is only shown when activities are linked to the risk.
Its editor explains that the values forecast the remaining risk after those activities are completed.
Projects do not offer Excel export.
Risk cards initially show only their collapsible header, including current
probability and consequence levels. Activate the header to expand the details.
Saving
persists the risk first, then changed activities and their risk links through
separate CRUD requests. Partial failures are reported explicitly and saving can
be retried without recreating successfully saved records.
Approval, reassessment, archival and deletion use the existing server actions
and authorization rules, with confirmations shown through the shared `utils.js`
`confirmDialog`. Reassessment reopens the same project risk without
creating a replacement chain. History and comment panels are not displayed in
the project module; backend history logging is unchanged.
The risk matrix uses the installation's configured
probability, consequence and risk levels, not a fixed five-point scale.
Work packages are configured in their own tab immediately after Activities, using
`ProjectWorkPackage` (`project_work_packages`) and the
`project_work_package_activities` pivot table. The timeline layout has status
diamonds, date ranges, progress counts and linked activity chips. A compact form
adds packages with a name and optional start/end dates, both defaulting to the
current local date for new packages (including after adding a package). Editing
preserves existing dates, including empty dates. The full editor also
provides a description and the shared `MultiSelect2` activity selector. End dates
cannot precede start dates. Packages have links to activities in the same project;
an activity may belong to
multiple packages. Completion is manual via `completed_at` and requires at least
one linked activity, with all linked activities completed. Completing activities
does not automatically complete a package. Completed packages must be reopened
before editing their contents, and all completed packages containing an activity
must be reopened before that activity can be reopened or deleted. Deleting a
package does not delete its activities. Archived projects cannot have their
packages changed. Package read/write and deletion permissions follow those for
project activities. Existing activities can be linked or unlinked directly in
each package. New activities use the shared activity fields and are created and
linked atomically by the `ProjectWorkPackage.createactivity` server action, so a
failed save cannot leave an unlinked activity behind. Completed packages must
be reopened before linking or creating activities.
Apply `2026_10_24_143000_create_project_work_packages` before
using the tab.
### Reusable Gantt component

The All projects view shows a personal Gantt above the project list. It groups
activities by ongoing (non-archived) projects where the current user is an
explicit participant (including read-only participants) or the responsible
project owner. Only activities assigned to that user are requested and displayed;
work packages and other users' activities are excluded. Projects with no matching
activities retain an explicit empty group. List search and archive filters do not
change this personal timeline. Loading errors are displayed with a retry button.
The timeline is hidden when the user cannot read project activities.

Import `Gantt` and its `GanttProps`, `GanttGroup` and `GanttRow` types from
`resources/js/components/ui` (or the `ui/Gantt` entry point). The component
automatically imports its own styles, uses shared `ls-*` classes and `--ui-*`
theme variables, and works without a `projects-module` wrapper or project API.
Supply already-authorized data: the shared component does not fetch data or
perform permission checks.

```tsx
import { Gantt, type GanttGroup } from '../ui';

const groups: GanttGroup[] = [{
    id: 'deliveries',
    label: 'Deliveries',
    emptyMessage: 'No deliveries',
    rows: [{
        id: 1,
        name: 'First delivery',
        meta: 'Team A',
        start: '2026-10-01',
        end: '2026-10-31',
        completed: false,
        kind: 'bar',
    }, {
        id: 2,
        name: 'Review',
        end: '2026-10-20',
        kind: 'activity',
    }],
}];

<Gantt title="Delivery schedule" groups={groups} locale="sv" />;
```

Rows use `kind: 'bar'` for inclusive date-range bars or `kind: 'activity'`
for a start-to-end line with an endpoint dot (only a dot without `start`).
Dates use `YYYY-MM-DD`; optional `startDate`/`endDate` expand the chart range,
`today` overrides the current local date, `locale` overrides the page language,
and `ariaLabel` names the scrollable chart for assistive technology.
Group and row IDs must be unique within their respective lists.
The project adapter remains in `projects/Gantt.tsx`, mapping project models
and filtering groups using existing read permissions.

The Gantt tab follows Work packages and displays existing project data without
additional API requests or database tables. Work packages use orange bars, or
green when manually completed. Each work package is a group heading with its
activities underneath, without an activity count. An activity linked to multiple
packages appears under each one. Unlinked activities appear last under Other
project activities. Activities show a line from start to due date
ending in a dot; without a start date only the due-date dot is shown. Completed
activities are green and other activities teal. Missing dates are explicitly
identified rather than inferred. The monthly axis runs from the earliest visible
package/activity date through the entire month after the latest visible date,
using actual calendar-day proportions. Project boundaries and today's date do
not expand this range. With no dated rows, explicit component bounds are used,
or the current and following month if no bounds are supplied.
Today is marked in red and centered on load (within the scroll limits) when it
falls within the range. Unfinished activities and work packages with an end/due
date before today have red bars/lines and highlighted labels without a status badge;
completed items and items due today are not overdue. Month labels follow
the page language. The chart scrolls horizontally with sticky row labels and
respects the existing activity and work-package read permissions.
Meetings and staffing are not exposed until backend support
exists. The responsible user manages participants exclusively from the Team tab,
including read-only or read/write access. The `project_user.readonly` flag defaults
to `false`, preserving write access for existing participants. Read-only members
can view the project, risks, activities and work packages, but cannot change them or add comments.
Project settings and participant administration remain reserved for their existing
administrative roles and the responsible user respectively.

## User role attributes

The `User` model and user API use `org_roles` for organizational roles, with
`id`/`name` pairs when reading and role IDs when writing. These still use the
existing `int_roles()` relation and `role_user` pivot table; no database migration
is needed. The `roles` relation is reserved for Spatie permission roles (access
groups).

## License

Ledningssystemet is licensed under the [GNU Affero General Public License v3.0 or later](https://www.gnu.org/licenses/agpl-3.0.html). If you modify and run the software as a network service, you must make the corresponding source code available to its users under the same license.

## Contributing

Contributions are welcome. Please open an issue or submit a pull request with a clear description of your change.