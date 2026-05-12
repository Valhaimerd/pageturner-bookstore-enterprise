-- PageTurner Lab 7: MySQL 8 books table partitioning notes
--
-- STATUS: DOCUMENTATION ONLY.
-- Do not run this file against the current application schema without a
-- separate MySQL infrastructure review and a uniqueness redesign.
--
-- Why this is not automated:
-- MySQL requires every unique key on a partitioned table to include the
-- partitioning column. The current books table has unique slug and isbn keys
-- that intentionally do not include published_at. Applying YEAR(published_at)
-- range partitioning directly would require weakening or redesigning those
-- uniqueness guarantees, which would affect route-model binding and ISBN lookup.
--
-- Driver safety:
-- This SQL is for MySQL 8 only. SQLite tests and local development must not run
-- physical partitioning SQL.

-- Preconditions before considering this in production:
-- 1. DB_CONNECTION=mysql on MySQL 8.x.
-- 2. Back up the database and test restore.
-- 3. Confirm all unique keys can safely include published_at or replace them
--    with application-enforced uniqueness plus non-unique indexes.
-- 4. Confirm foreign keys and dependent tables are compatible with the final
--    partitioning strategy.
-- 5. Run on a staging clone with production-scale data first.

-- Example only: direct ALTER after uniqueness has been redesigned.
-- This example assumes all unique keys have already been made compatible with
-- published_at partitioning.
/*
ALTER TABLE books
PARTITION BY RANGE (YEAR(published_at)) (
    PARTITION p_before_2020 VALUES LESS THAN (2020),
    PARTITION p_2020 VALUES LESS THAN (2021),
    PARTITION p_2021 VALUES LESS THAN (2022),
    PARTITION p_2022 VALUES LESS THAN (2023),
    PARTITION p_2023 VALUES LESS THAN (2024),
    PARTITION p_2024 VALUES LESS THAN (2025),
    PARTITION p_2025 VALUES LESS THAN (2026),
    PARTITION p_2026 VALUES LESS THAN (2027),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
*/

-- Operational example: add the next yearly partition before data arrives.
/*
ALTER TABLE books
REORGANIZE PARTITION p_future INTO (
    PARTITION p_2027 VALUES LESS THAN (2028),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
*/

-- Rollback notes:
-- Removing partitioning rebuilds the table and can be expensive on a large
-- catalog. Schedule maintenance and test this on a clone before production.
/*
ALTER TABLE books REMOVE PARTITIONING;
*/

-- Verification helpers for MySQL:
/*
SELECT
    TABLE_SCHEMA,
    TABLE_NAME,
    PARTITION_NAME,
    PARTITION_METHOD,
    PARTITION_EXPRESSION,
    TABLE_ROWS
FROM information_schema.PARTITIONS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'books'
ORDER BY PARTITION_ORDINAL_POSITION;
*/
