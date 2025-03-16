#!/bin/bash
set -e

echo "Making scripts executable..."
chmod +x db-update.sh

echo "Creating database..."
./inc/db-update.sh

echo "Verifying database..."
if [ ! -f quotes.db ]; then
    echo "❌ Error: Database file was not created"
    exit 1
fi

count=$(sqlite3 quotes.db "SELECT COUNT(*) FROM quotes;")
if [ "$count" -gt 0 ]; then
    echo "✅ Database initialized successfully with $count quotes"
    echo "Run 'php index.php' to start the application"
else
    echo "❌ Error: Database has no quotes"
    exit 1
fi