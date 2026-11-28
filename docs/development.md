# Local development

## Prerequisites

- PHP 8.5 with the extensions required by `composer.json` and the application
  (including PDO for MariaDB/MySQL), plus Composer.
- Node.js and npm for the Vite frontend (`Dockerfile` builds with Node.js 26).
- An isolated MariaDB/MySQL database for development. Do not point local scripts
  or tests at a production database.

The root `docker-compose.yaml` is specific to one developer's machine (including
a Windows certificate mount and `docker-env`), so it is not a ready-made local
stack for other machines. You can instead run the application directly with your
own database, or adapt a private Compose configuration to your environment.

## Setup

1. Run `composer install` and `npm ci` in the repository root.
2. Copy `.env.example` to `.env`. Replace its placeholder database credentials,
   external URL and **dummy** `APP_KEY` with local values. For local work set
   `APP_ENV=local`, `APP_DEBUG=true`, and an appropriate `APP_URL` (for example
   `http://localhost:8010`). Keep `.env` out of version control.
3. Generate an application key with `php artisan key:generate` and run
   `php artisan migrate` against the isolated database.
4. Start the server with `npm run dev:app` and Vite with `npm run dev:frontend`
   in separate terminals. Alternatively, `npm run dev` also starts a queue worker
   for the `high`, `default`, and `low` queues. The server script uses port 8010.

Use `npm run build` to build production frontend assets. Run the scheduler
locally with `php artisan schedule:run` when testing scheduled tasks.

## Tailwind theme

The shared Tailwind theme is configured in `tailwind.config.ts` and
`../resources/css/tailwind.css`. After changing the theme or Tailwind classes,
run `npm run build:css` to regenerate the stylesheet imported by the table and
authentication components. The semantic colors, including primary, background,
foreground and border, apply to both. The muted text color is slightly darker than
the brand reference to maintain contrast on the light background.

The theme also defines `brand-navy`, `brand-navy-deep`, `brand-navy-ink`,
`brand-teal`, `brand-teal-soft`, `brand-teal-dark`, `on-navy` and
`on-navy-muted` colors, `bg-brand` and `bg-teal` gradients, `shadow-card`, and
the `font-brand` and `font-heading` font stacks. The named fonts Instrument Sans
and Quicksand are provided by the `@fontsource-variable` npm dependencies and
loaded from the generated stylesheet. These stacks fall back to system fonts if
the font assets cannot be loaded. The generated stylesheet includes classes found in this
repository's configured content paths; host-specific classes require a build
that also scans the host's sources.

In the separate consumer regression project, check the table, dialogs, primary
actions and authentication screens on light backgrounds, including focus and
hover states and the generated stylesheet when used from the npm package.
Authentication uses its own Tailwind class definitions rather than importing
table styles. Also check password and SSO login, password reset, two-factor
authentication and MFA enrollment, including checkbox and disabled states.

## Verification and formatting

Test cases may be created and run locally in `tests/`, but must remain
untracked and must not be committed or force-added. Keep the directory's
contents ignored by Git. Shared regression testing takes place in a separate
project that consumes this application as its base through Composer and npm.
The local checks described below do not replace that regression suite.

Run `php tests\Feature\PasswordResetAccessTest.php` to check the registered password
reset routes for guests, regular users and system administrators with password
login enabled and disabled. This standalone regression check requires Composer
dependencies but does not access the database or send mail.

There is currently no PHPUnit configuration in the working repository.
Run `php tests\Feature\SwaggerDocumentationTest.php` to generate the Swagger
specification and check project participant payloads, ControlAction source fields,
read-only origin metadata and reporting guidance. This standalone check requires
Composer dependencies but does not load `.env` or access a database.

Verify other changes in an isolated development environment,
including the workflows you changed, and document the verification in your
pull request. Use `vendor/bin/pint` for PHP formatting. When using local automated
tests, report their prerequisites, commands and results without committing the
test files. For package integration changes, also verify the affected behavior
in a consuming application; report explicitly if that verification was not run.

See [Contributing](../CONTRIBUTING.md) for expectations when changing behavior
and [Architecture](architecture.md) for the main entry points.
