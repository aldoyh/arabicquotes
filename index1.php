<?php
if ($quoteHtml && file_exists($htmlPath)) {
        $htmlContent = file_get_contents($htmlPath);
        // Replace the quote section in index.html with improved pattern matching
        $updatedHtml = preg_replace(
            '/<div class="w-full max-w-2xl quote-card.*?<div class="text-3xl.*?>(.*?)<\/div>.*?<div class="text-xl.*?>(.*?)<\/div>.*?<div class="text-sm.*?<p>(.*?)<\/p>/s',
            '<div class="w-full max-w-2xl quote-card rounded-xl p-8 mb-6 border-r-4 border-amber-500 dark:border-amber-600">
                <div class="text-3xl font-bold text-gray-800 dark:text-amber-50 mb-6 text-center leading-relaxed quote-text">'
                    . $selectedQuote['quote'] . '
                </div>
                <div class="text-xl font-semibold text-amber-700 dark:text-amber-300 text-center author-text">'
                    . $selectedQuote['author'] . '
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400 italic">
                    <p>اليوم: ' . date('l jS \of F Y - H:i') . ' 🎯 المشاهدات: ' . $selectedQuote['hits'] . '</p>
                </div>
            </div>',
            $htmlContent
        );
        // ...rest of your logic (e.g., file_put_contents($htmlPath, $updatedHtml);)
    }