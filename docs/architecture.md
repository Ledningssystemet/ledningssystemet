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
