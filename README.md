# PageTurner Online Bookstore Management System

PageTurner is a Laravel 12 bookstore management system with customer catalog browsing, carts, checkout, admin inventory management, imports/exports, backup monitoring, audit logging, API rate limiting, and Lab 7 scalability tooling.

## Requirements

- PHP 8.3 or newer for the locked dependency set
- Composer
- Node.js and npm
- SQLite for local lightweight development, or MySQL/MariaDB for Lab 7 full-text indexes and large-scale benchmarks
- Redis for Lab 7 cache, session, queue, and rate-limit storage
- PHP extensions required by the locked packages, including `gd`

## Setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

For local development:

```bash
composer run dev
```

## Moving From SQLite To PostgreSQL

SQLite is fine for small local checks, but Lab 7 benchmarks and 1,000,000-row seeding should run on PostgreSQL or another server database. To move the current SQLite data into PostgreSQL:

1. In pgAdmin, create a database named `pageturner` with owner `postgres`.
2. Confirm PHP has PostgreSQL support:

```bash
php -m | findstr pgsql
```

3. Update `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pageturner
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password
SQLITE_TRANSFER_DATABASE=database/database.sqlite
```

4. Clear cached config and create the PostgreSQL schema:

```bash
php artisan config:clear
php artisan migrate --database=pgsql
```

5. Transfer the existing SQLite rows into PostgreSQL:

```bash
php artisan db:transfer-sqlite-to-pgsql --target=pgsql --chunk=1000
```

If the PostgreSQL tables already contain data and you want to replace it after taking a backup:

```bash
php artisan db:transfer-sqlite-to-pgsql --target=pgsql --chunk=1000 --truncate --force
```

6. Verify PostgreSQL is now active:

```bash
php artisan db:show
php artisan test --filter=LabSeven
```

After the transfer, continue Lab 7 work on PostgreSQL:

```bash
php artisan books:seed-mass --count=1000000 --chunk=5000
php artisan app:refresh-materialized-views
php artisan books:index-batch --chunk=2000
php artisan benchmark:books --iterations=100
```

## Lab 6 Features

- Chunked queued book and user imports
- CSV, XLSX, and PDF exports with export logs
- Backup run, cleanup, and monitoring through Spatie Backup
- Audit logging for user and catalog activity
- Tiered API rate limiting for public, standard, premium, and admin users
- API request/response transforms with camelCase output, field filtering, and ETags

## Laboratory Activity 7

Laboratory Activity 7 adds mass data seeding, query optimization, Redis-backed caching, full-text search, benchmark tooling, and database scalability notes for a bookstore catalog that can grow to high record counts. The implementation keeps the existing schema language: inventory uses `stock`, and active-book filtering uses `status = active`.

### Implemented Features

- Mass data seeding uses `BookFactory`, `MassBookSeeder`, and `php artisan books:seed-mass` to generate up to 1,000,000 realistic books in 5,000-row chunks. The seeder uses valid ISBN-13 values, existing category IDs, varied titles/authors/publishers/formats, and raw batch inserts for speed.
- Optimized catalog queries are centralized in `BookRepository`. Catalog and API listings use cursor pagination, selected columns, constrained eager loading such as `category:id,name,slug`, and active-book filters to avoid large `OFFSET` scans and obvious N+1 queries.
- Redis-backed caching is handled by `BookCacheService`, with `BookObserver` invalidating catalog, category, ISBN, and summary cache keys when books change. `WarmCategoryCache` can preload active category results. Non-tag cache stores fall back to tracked cache keys, so local array/database cache remains compatible.
- Search uses Laravel Scout with the database driver by default. MySQL/MariaDB can use the guarded full-text index on book title and description, while SQLite tests fall back to safe `LIKE` search behavior.
- `benchmark:books` measures catalog listing, exact ISBN lookup, category filtering, full-text search, and export chunk simulation. It prints pass/fail results, stores database benchmark rows when `query_performance_logs` exists, and writes `storage/logs/lab7-benchmark.log`.
- `mv_bestseller_stats` is the MySQL-compatible practical alternative to a true materialized view. Refresh it with `php artisan app:refresh-materialized-views`; the task is scheduled hourly without overlapping.
- Read replica settings are available through `DB_READ_HOSTS`, `DB_WRITE_HOSTS`, and `DB_STICKY`. Reporting and export queries can benefit from read replicas when deployed on MySQL/MariaDB infrastructure.
- MySQL 8 partitioning is documented only in `documentations/mysql8_books_partitioning.sql`. It is not applied automatically because the current unique `slug` and `isbn` constraints would need redesign before safe range partitioning by `YEAR(published_at)`.

### Setup Commands

```bash
composer install
npm install
php artisan migrate
php artisan db:seed
php artisan books:seed-mass --count=1000000 --chunk=5000
php artisan benchmark:books --iterations=100
php artisan test
```

For local proof runs, use a smaller seed size:

```bash
php artisan books:seed-mass --count=10000 --chunk=1000
```

The normal `php artisan db:seed` command keeps demo data by default. Mass seeding is opt-in through `books:seed-mass` or by setting:

```env
MASS_BOOK_SEED_ENABLED=true
MASS_BOOK_SEED_TOTAL=1000000
MASS_BOOK_SEED_CHUNK=5000
MASS_BOOK_SEED_ISBN_START=200000000
```

### Redis Setup Notes

Recommended Lab 7 `.env` settings:

```env
CACHE_STORE=redis
CATALOG_CACHE_STORE=catalog
SESSION_DRIVER=redis
SESSION_CONNECTION=session
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2
REDIS_QUEUE_CONNECTION=default
REDIS_CACHE_CONNECTION=cache
REDIS_CATALOG_CACHE_CONNECTION=cache
```

Start a queue worker before large exports, cache warmups, or Scout indexing:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Local tests use `CACHE_STORE=array`, so Redis is not required for PHPUnit.

### Scout And Full-Text Search Notes

Scout is configured with the database driver by default:

```env
SCOUT_DRIVER=database
SCOUT_QUEUE=true
```

Index active books in chunks:

```bash
php artisan books:index-batch --chunk=2000
```

MySQL/MariaDB uses a guarded full-text index for title and description search when available. SQLite does not receive full-text indexes during tests and uses the repository fallback search path instead.

### Read Replica Notes

For MySQL/MariaDB deployments, configure read and write hosts in `.env`:

```env
DB_READ_HOSTS=10.0.0.11,10.0.0.12
DB_WRITE_HOSTS=10.0.0.10
DB_STICKY=true
DB_PERSISTENT=false
DB_PERSISTENT_CONNECTION_NAME=pageturner
```

Laravel will send read-only `SELECT` queries to read hosts and writes to write hosts. Keep `DB_STICKY=true` when requests need read-your-writes behavior after a mutation. Reporting and export jobs should keep heavy operations read-only where possible so replicas can absorb that load.

### Partitioning And Materialized View Notes

- Physical MySQL 8 range partitioning by `YEAR(published_at)` is documented in `documentations/mysql8_books_partitioning.sql`.
- Partitioning is not run automatically and is not used on SQLite.
- The current `books` table has unique `slug` and `isbn` constraints; MySQL partitioning would require every unique key to include the partition column, so this needs a future schema redesign.
- `mv_bestseller_stats` stores refreshable category summaries: total books, average price, total inventory, bestseller count from sales evidence, and latest publication date.
- Refresh summary data manually with:

```bash
php artisan app:refresh-materialized-views
```

### Benchmark Result Placeholders

Record final benchmark evidence in `documentations/Lab7_Benchmark_Evidence.md`.

- Hardware specs:
- Seed time:
- Peak memory:
- Catalog average:
- ISBN average:
- Category filter average:
- Full-text average:
- Export chunk average:

Lab 7 benchmark targets:

- Catalog listing, 100 records: less than 100 ms
- ISBN lookup: less than 50 ms
- Category filter: less than 150 ms
- Full-text/search query: less than 300 ms
- Export 10K chunk simulation: less than 30 seconds

### Deliverables Checklist

- [x] `BookFactory`
- [x] `MassBookSeeder` / `books:seed-mass`
- [x] `BookRepository`
- [x] `BookCacheService`
- [x] `BookObserver`
- [x] `BenchmarkBookQueries`
- [x] `WarmCategoryCache`
- [x] Scout config
- [x] migrations
- [x] `.env.example`
- [x] database/cache config
- [x] tests

## Laboratory Activity 8

### Feature Name

**PageTurner AI Book Discovery and Customer Support Assistant Using Ollama**

Laboratory Activity 8 adds an Ollama-first AI assistant to PageTurner. The assistant helps visitors and customers describe what they want to read in natural language, then returns grounded recommendations from the real PageTurner catalog. It also supports basic bookstore support responses, usage tracking, audit logging, responsible AI safeguards, queued conversation summaries, and an admin monitoring dashboard.

### Problem Identification

Customers do not always know the exact book title, author, ISBN, or category that they need. In a large bookstore catalog, a normal keyword search is useful only when the customer's words match stored catalog text. Many users search by mood, theme, subject, problem, or learning goal instead. Examples include "I want something inspiring about friendship," "Recommend beginner Laravel books," or "What should I read after learning PHP?" These requests are clear to a human bookseller, but they are difficult for a strict keyword search because they may not directly match a title or category.

This affects visitors who are exploring the catalog, customers who want a faster path to a relevant book, and students or professionals who need learning guidance. The AI assistant solves the problem by interpreting natural language, retrieving real active books from the database, and explaining why each recommendation matches the request.

### Solution Design

The Lab 8 recommendation flow is:

```text
user question
-> retrieve relevant books from PageTurner database
-> build grounded prompt with only candidate books
-> send to Ollama through AIServiceManager
-> validate JSON response
-> remove invented book IDs
-> show recommendations in the customer UI
-> log usage and audit event
```

The assistant never sends the full books table to the AI provider. `BookDiscoveryAIService` first retrieves a small set of active, in-stock candidate books using the existing repository/search behavior. Ollama receives only the user question, safe instructions, and those candidate books. The response must be JSON, and the application validates that every recommended `book_id` exists in the retrieved candidate set. If Ollama is unavailable or returns unusable output, the fake provider and deterministic local ranking keep the user experience graceful.

### Architecture

- `OllamaProvider`: the real local AI provider. It calls the Ollama HTTP API using the configured base URL and model.
- `FakeAIProvider`: deterministic offline provider for tests, demos, and graceful fallback. It does not call external services.
- `AIServiceManager`: the single entry point for AI generation. It handles provider selection, fallback, safety checks, usage logs, and audit events.
- `BookDiscoveryAIService`: retrieves real PageTurner books, builds the grounded prompt, calls the AI manager, validates JSON, removes invented IDs, and returns structured recommendations.
- `AISafetyService`: blocks unsafe prompts, prompt injection, secret extraction, private-data requests, SQL-like input, spam, and unsafe output.
- `AIUsageTracker`: stores provider, feature, token, latency, fallback, success, and zero-cost usage records in `ai_usage_logs`.
- `AIAuditLogger`: records decision events in `ai_audit_events` using input and output hashes instead of raw sensitive content.
- `ProcessAIConversationSummary`: queued job on `ai-tasks` that summarizes longer conversations in the background and stores the summary in conversation metadata.
- Admin monitoring dashboard: `/admin/ai-monitoring` shows Lab 8 usage totals, provider breakdown, fallback counts, recent usage logs, recent audit events, top features, latency, and zero-cost status.

### Setup Commands

```bash
composer install
npm install
php artisan migrate
php artisan db:seed
ollama pull llama3.2
ollama serve
php artisan serve
php artisan queue:work --queue=ai-tasks
php artisan test --filter=LabEight
```

### Environment Setup

For lab reporting, the AI environment can be described with these settings:

```env
AI_DEFAULT_PROVIDER=ollama
AI_FALLBACK_ENABLED=true
AI_FALLBACK_CHAIN=ollama,fake
OLLAMA_ENABLED=true
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2
AI_FAKE_ENABLED=true
```

The current Laravel implementation uses these project keys:

```env
AI_ENABLED=true
AI_PROVIDER=ollama
AI_FALLBACK_PROVIDER=fake
AI_QUEUE_SUMMARIES=false
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.2
OLLAMA_TIMEOUT=15
```

`AI_PROVIDER=ollama` maps to the main local provider. `AI_FALLBACK_PROVIDER=fake` maps to the fallback chain concept. `AI_ENABLED=true` maps to Ollama availability. `AI_QUEUE_SUMMARIES=false` keeps queued summaries disabled by default until a queue worker is intentionally started.

### Testing Result Placeholders

- Ollama response time:
- Fake fallback result:
- Queue result:
- Usage tracking result:
- Audit log result:
- Security test result:

Recommended verification commands:

```bash
php artisan test --filter=LabEight
php artisan test --filter=AIServiceManager
php artisan test --filter=BookDiscoveryAIService
php artisan test
```

### Responsible AI Safeguards

- Recommendations are grounded in retrieved PageTurner database books only.
- The assistant must not invent books, titles, prices, authors, categories, or stock.
- Prompt-injection attempts are blocked or neutralized before provider calls.
- Secret extraction, `.env` requests, hidden-instruction disclosure, unsafe SQL-like input, and private-data requests are blocked.
- AI usage logs and audit events are recorded for monitoring and evidence.
- Low-confidence responses trigger `needs_human_help=true`.
- Blade views escape AI output with normal `{{ }}` rendering.
- No cloud API keys are required.

### Cost Analysis

Ollama runs locally and does not require paid cloud API usage. `FakeAIProvider` is also local and free. PageTurner records estimated AI cost as zero because no OpenAI, Gemini, Hugging Face, Google, or paid cloud provider is required. The admin monitoring dashboard displays zero-cost status as evidence that the Lab 8 implementation is local-first.

### Future Improvements

- Add embeddings with `nomic-embed-text`.
- Add semantic vector search for better natural-language matching.
- Add voice search for accessibility and faster customer input.
- Summarize book reviews for customers.
- Expand admin analytics for trends, unanswered questions, fallback rates, and conversion insights.

### Lab 8 Deliverables Checklist

- [x] Ollama-first AI provider abstraction
- [x] Fake provider fallback
- [x] Grounded book discovery service
- [x] Responsible AI safety service
- [x] AI usage tracking
- [x] AI audit logging
- [x] Customer assistant UI
- [x] API assistant endpoints
- [x] Conversation and message storage
- [x] Queue-based conversation summary job
- [x] Admin AI monitoring dashboard
- [x] Lab 8 tests that run without Ollama
- [x] Technical documentation
