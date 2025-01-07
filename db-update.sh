#!/bin/bash
set -e

# Create SQLite database if it doesn't exist
if [ ! -f "quotes.db" ]; then
    echo "Creating new quotes database..."
    sqlite3 quotes.db << 'END_SQL'
    CREATE TABLE IF NOT EXISTS quotes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        head TEXT,
        quote TEXT,
        author TEXT,
        hits INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        category TEXT DEFAULT 'General'
    );
    INSERT INTO quotes (quote, author, hits) VALUES 
        ('اطلبوا العلم وزيِّنوه بالوَقَار والحِلْم.', 'علي بن أبي طالب', 0),
        ('العلم ينادي العمل، فإن أجابه وإلا ارتحل.', 'أبو الدرداء', 0);
END_SQL
    echo "✅ Database created successfully"
fi

# Make sure database is writable
chmod 666 quotes.db

echo "Database setup complete!"