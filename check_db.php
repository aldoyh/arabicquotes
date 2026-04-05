<?php
require_once 'inc/db-utils.php';

$db = getDB();
if (!$db) {
    echo "Failed to connect to DB\n";
    exit(1);
}

echo "Connected to DB\n";

// Ensure table exists
create_table($db);

$result = $db->query('SELECT COUNT(*) as count FROM quotes');
if (!$result) {
    echo "No table or error\n";
    exit(1);
}

$row = $result->fetchArray(SQLITE3_ASSOC);
echo "Total quotes: " . $row['count'] . "\n";

$result = $db->query('SELECT * FROM quotes LIMIT 5');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "ID: " . $row['id'] . " - " . substr($row['quote'], 0, 50) . "...\n";
}
?>