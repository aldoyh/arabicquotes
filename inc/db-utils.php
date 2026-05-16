<?php

if (defined('TEST_MODE') && TEST_MODE) {
    define('DB_NAME', __DIR__ . '/' . TEST_DB_FILE);
} else {
    define('DB_NAME', __DIR__ . '/../assets/QuotesDB.db');
}

function getDB()
{
    $db = new SQLite3(DB_NAME);
    if (!$db) {
        error_log('Failed to connect to the DB');
        return false;
    }

    ensureQuotesTableExists($db);
    return $db;
}

function getDBSQLite()
{
    return getDB();
}

function makeDBClone()
{
    $db = getDB();
    $backupPath = __DIR__ . '/../assets/QuotesDB.backup-' . date('Ymd-His') . '.db';
    copy(DB_NAME, $backupPath);
    $db->close();
}

function create_table($db)
{
    $db->query('DROP TABLE IF EXISTS quotes;');
    $db->query("CREATE TABLE quotes (
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
    )") or die('Failed to create table');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_quotes_unique_keys ON quotes (quote_key, author_key)');
}

function ensureQuotesTableExists($db = null)
{
    $shouldClose = false;
    if ($db === null) {
        $db = new SQLite3(DB_NAME);
        $shouldClose = true;
    }

    try {
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='quotes'");
        $tableExists = $result && $result->fetchArray();

        if (!$tableExists) {
            create_table($db);
        } else {
            ensureQuoteColumns($db);
            backfillQuoteKeys($db);
            removeDuplicateQuotes($db);
            $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_quotes_unique_keys ON quotes (quote_key, author_key)');
        }

        if ($shouldClose) {
            $db->close();
        }
        return true;
    } catch (Exception $e) {
        error_log('Error ensuring quotes table exists: ' . $e->getMessage());
        if ($shouldClose) {
            $db->close();
        }
        return false;
    }
}

function ensureQuoteColumns($db)
{
    $columns = [];
    $result = $db->query('PRAGMA table_info(quotes)');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[$row['name']] = true;
    }

    $definitions = [
        'head' => 'TEXT',
        'hits' => 'INTEGER DEFAULT 0',
        'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'category' => "TEXT DEFAULT 'General'",
        'source' => "TEXT DEFAULT 'manual'",
        'quote_key' => 'TEXT',
        'author_key' => 'TEXT',
    ];

    foreach ($definitions as $column => $definition) {
        if (!isset($columns[$column])) {
            $db->exec("ALTER TABLE quotes ADD COLUMN {$column} {$definition}");
        }
    }
}

function cleanQuoteText($text)
{
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    $text = preg_replace('/\s+/u', ' ', trim($text));
    $text = preg_replace('/^[—\-–\s]+/u', '', $text);
    return trim($text);
}

function normalizeQuoteKey($text)
{
    $text = cleanQuoteText($text);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[ًٌٍَُِّْـ]/u', '', $text);
    $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
    $text = str_replace(['ى'], 'ي', $text);
    $text = str_replace(['ؤ'], 'و', $text);
    $text = str_replace(['ئ'], 'ي', $text);
    $text = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', '', $text);
    return $text;
}

function quoteHash($text)
{
    return hash('sha256', normalizeQuoteKey($text));
}

function prepareQuoteRow($row)
{
    $quote = cleanQuoteText($row['quote'] ?? '');
    $author = cleanQuoteText($row['author'] ?? 'غير معروف');

    if ($quote === '') {
        return null;
    }

    $category = $row['category'] ?? $row['tags'] ?? 'General';
    if (is_array($category)) {
        $category = implode('، ', array_values(array_filter(array_map('trim', $category))));
    }
    $category = cleanQuoteText($category ?: 'General');

    return [
        'head' => cleanQuoteText($row['head'] ?? ''),
        'quote' => $quote,
        'author' => $author,
        'hits' => max(0, (int) ($row['hits'] ?? 0)),
        'category' => $category ?: 'General',
        'source' => cleanQuoteText($row['source'] ?? 'manual'),
        'quote_key' => quoteHash($quote),
        'author_key' => quoteHash($author),
    ];
}

function backfillQuoteKeys($db)
{
    $result = $db->query("SELECT id, quote, author FROM quotes WHERE quote_key IS NULL OR quote_key = '' OR author_key IS NULL OR author_key = ''");
    $stmt = $db->prepare('UPDATE quotes SET quote = :quote, author = :author, quote_key = :quote_key, author_key = :author_key WHERE id = :id');

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $quote = cleanQuoteText($row['quote']);
        $author = cleanQuoteText($row['author']);
        $stmt->bindValue(':quote', $quote, SQLITE3_TEXT);
        $stmt->bindValue(':author', $author, SQLITE3_TEXT);
        $stmt->bindValue(':quote_key', quoteHash($quote), SQLITE3_TEXT);
        $stmt->bindValue(':author_key', quoteHash($author), SQLITE3_TEXT);
        $stmt->bindValue(':id', (int) $row['id'], SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->reset();
    }
}

function removeDuplicateQuotes($db)
{
    $result = $db->query("SELECT quote_key, author_key, MIN(id) AS keep_id, SUM(COALESCE(hits, 0)) AS merged_hits, COUNT(*) AS total
        FROM quotes
        WHERE quote_key IS NOT NULL AND author_key IS NOT NULL
        GROUP BY quote_key, author_key
        HAVING total > 1");

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $update = $db->prepare('UPDATE quotes SET hits = :hits WHERE id = :id');
        $update->bindValue(':hits', (int) $row['merged_hits'], SQLITE3_INTEGER);
        $update->bindValue(':id', (int) $row['keep_id'], SQLITE3_INTEGER);
        $update->execute();

        $delete = $db->prepare('DELETE FROM quotes WHERE quote_key = :quote_key AND author_key = :author_key AND id != :keep_id');
        $delete->bindValue(':quote_key', $row['quote_key'], SQLITE3_TEXT);
        $delete->bindValue(':author_key', $row['author_key'], SQLITE3_TEXT);
        $delete->bindValue(':keep_id', (int) $row['keep_id'], SQLITE3_INTEGER);
        $delete->execute();
    }
}

function addQuotesToDatabase($quotes, $source = 'manual')
{
    $db = getDB();
    $stats = ['inserted' => 0, 'skipped' => 0, 'invalid' => 0];

    $stmt = $db->prepare('INSERT OR IGNORE INTO quotes
        (head, quote, author, hits, category, source, quote_key, author_key)
        VALUES (:head, :quote, :author, :hits, :category, :source, :quote_key, :author_key)');

    foreach ($quotes as $row) {
        if (!isset($row['source'])) {
            $row['source'] = $source;
        }
        $prepared = prepareQuoteRow($row);
        if ($prepared === null) {
            $stats['invalid']++;
            continue;
        }

        foreach ($prepared as $key => $value) {
            $type = $key === 'hits' ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue(':' . $key, $value, $type);
        }

        $stmt->execute();
        if ($db->changes() > 0) {
            $stats['inserted']++;
        } else {
            $stats['skipped']++;
        }
        $stmt->reset();
    }

    $db->close();
    return $stats;
}

function import_data($db = null, $data = null)
{
    $shouldClose = false;
    if ($db === null) {
        $db = getDB();
        $shouldClose = true;
    } else {
        ensureQuotesTableExists($db);
    }

    if ($data === null) {
        $jsonPath = __DIR__ . '/../assets/quotes.json';
        $data = file_exists($jsonPath) ? json_decode(file_get_contents($jsonPath), true) : [];
    }

    $stats = ['inserted' => 0, 'skipped' => 0, 'invalid' => 0];
    $stmt = $db->prepare('INSERT OR IGNORE INTO quotes
        (head, quote, author, hits, category, source, quote_key, author_key)
        VALUES (:head, :quote, :author, :hits, :category, :source, :quote_key, :author_key)');

    foreach ($data as $row) {
        $prepared = prepareQuoteRow($row);
        if ($prepared === null) {
            $stats['invalid']++;
            continue;
        }
        foreach ($prepared as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $key === 'hits' ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        $stmt->execute();
        $stats[$db->changes() > 0 ? 'inserted' : 'skipped']++;
        $stmt->reset();
    }

    if ($shouldClose) {
        $db->close();
    }
    return $stats;
}

function exportQuotesToJson()
{
    $db = getDB();
    $result = $db->query('SELECT id, head, quote, author, hits, created_at, category, source FROM quotes ORDER BY id');
    $quotes = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $quotes[] = $row;
    }
    $db->close();

    $json = json_encode($quotes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    file_put_contents(__DIR__ . '/../assets/quotes.json', $json . PHP_EOL);
}

function getAllQuotes()
{
    $db = getDB();
    $result = $db->query('SELECT * FROM quotes ORDER BY id');
    $quotes = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $quotes[] = $row;
    }
    $db->close();
    return $quotes;
}

function getRandomQuote()
{
    $db = getDB();
    $result = $db->query('SELECT * FROM quotes ORDER BY RANDOM() LIMIT 1');
    $quote = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    return $quote;
}

function getQuotesByCategory($category)
{
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM quotes WHERE category = :category ORDER BY RANDOM()');
    $stmt->bindValue(':category', $category, SQLITE3_TEXT);
    $result = $stmt->execute();
    $quotes = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $quotes[] = $row;
    }
    $db->close();
    return $quotes;
}
