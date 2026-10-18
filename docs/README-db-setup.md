# Database Setup Instructions

The SQLite database file (`assets/QuotesDB.db`) is not committed to source control. Instead, schema changes and seeding are automated using migrations located in `database/migrations/`.

## Running Migrations

To initialize and migrate the database:

```bash
composer run migrate -- --seed
```

Or using the CLI tool:

```bash
php scripts/migrate.php --seed
```

## Useful Commands

- `composer run db:status` - View all migrations and their execution status.
- `composer run db:seed` - Seed quotes table from `assets/quotes.json`.
- `composer run db:fresh` - Drop all tables, re-run migrations, and re-seed quotes.