# Deployment Runbook (cPanel)

Sprint 19 hardening artifact. Extends `docs/cpanel-deployment.md` (baseline
environment rules) with a step-by-step release procedure. Consumed by
Sprint 20 (UAT & cPanel Staging) for the actual staging deployment, which
this sprint cannot execute directly — no real cPanel hosting is reachable
from this development/CI environment.

## Pre-conditions

- CI quality gate (`.github/workflows/main.yml` job `quality-gate`) is green
  on the commit being deployed: tests, Pint, PHPStan, frontend build.
- `php artisan app:verify-production-config` passes against the target
  `.env` (run manually before cutting the release — it deliberately does not
  run automatically at request time, see command source).
- A fresh backup exists (see `docs/backup-restore-runbook.md`) taken
  immediately before this deployment.

## Steps

1. **Backup first.** Run `backend/scripts/backup.sh` (or the cPanel-cron
   equivalent) and confirm the archive was written outside the public web
   root.
2. **Maintenance window (only for risky schema changes).**
   `php artisan down --secret="<one-time-token>"` — skip for routine,
   backward-compatible deploys to avoid unnecessary downtime.
3. **Upload code.** Deploy only the `Backend/` application contents (not
   `Template-Velzon/` raw template sources, not `node_modules/`, not local
   IDE/cache files). The CI `FTP-Deploy-Action` job already scopes
   `local-dir: Backend/`.
4. **Install PHP dependencies.**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
   Do NOT run `composer update` during deployment.
5. **Environment.** Confirm `.env` has the correct production values, at
   minimum: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`,
   `SESSION_SECURE_COOKIE=true`, real `DB_*` credentials,
   `QUEUE_CONNECTION=redis`, Redis reachable (`REDIS_HOST`/`REDIS_PORT`),
   `FILESYSTEM_DISK=public`,
   `CORS_ALLOWED_ORIGINS` set to the real admin origin(s). For FCM, set
   `FCM_ENABLED=true`, the non-production Firebase project in staging, and a
   server-private `FCM_CREDENTIALS_PATH`; never place its service-account JSON
   below `public_html`, public storage, source control, or Blade views. The
   server PHP runtime must have the `sodium` extension enabled for the Firebase
   SDK's JWT dependency, and `ext-redis` (or predis) for the queue driver.
6. **Migrate.**
   ```bash
   php artisan migrate --force
   ```
   Never edit already-applied migrations; ship a new migration for any fix.
7. **Storage link.**
   ```bash
   php artisan storage:link
   ```
   (or the hosting-compatible equivalent if symlinks are restricted).
8. **Rebuild caches** (only once `.env` is final and correct):
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
9. **Frontend assets.**
   ```bash
   npm install
   npm run build
   ```
10. **Queue / scheduler.** Confirm the cPanel cron entries are active (replace
    the paths with the hosting account's actual absolute paths):
    ```cron
    * * * * * cd /home/account/lamma && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
    * * * * * cd /home/account/lamma && /usr/local/bin/php artisan queue:work redis --queue=notifications,search --sleep=3 --tries=3 --max-time=55 >> /dev/null 2>&1
    ```
    Processes FCM (`notifications`) and catalog search indexing (`search`) on Redis only (database queue removed).
    Inspect failed work with `php artisan queue:failed`, retry a selected
    failure with `php artisan queue:retry <id>`, and requeue a bounded stale
    notification set with `php artisan notifications:replay-pending`. The
    latter command is the recovery path for a process failure after a
    notification transaction commits but before its after-commit job runs.
11. **Permissions.** Verify `storage/` and `bootstrap/cache/` are writable
    without `chmod 777`.
12. **Health verification.**
    - `GET /up` (Laravel health route) responds.
    - Login page loads, an authorized user can sign in.
    - `curl -I` the login page and confirm the Sprint 19 security headers
      are present (`X-Content-Type-Options`, `X-Frame-Options`,
      `Content-Security-Policy`, `Referrer-Policy`).
    - A representative admin list page (Products, Orders) loads without
      errors.
    - Trigger a deliberate 404/expected error and confirm `APP_DEBUG=false`
      hides stack traces from the response.
13. **Exit maintenance mode** if it was enabled: `php artisan up`.

## Rollback

- **Application code / assets:** redeploy the previous known-good commit
  through the same pipeline (FTP deploy is a full overwrite; keep the
  previous release's files retrievable, e.g. via git tag, until the new
  release is confirmed healthy).
- **Configuration:** keep the previous `.env` backed up before editing;
  restore it if the new configuration causes a boot failure.
- **Database migrations:** prefer backward-compatible, additive migrations
  so rollback never requires a destructive down-migration against
  production data. If a migration must be reverted, restore from the
  pre-deploy backup (see `docs/backup-restore-runbook.md`) rather than
  running `migrate:rollback` against live financial/inventory data.

## Known gap (tracked, not closed by this Sprint)

Executing this runbook against a real cPanel account, and rehearsing the
rollback path there, requires a real hosting environment not available in
this development/CI session. This is explicitly deferred to Sprint 20
(UAT & cPanel Staging) per `.ai/Sprints/sprint_19.md` / `sprint_20.md`.
