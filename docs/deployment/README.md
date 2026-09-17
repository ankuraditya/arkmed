# ARK med deployment runbook

## Required services

- PHP 8.2+, Composer, Node.js 20+, a supported SQL database, HTTPS, and a process manager.
- A private persistent volume for `backend/storage/app/prescriptions` and public persistent storage for `backend/storage/app/public`.
- A queue worker is recommended when queued jobs are introduced. The scheduler is required now.

## Release procedure

1. Take and verify a database and storage backup.
2. Put the API into maintenance mode: `php artisan down --retry=60`.
3. Install locked PHP packages with `composer install --no-dev --classmap-authoritative`.
4. Install locked frontend packages with `npm ci`, then run `npm run check`.
5. Set production secrets in `backend/.env`. Never copy the development application key into production.
6. Create private writable `storage/app/prescriptions` and `storage/app/imports` directories.
7. Run `php artisan migrate --force` and `php artisan storage:link` from `backend/`.
8. Run `php artisan app:production-check`; do not continue if it fails.
9. Cache Laravel configuration, events, routes and views with `php artisan optimize`.
10. Restart PHP and the queue worker, then run `php artisan up`.
11. Confirm `/up`, `/api/v1/health/ready`, `/robots.txt`, and `/sitemap.xml` return successfully.
12. Place frontend `dist/` behind HTTPS and route non-file requests to `index.html`.
13. Complete the smoke tests in `docs/deployment/LAUNCH_CHECKLIST.md`.

Run Laravel's scheduler every minute:

```cron
* * * * * cd /srv/arkmed/backend && php artisan schedule:run >> /dev/null 2>&1
```

Run the queue worker under a process supervisor:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## Environment checklist

- `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL`, and a unique `APP_KEY`.
- Set database credentials, `WHATSAPP_NUMBER`, mail settings, and the frontend `VITE_API_BASE_URL`.
- Redirect HTTP to HTTPS. Retain the application-added security headers at the proxy.
- Ensure only the web server and application user can read prescription storage.
- Configure log rotation, uptime checks, database alerts, and sufficient disk-space alerts.
- Use `LOG_STACK=daily`, `LOG_LEVEL=warning`, and an error-reporting integration in production.
- Monitor both `/up` for process liveness and `/api/v1/health/ready` for database/storage readiness.
- Alert on HTTP 5xx rate, queue failures, readiness failures, disk usage and backup failures.

## Web-server requirements

- Serve `dist/assets` with immutable one-year caching; do not apply immutable caching to `index.html` or `sw.js`.
- Serve `index.html`, `sw.js` and `manifest.webmanifest` with revalidation enabled.
- Set the maximum request body above 20 MB plus multipart overhead (25 MB minimum).
- Preserve client IP forwarding only from trusted proxies.
- Never expose `backend/storage`, `.env`, logs, imports, prescriptions or database files.
- Keep the API and frontend on explicitly configured origins; do not use wildcard CORS.

## Rollback

Re-deploy the preceding application artifact. Roll back database migrations only when the migration is explicitly known to be backward-safe; otherwise restore a verified pre-release backup and application artifact together.

Record the deployed commit, migration status, backup identifier, operator and timestamps for every release and rollback.
