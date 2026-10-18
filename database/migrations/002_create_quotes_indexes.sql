-- Migration: 002_create_quotes_indexes
-- Description: Create indexes for fast lookup and deduplication

CREATE UNIQUE INDEX IF NOT EXISTS idx_quotes_unique_keys ON quotes (quote_key, author_key);
CREATE INDEX IF NOT EXISTS idx_quotes_hits ON quotes (hits);
CREATE INDEX IF NOT EXISTS idx_quotes_category ON quotes (category);
CREATE INDEX IF NOT EXISTS idx_quotes_author ON quotes (author);
