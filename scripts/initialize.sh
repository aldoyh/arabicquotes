#!/bin/bash
set -e

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "Making scripts executable..."
chmod +x "$ROOT_DIR/inc/db-update.sh"

echo "Creating database..."
"$ROOT_DIR/inc/db-update.sh"

echo "Verifying database..."
if [ ! -f "$ROOT_DIR/quotes.db" ]; then
    echo "❌ Error: Database file was not created"
    exit 1
fi

count=$(sqlite3 "$ROOT_DIR/quotes.db" "SELECT COUNT(*) FROM quotes;")
if [ "$count" -gt 0 ]; then
    echo "✅ Database initialized successfully with $count quotes"
    echo "Run 'php index.php' to start the application"
else
    echo "❌ Error: Database has no quotes"
    exit 1
fi
