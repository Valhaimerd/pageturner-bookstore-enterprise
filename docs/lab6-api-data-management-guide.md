# Lab 6 API And Data Management Guide

## Admin Data Management

Admin dashboard:

```text
GET /admin/data-management
```

Available operations:

- Download book import template: `GET /admin/data-management/template/books`
- Download user import template: `GET /admin/data-management/template/users`
- Import books: `POST /admin/data-management/imports/books`
- Import users: `POST /admin/data-management/imports/users`
- Export books: `POST /admin/data-management/exports/books`
- Export orders: `POST /admin/data-management/exports/orders`
- Export users: `POST /admin/data-management/exports/users`
- Download failed import report: `GET /admin/data-management/imports/{importLog}/failures`
- Run manual backup: `POST /admin/data-management/backups/run`
- Download completed export: `GET /exports/{exportLog}/download`

Book import headers:

```text
isbn,title,author,price,stock,category,description
```

Book import validation:

- `isbn`: required, ISBN-10 or ISBN-13 style format.
- `title`: required, maximum 255 characters.
- `author`: required, maximum 255 characters.
- `price`: numeric, greater than zero, maximum 9999.99.
- `stock`: integer, zero or greater.
- `category`: must match an existing category name.

User import headers:

```text
name,email,role,subscription_tier,phone,city,province,country,password
```

Duplicate strategies:

- `skip`: keep existing records and write failures for duplicate ISBN/email rows.
- `update_existing`: update matching book ISBNs or user emails.

## Export Options

Book exports support:

- `format`: `csv`, `xlsx`, or `pdf`
- `category`: category slug
- `price_min`, `price_max`
- `stock_status`: `in_stock`, `out_of_stock`, or `low_stock`
- `date_from`, `date_to`
- `columns[]`: selected book fields

Order exports support:

- `format`: `csv`, `xlsx`, or `pdf`
- `status`
- `customer_id`
- `date_from`, `date_to`
- `columns[]`: selected order fields

Financial report exports use the existing order export route:

```text
financial_report_type=revenue_summary
financial_report_type=tax_report
```

Revenue summary output:

```text
Date,Completed Orders,Revenue
```

Tax report output:

```text
Date,Taxable Revenue,Estimated Tax
```

User exports support:

- `format`: `csv`, `xlsx`, or `pdf`
- `role`: `admin` or `customer`
- `verified_only`
- `redact_pii`
- `columns[]`: selected user fields

## Customer Data Portability

Customer dashboard:

```text
GET /customer/dashboard
```

Customer exports:

- Personal data JSON: `GET /customer/exports/personal-data`
- Order history CSV/XLSX/PDF: `POST /customer/exports/orders`
- Reading history JSON: `GET /customer/exports/reading-history`

Customers can only download their own generated exports. Admin users can download all generated exports for compliance and support workflows.

## API Endpoints

Public catalog:

```text
GET /api/v1/books
GET /api/v1/books/{slug}
```

Authenticated customer/admin order API:

```text
GET /api/v1/orders
GET /api/v1/orders/{order}
```

AI/book discovery endpoints also use the same API tracking and transformation stack where configured:

```text
POST /api/v1/ai/book-discovery
POST /api/ai/book-assistant/message
```

Response behavior:

- Responses are transformed to camelCase.
- Request/query keys are accepted in camelCase and normalized to snake_case internally.
- `?fields=title,created_at` filters response fields and returns `createdAt`.
- ETag headers are returned for JSON responses.
- Cursor pagination is used for large lists.

## Rate Limits

Configured Lab 6 tiers:

| Tier | Requests per minute | Burst limit | Scope |
| --- | ---: | ---: | --- |
| public | 30 | 3/sec | Guest catalog API |
| standard | 60 | 6/sec | Authenticated customers |
| premium | 300 | 20/sec | Premium customers |
| admin | 1000 | 50/sec | Administrators |
| auth | 10 | 2/sec | Login, registration, password reset |

Rate-limit responses include JSON body fields:

```json
{
  "message": "Rate limit exceeded.",
  "tier": "public",
  "limit": 30,
  "retry_after": 1
}
```

Relevant headers:

```text
X-RateLimit-Limit
X-RateLimit-Remaining
Retry-After
X-RateLimit-Tier
```

API usage and throttled requests are stored in `api_rate_limit_hits`.

## Backup And Scheduler

Manual backup:

```bash
php artisan app:backup-run manual
```

Dry-run backup evidence for tests or demos:

```bash
php artisan app:backup-run daily --dry-run
```

Weekly summary:

```bash
php artisan backup:weekly-summary
```

Audit checksum verification:

```bash
php artisan audit:verify-checksums
```

Required server cron:

```cron
* * * * * cd /path/to/pageturner-bookstore && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled task evidence is written to `scheduled_task_logs`. Backup evidence is written to `backup_monitoring`.

## Evidence Checklist

Capture these screenshots or recordings for Lab 6 submission:

- Admin data-management dashboard with import/export panels.
- Successful book CSV import and the corresponding `import_logs` row.
- Failed book import with missing headers or invalid ISBN and downloaded JSONL failure report.
- Revenue summary and tax report exports with completed `export_logs` rows.
- Manual backup button result and backup monitoring cards.
- Audit dashboard filtered by a custom event such as `two_factor_enabled` or `order_status_transition`.
- `audit:verify-checksums` successful run and tamper-detection failure demonstration.
- API 429 response showing JSON body and rate-limit headers.
- Customer dashboard data export buttons and recent export download row.
