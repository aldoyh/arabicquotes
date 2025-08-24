<?php

/**
 * @package ma-qeal
 * @subpackage index.php
 * @since ma-qeal 1.0
 */

error_reporting(E_ALL);

/**
 * Quote Manager
 *
 * This class manages quotes, including fetching random quotes, 
 * updating the README file with a new quote, and logging updates.
 */
class QuoteManager
{
    /**
     * Get the last N valid daily quotes from the deployment log.
     *
     * @param int $n Number of days/quotes to retrieve.
     * @return array Array of ['date' => ..., 'quote' => ..., 'author' => ...]
     */
    public function getLastNDailyQuotes($n = 5)
    {
        $logPath = $this->basePath . "assets/DEPLOYMENT.log";
        if (!file_exists($logPath)) return [];
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $quotes = [];
        $seenDates = [];
        for ($i = count($lines) - 1; $i >= 0 && count($quotes) < $n; $i--) {
            $line = $lines[$i];
            if (preg_match('/^(\d{4}-\d{2}-\d{2}) \d{2}:\d{2}:\d{2} - (.+?) - — (.+)$/u', $line, $m)) {
                $date = $m[1];
                $quote = trim($m[2]);
                $author = trim($m[3]);
                if (!isset($seenDates[$date])) {
                    $quotes[] = [
                        'date' => $date,
                        'quote' => $quote,
                        'author' => $author
                    ];
                    $seenDates[$date] = true;
                }
            }
        }
        return array_reverse($quotes);
    }

    private $basePath = '';

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->basePath = __DIR__ . "/../../";
    }

    /**
     * Selects a random quote from the JSON file.
     *
     * @return array|null The random quote, or null if an error occurs.
     */
    public function getRandomQuote()
    {
        $quotes = json_decode(file_get_contents($this->basePath . "assets/quotes.json"), true);
        if (!$quotes) {
            error_log('Error opening json file.');
            return null;
        }
        return $quotes[array_rand($quotes)];
    }

    /**
     * Logs the new Quote of the day.
     *
     * @param string $logMessage The message to log.
     */
    public function logQuoteUpdate($logMessage)
    {
        $logEntry = date('Y-m-d H:i:s') . " - " . $logMessage . "\n";
        $result = file_put_contents($this->basePath . "assets/DEPLOYMENT.log", $logEntry, FILE_APPEND);
        return $result !== false;
    }

    /**
     * Updates the README.md file with a new quote.
     *
     * @param array $selectedQuote The selected quote.
     * @return bool True on success, false on failure.
     */
    public function updateReadme($selectedQuote)
    {
        if (!$selectedQuote) {
            error_log("No quote found");
            return false;
        }

        $quoteMarkdown = $this->generateQuoteMarkdown($selectedQuote);
        $readmePath = $this->basePath . "README.md";

        if (!file_exists($readmePath)) {
            error_log("README.md file not found");
            return false;
        }

        $readmeContent = file_get_contents($readmePath);
        if (!$readmeContent) {
            error_log("Failed to read README.md");
            return false;
        }

     
        $updatedContent = preg_replace(
            '/<!-- QUOTE:START -->.*?<!-- QUOTE:END -->/s',
            "<!-- QUOTE:START -->\n" . $quoteMarkdown . "\n<!-- QUOTE:END -->",
            $readmeContent
        );

        if ($updatedContent === null || $updatedContent === $readmeContent) {
            error_log("Failed to replace quote section");
            return false;
        }

        if (file_put_contents($readmePath, $updatedContent) === false) {
            error_log("Failed to write to README.md");
            return false;
        }

        $this->logQuoteUpdate($selectedQuote['quote'] . " - " . $selectedQuote['author']);
        return true;
    }

    /**
     * Generates Markdown for the quote.
     *
     * @param array $quote The quote data.
     * @return string The Markdown representation of the quote.
     */
    public function generateQuoteMarkdown($quote)
    {
        // Clean up quote text by removing newlines and extra spaces
        $cleanQuote = preg_replace('/\s+/', ' ', trim($quote['quote']));
        $cleanAuthor = preg_replace('/\s+/', ' ', trim($quote['author']));

        $quoteMarkdown = PHP_EOL . "# " . $cleanQuote . PHP_EOL . PHP_EOL . "- " . $cleanAuthor . PHP_EOL . PHP_EOL;
        if (isset($quote['image'])) {
            $quoteMarkdown .= PHP_EOL . "![Quote Image](" . $quote['image'] . ")";
        }
        return $quoteMarkdown;
    }

    /**
     * Generates HTML for the quote.
     *
     * @param array $quote The quote data.
     * @return string The HTML representation of the quote.
     */
    public function generateQuoteHtml($quote)
    {
        return '
        <div class="flex flex-col items-center">
            <div class="w-full max-w-2xl bg-amber-50 dark:bg-gray-700 rounded-lg p-8 mb-6 border-r-4 border-amber-500">
                <div class="text-3xl font-bold text-gray-800 dark:text-white mb-6 text-center leading-relaxed" dir="rtl">
                    ' . htmlspecialchars($quote['quote']) . '
                </div>
                <div class="text-xl font-semibold text-amber-700 dark:text-amber-300 text-center" dir="rtl">
                    — ' . htmlspecialchars($quote['author']) . '
                </div>
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400 italic">
                <p>اليوم: ' . date('l jS \of F Y - H:i') . ' 🎯 المشاهدات: ' . @$quote['hits'] . '</p>
            </div>
        </div>';
    }

    /**
     * Updates the index.html file with a new quote.
     *
     * @param array $selectedQuote The selected quote.
     * @return bool True on success, false on failure.
     */
    public function updateIndexHtml($selectedQuote = null)
    {
        // Get last 5 daily quotes (most recent last)
        $quotes = $this->getLastNDailyQuotes(5);
        if (empty($quotes)) {
            error_log("No daily quotes found");
            return false;
        }

        // Generate HTML for slides
        $slidesHtml = '';
        foreach ($quotes as $i => $q) {
            $slidesHtml .= '<section class="quote-slide flex flex-col justify-center items-center min-h-screen w-full px-4 py-12" data-slide="' . $i . '">
                <div class="w-full max-w-2xl bg-amber-50 dark:bg-gray-700 rounded-lg p-8 mb-6 border-r-4 border-amber-500 shadow-xl quote-card animate-fade-in">
                    <div class="text-3xl font-bold text-gray-800 dark:text-white mb-6 text-center leading-relaxed quote-text" dir="rtl">' . $q['quote'] . '</div>
                    <div class="text-xl font-semibold text-amber-700 dark:text-amber-300 text-center author-text" dir="rtl">&mdash; ' . $q['author'] . '</div>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400 italic mb-2">' . $q['date'] . '</div>
            </section>';
        }

        // Add slider navigation (arrows and dots)
        $sliderNav = '<div class="flex justify-center items-center gap-4 mt-4">
            <button id="prev-slide" class="p-2 rounded-full bg-amber-200 dark:bg-gray-600 hover:bg-amber-300 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500" aria-label="السابق">&#8592;</button>
            <div id="slide-dots" class="flex gap-2">' .
            str_repeat('<span class="slide-dot w-3 h-3 rounded-full bg-amber-400 dark:bg-amber-700 opacity-50 cursor-pointer"></span>', count($quotes)) .
            '</div>
            <button id="next-slide" class="p-2 rounded-full bg-amber-200 dark:bg-gray-600 hover:bg-amber-300 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500" aria-label="التالي">&#8594;</button>
        </div>';

        $allSlides = '<div id="quote-slider" class="relative w-full overflow-hidden">' . $slidesHtml . $sliderNav . '</div>';

        $htmlPath = $this->basePath . "index.html";
        if (!file_exists($htmlPath)) {
            error_log("index.html file not found");
            return false;
        }
        $htmlContent = file_get_contents($htmlPath);
        if (!$htmlContent) {
            error_log("Failed to read index.html");
            return false;
        }
        // Replace the quote section in index.html
        $updatedHtml = preg_replace(
            '/<div id="quote-container">.*?<\/div>/s',
            '<div id="quote-container">' . $allSlides . '</div>',
            $htmlContent
        );
        if ($updatedHtml === null || $updatedHtml === $htmlContent) {
            error_log("Failed to replace quote section in index.html");
            return false;
        }
        if (file_put_contents($htmlPath, $updatedHtml) === false) {
            error_log("Failed to write to index.html");
            return false;
        }
        return true;
    }
}

/**
 * Wikiquote Fetcher
 *
 * This class fetches quotes from Wikiquote.
 */
class WikiquoteFetcher
{
    private $cacheFile = '';
    private $cacheTime = 24 * 60 * 60; // 24 hours

    public function __construct()
    {
        $this->cacheFile = __DIR__ . '/../../assets/wikiquote_cache.json';
    }

    /**
     * Fetches a random quote from Wikiquote, using cache if available.
     *
     * @return array|null The fetched quote, or null if an error occurs.
     */
    public function fetchRandomWikiQuote()
    {
        if (file_exists($this->cacheFile) && (time() - filemtime($this->cacheFile) < $this->cacheTime)) {
            $cachedQuote = json_decode(file_get_contents($this->cacheFile), true);
            if ($cachedQuote) {
                echo "Fetching quote from cache.\n";
                return $cachedQuote;
            }
        }

        echo "Fetching a new quote from Wikiquote.\n";
        $quote = $this->fetchFromWikiWithRetry();
        if ($quote) {
            file_put_contents($this->cacheFile, json_encode($quote));
        }
        return $quote;
    }

    /**
     * Fetches and parses a quote from Wikiquote with retry mechanism.
     *
     * @param int $retries Number of retries.
     * @param int $delay Delay between retries in seconds.
     * @return array|null The parsed quote, or null if an error occurs.
     */
    public function fetchFromWikiWithRetry($retries = 3, $delay = 2)
    {
        for ($i = 0; $i < $retries; $i++) {
            $html = $this->fetchRaw();
            if ($html) {
                $quote = $this->parseQuote($html);
                if ($quote) {
                    return $quote;
                }
            }
            sleep($delay * ($i + 1)); // Exponential backoff
        }
        return null;
    }

    /**
     * Parses the quote from the HTML content.
     *
     * @param string $html The HTML content.
     * @return array|null The parsed quote, or null if an error occurs.
     */
    private function parseQuote($html)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // More flexible XPath to find the quote
        $nodes = $xpath->query('//div[contains(@class, "mw-parser-output")]//table//td[3]');
        if ($nodes->length > 0) {
            $textContent = trim($nodes->item(0)->textContent);
            $lines = explode("\n", $textContent);
            $lines = array_filter($lines, 'trim');
            if (count($lines) >= 2) {
                return [
                    'quote' => trim($lines[0]),
                    'author' => trim($lines[count($lines) - 1])
                ];
            }
        }
        return null;
    }

    /**
     * Fetches raw HTML content from Wikiquote.
     *
     * @return string|null The HTML content, or null if an error occurs.
     */
    private function fetchRaw()
    {
        $url = "https://ar.wikiquote.org/wiki/%D8%A7%D9%84%D8%B5%D9%81%D8%AD%D8%A9_%D8%A7%D9%84%D8%B1%D8%A6%D9%8A%D8%B3%D9%8A%D8%A9";
        $context = stream_context_create([
            'http' => [
                'timeout' => 10, // 10 seconds timeout
            ],
        ]);
        return @file_get_contents($url, false, $context);
    }
}


// Main execution

require_once __DIR__ . '/update-quote.php';

try {
    $updater = new QuoteUpdater();
    $updatedQuote = $updater->updateQuoteOfTheDay();
    if ($updatedQuote) {
        echo "✅ Quote of the day updated successfully!\n";
    } else {
        echo "❌ Failed to update quote of the day.\n";
    }
} catch (Exception $e) {
    error_log("Error in main execution: " . $e->getMessage());
    echo "❌ Error: " . $e->getMessage() . "\n";
}
