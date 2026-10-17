# Backup & Restore Runbook (cPanel)

Sprint 19 hardening artifact. `docs/cpanel-deployment.md` previously only
noted "back up the database, media, and `.env`" with no procedure. This
document defines the actual policy, commands, and restore steps.

## What must be backed up

1. MySQL database (all business data: catalog, inventory, orders, finance,
   treasury, loyalty, wallet, audit logs).
2. `storage/app/public` (product/variant media — the local public disk).
3. The production `.env` file (stored securely, encrypted at rest,
   **never** inside the public web root or in version control).

## Frequency and retention (proposed policy — confirm with project owner
before production launch)

- **Database:** daily full logical dump via cron, retained 14 days rolling
  + 1 monthly snapshot retained 6 months.
- **Media (`storage/app/public`):** daily incremental (rsync-style) or
  daily full archive if the media volume is small; same retention as above.
- **`.env` / deployment configuration:** backed up on every change, stored
  in a secrets-appropriate location (not plain object storage next to
  media), retained indefinitely with access limited to authorized
  operators.
- **Storage location:** outside the public web root, ideally off-server
  (e.g. downloaded off the cPanel account on a schedule, or pushed to a
  private remote storage target) so a single compromised/lost account does
  not destroy both the live data and its only backup copy.

## Backup commands

Use `backend/scripts/backup.sh` (cron-triggered on cPanel) which wraps the
following:

```bash
# Database (adjust credentials/host to the production values)
mysqldump --single-transaction --quick --routines --triggers \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip > "$BACKUP_DIR/db-$(date +%Y%m%d-%H%M%S).sql.gz"

# Media
tar -czf "$BACKUP_DIR/media-$(date +%Y%m%d-%H%M%S).tar.gz" storage/app/public
```

`--single-transaction` keeps the dump MySQL-consistent for InnoDB tables
without locking the whole database, matching the project's InnoDB-only
requirement for transactional tables — never rely on copying live
`ibdata`/`.ibd` files directly.

## Restore procedure

1. Provision a target environment (staging, or a recovery instance) with
   the same PHP/MySQL versions as production.
2. Restore the database:
   ```bash
   gunzip < db-YYYYMMDD-HHMMSS.sql.gz | mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"
   ```
3. Restore media:
   ```bash
   tar -xzf media-YYYYMMDD-HHMMSS.tar.gz -C /path/to/backend/storage/app/public --strip-components=3
   ```
   (adjust `--strip-components` to the actual archive path depth).
4. Restore/re-create `.env` from its secure backup location; run
   `php artisan storage:link`.
5. Run `php artisan migrate:status` to confirm the restored schema matches
   the expected migration history (do not run new migrations blindly
   against a restored snapshot without checking status first).
6. Run the project's reconciliation tooling to confirm integrity post
   restore, at minimum:
   ```bash
   php artisan inventory:reconcile
   ```
   plus the Trial Balance / Treasury / COD / Loyalty / Wallet reconciliation
   checks already established in Sprints 13–18 (see their respective
   completion reports for the exact commands/reports).
7. Confirm the application boots and a representative login succeeds.

## Known gap (tracked, not closed by this Sprint)

An actual restore rehearsal against a real MySQL/cPanel target, and a real
`composer audit` / `npm audit` run in that environment, require
infrastructure not available in this development/CI session. Both are
explicitly deferred to Sprint 20 (UAT & cPanel Staging), which owns
executing this runbook end-to-end and recording the result. `composer.json`
now exposes a `security-check` script (`composer run security-check`) so
this can be run manually or wired into CI once a suitable environment is
available.
