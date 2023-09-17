<?php
// Get environment variables
$issueTitle = getenv('ISSUE_TITLE');
$issueBody = getenv('ISSUE_BODY');

// Check if the issue title matches the pattern
if (preg_match('/^ARQUOTES\|New$/', $issueTitle)) {
    // Split the issue body into lines
    $lines = explode("\n", $issueBody);

    // Initialize an array to hold the JSON payload
    $payload = [];

    // Process each line
    foreach ($lines as $line) {
        if (!empty($line)) {
            $payload[] = ['quote' => $line];
        }
    }

    // Convert the payload to JSON
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

    // TODO: Send the JSON payload to your endpoint to add it to the MySQL database
}

/**
 * Add to MySQL database
 *
 * @param string $jsonPayload
 *
 */
function addToDatabase($jsonPayload) {
    // TODO: Add the JSON payload to the MySQL database

    
}

?>
