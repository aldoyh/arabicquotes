<?php

// Setup test environment
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once __DIR__ . '/../calIngest.php';
require_once __DIR__ . '/../inc/db-utils.php';
require_once __DIR__ . '/../.github/scripts/hourly.php';

// Setup test database
defined('TEST_MODE') or define('TEST_MODE', true);
defined('TEST_DB_FILE') or define('TEST_DB_FILE', 'test_quotes.db');

// Create test tables if needed
if (!file_exists(TEST_DB_FILE)) {
    $db = new SQLite3(TEST_DB_FILE);
    create_table($db);
    
    // Add some test data
    $db->exec("INSERT INTO quotes (quote, author, hits) VALUES ('Test quote 1', 'Author 1', 0)");
    $db->exec("INSERT INTO quotes (quote, author, hits) VALUES ('Test quote 2', 'Author 2', 5)");
    $db->close();
}

// Clean up function
register_shutdown_function(function() {
    if (file_exists(TEST_DB_FILE)) {
        unlink(TEST_DB_FILE);
    }
});