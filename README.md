<p align="center"><img width="294" src="/art/logo.svg" alt="Sage Sail"></p>

## Introduction

Sage Sail provides a Docker powered local development experience for
[Roots Bedrock](https://roots.io/bedrock/) and [Roots Sage](https://roots.io/sage/),
compatible with macOS, Windows (WSL2), and Linux. Other than Docker, nothing needs to be
installed on your machine.

It is derived from [Laravel Sail](https://github.com/laravel/sail), adapted to the
WordPress stack: WP-CLI and Acorn instead of Artisan, Bedrock's `web/` document root,
and Bedrock's `.env` variable names.

> **Status:** the CLI, the Compose scaffolding, and the environment configuration are in
> place. The runtime images are still being adapted for WordPress (nginx + PHP-FPM +
> WP-CLI), so `up` does not serve the site yet. See [Roadmap](#roadmap).

## Requirements

- PHP 8.2+ on the host (only to run the installer)
- Docker
- A Bedrock project

## Installation

From the root of your Bedrock project:

```bash
composer require jeffersonrucu/sage-sail --dev
./vendor/bin/sage-sail install
```

The installer writes a `compose.yaml`, points your `.env` at the selected services, and
generates any missing WordPress salts.

Pick services non-interactively with `--with`, and the PHP version with `--php`:

```bash
./vendor/bin/sage-sail install --with=mysql,redis,mailpit --php=8.4 --no-interaction
```

Then start the containers:

```bash
./vendor/bin/sage-sail up -d
```

## Usage

```bash
./vendor/bin/sage-sail up -d          # start in the background
./vendor/bin/sage-sail wp plugin list # run a WP-CLI command
./vendor/bin/sage-sail acorn optimize:clear
./vendor/bin/sage-sail composer install
./vendor/bin/sage-sail npm run build
./vendor/bin/sage-sail shell
./vendor/bin/sage-sail down
```

Run `./vendor/bin/sage-sail help` for the full command list. Any unrecognized command is
passed straight through to `docker compose`.

## Available services

WordPress only supports MySQL and MariaDB, so no other database is offered.

`mysql`, `mariadb`, `redis`, `valkey`, `memcached`, `meilisearch`, `typesense`, `minio`,
`rustfs`, `mailpit`, `rabbitmq`, `selenium`, `soketi`

Add one to an existing installation:

```bash
./vendor/bin/sage-sail add valkey
```

## Environment variables

The installer writes the variables below into your `.env`. Existing definitions are
replaced in place, and commented-out ones are uncommented.

| Service | Variables |
| --- | --- |
| `mysql` / `mariadb` | `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` |
| `redis` / `valkey` | `WP_REDIS_HOST`, `WP_REDIS_PORT` |
| `memcached` | `MEMCACHED_HOST`, `MEMCACHED_PORT` |
| `mailpit` | `SMTP_HOST`, `SMTP_PORT` |
| `meilisearch` | `MEILISEARCH_HOST` |
| `typesense` | `TYPESENSE_HOST`, `TYPESENSE_PORT`, `TYPESENSE_PROTOCOL`, `TYPESENSE_API_KEY` |
| `minio` / `rustfs` | `S3_ENDPOINT`, `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY`, `S3_USE_PATH_STYLE_ENDPOINT` |
| `rabbitmq` | `RABBITMQ_HOST`, `RABBITMQ_PORT` |
| `soketi` | `PUSHER_HOST`, `PUSHER_PORT`, `PUSHER_SCHEME`, `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET` |

Sage Sail only writes these values; wiring them into WordPress is up to your project
configuration or plugins.

## Customization

To take ownership of the Docker files, publish them into your project:

```bash
./vendor/bin/sage-sail publish
```

The runtimes and database scripts are copied to `docker/`, and `compose.yaml` is
rewritten to build from there.

## Roadmap

- [x] CLI, Compose scaffolding, and Bedrock environment configuration
- [ ] Runtime images for WordPress: nginx + PHP-FPM + WP-CLI, serving Bedrock's `web/`
- [ ] Integration test booting a real Bedrock site in CI
- [ ] Support for a plain WordPress layout (`wp-content/themes/<theme>`)

## Credits

Sage Sail is derived from [Laravel Sail](https://github.com/laravel/sail) by
[Taylor Otwell](https://github.com/taylorotwell), which is itself derived from
[Vessel](https://github.com/shipping-docker/vessel) by
[Chris Fidao](https://github.com/fideloper).

## License

Sage Sail is open-sourced software licensed under the [MIT license](LICENSE.md).
