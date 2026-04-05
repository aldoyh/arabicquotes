<?php

if (defined('TEST_MODE') && TEST_MODE) {
    define("DB_NAME", __DIR__ . "/" . TEST_DB_FILE);
} else {
    define("DB_NAME", __DIR__ . "/../assets/QuotesDB.db");
}

function getDB()
{
    if (!file_exists(DB_NAME)) {
        $db = new SQLite3(DB_NAME);
        create_table($db);
    } else {
        $db = new SQLite3(DB_NAME);
    }

    if (!$db) {
        error_log("Failed to connect to the DB");
        return false;
    }

    return $db;
}

function getDBSQLite()
{
    if (!file_exists(DB_NAME)) {
        $db = new SQLite3(DB_NAME);
        create_table($db);
    } else {
        $db = new SQLite3(DB_NAME);
    }

    if (!$db) {
        error_log("Failed to connect to the DB");
        return false;
    }

    return $db;
}


function makeDBClone()
{
    $db = getDB();

    // Clone a SQLite DB
    // $db->exec("ATTACH DATABASE 'backup/QuotesDB.db' AS backup");
    $db->query("CREATE DATABASE IF NOT EXISTS backup");

    // $db->exec("CREATE TABLE backup.quotes AS SELECT * FROM quotes");
    $db->query("CREATE TABLE IF NOT EXISTS backup.quotes AS SELECT * FROM `quotes`");

    // $db->exec("DETACH DATABASE backup");
    // $db->query("DROP DATABASE backup");

    $db->close();
}

/**
 * Reads the json and creates a DB Table with exact structure
 *
 * @return void
 */
function create_table($db)
{
    // Drop the table if it exists
    $db->query("DROP TABLE IF EXISTS quotes;");

    $db->query("CREATE TABLE quotes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        head TEXT,
        quote TEXT,
        author TEXT,
        hits INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        category TEXT DEFAULT 'General'

  )") or die("Failed to create table");
}


/**
 * Import data from the json file to the DB
 *
 * @return void
 */
function import_data()
{

    $json =  file_get_contents(DB_NAME);

    $data = json_decode($json, true);

    $db = getDB();

    // bulk insert into tables from json
    foreach ($data as $row) {
        echo "Importing " . $row['id'] . "\n";

        $row['hits'] = random_int(0, 100);

        // cleanup the quote
        $row['quote'] = preg_replace('/\s*\.["w-]+\s*{[^}]+}/', '', $row['quote']);

        // . print_r($row, true) . "\n";

        try {
            // generate a random numeric ID for each quote
            // $row['id'] = rand(100000, 999999);

            $sql_query = "INSERT INTO quotes (head, quote, author, hits) VALUES (
                '" . $row['head'] . "',
                '" . $row['quote'] . "',
                '" . $row['author'] . "', " . $row['hits'] . ")";

            // pepare the query
            $stmt = $db->prepare($sql_query);

            // execute the query
            $stmt->execute();


            // $db->exec("INSERT INTO quotes (head, quote, author, hits) VALUES (
            // $db->query($sql_query);


        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
        }
    }

    $db->close();
}

/**
 * Add quotes to the database
 *
 * @param array $quotes Array of quotes, each with 'head', 'quote', 'author', 'category' (optional)
 * @return void
 */
function addQuotesToDatabase($quotes)
{
    $db = getDB();

    foreach ($quotes as $row) {
        $row['hits'] = random_int(0, 100);

        // cleanup the quote
        $row['quote'] = preg_replace('/\s*\.["w-]+\s*{[^}]+}/', '', $row['quote']);

        $category = isset($row['category']) ? $row['category'] : 'General';

        try {
            $sql_query = "INSERT INTO quotes (head, quote, author, hits, category) VALUES (?, ?, ?, ?, ?)";

            $stmt = $db->prepare($sql_query);
            $stmt->bindValue(1, $row['head'], SQLITE3_TEXT);
            $stmt->bindValue(2, $row['quote'], SQLITE3_TEXT);
            $stmt->bindValue(3, $row['author'], SQLITE3_TEXT);
            $stmt->bindValue(4, $row['hits'], SQLITE3_INTEGER);
            $stmt->bindValue(5, $category, SQLITE3_TEXT);

            $stmt->execute();

        } catch (Exception $e) {
            error_log("Error adding quote: " . $e->getMessage());
        }
    }

    $db->close();
}

/**
 * Export quotes to JSON file
 *
 * @return void
 */
function exportQuotesToJson()
{
    $db = getDB();
    $result = $db->query('SELECT * FROM quotes ORDER BY id');
    $quotes = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $quotes[] = $row;
    }
    $db->close();

    $json = json_encode($quotes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents(__DIR__ . '/../assets/quotes.json', $json);
}

/**
 * Get all quotes from the database
 * 
 * @return array Array of quotes, each with 'id', 'head', 'quote', 'author', 'hits', 'created_at', 'category'
 * 
 */
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

/**
 * Get a random quote from the database
 * 
 * @return array|null Quote data with 'id', 'head', 'quote', 'author', 'hits', 'created_at', 'category' or null if no quotes
 */
function getRandomQuote()
{
    $db = getDB();
    $result = $db->query('SELECT * FROM quotes ORDER BY RANDOM() LIMIT 1');
    $quote = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    return $quote;
}

/**
 * Get quotes by category
 * 
 * @param string $category Category name
 * @return array Array of quotes in the given category
 */
function getQuotesByCategory($category)
{
    $db = getDB();
    $result = $db->query("SELECT * FROM quotes WHERE category = ? ORDER BY RANDOM()", [$category]);
    $quotes = [];   

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $quotes[] = $row;
    }

    $db->close();
    return $quotes;
}

/**
 * Ensures the quotes table exists and is properly structured
 *
 * @param SQLite3 $db Database connection (optional, will create one if not provided)
 * @return bool True if table exists or was created successfully
 */
function ensureQuotesTableExists($db = null)
{
    $shouldClose = false;
    if ($db === null) {
        $db = getDB();
        $shouldClose = true;
    }
    
    try {
        // Check if table exists
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='quotes'");
        $tableExists = $result->fetchArray();
        
        if (!$tableExists) {
            // Table doesn't exist, create it
            create_table($db);
        }
        
        if ($shouldClose) {
            $db->close();
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error ensuring quotes table exists: " . $e->getMessage());
        if ($shouldClose) {
            $db->close();
        }
        return false;
    }
}