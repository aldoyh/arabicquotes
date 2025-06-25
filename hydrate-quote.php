<?php
require_once __DIR__ . '/index.php'; // This should include QuoteManager via hourly.php
require_once __DIR__ . '/inc/db-utils.php'; // For getDB, ensureQuotesTableExists if QuoteManager needs it explicitly

// If QuoteManager's constructor or getRandomQuote ensures DB & table existence,
// explicit calls here might be redundant. Assuming QuoteManager handles this.

try {
    // QuoteManager is defined in .github/scripts/hourly.php, included via index.php
    // It's configured to use assets/QuotesDB.sqlite3 and increment hits.
    $quoteManager = new QuoteManager();
    $selectedQuote = $quoteManager->getRandomQuote();

    if (!$selectedQuote) {
        // If the database is empty and this is the first run, this might happen.
        // The scrape_and_store_quotes.php script is responsible for populating the DB.
        // For robust display, one might want a fallback quote here, or ensure scraper runs first.
        throw new Exception('Failed to get random quote');
    }

    // Update README.md
    $readmePath = __DIR__ . '/README.md';
    if (file_exists($readmePath)) {
        $readmeContent = file_get_contents($readmePath);
        $quoteMarkdown = $quoteManager->generateQuoteMarkdown($selectedQuote);
        $updatedContent = preg_replace(
            '/<!-- QUOTE:START -->.*?<!-- QUOTE:END -->/s',
            "<!-- QUOTE:START -->\n" . $quoteMarkdown . "\n<!-- QUOTE:END -->",
            $readmeContent
        );
        file_put_contents($readmePath, $updatedContent);
    }

    // Export to index.html for GH Pages
    $htmlPath = __DIR__ . '/index.html';
    $quoteHtml = $quoteManager->generateQuoteHtml($selectedQuote);
    if ($quoteHtml && file_exists($htmlPath)) {
        $htmlContent = file_get_contents($htmlPath);
        // Replace the quote section in index.html with improved pattern matching
        $updatedHtml = preg_replace(
            '/<div class="w-full max-w-2xl quote-card.*?<div class="text-3xl.*?>(.*?)<\/div>.*?<div class="text-xl.*?>(.*?)<\/div>.*?<div class="text-sm.*?<p>(.*?)<\/p>/s',
            '<div class="w-full max-w-2xl quote-card rounded-xl p-8 mb-6 border-r-4 border-amber-500 dark:border-amber-600">
                <div class="text-3xl font-bold text-gray-800 dark:text-amber-50 mb-6 text-center leading-relaxed quote-text">
                    ' . htmlspecialchars($selectedQuote['quote']) . '
                </div>
                <div class="text-xl font-semibold text-amber-700 dark:text-amber-300 text-center author-text">
                    — ' . htmlspecialchars($selectedQuote['author']) . '
                </div>
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400 italic">
                <p>اليوم: ' . date('l jS \of F Y - H:i') . ' 🎯 المشاهدات: ' . $selectedQuote['hits'] . '</p>',
            $htmlContent
        );
        file_put_contents($htmlPath, $updatedHtml);
    }

    // Optionally log the update
    $quoteManager->logQuoteUpdate('Hydrated quote for CI/CD and GH Pages.');
    echo "✅ Hydration complete. README.md and index.html updated.\n";
} catch (Exception $e) {
    fwrite(STDERR, "❌ Hydration failed: " . $e->getMessage() . "\n");
    exit(1);
}
