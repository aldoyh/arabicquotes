<?php

// Ensure all necessary components are loaded.
// db-utils.php is needed by QuoteManager in hourly.php
require_once __DIR__ . '/inc/db-utils.php';
// hourly.php contains the primary QuoteManager and WikiquoteFetcher definitions
require_once __DIR__ . '/.github/scripts/hourly.php';

error_reporting(E_ALL);
// error_log("Current directory (index.php): " . __DIR__); // Optional logging

// The QuoteManager class is now defined in hourly.php and uses assets/QuotesDB.sqlite3

// The main execution block of index.php.
// This block currently calls updateReadme() from the (now external) QuoteManager.
// This might be redundant if hydrate-quote.php is the primary script for this action.
// For now, let it use the correct QuoteManager.
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    error_log("index.php executed as main script.");
    try {
        // QuoteManager is now from hourly.php, which uses assets/QuotesDB.sqlite3
        // and whose getRandomQuote() increments hits in that DB.
        $quoteManager = new QuoteManager();

        // The updateReadme method in the hourly.php QuoteManager fetches a random quote
        // (incrementing its hits in assets/QuotesDB.sqlite3) and updates README.md.
        $updatedQuote = $quoteManager->updateReadme();

        if ($updatedQuote) {
            echo "✅ (index.php) README.md updated successfully with a quote from SQLite DB!\n";
            // echo json_encode($updatedQuote, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "❌ (index.php) Failed to update README.md.\n";
        }
    } catch (Exception $e) {
        error_log("Error in main execution of index.php: " . $e->getMessage());
        echo "❌ (index.php) Error: " . $e->getMessage() . "\n";
    }
} else {
    // error_log("index.php included, not run as main script."); // Optional logging
}

// Any other code that was in index.php and relied on its local QuoteManager
// would need to be adjusted or is now implicitly using the global QuoteManager from hourly.php.
// For example, if index.php was meant to render HTML directly, that logic would use the
// QuoteManager from hourly.php. The current index.php doesn't seem to have direct HTML output
// beyond what its main block does.

?>