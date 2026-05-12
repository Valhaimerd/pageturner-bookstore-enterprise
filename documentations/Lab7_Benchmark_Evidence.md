# Lab 7 Benchmark Evidence

Use this file to record the actual evidence after running Lab 7 on the target machine.

## Environment

- Date:
- Machine/CPU:
- RAM:
- OS:
- PHP version:
- Database engine/version:
- Redis version:
- Queue driver:
- Cache store:
- Total `books` rows:

## Commands Run

```bash
php artisan migrate
php artisan books:seed-mass --count=1000000 --chunk=5000
php artisan books:refresh-materialized-views
php artisan books:index-batch --chunk=2000
php artisan benchmark:books --iterations=100
```

## Benchmark Results

| Query | Target | Average | Min | Max | Pass |
| --- | ---: | ---: | ---: | ---: | --- |
| Catalog listing, 100 records | < 100 ms |  |  |  |  |
| ISBN lookup | < 50 ms |  |  |  |  |
| Category filter | < 150 ms |  |  |  |  |
| Full-text/search | < 300 ms |  |  |  |  |

## Seeding Evidence

- Target rows:
- Actual rows:
- Total seed time:
- Peak memory:
- Chunk size:
- Command output captured:

## Export Evidence

- Export type:
- Rows exported:
- Chunk size:
- Total export time:
- Peak memory:

## Cache Evidence

- Redis cache DB:
- Redis session DB:
- Cache warmup command/job:
- Repeated catalog request average:

## Partitioning Decision

Physical range partitioning by `published_at` was not applied because the current `books` table has unique `slug` and `isbn` constraints. In MySQL, every unique key on a partitioned table must include the partitioning column, which would weaken current route-model binding and ISBN uniqueness guarantees. Lab 7 performance work instead uses composite indexes, full-text search, query caching, cursor pagination, and precomputed summary tables.
