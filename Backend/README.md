# Lamma Backend

Laravel 12 REST API foundation for the Lamma beauty commerce platform.

## Requirements

- PHP 8.2+
- Composer 2+
- MySQL 8+ (production)
- Required PHP extensions enabled by Laravel and the installed packages

## Setup

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Set MySQL values in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
FILESYSTEM_DISK=public
QUEUE_CONNECTION=redis
```

Ensure Redis is running and `ext-redis` is enabled (`REDIS_HOST`/`REDIS_PORT` in `.env`).

Then run:

```bash
php artisan migrate
php artisan storage:link
php artisan queue:work redis --queue=notifications,search --tries=3
php artisan serve
```

Queues:
- `notifications` — FCM push (`SendPushNotificationJob`)
- `search` — catalog sync (`SyncProductToSearchJob`) and image embeddings (`GenerateImageEmbeddingJob`)

Health check: `GET /api/v1/health`.

## Sprint 1 admin API

Authentication uses Sanctum bearer tokens and Laratrust is the only active authorization source.

```text
POST  /api/v1/admin/login
POST  /api/v1/admin/logout
GET   /api/v1/admin/profile
PATCH /api/v1/admin/profile
PUT   /api/v1/admin/password
GET|POST|PATCH /api/v1/admin/users
PATCH /api/v1/admin/users/{user}/activate
PATCH /api/v1/admin/users/{user}/deactivate
PUT   /api/v1/admin/users/{user}/roles
GET|POST|PATCH /api/v1/admin/roles
PUT   /api/v1/admin/roles/{role}/permissions
GET   /api/v1/admin/permissions
```

All management endpoints enforce Laratrust permissions server-side. Run `php artisan db:seed` to install the deterministic initial roles and Sprint 1 permissions. Bootstrap the first production administrator interactively with:

```bash
php artisan app:create-super-admin
```

The command never prints or stores the password in source code or logs.

## Quality checks

```bash
php artisan about
php artisan route:list
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
npm run build
```

Tests use SQLite in memory and do not require a local MySQL server. Production and deployment configuration remains MySQL-based.

Sprint 0 regression tests and Sprint 1 authentication/authorization tests run together with `php artisan test`.

## Sprint 3 admin UI

The server-rendered panel under `/admin` uses the local Velzon template as its component reference. Arabic RTL is enabled with the copied `bootstrap-rtl.min.css` and `app-rtl.min.css` runtime assets, while project-specific styling and behavior are built through Laravel Vite.

The panel includes login, dashboard, user/role/permission management, profile/password flows, and all Sprint 2 Master Data screens. Laratrust controls both route authorization and action/navigation visibility. See `docs/sprint-3.md` for discovery, asset mapping, exclusions, and acceptance notes.

## Architecture

See `docs/architecture.md` for DDD boundaries, dependency direction, and code conventions. Sprint 0 intentionally does not implement business modules.

## Deployment

See `docs/cpanel-deployment.md`. cPanel deployment uses a public document root of `public/`, database queues, and cron-driven scheduling; Redis, Docker, and Supervisor are not required.
