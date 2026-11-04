# Contributing

Contributions are welcome. Open an issue or pull request with a clear description
of the problem and the intended change. Keep changes focused, verify behavior
changes, and update the relevant documentation when workflows or APIs change.

Local test cases may be created in `tests/`, but must not be tracked by Git or
included in commits or pull requests. Keep this directory's contents ignored;
do not force-add tests. Shared regression tests live in a separate project that
uses this system as its base through Composer and npm. Describe relevant test
scenarios and verification results when contributing behavior changes.

Changes must work both in this application and in applications that consume
its Composer and npm packages. Register package functionality through
`src/LedningssystemetServiceProvider.php`, including Artisan commands and their
schedules, rather than relying solely on this repository's local bootstrap.
See [AGENTS.md](AGENTS.md) for coding-agent instructions and package integration
expectations.

See [local development](docs/development.md) for setup, tests and formatting,
and [architecture](docs/architecture.md) for the main code locations. Never
commit real credentials, deployment `.env` files, production data or backups.
