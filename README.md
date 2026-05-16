# rickyx12/db-sync

Laravel artisan command that diffs two MySQL databases and generates SQL files describing what the second one is missing — perfect for syncing a staging schema against production.

## Install

```bash
composer require rickyx12/db-sync
```

Laravel 5.5+ auto-discovers the service provider, no manual registration needed.

## Configure

Publish the config file:

```bash
php artisan vendor:publish --tag=db-sync-config
```

This creates `config/db-sync.php`. Then set the connection details in your application's `.env`:

```env
DB_SYNC_MYSQL1_HOST=
DB_SYNC_MYSQL1_DATABASE=
DB_SYNC_MYSQL1_USERNAME=
DB_SYNC_MYSQL1_PASSWORD=

DB_SYNC_MYSQL2_HOST=
DB_SYNC_MYSQL2_DATABASE=
DB_SYNC_MYSQL2_USERNAME=
DB_SYNC_MYSQL2_PASSWORD=
```

- **mysql1** = the *source* (source of truth)
- **mysql2** = the *target* being compared against the source

Optional keys: `DB_SYNC_MYSQL{1,2}_CHARSET`, `DB_SYNC_MYSQL{1,2}_COLLATION`, and `DB_SYNC_OUTPUT_DISK` (defaults to `local`).

## Run

```bash
php artisan db:sync
```

Generates the following SQL files in `storage/app/` (or whichever disk you configured):

| File | What it contains |
|---|---|
| `missing_tables.sql` | `CREATE TABLE IF NOT EXISTS` for tables that exist in mysql1 but not mysql2 |
| `missing_views.sql` | `CREATE OR REPLACE VIEW` for views missing in mysql2 |
| `missing_columns.sql` | `ALTER TABLE ... ADD COLUMN` for columns missing in mysql2 |
| `mismatch_column_props.sql` | `ALTER TABLE ... MODIFY COLUMN` for columns whose type, nullability, default, or extra differs |

Review the generated SQL before running it against mysql2.

## Requirements

- PHP ^8.0
- Laravel 9, 10, 11, or 12
- Both connections must be MySQL (the command uses `SHOW TABLES` / `SHOW CREATE TABLE`)

## License

MIT
