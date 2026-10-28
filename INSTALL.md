# Installing Ledningssystemet

This document covers **application prerequisites and deployment requirements**. It does not prescribe how to install or secure an operating system, database server, container runtime, TLS certificates, or reverse proxy. Arrange those according to your organization's infrastructure standards before deploying the application.

## Prerequisites

- A host capable of running the published image `ghcr.io/ledningssystemet/ledningssystemet:<tag>`, or an environment for building the repository's `Dockerfile`. The image serves HTTP on **container port 80** and includes PHP 8.5, Nginx, Redis, and the built frontend. CI publishes `latest` and a short commit-SHA tag from `main`; prefer a tested immutable image reference for production.
- A reachable **MariaDB or MySQL** database with a dedicated database and account permitted to create and modify tables. The default driver is `mariadb`. Ensure the database and application use compatible `utf8mb4` settings; the default collation is `utf8mb4_swedish_ci`.
- A public HTTPS URL with a reverse proxy forwarding requests to the container's HTTP port. Set `APP_URL` to that external URL; keep the database and container's HTTP port inaccessible from the public internet. Configure proxy trust, headers and request size for your deployment.
- Persistent, writable application `storage/` (including `storage/app`, `storage/framework` and `storage/logs`) and a securely stored application `.env`. In the image the application runs as `www-data` (UID/GID 33). Back up the database, storage and configuration together.
- A way to run the Laravel scheduler once a minute and, if an asynchronous queue driver is configured, a long-running queue worker. The supplied image starts cron for `schedule:run`; other deployment methods must provide their own scheduler.
- Working mail delivery if the installation needs password resets or email notifications. The default `MAIL_MAILER=log` writes messages to logs instead of sending them.

The repository's `docker-compose.yaml` is **not a portable production template**: it builds locally, mounts `docker-env`, and refers to a machine-specific Windows certificate path. Supply your own deployment configuration rather than using it unchanged.

## Application configuration

Start from [`.env.example`](.env.example) and supply deployment-specific values. Treat its example key and passwords as placeholders, **not secrets to reuse**. In particular:

| Setting | Purpose |
| --- | --- |
| `APP_KEY` | Generate a unique key with `php artisan key:generate --show` in the application environment; retain it across updates and restores. |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Use `production`, `false`, and the installation's external HTTPS URL in production. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Connection to the prepared database; `DB_CONNECTION` defaults to `mariadb`. `DATABASE_URL` can also be used. |
| `DB_SSLMODE`, `MYSQL_ATTR_SSL_CA`, `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` | Optional database TLS settings in `config/database.php`. If TLS is required, provide a trusted CA and explicitly enable certificate verification; do not assume the default verifies the server. |
| `QUEUE_CONNECTION` | Defaults to `sync`; with `database` or `redis`, arrange a worker and its backing service. |
| `MAIL_MAILER` and `MAIL_*` | Configure an actual mail transport when mail should be delivered. |

Keep `.env` outside public web access, restrict access to credentials, and mount it at `/var/www/html/.env` when using the image. If using the public filesystem disk, run `php artisan storage:link` with the deployed application files and persistent storage in place. Optional SSO, Graph and branding settings are defined in `config/ledningssystemet.php`.

## Deploy and verify

1. Prepare the database, storage, environment file, HTTPS routing, backups, and monitoring. Pin the application image to a tested tag or digest. Do not mount the repository's `.env.example` unchanged.
2. Deploy the application. **Run database migrations before serving traffic**: `php artisan migrate --force`. The supplied image runs migrations and caches routes, views and configuration on startup (`Dockerfile`); check its logs and database state before considering the deployment complete. A different runtime must arrange these steps itself.
3. If this is a new installation, create the first administrator interactively with `php artisan db:seed --class=CreateAdmin` and follow the password-reset instructions it prints. Do not rerun seeding blindly against an existing installation.
4. Verify that `/login` responds through the public HTTPS address, the container is healthy, a user can log in, and scheduled jobs and configured mail/queues work. Keep the application and database logs available for troubleshooting.

The image includes a health check for `http://localhost:80/login`. It does not by itself prove that mail delivery, scheduled work or all application features function correctly.

For upgrades, backups, restoration, and routine checks see [docs/operations.md](docs/operations.md). For local source development see [docs/development.md](docs/development.md).
