<?php

require_once __DIR__ . '/../inc/db-utils.php';

$url = $argv[1] ?? 'https://huggingface.co/datasets/HeshamHaroon/arabic-quotes/resolve/main/arabic_Q.jsonl';
$source = 'HeshamHaroon/arabic-quotes';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: arabicquotes-importer/1.0\r\n",
        'timeout' => 60,
    ],
]);

$handle = fopen($url, 'r', false, $context);
if (!$handle) {
    fwrite(STDERR, "Failed to open dataset: {$url}\n");
    exit(1);
}

$batch = [];
$stats = ['read' => 0, 'inserted' => 0, 'skipped' => 0, 'invalid' => 0];

while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $stats['read']++;
    $row = json_decode($line, true);
    if (!is_array($row)) {
        $stats['invalid']++;
        continue;
    }

    $batch[] = [
        'quote' => $row['quote'] ?? '',
        'author' => $row['author'] ?? 'غير معروف',
        'category' => $row['tags'] ?? 'General',
        'source' => $source,
        'hits' => 0,
    ];

    if (count($batch) >= 500) {
        $result = addQuotesToDatabase($batch, $source);
        $stats['inserted'] += $result['inserted'];
        $stats['skipped'] += $result['skipped'];
        $stats['invalid'] += $result['invalid'];
        $batch = [];
    }
}

fclose($handle);

if ($batch) {
    $result = addQuotesToDatabase($batch, $source);
    $stats['inserted'] += $result['inserted'];
    $stats['skipped'] += $result['skipped'];
    $stats['invalid'] += $result['invalid'];
}

exportQuotesToJson();

$db = getDB();
$total = (int) $db->querySingle('SELECT COUNT(*) FROM quotes');
$authors = (int) $db->querySingle("SELECT COUNT(DISTINCT author) FROM quotes WHERE author IS NOT NULL AND author != ''");
$db->close();

echo json_encode([
    'dataset' => $source,
    'url' => $url,
    'read' => $stats['read'],
    'inserted' => $stats['inserted'],
    'skipped_duplicates' => $stats['skipped'],
    'invalid' => $stats['invalid'],
    'total_quotes' => $total,
    'total_authors' => $authors,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
