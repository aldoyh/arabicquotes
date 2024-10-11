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
 * pulls latest gists from a github repo
 * 
 * @param string $owner
 * @param string $repo
 * @return array
 */
function getLatestGists()
{
    // $url = "XXXXXXXXXXXXXXXXXXXXXXXXXXXXX$owner/$repo/issues";
    $url = "https://api.github.com/gists";
    $headers = [
        // 'Authorization: Bearer ' . $token,
        'User-Agent: PHP'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // set cache
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    $response = curl_exec($ch);
    if (!$response) {
        die(curl_error($ch));
    }
    curl_close($ch);

    return json_decode($response, true);
}

if (!empty($payload)) {
    addToDatabase($payload);
} else {
    $gists = getLatestGists();
    foreach ($gists as $gist) {
        if (empty($gist['url'])) {

            // colored in red
            echo "\033[31m" . $gist['description'] . "\033[0m" . PHP_EOL;
        } else {

            // colored in green
            echo "\033[32m" . $gist['description'] . "\033[0m" . PHP_EOL;
            echo PHP_EOL
                . " 🚛 " . $gist['description']
                . "    🔗 " . $gist['url']
                . PHP_EOL;
        }
        // addToDatabase($payload);
    };
}

/**
 * Add to MySQL database
 *
 * @param array $jsonPayload
 *
 */
function addToDatabase($jsonPayload)
{
    $db = getDB();
    if (!$db) {
        error_log("Failed to connect to the database");
        return;
    }

    foreach ($jsonPayload as $quote) {
        $query = "INSERT INTO quotes (quote) VALUES (:quote)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':quote', $quote['quote']);
        $stmt->execute();
    }

    $db->close();
}
