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
        file_put_contents($this->basePath . "assets/DEPLOYMENT.log", $logEntry, FILE_APPEND);
    }

    /**
     * Updates the README.md file with a new quote.
     *
     * @return array|false The selected quote, or false if an error occurs.
     */
    public function updateReadme()
    {
        $selectedQuote = $this->getRandomQuote();

        if (!$selectedQuote) {
            error_log("No quote found");
            return false;
        }

        $selectedQuote['hits']++;
        $selectedQuote['quote'] = str_replace("\n", " ", $selectedQuote['quote']);

        $quoteMarkdown = $this->generateQuoteMarkdown($selectedQuote);

        $readmePath = $this->basePath . "README.md";
        if (!file_exists($readmePath)) {
            error_log("README.md file not found");
            return false;
        }

        $readmeContent = file_get_contents($readmePath);
        if (!$readmeContent) {
            error_log("No README.md file found");
            return false;
        }

        $updatedReadme = preg_replace(
            "/<!-- QUOTE:START -->.*?<!-- QUOTE:END -->/s",
            $quoteMarkdown,
            $readmeContent
        );

        file_put_contents($readmePath, $updatedReadme);
        $this->logQuoteUpdate($selectedQuote['id'] . " - " . $selectedQuote['hits']);

        return $selectedQuote;
    }

    /**
     * Generates Markdown for the quote.
     *
     * @param array $quote The quote data.
     * @return string The Markdown representation of the quote.
     */
    private function generateQuoteMarkdown($quote)
    {
        $quoteMarkdown = PHP_EOL . "# " . $quote['quote'] . PHP_EOL . PHP_EOL . "- " . $quote['author'] . PHP_EOL . PHP_EOL;
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
        <div class="flex justify-center mt-16 px-0 sm:items-center sm:justify-between quote-of-the-day">
            <div class="flex flex-col items-center w-full max-w-xl px-4 py-8 mx-auto bg-white rounded-lg shadow dark:bg-gray-800 sm:px-6 md:px-8 lg:px-10">
                <div class="text-center text-sm text-gray-500 dark:text-gray-400 sm:text-right">
                    <div class="flex items-center gap-4">
                        <div class="quote-header">
                            <p class="quote-date" style="font-size: smaller;">اليوم: ' . date('l jS \of F Y - H:i') . ' 🎯 المشاهدات: ' . $quote['hits'] . '</p>
                        </div>
                        <div class="ml-4 text-center text-sm text-gray-500 dark:text-gray-400 sm:text-right sm:ml-0 quote-content" dir="rtl">
                            <h1 class="quote-text">' . $quote['quote'] . '</h1>
                        </div>
                        <div class="quote-footer">
                            <p class="quote-author">' . $quote['author'] . '</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }
}

/**
 * Wikiquote Fetcher
 *
 * This class fetches quotes from Wikiquote.
 */
class WikiquoteFetcher
{

    public $updatedQuote;

    public function __construct()
    {
        $this->updatedQuote = $this->fetchRandomWikiQuote();
    }

    /**
     * Fetches a random quote from Wikiquote.
     *
     * @return array|null The fetched quote, or null if an error occurs.
     */
    public function fetchRandomWikiQuote()
    {
        $htmlChunk = $this->fetchFromWiki();
        if (!$htmlChunk) {
            return null;
        }

        return [
            'quote' => $htmlChunk['quote'],
            'author' => $htmlChunk['author']
        ];
    }

    /**
     * Fetches and parses a quote from Wikiquote.
     *
     * @return array|null The parsed quote, or null if an error occurs.
     */
    private function fetchFromWiki()
    {
        $html = $this->fetchRaw();
        if (!$html) {
            return null;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $chunk = $xpath->query('/html/body/div[2]/div/div[3]/main/div[3]/div[3]/div[1]/table[1]/tbody/tr[1]/td/div[2]/div[2]/center/table/tbody/tr/td[3]');
        $chunk = explode("\n", trim($chunk->item(0)->textContent));
        $chunk = array_filter($chunk, function ($value) {
            return !empty(trim($value));
        });
        $randomQuote = $chunk[0];
        $author = $chunk[2];
        return [
            'quote' => trim($randomQuote),
            'author' => trim($author)
        ];
    }

    /**
     * Fetches raw HTML content from Wikiquote.
     *
     * @return string|null The HTML content, or null if an error occurs.
     */
    private function fetchRaw()
    {
        $url = "https://ar.wikiquote.org/wiki/%D8%A7%D9%84%D8%B5%D9%81%D8%AD%D8%A9_%D8%A7%D9%84%D8%B1%D8%A6%D9%8A%D8%B3%D9%8A%D8%A9";
        if (!$html = file_get_contents($url)) {
            return null;
        }

        return $html;
    }
}


// Main execution

$quoteManager = new QuoteManager();
$wikiquoteFetcher = new WikiquoteFetcher();

if (!$wikiQuote = $wikiquoteFetcher->fetchRandomWikiQuote()) {
    echo "Failed to update daily quote.\n";

    echo "Fetching a random quote from Wikipedia...\n";

    if (!$wikiQuote) {
        echo "Failed to fetch a random quote from Wikipedia.\n";
    }
} else {
    echo "✅ Daily quote updated successfully.\n";
    echo "Quote: " . $wikiQuote['quote'] . PHP_EOL;
    echo "Author: " . $wikiQuote['author'] . PHP_EOL;
}
