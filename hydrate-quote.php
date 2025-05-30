<?php
require_once __DIR__ . '/index.php';

try {
    $quoteManager = new QuoteManager();
    $selectedQuote = $quoteManager->getRandomQuote();
    if (!$selectedQuote) {
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
        // Replace the quote section in index.html (customize selector as needed)
        $updatedHtml = preg_replace(
            '/(<h2 class=\"text-lg font-bold\">ما قيل من خيرة العرب اليوم:[^<]*<\/h2>)([\s\S]*?)(<\/div>\s*<\/div>)/u',
            '$1' . "\n" . $quoteHtml . "\n" . '$3',
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
