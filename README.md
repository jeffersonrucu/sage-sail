<p align="center"><img width="294" src="/art/logo.svg" alt="Sage Sail"></p>

## Introduction

Sage Sail provides a Docker powered local development experience for
[Roots Bedrock](https://roots.io/bedrock/) and [Roots Sage](https://roots.io/sage/),
compatible with macOS, Windows (WSL2), and Linux. Other than Docker, nothing needs to be
installed on your machine.

It is derived from [Laravel Sail](https://github.com/laravel/sail), adapted to the
WordPress stack: WP-CLI and Acorn instead of Artisan, Bedrock's `web/` document root,
and Bedrock's `.env` variable names.

Sage Sail targets Bedrock exclusively. A classic WordPress install, with the site at the
project root and settings in `wp-config.php`, is out of scope: `install` refuses to run
outside a Bedrock project rather than writing a compose file that could not serve it.

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

That single command takes the project from checked out to running. It writes a
`compose.yaml`, points your `.env` at the selected services, generates any missing
WordPress salts, builds the images, starts the containers, installs WordPress with
WP-CLI, and installs a Sage theme with its assets built. It finishes by printing your
site URL, admin credentials, and theme.

You are asked for the services, the administrator details, and the theme name, or you
can pass them:

```bash
./vendor/bin/sage-sail install \
    --with=mysql,redis,mailpit \
    --php=8.4 \
    --title="My Site" \
    --admin-user=admin \
    --admin-password=password \
    --admin-email=admin@example.com \
    --theme=sage \
    --no-interaction
```

Re-running `install` is safe: an already installed WordPress is detected and left alone,
and an existing theme directory is never overwritten.

### The Sage theme

`install` creates the theme with `composer create-project roots/sage`, activates it, and
runs `npm install && npm run build` inside the container, so the site renders styled on
the first request. Leave the theme prompt empty to skip it.

Afterwards, work on it through the container:

```bash
./vendor/bin/sage-sail acorn optimize:clear
./vendor/bin/sage-sail run bash -c 'cd web/app/themes/sage && npm run dev'
```

| Option | Effect |
| --- | --- |
| `--with=mysql,redis` | Services to install. `--with=none` installs no services |
| `--php=8.4` | PHP version, one of `8.2`, `8.3`, `8.4`, `8.5` |
| `--title`, `--admin-user`, `--admin-password`, `--admin-email` | WordPress administrator details |
| `--theme=sage` | Sage theme to install into `web/app/themes`. Omitted without interaction, no theme is installed |
| `--devcontainer` | Also write a `.devcontainer` directory |
| `--no-build` | Do not pull or build the images |
| `--no-start` | Do not start the containers or install WordPress |

Use `--no-build --no-start` to only write the scaffolding.

### Site URL

The site is published on `APP_PORT`, which defaults to `80`. Set it in `.env` before
installing and `WP_HOME` is written to match:

```dotenv
APP_PORT=8080
```

### Port conflicts

Every service publishes a host port, so a second project already using one makes the
containers fail to start. Remap them in `.env`:

```dotenv
APP_PORT=8087
VITE_PORT=5174
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
FORWARD_MAILPIT_PORT=1026
FORWARD_MAILPIT_DASHBOARD_PORT=8026
```

Each service stub reads a `FORWARD_*` variable named after it. Note that `mysql` and
`mariadb` share `FORWARD_DB_PORT`, and `minio` and `rustfs` both default to `9000`, so
installing either pair together needs one of them remapped.

## The application container

The image serves your site with **nginx** and **PHP-FPM** under supervisord, and ships
**WP-CLI**, Composer, Node, npm, pnpm, yarn, and bun.

The document root is Bedrock's `web/` directory, resolved from the `WEB_ROOT` environment
variable that `compose.yaml` passes in. Override it if you renamed that directory:

```yaml
environment:
    WEB_ROOT: /var/www/html/public
```

Nginx falls back to `index.php`, so WordPress pretty permalinks work out of the box.
PHP-FPM runs as the `sage` user, remapped to your host UID so files written inside the
container stay editable outside it.

Xdebug is installed but off by default. Enable it by setting `SAGE_SAIL_XDEBUG_MODE` in
your `.env`:

```dotenv
SAGE_SAIL_XDEBUG_MODE=develop,debug
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

### Mail

WordPress has no native SMTP support, so selecting `mailpit` also installs
`web/app/mu-plugins/sage-sail-mailer.php`, which points PHPMailer at the mail catcher on
the `phpmailer_init` hook. Read the caught mail at http://localhost:8025.

It also rewrites the sender address when the site domain has no dot, since WordPress
derives it from the site host and PHPMailer rejects `wordpress@localhost` outright.
Addresses with a real domain are left untouched.

The plugin only runs inside the Sage Sail container: it bails out unless `SAGE_SAIL` is
present in the environment, which `compose.yaml` injects and nothing else does. Deploying
the file therefore changes nothing, even if the target defines `SMTP_HOST` for a real mail
relay. An existing file is never overwritten.

Every other variable above is only written to `.env` — wiring those into WordPress is up
to your project configuration or plugins.

## Customization

To take ownership of the Docker files, publish them into your project:

```bash
./vendor/bin/sage-sail publish
```

The runtimes and database scripts are copied to `docker/`, and `compose.yaml` is
rewritten to build from there.

## Credits

Sage Sail is derived from [Laravel Sail](https://github.com/laravel/sail) by
[Taylor Otwell](https://github.com/taylorotwell), which is itself derived from
[Vessel](https://github.com/shipping-docker/vessel) by
[Chris Fidao](https://github.com/fideloper).

## License

Sage Sail is open-sourced software licensed under the [MIT license](LICENSE.md).
