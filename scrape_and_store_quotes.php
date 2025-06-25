<?php

require_once __DIR__ . '/vendor/autoload.php'; // Assuming PSR-4 autoloader
require_once __DIR__ . '/inc/db-utils.php';
require_once __DIR__ . '/.github/scripts/hourly.php'; // Include hourly.php to access WikiquoteFetcher

echo "Starting to scrape and store quotes...\n";

try {
    // PRE-SCRAPE: Configure Git and ensure we are on a branch
    echo "Configuring Git and checking out branch...\n";
    $output = [];
    $return_var = 0;
    $checkoutBranch = ''; // Initialize to be used later for push command

    // Configure git user
    $gitConfigUserCmd = "git config user.name 'Automated Scraper'";
    $gitConfigEmailCmd = "git config user.email 'scraper@example.com'";
    exec($gitConfigUserCmd, $output, $return_var); // Errors here are not usually fatal
    $output = [];
    exec($gitConfigEmailCmd, $output, $return_var);
    $output = [];
    echo "Git user configured.\n";

    // Determine current branch or switch to a default one
    $getCurrentBranchCmd = "git rev-parse --abbrev-ref HEAD";
    exec($getCurrentBranchCmd, $currentBranchOutput, $return_var_branch);
    $currentBranch = ($return_var_branch === 0 && !empty($currentBranchOutput[0]) && $currentBranchOutput[0] !== 'HEAD') ? $currentBranchOutput[0] : '';

    if (empty($currentBranch) || $currentBranch === 'HEAD') {
        echo "Currently in a detached HEAD state. Attempting to checkout 'main'.\n";
        $checkoutBranch = 'main'; // Default branch to try
        // Attempt to checkout existing branch first, then create if it doesn't exist
        $gitCheckoutCmd = "git checkout $checkoutBranch 2>/dev/null || git checkout -b $checkoutBranch";
        exec($gitCheckoutCmd, $output, $return_var);
        if ($return_var !== 0) {
            echo "Failed to checkout 'main', trying 'master'. Output: " . implode("\n", $output) . "\n";
            $output = [];
            $checkoutBranch = 'master';
            $gitCheckoutCmd = "git checkout $checkoutBranch 2>/dev/null || git checkout -b $checkoutBranch";
            exec($gitCheckoutCmd, $output, $return_var);
            if ($return_var !== 0) {
                throw new Exception("Failed to checkout a working branch (tried 'main' and 'master'). Output: " . implode("\n", $output));
            }
        }
        echo "Switched to branch '$checkoutBranch'.\n";
    } else {
        $checkoutBranch = $currentBranch; // Use the existing branch
        echo "Already on branch '$checkoutBranch'.\n";
    }
    $output = []; // Clear output

    // 1. Fetch a quote using WikiquoteFetcher
    echo "Fetching quote...\n";
    // Use shorter timeout for testing in sandbox environment
    $fetcher = new WikiquoteFetcher(2, 5); // 2 retries, 5 second timeout
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
        throw new Exception("Failed to connect to the database.");
    }
    echo "Successfully connected to the database.\n";

    // Ensure the 'quotes' table exists
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
    $head = mb_substr($quoteText, 0, 25) . "...";
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

    // 4. Commit and push changes to the repository
    echo "Attempting to commit and push database changes...\n";
    $dbRepoRelativePath = "assets/QuotesDB.db"; // Path relative to repository root
    $commitMessage = "Update quotes database with new scrape on " . date("Y-m-d H:i:s");

    $gitAddCommand = "git add " . escapeshellarg($dbRepoRelativePath);
    $gitCommitCommand = "git commit -m " . escapeshellarg($commitMessage);
    // $checkoutBranch should be set from the Git configuration logic above
    $gitPushCommand = "git push --set-upstream origin " . escapeshellarg($checkoutBranch);

    $output = []; // Reset output array for git commands
    $return_var = 0;

    // Git Add
    echo "Executing: $gitAddCommand\n";
    exec($gitAddCommand, $output, $return_var);
    if ($return_var !== 0) {
        throw new Exception("Failed to git add database file. Output: " . implode("\n", $output));
    }
    echo "Git add successful.\n";
    $output = [];

    // Git Commit
    echo "Executing: $gitCommitCommand\n";
    exec($gitCommitCommand, $output, $return_var);
    if ($return_var !== 0) {
        if (strpos(implode("\n", $output), "nothing to commit") === false && strpos(implode("\n", $output), "no changes added to commit") === false) {
            throw new Exception("Failed to git commit database changes. Output: " . implode("\n", $output));
        }
        echo "Git commit: No changes to commit or already up to date.\n";
    } else {
        echo "Git commit successful.\n";
    }
    $output = [];

    // Git Push
    echo "Executing: $gitPushCommand\n";
    exec($gitPushCommand, $output, $return_var);
    if ($return_var !== 0) {
        // Check for common non-fatal push messages if commit didn't happen
        if (strpos(implode("\n", $output), "Everything up-to-date") !== false || strpos(implode("\n", $output), "HEAD -> FETCH_HEAD") !== false ) {
             echo "Git push: Everything up-to-date or no new commits to push.\n";
        } else {
            throw new Exception("Failed to git push changes. Output: " . implode("\n", $output));
        }
    } else {
        echo "Git push successful.\n";
    }
    echo "Database changes committed and pushed to the repository.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("scrape_and_store_quotes.php Error: " . $e->getMessage());
    if (isset($db) && $db instanceof SQLite3) { // Check if $db is valid SQLite3 object
        $db->close();
        echo "Database connection closed due to error.\n";
    }
    exit(1);
}

echo "Script finished.\n";

?>
