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

## Verification and formatting

Run `php tests\Feature\PasswordResetAccessTest.php` to check the registered password
reset routes for guests, regular users and system administrators with password
login enabled and disabled. This standalone regression check requires Composer
dependencies but does not access the database or send mail.

There is currently no PHPUnit configuration in the working repository.
Verify other changes in an isolated development environment,
including the workflows you changed, and document the verification in your
pull request. Use `vendor/bin/pint` for PHP formatting. If automated tests
are added, document their prerequisites and commands here.

See [Contributing](../CONTRIBUTING.md) for expectations when changing behavior
and [Architecture](architecture.md) for the main entry points.
