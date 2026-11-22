# Architecture and settings

Ledningssystemet is a Laravel application (PHP 8.5, Laravel 13) with a Vite-built
frontend including React components. `src/LedningssystemetServiceProvider.php`
registers package services; `src/Providers/RouteServiceProvider.php` loads the
web and API routes from `routes/`. Backend models and authorization policies live
under `app/Models` and `app/Policies`; database changes live under
`database/migrations`. The API controllers are under `app/Http/Controllers/Api`.
Frontend components are under `resources/js/components`, including the shared
UI described in [ui.md](ui.md). The global React component registry uses
`React.lazy` so each component's code is loaded only when its matching element
or mount function is rendered.

## Table CSV export

API-backed table configurations automatically request `fields[_]` (or
`fields[tableName]`) for the ID, title, subtitles, configured columns and
non-child detail sections. This includes hidden columns used in forms or CSV
export. `dataSource.fields` adds fields used outside these declarations, such
as by renderers, permissions, status functions or row commands; explicitly
include `access` or `accessFlags` when the model exposes them. Mark a
render-only column with `apiField: false` if its key is not an API field, and
list its actual dependencies in `dataSource.fields`. Child tables build their
own field selections. All requested fields must be allowed by the model.

The table view can export all configured table data columns, including hidden
columns, as a UTF-8 CSV with a BOM and semicolon separators for Excel. The export
includes all rows matching the active search and filters, in the current sort
order, not just the current page or selected rows. Cell text follows the table
rendering; action and child-table columns are excluded. Values that could be
interpreted as spreadsheet formulas are prefixed with an apostrophe.

API-backed tables request the full result with the same query and without
pagination. The server must support an unpaginated list response; an incomplete
paginated response produces an error instead of a partial export. The separate
consumer regression project should cover local and API-backed tables, filtering,
sorting, pagination, hidden columns and column selection, rendered cells, CSV escaping, formula
protection, and API errors and incomplete responses.

For field selection, also cover the paginated and export requests, callbacks
using additional fields, virtual and hidden columns, detail sections and nested
tables. Verify custom properties, computed values, authorization-dependent
fields, rejected fields and models without dependency declarations, as well as
user reassignment and notification settings in the Composer/npm host.

The `User` model and user API use `org_roles` for organizational roles, with
`id`/`name` pairs when reading and role IDs when writing. These use the existing
`int_roles()` relation and `role_user` pivot table. The `roles` relation is
reserved for Spatie permission roles (access groups).

## BPMN conversion

`app/Support/ProcessChartBpmn.php` converts the supported BPMN subset to and from
`chart_data`. End events support incoming data associations from information
objects, including the `property` placeholder used by bpmn.io. These connections
and their diagram geometry are preserved on import and export as graphics, not
as relations between database objects. Unsupported elements still cause an
explicit conversion error.

Import validates the regenerated BPMN XML against the original before returning
`chart_data`. Comparison ignores namespace prefixes, attribute and element order,
and consistently renamed IDs, but requires an exact mapping of elements,
containment and references. Coordinates are compared numerically; empty names and
the default `isExpanded=false` are equivalent to omitted attributes. Other
attributes, documentation and diagram geometry must be preserved. Errors identify
changed XML paths and values or incompatible connections. Unsupported XML cannot
silently disappear just because a second import produces the same `chart_data`.
`ledningssystemet:convert-process-charts` additionally compares `chart_data`
before saving.

Optional preservation metadata includes `bpmnName` on information objects whose
XML name differs from the name inferred from a reference, `hasDiagram` on
subprocess graphics with an empty diagram plane, and `hasLabel` on graphics
with an empty diagram label. These survive encoding and decoding and are emitted
on export. Existing charts without this metadata remain supported.

`bpmn:documentation` is preserved on the process and supported nodes and
connections. Optional `documentation` lists contain entries with a `text` string
and an optional `textFormat` string. Process documentation is stored at the root
of `chart_data`; data-object documentation is stored on its `objects` entry;
documentation for graphical nodes (including tasks and data-object references)
and connections is stored on their `graphics` entries. Multiple entries, empty
text, whitespace and escaped HTML/CDATA text survive import/export. Nested XML
elements inside documentation are rejected rather than silently flattened.
Documentation remains chart metadata and does not update entity descriptions.
Existing charts without documentation keep their existing structure.

Regression coverage in the separate consuming project should include end events
with one or multiple data inputs sharing a placeholder, preservation of waypoints
and labels across import/export, and unchanged task/subprocess data associations.
Also cover documentation on tasks and data-object references, multiple formatted
entries, database-linked objects, process/data-object/connection documentation,
and rejection of malformed documentation metadata. Include XML comparisons with
renamed IDs and reordered nodes, identical-looking nodes with different
connections, cyclic graphs, missing or dangling references, changed coordinates,
empty labels and subprocess planes. Exported BPMN should also import without
warnings in bpmn.io.

## Password authentication

`LOGIN_ALLOW_PASSWORDLOGIN=false` disables password login and denies unauthenticated
access to both GET and POST password reset routes (`/forgot-password` and
`/reset-password`). Authenticated system administrators with
`systemadministrator.edit` can still use these routes and send reset links from
user administration. Other authenticated users cannot use the reset routes while
password login is disabled. When password login is enabled, guest password reset
continues to work as before.

Successful session authentication updates the package user's `last_login_at`
through Laravel's `Login` event, including password login after any MFA
challenge, Azure and Google SSO, and restoration from a remember-me cookie.
For SSO users redirected to mandatory MFA enrollment, the timestamp reflects
the initial authenticated session, before enrollment is completed. Failed
authentication and an unfinished password MFA challenge do not update it.
The listener also runs in Composer host applications and ignores users that do
not use the package's `User` model.

The separate consumer regression suite should cover password login with and
without MFA, failed credentials and MFA, both SSO providers including mandatory
MFA enrollment, remember-me restoration, and unrelated host user models.

## System settings

System administrators manage Staff, Processes, Findings, Suppliers, GDPR and
Archival, as well as the data protection contact's name and email address, under
**System settings > General settings** (`/systemadmin/settings`). Settings
apply to the entire installation and are persisted by Laravel Pennant in
`system_settings`, using its dedicated `ledningssystemet` store and null scope.
The Assessment tab also stores the control-status reassessment deadline in days;
it defaults to 365 days. Disabling a module does not delete its data.

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
The processing register reads its contact details from the database. Changes in
the administration page take effect without rebuilding configuration or route
caches.

System administrators can manage a user's weekly status-email schedule and
notification channels from **System administration > Users**. The notification
settings action is available to users with permission to update users; changes
to the schedule use the protected system-admin endpoint, while notification
channels use the existing `UserNotificationChannel` API and authorization.
Regression coverage should verify that administrators can view and update both
settings for another user, while other users cannot access the admin endpoint.
