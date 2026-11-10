# Architecture and settings

Ledningssystemet is a Laravel application (PHP 8.5, Laravel 13) with a Vite-built
frontend including React components. `src/LedningssystemetServiceProvider.php`
registers package services; `src/Providers/RouteServiceProvider.php` loads the
web and API routes from `routes/`. Backend models and authorization policies live
under `app/Models` and `app/Policies`; database changes live under
`database/migrations`. The API controllers are under `app/Http/Controllers/Api`.
Frontend components are under `resources/js/components`, including the shared
UI described in [ui.md](ui.md).

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

## System settings

System administrators manage Staff, Processes, Findings, Suppliers, GDPR and
Archival, as well as the data protection contact's name and email address, under
**System settings > General settings** (`/systemadmin/settings`). Settings
apply to the entire installation and are persisted by Laravel Pennant in
`system_settings`, using its dedicated `ledningssystemet` store and null scope.
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
The processing register reads its contact details from the database. Changes in
the administration page take effect without rebuilding configuration or route
caches.
