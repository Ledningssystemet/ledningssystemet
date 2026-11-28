# API

The application generates a Swagger 2.0 specification at `GET /api/v1/swagger`.
Authenticated API users can retrieve it with `Authorization: Bearer <token>` and
`Accept: application/json`; system administrator rights are not required. This
endpoint also accepts a browser login session without `Referer` or `Origin` headers.
Explicit session support here does **not** change authentication for other API
endpoints. System administrators can browse Swagger UI at `/systemadmin/swagger`.

The specification describes `/api/v1/crud/{model}`, its item routes and custom
actions. The older `/api/v1/items` API is not covered. Model names, available
operations, fields and allowed filters depend on the model and the requesting
user's permissions; consult the live specification for the deployed version.

## Frontend permissions

`GET /api/v1/permissions` returns the authenticated user's `permissions` array
of permission names, `currentUserId`, and `pages` with policy-derived
capabilities. `pages.projects` contains `canView` and `canCreate`;
`pages.ghgcalculator` contains `canView` and the processes module's `enabled`
state. These capabilities honor Gate overrides, including superadmin access.
The response remains `Cache-Control: no-store`. Existing consumers of the
`permissions` array remain compatible.

The `/app` router requests this endpoint once when the app loads and reuses the
result until the app is reloaded. Permission changes may not be reflected in the
interface until reload. These values control frontend presentation only. The
shared `/app` shell requires authentication but does not check page permissions
or module settings; API operations enforce their own authorization. Consumer
regression coverage should include ordinary
users, project creators, GHG readers/editors, superadmins, host policy overrides,
disabled processes, permission changes between navigations and after reload, and
expired sessions.

## System administration hydration

There is no separate hydration endpoint. The shared system-administration app
reads reference data through the existing `/api/v1/crud/{model}` routes:
`User`, `Department`, `Role`, `AccessGroup`, `RiskLevel` and `CustomProperty`.
Requests use explicit `fields[_]`, name sorting (ordinal sorting for risk levels
and custom properties), and no pagination, so option lists are complete.
Custom properties are filtered by `context` for the package's User class.

`CustomProperty` additionally supports the optional read-only fields
`translated_name` and `select_options`. Request them explicitly with field
selection; default serialization remains unchanged. Options reuse the model's
existing options helper and contain string `value`, translated `label` and
`disabled`, preserving boolean/reference form behavior without returning a
finished table configuration.

Authorization stays on the server. The React application does not calculate
page permissions or skip requests based on permissions; the API applies its
normal Gates and reports denied requests. System administrators can read/write
Department and Role resources (Role still requires staff to be enabled), and
read RiskLevel references; risk-level write permissions remain unchanged.
The actual permission name used by the server is `systemadministrator.edit`.

Configuration, translated UI labels and resources without CRUD support
(permission options, external-provider groups and notification definitions)
remain in the authenticated Blade bootstrap. System settings are loaded from
the API described below and update the app's shared settings state.

The app rehydrates CRUD references on entry and page-path navigation, and
refreshes affected references after successful mutations. Requests bypass the
browser cache. Failures after a successful write are reported separately and
can be retried. External reference changes are discovered on navigation or
reload; no polling or real-time synchronization is used. Bootstrap-only data
and server-rendered sidebar permissions are refreshed by a manual browser
reload, not by client-side authorization checks.

Consumer regression coverage should include complete field-selected option
lists, custom-property translations/options and unchanged default responses,
server authorization and host Gate overrides, mutation invalidation, races,
refresh retry, module settings and expired sessions, plus direct loads and
client navigation across all five pages.

## System settings

Authenticated API users can read `GET /api/v1/settings`, without system
administrator permission. The response is not cacheable and includes `modules`
(an array of `{key, label, enabled}`), `contact` and `assessment` objects,
and translated `contactFields` and `assessmentFields` metadata.

Only users allowed `systemadministrator.edit` can write settings.
`PATCH /api/v1/settings/modules/{module}` accepts `{"enabled":true}` and
returns `{"module":"staff","enabled":true}` for the corresponding module.
Unknown module names and invalid boolean values return 422. It changes no other
settings. `PUT /api/v1/settings` requires `data_privacy_contact_name`,
`data_privacy_contact_email` (both nullable) and the positive integer
`control_status_reassessment_days`. It optionally accepts a complete `modules`
object, with a boolean for each supported module. Omit `modules` to preserve
existing module settings; an empty object also leaves them unchanged. Its JSON response contains `message`, `contact` and
`assessment`. Authorization failures return 403 and invalid values return 422.
The existing web PUT and module PATCH routes remain available; non-JSON web PUT
submissions redirect to `/systemadmin/settings`.

The shared system-administration app reloads settings on page navigation and
uses the saved module state to decide which reference lists to load. Consumer
regression coverage should include authenticated readers without edit rights,
administrators and Gate overrides, guest access, each write path, invalid input,
session/CSRF and token authentication, refresh races, and changes made in
another session.

## Listing records

For a model that permits listing, for example:

```text
GET /api/v1/crud/Project?filter[id]=1&sort=-id&per_page=20&page=1
Authorization: Bearer <token>
Accept: application/json
```

List operations accept `filter[field]=value`, comma-separated `sort` (prefix a
field with `-` for descending order), comma-separated `include`, and
`fields[table]=field1,field2` (or `fields[_]`). Allowed filters include explicit
model filters and filters based on visible columns and visible relationship paths;
legacy `index` parameters such as `search` are not automatically available.
Without `per_page`, a list returns an array. With `per_page` (and optionally
`page`), it returns a pagination object with `data` and pagination metadata.

Field selection applies to the response, including requested appended attributes
and configured `customproperty_<id>` attributes. Unknown or disallowed fields
return HTTP 400. The API also selects the model key and any declared columns
needed for authorization or requested accessors; those internal columns are
not returned unless explicitly requested and allowed. When a model has not
declared its authorization dependencies, or an accessor's dependencies are
unknown, the API selects all database columns to preserve behavior. Omitting
`fields` retains the full existing response.

Models can opt into a narrower query by implementing
`getAuthorizationFields(): array` with every column used by their view and
access policies, and `getFieldDependencies(): array` mapping each supported
appended attribute to the database columns it needs. Declare an empty array
when no columns beyond the model key are needed. Include foreign keys used by
relations and eager loads. These internal dependencies do not expand the public
field allowlist. Review these declarations when policies or accessors change.

For `/api/v1/crud/User`, `filter[name_or_email]` searches names or e-mail
addresses, `filter[enabled]` filters by enabled state, and
`filter[customproperty_<id>]` filters non-text custom properties by their stored
option value. Reference IDs match both JSON numbers and strings. Boolean options
`0`/`false` and `1`/`true` match the corresponding JSON boolean, number or
numeric string. These filters are also available through TableModule's
`searchKeys: ["name", "email"]` and filter configuration.

For writes, use the create, item-update, delete, and custom-action operations
defined for the model in Swagger. The server applies authorization and validation;
do not assume that a field visible in a response is writable. See
[`routes/api.php`](../routes/api.php) and
[`app/Http/Controllers/Api/SwaggerController.php`](../app/Http/Controllers/Api/SwaggerController.php)
for the routing and specification implementation.

For existing records with configured custom properties, an update changes only
the `customproperty_<id>` fields included in the request. Omitted properties,
including required ones, retain their existing values. An explicitly supplied
`null` clears an optional property but fails validation for a required property;
other supplied values remain subject to the property's validation rules. This
also applies to the older `/api/v1/items` API. Creation retains its existing
validation behavior.

The separate consumer regression project should cover partial and full updates
through both APIs, including mass editing multiple records with different
unselected values, required properties, explicit null and false values, invalid
values, creation, and permission-denied records.

## Project participants

`Project` is the current CRUD model, including projects called "risk projects"
in older terminology. Use `/api/v1/crud/Project`; `RiskProject` is not a supported
model and `/api/v1/crud/RiskProject` returns 404.

The project responsible user can replace participants with:

```http
POST /api/v1/crud/Project/123/updateparticipants
Content-Type: application/json
Accept: application/json

{"users":[{"id":456,"readonly":false},{"id":789,"readonly":true}]}
```

`users` is required and is the **entire replacement list**, not additions.
Omitted participants are removed; `{"users":[]}` removes all participants.
Each entry requires an existing, unique user `id` and a `readonly` boolean
(`true` for read-only access, `false` for write access). Do not include the project
responsible user: that user's access is managed separately. Invalid input returns
422; callers other than the project responsible user receive 403.

## ControlAction sources

On both `POST /api/v1/crud/ControlAction` and
`PATCH /api/v1/crud/ControlAction/{id}`, optional `risk_id` and `finding_id` fields
link an existing risk or finding (observation), respectively. `incident_id` and
`objective_id` similarly link incidents and objectives. Each field takes **one
positive integer ID**, not an array. A control action may have zero or more
sources, including multiple risks or findings.

Links are additive: PATCH preserves existing sources. To attach several risks,
send `{"risk_id":123}` and then `{"risk_id":456}` in separate PATCH requests to
the same control action. Different types may be linked in one request, for
example `{"risk_id":123,"finding_id":789}`. Linking an already linked source does
not duplicate it. Omitting these fields leaves existing links unchanged; these
fields do not replace or remove sources. On create, include the normal required
fields (`name` and `control_id`) as well.

`origin` is a generated, localized, newline-separated display string in responses,
not writable free text. Risk links appear as RISK origins and finding links as
observation origins (labels depend on locale). Do not send `origin` or `risk_ids`:
they do not establish links. Unsupported fields may currently be silently ignored
even when the request returns 200; this documentation update does not introduce
strict unknown-field validation. A successful status alone does not confirm a
source link; retrieve the control action and check its generated `origin`.

## Reporting API bugs

Report reproducible API bugs and Swagger/documentation discrepancies through
[GitHub Issues](https://github.com/Ledningssystemet/ledningssystemet/issues).
AI agents should use the same channel; if they lack repository access, provide a
sanitized report to the user to submit instead of claiming an issue was created.

Include the environment and verification date, HTTP method and endpoint, a
minimal sanitized request body, HTTP status and relevant response, expected
behavior, and reproduction steps. For Swagger discrepancies, identify the
operation/schema and compare it with the actual request. Never publish API
tokens, cookies, credentials, personal data or production records.
