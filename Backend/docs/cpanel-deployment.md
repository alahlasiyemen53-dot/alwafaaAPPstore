# cPanel Deployment Notes

- Point the domain document root to `Backend/public` (or the equivalent absolute path).
- Keep `.env`, `app`, `config`, `database`, `storage`, and `vendor` outside the public document root.
- Use `APP_ENV=production` and `APP_DEBUG=false`.
- Configure MySQL credentials only through `.env`.
- Use `FILESYSTEM_DISK=public` and run `php artisan storage:link` where symlinks are supported.
- Ensure `storage/` and `bootstrap/cache/` are writable without using `777` by default.
- Use `QUEUE_CONNECTION=redis` with a reachable Redis server (`ext-redis` or predis). The database queue driver is disabled — do not set `QUEUE_CONNECTION=database`. Process jobs with a cPanel cron every minute:
  `php artisan queue:work redis --queue=notifications,search --sleep=3 --tries=3 --max-time=55`.
- Run Laravel Scheduler from a cron entry every minute: `php artisan schedule:run`.
- Supervisor is optional; Redis is required for all queued work.
- After the final `.env` is present, run `php artisan optimize` (and only cache routes if the route set supports it).
- Back up the MySQL database, `storage/app/public`, and the production `.env` securely.
- Require HTTPS in production.
