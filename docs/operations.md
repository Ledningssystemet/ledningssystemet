# Operations

This document covers application operations, not the setup or hardening of the
underlying host, database or proxy. See [installation prerequisites](../INSTALL.md)
before first deployment.

## Container image publishing

GitHub Actions builds the root `Dockerfile` and publishes images to GitHub
Container Registry on pushes to these branches (including merged pull requests):

| Branch | Workflow | Image |
| --- | --- | --- |
| `main` | `.github/workflows/main.yml` | `ghcr.io/ledningssystemet/ledningssystemet` |
| `stage` | `.github/workflows/stage.yml` | `ghcr.io/ledningssystemet/ledningssystemet_stage` |

Both images receive a `latest` tag and a seven-character commit SHA tag. Publishing
uses the workflow's `GITHUB_TOKEN` with `packages: write`; no separate registry
secret is required. If the package already exists, ensure this repository has
Actions write access to it in the package settings. The stage workflow must be
present on `stage` to run. These workflows publish images only; they do not deploy
containers to a running environment.

## Upgrades

1. Test the target image or source revision in a staging environment, including
   any database migrations. Back up the current database, persistent `storage/`,
   `.env` and deployment configuration, and test that the backups are restorable.
2. Deploy a tested image tag/digest. Ensure `php artisan migrate --force` has
   completed before serving traffic. The supplied Docker image runs migrations
   and builds Laravel caches on startup; inspect its logs for errors. If deploying
   another way, run migrations and refresh caches as appropriate for that runtime.
3. Verify `/login`, login and a representative application workflow. Inspect
   application logs and confirm that the scheduler, queue (if used) and mail work.

Rolling back only the container or source code **does not undo database
migrations**. Plan for a compatible rollback or a tested data restoration; do
not restore a production backup over current data without an approved recovery
procedure and a clear understanding of what will be lost.

## Scheduled and background work

Laravel's `schedule:run` must execute every minute. The supplied image sets up
cron for this; external deployments must arrange equivalent execution. Current
scheduled work in `routes/console.php` includes hourly Graph synchronization,
daily risk reassessment, recurring project activities, status mail and expired
API-token purging. Scheduling uses the application's configured timezone.

With `QUEUE_CONNECTION=sync` jobs execute inline. If switching to an asynchronous
driver, provision its backend and run workers for the queues you use. The local
`npm run dev` script starts a worker for `high,default,low`, but that script is
**not** a production process supervisor. Restart workers after deployments.

## Backups and monitoring

Back up the **database and persistent `storage/` together**, as well as `.env`
and the deployment configuration. Secure the backups separately from the live
installation; protect `APP_KEY` and other secrets. Regularly test a full restore
in an isolated environment. Include any external services or certificate
material required by your own infrastructure.

Monitor availability of `/login`, application and database logs, storage space,
backup success, scheduler execution, queue failures (if applicable), mail
delivery and TLS certificate expiry. The image's health check only tests the
local login endpoint; it does not replace these checks.

For application-level troubleshooting, inspect `storage/logs`, container/startup
logs, database connectivity and configured `.env` values. If environment
configuration is changed in the supplied image, recreate the container so
Laravel's cached configuration is rebuilt.
