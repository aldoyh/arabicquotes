<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/db-utils.php';
// WikiquoteFetcher class definition is in hourly.php
require_once __DIR__ . '/.github/scripts/hourly.php';

echo "Starting to scrape and store quotes...\n";

try {
    // PRE-SCRAPE: Configure Git and ensure we are on a branch
    echo "Configuring Git and checking out branch...\n";
    $output = [];
    $return_var = 0;
    $checkoutBranch = '';

    $gitConfigUserCmd = "git config user.name 'Automated Scraper'";
    $gitConfigEmailCmd = "git config user.email 'scraper@example.com'";
    exec($gitConfigUserCmd, $output, $return_var);
    $output = [];
    exec($gitConfigEmailCmd, $output, $return_var);
    $output = [];
    echo "Git user configured.\n";

    $getCurrentBranchCmd = "git rev-parse --abbrev-ref HEAD";
    exec($getCurrentBranchCmd, $currentBranchOutput, $return_var_branch);
    $currentBranchName = ($return_var_branch === 0 && !empty($currentBranchOutput[0]) && $currentBranchOutput[0] !== 'HEAD') ? $currentBranchOutput[0] : '';

    if (empty($currentBranchName) || $currentBranchName === 'HEAD') {
        echo "Currently in a detached HEAD state. Attempting to checkout 'main'.\n";
        $checkoutBranch = 'main';
        // Try to checkout existing branch, if fail, create it. Suppress stderr for the first checkout attempt.
        $gitCheckoutCmd = "git checkout $checkoutBranch 2>/dev/null || git checkout -b $checkoutBranch";
        exec($gitCheckoutCmd, $output, $return_var);
        if ($return_var !== 0) {
            echo "Failed to checkout 'main', trying 'master'. Output: " . implode("\n", $output) . "\n";
            $output = []; // Clear output before next attempt
            $checkoutBranch = 'master';
            $gitCheckoutCmd = "git checkout $checkoutBranch 2>/dev/null || git checkout -b $checkoutBranch";
            exec($gitCheckoutCmd, $output, $return_var);
            if ($return_var !== 0) {
                throw new Exception("Failed to checkout a working branch (tried 'main' and 'master'). Output: " . implode("\n", $output));
            }
        }
        echo "Switched to branch '$checkoutBranch'.\n";
    } else {
        $checkoutBranch = $currentBranchName;
        echo "Already on branch '$checkoutBranch'.\n";
    }
    $output = []; // Clear output array

    // 1. Fetch a quote using WikiquoteFetcher
    echo "Fetching quote...\n";
    $fetcher = new WikiquoteFetcher(2, 5); // 2 retries, 5 sec timeout. HTML stripping is within WikiquoteFetcher.
    $fetchedQuote = $fetcher->fetchRandomWikiQuote();

    if (!$fetchedQuote || !isset($fetchedQuote['quote']) || !isset($fetchedQuote['author'])) {
        throw new Exception("Failed to fetch a valid quote or quote structure is incorrect.");
    }
    $quoteText = $fetchedQuote['quote'];
    $quoteAuthor = $fetchedQuote['author'];
    echo "Successfully fetched quote: \"{$quoteText}\" by {$quoteAuthor}\n";

    // 2. Connect to the database
    echo "Connecting to database...\n";
    $db = getDB();
    if (!$db) {
        throw new Exception("Failed to connect to the database. DB_PATH_USED_BY_SCRIPT: " . DB_NAME);
    }
    echo "Successfully connected to the database.\n";

    // ensureQuotesTableExists is defined in db-utils.php
    if (!ensureQuotesTableExists($db)) {
        throw new Exception("Failed to ensure the 'quotes' table exists. Check logs for details.");
    }
    echo "Ensured 'quotes' table exists.\n";

    // 3. Prepare and execute SQL INSERT statement
    echo "Inserting quote into database...\n";
    $stmt = $db->prepare("INSERT INTO quotes (quote, author, head) VALUES (:quote, :author, :head)");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $db->lastErrorMsg());
    }
    // Use mb_substr for UTF-8 strings
    $head = mb_substr($quoteText, 0, 25, 'UTF-8') . "...";
    $stmt->bindValue(':quote', $quoteText, SQLITE3_TEXT);
    $stmt->bindValue(':author', $quoteAuthor, SQLITE3_TEXT);
    $stmt->bindValue(':head', $head, SQLITE3_TEXT);
    $result = $stmt->execute();
    if (!$result) {
        throw new Exception("Failed to execute statement: " . $db->lastErrorMsg());
    }
    echo "Successfully inserted quote into the database. Last inserted row ID: " . $db->lastInsertRowID() . "\n";
    $db->close();
    echo "Database connection closed.\n";

    // 4. Commit and push changes
    echo "Attempting to commit and push database changes...\n";
    $dbRepoRelativePath = "assets/QuotesDB.db"; // Path relative to repo root for git
    $commitMessage = "Update quotes database with new scrape on " . date("Y-m-d H:i:s");

    $gitAddCommand = "git add " . escapeshellarg($dbRepoRelativePath);
    $gitCommitCommand = "git commit -m " . escapeshellarg($commitMessage);
    $gitPushCommand = "git push --set-upstream origin " . escapeshellarg($checkoutBranch);

    // Reset output array before exec calls for git
    $output = [];
    exec($gitAddCommand, $output, $return_var);
    if ($return_var !== 0) {
        throw new Exception("Failed to git add database file. Output: " . implode("\n", $output));
    }
    echo "Git add successful.\n";
    $output = [];

    exec($gitCommitCommand, $output, $return_var);
    if ($return_var !== 0) {
        // Check for "nothing to commit" or "no changes added to commit"
        if (strpos(implode("\n", $output), "nothing to commit") === false && strpos(implode("\n", $output), "no changes added to commit") === false) {
            throw new Exception("Failed to git commit database changes. Output: " . implode("\n", $output));
        }
        echo "Git commit: No changes to commit or already up to date.\n";
    } else {
        echo "Git commit successful.\n";
    }
    $output = [];

    exec($gitPushCommand, $output, $return_var);
    if ($return_var !== 0) {
        // Check for common non-fatal push messages if commit didn't happen or auth failed
        if (strpos(implode("\n", $output), "Everything up-to-date") !== false ||
            strpos(implode("\n", $output), "fatal: Authentication failed") !== false ||
            strpos(implode("\n", $output), "remote: No anonymous write access.") !== false ) {
             echo "Git push: Up-to-date or authentication failure (expected in sandbox). Output: " . implode("\n", $output) . "\n";
        } else {
            throw new Exception("Failed to git push changes. Output: " . implode("\n", $output));
        }
    } else {
        echo "Git push successful.\n";
    }
    echo "Database changes processed for repository.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("scrape_and_store_quotes.php Error: " . $e->getMessage());
    if (isset($db) && $db instanceof SQLite3) {
        $db->close();
        echo "Database connection closed due to error.\n";
    }
    exit(1);
}
echo "Script finished.\n";
?>
