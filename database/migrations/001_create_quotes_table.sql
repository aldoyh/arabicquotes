-- Migration: 001_create_quotes_table
-- Description: Create the main quotes table

CREATE TABLE IF NOT EXISTS quotes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    head TEXT,
    quote TEXT NOT NULL,
    author TEXT NOT NULL,
    hits INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    category TEXT DEFAULT 'General',
    source TEXT DEFAULT 'manual',
    quote_key TEXT,
    author_key TEXT
);
