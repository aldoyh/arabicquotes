<?php
/**
 * One-time migration: re-run cleanQuoteText() over every existing row.
 *
 * Older rows were inserted before cleanQuoteText() stripped <style>/<script>
 * content and tatweel/zero-width characters, so their quote_key/author_key
 * hashes are stale too. This re-cleans quote/author/head/category, recomputes
 * the dedup keys, and merges any duplicates that purification exposes (e.g.
 * two variants that only differed by a tatweel now normalize identically).
 */

require_once __DIR__ . '/../inc/db-utils.php';

$db = getDB();

$result = $db->query('SELECT id, head, quote, author, category FROM quotes ORDER BY id');
$rows = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $rows[] = $row;
}

$db->exec('BEGIN TRANSACTION');

$updated = 0;
$emptied = 0;
$stmt = $db->prepare('UPDATE quotes SET quote = :quote, author = :author, head = :head, category = :category, quote_key = :quote_key, author_key = :author_key WHERE id = :id');

foreach ($rows as $row) {
    $quote = cleanQuoteText($row['quote']);
    $author = cleanQuoteText($row['author']);
    $head = cleanQuoteText($row['head']);
    $category = cleanQuoteText($row['category']) ?: 'General';

    if ($quote === '') {
        // Shouldn't happen (prepareQuoteRow already guards this on insert),
        // but don't silently corrupt a row into an empty quote.
        $emptied++;
        continue;
    }

    if ($quote !== $row['quote'] || $author !== $row['author'] || $head !== $row['head'] || $category !== $row['category']) {
        $stmt->bindValue(':quote', $quote, SQLITE3_TEXT);
        $stmt->bindValue(':author', $author, SQLITE3_TEXT);
        $stmt->bindValue(':head', $head, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $stmt->bindValue(':quote_key', quoteHash($quote), SQLITE3_TEXT);
        $stmt->bindValue(':author_key', quoteHash($author), SQLITE3_TEXT);
        $stmt->bindValue(':id', (int) $row['id'], SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->reset();
        $updated++;
    }
}

$db->exec('COMMIT');

// Purification can make two previously-distinct rows normalize to the same
// quote_key/author_key (e.g. two tatweel variants of the same proverb).
removeDuplicateQuotes($db);

$countQuery = $db->query('SELECT COUNT(*) AS count FROM quotes');
$remaining = $countQuery->fetchArray(SQLITE3_ASSOC)['count'];

$db->close();

echo "Purified {$updated} rows.\n";
if ($emptied > 0) {
    echo "Skipped {$emptied} rows that would have become empty.\n";
}
echo "Remaining quotes: {$remaining}\n";
