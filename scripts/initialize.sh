#!/bin/bash
set -e

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "Initializing database migrations and quotes..."
php "$ROOT_DIR/scripts/migrate.php" --seed

DB_FILE="$ROOT_DIR/assets/QuotesDB.db"

if [ ! -f "$DB_FILE" ]; then
    echo "❌ Error: Database file was not created at $DB_FILE"
    exit 1
fi

count=$(sqlite3 "$DB_FILE" "SELECT COUNT(*) FROM quotes;")
if [ "$count" -gt 0 ]; then
    echo "✅ Database initialized successfully with $count quotes"
    echo "Run 'php index.php' to start the application"
else
    echo "❌ Error: Database has no quotes"
    exit 1
fi
