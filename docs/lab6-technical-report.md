# Laboratory Activity 6 Technical Report

## Architecture Overview

Laboratory Activity 6 extends PageTurner from a transactional bookstore into a data-management and operations platform. The implementation keeps the existing Laravel 12 application structure and adds production-oriented capabilities around import/export workflows, automated backups, audit logging, API resilience, and operational monitoring. The design goal is to satisfy enterprise data portability requirements without disrupting the customer catalog, checkout, Lab 7 scalability work, or Lab 8 AI features already present in the project.

The main administrative surface is the data-management dashboard at `/admin/data-management`. It centralizes book and user imports, book/order/user exports, financial reports, manual backups, recent import/export logs, backup monitoring, audit summaries, API usage statistics, and system health metrics. Customer-facing portability is available from the customer dashboard through personal data, order history, and reading history exports.

## Import And Export Strategy

Book and user imports use Laravel Excel through `maatwebsite/excel`. The import classes implement chunked processing, batch inserts, validation, heading-row parsing, queued execution, and failure collection. Books are processed in chunks of 1000 rows and batches of 1000 inserts, matching the laboratory requirement for large files. User imports use the same chunk and batch sizes for predictable memory behavior.

Book imports require the assignment headers: `isbn`, `title`, `author`, `price`, `stock`, `category`, and `description`. The controller validates headers before queueing the import. If a required header is missing, the import is recorded as failed in `import_logs`, a JSONL failure report is created, and the admin receives a validation error. This prevents large malformed uploads from entering the queue with unclear row-level failures. ISBN is required and validated as ISBN-10 or ISBN-13 style input. Duplicate ISBN handling remains configurable: admins can skip duplicates and receive a row failure, or update the existing book when `update_existing` is selected.

Exports use `FromQuery` where the export maps directly to database records. Book exports support category, price range, stock status, date range, format selection, and custom columns. Order exports support status, customer, date range, CSV/XLSX/PDF output, and column selection. User exports support role filtering, verified-only filtering, custom columns, and PII redaction for email and phone. Export requests are written to `export_logs`; small exports complete synchronously and large exports can queue with `CompleteExportLog` updating the final path and row count.

Financial reports were added as explicit Lab 6 evidence. Admins can select `revenue_summary` or `tax_report` from the order export panel. Revenue summaries group completed orders by date and report order count and revenue. Tax reports group completed orders by date and report taxable revenue and stored tax amount. These reports produce CSV/XLSX through Laravel Excel and PDF through DomPDF.

Customer data portability is implemented through JSON personal-data export, customer-scoped order history export, and reading-history export. Download authorization is centralized through the existing export download route: admins can download all exports, while customers can only download exports they own.

## Backup And Maintenance Strategy

Backups use `spatie/laravel-backup`. The backup configuration includes application code, configuration, routes, resources, database files, public book-cover storage, and the environment file while excluding vendor dependencies, node modules, import working files, and export working files. Backups support local storage and an optional secondary disk through `BACKUP_SECONDARY_DISK`. Archive encryption is enabled when `BACKUP_ARCHIVE_PASSWORD` is configured. Notification email is controlled through `BACKUP_NOTIFICATION_EMAIL`.

The wrapper command `app:backup-run {scope}` records monitoring evidence before and after the underlying Spatie backup command. Supported scope labels are `daily`, `weekly`, and `manual`; tests can use `--dry-run` to verify monitoring without creating a real archive. The admin manual backup button calls this wrapper with the `manual` scope. The command writes to both `backup_monitoring` and `scheduled_task_logs`, so backup evidence is available in the dashboard and in scheduler records.

The scheduler defines daily backups at 02:00, weekly full backups on Sunday at 02:30, cleanup at 03:00, monitoring at 03:15, and a weekly summary at 04:00 Sunday. Maintenance commands cover pending order cleanup, session cleanup, log rotation, daily sales reports, notification pruning, audit archival, system health checks, and Lab 7 materialized view refreshes. Every scheduled entry uses `withoutOverlapping()` and registers success/failure hooks that write scheduled task evidence.

The server cron entry remains:

```cron
* * * * * cd /path/to/pageturner-bookstore && php artisan schedule:run >> /dev/null 2>&1
```

## Audit And Compliance Strategy

Audit logging uses `owen-it/laravel-auditing` with the application audit model `App\Models\Audit`. Books, categories, orders, users, reviews, import logs, export logs, and backup monitoring are auditable. Authentication events are captured through Laravel auth event listeners, including login, logout, failed login, lockout, registration, email verification, and password reset. Business events are captured through custom audit events for two-factor enable/disable and order status transitions.

Sensitive fields are excluded globally through `config/audit.php`, including passwords, remember tokens, secrets, recovery codes, and notes. Model-level exclusions add extra protection for fields such as cover images and buyer phone/notes where full storage is not useful for compliance review. The audit dashboard lets admins filter by user, event, model, and date range, inspect old/new values side by side, and export CSV/PDF trails.

Audit rows are made closer to write-once by blocking deletes and blocking updates except for the archival marker `archived_at`. Each audit row receives a SHA-256 checksum over the stable audit payload. The command `audit:verify-checksums` recalculates checksums and fails if a row was tampered with outside the model layer. The command is intended for compliance demonstrations and periodic operational checks.

Audit retention is split into online and archived storage. `audit:archive` marks audit entries older than the configured retention window and writes JSONL archive files to local storage. This keeps one year of online data available for dashboard review while allowing long-term retention evidence.

## API Rate Limiting And Transformation

API endpoints use tiered rate limiting through Laravel's `RateLimiter`. Public catalog APIs use the public tier. Authenticated order APIs dynamically resolve standard, premium, or admin limits based on the user role and subscription tier. Auth-sensitive routes use a strict auth tier. Each limiter combines a per-minute limit with a lower per-second burst limit, giving the required burst protection while keeping normal usage practical.

API usage is recorded by `TrackApiUsage`, which writes endpoint, method, tier, user, IP address, limit headers, retry-after values, status code, and throttled state into `api_rate_limit_hits`. The data-management dashboard aggregates this table to show top endpoints and throttled request counts.

Request and response transformation are middleware based. JSON request bodies and query keys are normalized from camelCase to snake_case before controllers run. JSON responses are transformed from snake_case to camelCase for client consistency. Field filtering supports `?fields=id,title,price`, and ETags support conditional requests. Book and order APIs use cursor pagination for large collections.

## Performance And Reliability Considerations

Chunked imports reduce memory pressure by avoiding full-file loading into application collections. Batch inserts reduce database write overhead. Export queries eager load required relationships to avoid obvious N+1 behavior, and large export paths can queue work rather than blocking a request. Read/write database host configuration is available for MySQL and MariaDB through `DB_READ_HOSTS`, `DB_WRITE_HOSTS`, and sticky reads; SQLite and PostgreSQL remain supported for development and tests.

Operational commands use scheduled task logs so failures are observable after unattended scheduler execution. Backup monitoring records started, success, failed, cleanup, health, and weekly summary events. API rate-limit hits provide abuse and capacity evidence. Together, these controls provide the data portability, disaster recovery, compliance, and API resilience capabilities required by Laboratory Activity 6.
