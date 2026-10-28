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
