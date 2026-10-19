# Noora migration repair v3 — 18 October 2026

Correct target from the latest screenshot: `pos.nooratradingco.lk`, database `nooratra_pos`. Use the Laravel project folder serving that domain, containing `artisan`, `app`, `routes` and `database`. Keep that server's existing `.env`.

The corrected screenshot fails verification for the three `account_transfers` foreign keys: `from_bank_id`, `to_bank_id` and `user_id`. The exact three-error failure was reproduced locally by creating this table on a MySQL connection whose default table engine is MyISAM. MyISAM ignores foreign keys. This reproduction identifies a supported cause matching the screenshot; the hosting database itself has not been directly inspected.

Version 3 explicitly creates MySQL application tables using InnoDB, checks/converts table engines again after migrations finish, restores missing foreign keys, and verifies engines as well as relationships. Existing transfer rows are retained. This package supersedes earlier repair packages and retains their partial-upgrade recovery fixes.

## What changed

- Migrations can resume when some tables, columns, indexes or foreign keys already exist. Existing column values are retained.
- The document migration ensures store/shipment parent tables exist, and restores its foreign keys even when the columns were added by an earlier failed attempt.
- The migration page no longer drops product-price columns to retry a failed migration.
- Tax, cheque and owner-account defaults preserve existing settings on retry. Product category/brand backfills avoid duplicate links. The preorder stock backfill does not run again after its column already exists.
- New MySQL tables explicitly use InnoDB, so a shared-host MyISAM default cannot silently omit their foreign keys. A second engine repair pass covers tables created/imported after preflight. Read-only checks also report unsupported engines.
- The repair runner restores missing table structures whose migrations were already recorded, completes missing columns/indexes/relationships and verifies the final schema.
- Missing parents required by existing child columns are created before any ID/engine alterations or migrations. This includes store transfer and bank account dependencies, even when their creator migrations are pending.
- During a MySQL repair, new foreign keys are deferred until all migrations have finished. Final required relationships are then installed and checked, with orphan-reference protection. Direct migration runs retain normal foreign-key behavior.
- Failure pages retain completed repair messages and migration output; they no longer incorrectly show an empty repair history after a later error.
- Incomplete migration uploads and unverified pending migrations from another release stop before repairs begin.
- Orphan relationships and conflicting indexes/constraints stop with an explanation. The repair does not delete or reassign business records, reset the database or erase migration history.
- Missing ID primary keys and AUTO_INCREMENT are repaired when existing IDs are valid; duplicate or empty IDs stop with an explanation.
- Concurrent repair requests are blocked. The result page supports mobile screens and does not expose the full application error log.

## Apply on the hosting server

1. Back up the database and application files. Use a short maintenance window so transactions are not entered during schema changes.
2. Extract `noora-migration-repair-2026-10-18-v3.zip` into the Laravel project root, replacing its matching files. Upload the whole package, including `app/Support/Migrations`, `app/Services/MigrationHealthService.php`, `routes/web.php`, the result view, schema contract and complete 70-file `database/migrations` folder. Preserve the server's `.env`, database, uploaded files and storage. Existing migration history must stay in place. Use the matching application release; do not combine unrelated project versions.
3. On **pos.nooratradingco.lk**, open `/migration` once, using the existing `?key=...` when `MIGRATION_KEY` is configured. The aliases `/migrate` and `/run-migration` use the same repair runner. Do not run the seeder or `migrate:fresh` to repair an existing business database.
4. Confirm the page footer says **Repair version: 2026-10-18.3**, then confirm **Migration Successful** and **Verified 70 migrations and 63 application tables**. The page also lists any completed structural repairs. If the old footer remains, the server is still serving old files; check the upload location and clear Laravel's view/route caches with `php artisan optimize:clear` when terminal access is available.
5. For a later read-only check, open `/migration?check=1` (or `?key=...&check=1` with the configured key).

Terminal alternatives, from the Laravel project folder:

```sh
php artisan database:check
php artisan database:check --repair
```

The first command is read-only. The second runs pending migrations, applies safe structural repairs and verifies the result. Both return a nonzero status when verification fails.

If an upload contains different pending migrations, deploy the matching release rather than mixing migration folders from different project versions. Already-recorded additional migration history is retained and reported. Legacy tables and their records are not automatically converted into a different application's schema.

If a deleted parent table contained business records, recreating its structure cannot recover those records. Restore missing records from a backup before retrying any relationships that report orphan references. A missing required column in a populated table is not automatically filled with invented values.

## Developer validation

Local validation: 192 application tests passed (1,369 assertions). The separate MySQL upgrade test passed with 40 assertions and covers MyISAM defaults, late-created MyISAM tables, restoration of all three account-transfer foreign keys, and retained transfer amounts/descriptions, in addition to earlier upgrade recovery cases. The local schema check verified 70 migrations and 63 application tables. The hosting database must still be checked after upload.

```sh
php artisan test --compact
RUN_MYSQL_MIGRATION_TESTS=1 php vendor/bin/phpunit tests/Integration/MigrationMysqlTest.php
```

The opt-in integration test only uses a local MySQL server. It creates a uniquely named `spare_parts_migration_qa_*` database and removes that exact database in cleanup. It tests fresh installation through the repair runner, the missing-store failure, partial product-price upgrades, repeated accounting/cheque/tax upgrades, missing-column repair, primary-key/AUTO_INCREMENT repair, no-op reruns and orphan-reference protection. It also tests pending store-transfer and bank-parent migrations alongside already-imported child columns, including an early foreign-key attempt before the parent migration runs, while preserving an existing stock transfer.

`database/schema/required-schema.json` contains schema definitions, not business records or credentials. It was built from a fresh disposable MySQL installation. When adding/changing migrations, regenerate it with:

```sh
php scripts/build-migration-schema.php --write
```

This generator is restricted to a local MySQL server and removes its disposable database afterward. Do not generate the contract from a business database or hand-edit it to hide missing schema. The health check verifies migration records, required tables/column names, indexes and foreign-key relationships; it does not certify business totals or recover lost data.
