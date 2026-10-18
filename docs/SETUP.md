# Initial Setup Instructions

## Database & Migrations

The SQLite database file (`assets/QuotesDB.db`) is generated locally and is excluded from Git source control. The source of truth for the quotes data is `assets/quotes.json` and the migrations in `database/migrations/`.

1. Run migrations and seed the database from JSON:
```bash
composer run migrate -- --seed
# Or: php scripts/migrate.php --seed
```

2. Check migration status:
```bash
composer run db:status
# Or: php scripts/migrate.php status
```

3. Run the application:
```bash
php index.php
```

If you need to reset and recreate the database from scratch at any time:
```bash
composer run db:fresh
# Or: php scripts/migrate.php fresh --seed
```