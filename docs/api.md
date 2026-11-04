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

For writes, use the create, item-update, delete, and custom-action operations
defined for the model in Swagger. The server applies authorization and validation;
do not assume that a field visible in a response is writable. See
[`routes/api.php`](../routes/api.php) and
[`app/Http/Controllers/Api/SwaggerController.php`](../app/Http/Controllers/Api/SwaggerController.php)
for the routing and specification implementation.

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
