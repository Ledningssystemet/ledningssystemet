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