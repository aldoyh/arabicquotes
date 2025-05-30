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
            error_log("Failed to read README.md");
            return false;
        }

        // Use the same specific markers for consistency
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
                <p>اليوم: ' . date('l jS \of F Y - H:i') . ' 🎯 المشاهدات: ' . $quote['hits'] . '</p>
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
    public function fetchFromWiki()
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
        $randomQuote = preg_replace('/\s+/', ' ', trim($chunk[0]));
        
        // Clean up the author by removing any HTML tags
        $author = strip_tags(trim($chunk[2]));
        // Further clean up by replacing multiple spaces with a single space
        $author = preg_replace('/\s+/', ' ', $author);
        // Remove any remaining HTML entities
        $author = html_entity_decode($author);
        
        return [
            'quote' => $randomQuote,
            'author' => $author
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
    echo "Failed to update daily quote from Wikiquote.\n";

    echo "Fetching a random quote from local database...\n";
    $localQuote = $quoteManager->getRandomQuote();
    
    if (!$localQuote) {
        echo "Failed to fetch a random quote from local database.\n";
        exit(1);
    }
    
    // Update README.md with the local quote
    if ($quoteManager->updateReadme()) {
        echo "✅ README.md updated with a local quote.\n";
        echo "Quote: " . $localQuote['quote'] . PHP_EOL;
        echo "Author: " . $localQuote['author'] . PHP_EOL;
    } else {
        echo "❌ Failed to update README.md with local quote.\n";
        exit(1);
    }
} else {
    echo "✅ Fetched quote from Wikiquote successfully.\n";
    echo "Quote: " . $wikiQuote['quote'] . PHP_EOL;
    echo "Author: " . $wikiQuote['author'] . PHP_EOL;
    
    // Update README.md with the wiki quote
    if ($quoteManager->updateReadme()) {
        echo "✅ README.md updated with Wikiquote quote.\n";
    } else {
        echo "❌ Failed to update README.md with Wikiquote quote.\n";
        exit(1);
    }
}
