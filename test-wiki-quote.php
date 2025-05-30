<?php
// test-wiki-quote.php - A simple test script to verify WikiquoteFetcher handling of HTML entities

require_once __DIR__ . '/.github/scripts/hourly.php';

$wikiquoteFetcher = new WikiquoteFetcher();
$quote = $wikiquoteFetcher->fetchRandomWikiQuote();

echo "Quote fetched from Wikiquote:\n";
echo "Quote: " . $quote['quote'] . "\n";
echo "Author (should be clean, no HTML): " . $quote['author'] . "\n";

// Test with a mock HTML containing HTML entities
$mockAuthor = '&lt;a href=&quot;/wiki/author&quot; title=&quot;Author&quot;&gt;Author Name&lt;/a&gt;';
echo "\nTesting HTML entity cleaning:\n";
echo "Original: " . $mockAuthor . "\n";
echo "Cleaned: " . strip_tags(html_entity_decode($mockAuthor)) . "\n";
