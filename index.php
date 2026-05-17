<?php
require_once 'inc/db-utils.php';

error_reporting(E_ALL);
error_log("Current directory: " . __DIR__);

/**
 * Class QuoteManager
 * Handles quote operations including fetching, updating, and formatting.
 */
class QuoteManager
{
    private $dbFile;
    private $basePath;

    public function __construct($dbFile = 'assets/QuotesDB.db')
    {
        $this->dbFile = $dbFile;
        $this->basePath = __DIR__ . '/';
        if (!file_exists($this->dbFile)) {
            throw new Exception("Database file not found: " . $this->dbFile);
        }
        if (!is_writable($this->dbFile)) {
            throw new Exception("Database file is not writable: " . $this->dbFile);
        }
        $this->initDatabase();
    }

    private function initDatabase()
    {
        try {
            $db = new SQLite3($this->dbFile);
            $db->exec('CREATE TABLE IF NOT EXISTS quotes (id INTEGER PRIMARY KEY, quote TEXT, author TEXT, image TEXT, hits INTEGER DEFAULT 0)');
            $db->close();
        } catch (Exception $e) {
            error_log("Error initializing database: " . $e->getMessage());
        }

        // populate the database if empty
        if ($this->getQuoteCount() === 0) {
            $this->populateDatabase();
        }
    }

    /**
     * Gets a random quote from the database, prioritising quotes with the fewest appearances.
     * Among quotes sharing the minimum hit count the selection is random.
     * @return array|null The quote data or null on error.
     */
    public function getRandomQuote()
    {
        try {
            $db = new SQLite3($this->dbFile);
            $result = $db->query(
                "SELECT * FROM quotes WHERE hits = (SELECT MIN(hits) FROM quotes) ORDER BY RANDOM() LIMIT 1"
            );
            if (!$result) {
                throw new Exception("Failed to query quotes from DB");
            }
            $quote = $result->fetchArray(SQLITE3_ASSOC);
            $db->close();
            return $quote;
        } catch (Exception $e) {
            error_log("Error in getRandomQuote: " . $e->getMessage());
            return null;
        }
    }

    private function getQuoteCount()
    {
        try {
            $db = new SQLite3($this->dbFile);
            $result = $db->querySingle("SELECT COUNT(*) FROM quotes");
            $db->close();
            return $result;
        } catch (Exception $e) {
            error_log("Error in getQuoteCount: " . $e->getMessage());
            return 0;
        }
    }

    private function populateDatabase()
    {
        try {
            $db = new SQLite3($this->dbFile);
            $quotes = json_decode(file_get_contents($this->basePath . 'assets/quotes.json'), true);
            foreach ($quotes as $quote) {
                $stmt = $db->prepare('INSERT INTO quotes (quote, author, image) VALUES (:quote, :author, :image)');
                $stmt->bindValue(':quote', $quote['quote'], SQLITE3_TEXT);
                $stmt->bindValue(':author', $quote['author'], SQLITE3_TEXT);
                // $stmt->bindValue(':image', $quote['image'], SQLITE3_TEXT);
                $stmt->execute();
            }
            $db->close();
        } catch (Exception $e) {
            error_log("Error in populateDatabase: " . $e->getMessage());
        }
    }

    /**
     * Cleans up all quotes in the database by removing HTML tags and entities.
     * This fixes quotes imported from Wikipedia that contain HTML markup.
     * @return int Number of quotes cleaned.
     */
    public function cleanupQuotesInDatabase()
    {
        try {
            $db = new SQLite3($this->dbFile);
            $result = $db->query('SELECT id, quote, author FROM quotes');
            if (!$result) {
                throw new Exception("Failed to query quotes from DB");
            }

            $cleaned = 0;
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $cleanedQuote = $this->cleanHtmlFromText($row['quote']);
                $cleanedAuthor = $this->cleanHtmlFromText($row['author']);

                // Only update if something changed
                if ($cleanedQuote !== $row['quote'] || $cleanedAuthor !== $row['author']) {
                    $stmt = $db->prepare('UPDATE quotes SET quote = :quote, author = :author WHERE id = :id');
                    $stmt->bindValue(':quote', $cleanedQuote, SQLITE3_TEXT);
                    $stmt->bindValue(':author', $cleanedAuthor, SQLITE3_TEXT);
                    $stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                    $stmt->execute();
                    $cleaned++;
                }
            }
            $db->close();
            return $cleaned;
        } catch (Exception $e) {
            error_log("Error in cleanupQuotesInDatabase: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cleans HTML tags and entities from text.
     * @param string $text Text to clean.
     * @return string Cleaned text.
     */
    private function cleanHtmlFromText($text)
    {
        // Decode HTML entities
        $cleaned = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remove escaped slashes
        $cleaned = str_replace('\\/', '/', $cleaned);
        // Strip all HTML tags
        $cleaned = strip_tags($cleaned);
        // Remove any remaining HTML-like patterns
        $cleaned = preg_replace('/<[^>]*>/', '', $cleaned);
        // Normalize whitespace
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        // Trim
        $cleaned = trim($cleaned);
        return $cleaned;
    }

    /**
     * Logs quote updates to a deployment log file.
     * @param string $logMessage Message to log.
     * @return bool True if successful, false otherwise.
     */
    public function logQuoteUpdate($logMessage)
    {
        try {
            $logEntry = date('Y-m-d H:i:s') . " - " . $logMessage . "\n";
            if (file_put_contents($this->basePath . "assets/DEPLOYMENT.log", $logEntry, FILE_APPEND) === false) {
                throw new Exception("Failed to write to log file");
            }
            return true;
        } catch (Exception $e) {
            error_log("Error in logQuoteUpdate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates the README with a new random quote and also refreshes index.html.
     * @return array|null The updated quote or null on error.
     */
    public function updateReadme()
    {
        try {
            $selectedQuote = $this->getRandomQuote();
            if (!$selectedQuote) {
                throw new Exception('Failed to get random quote');
            }

            $readmePath = $this->basePath . "README.md";

            if (!file_exists($readmePath)) {
                throw new Exception('README.md not found');
            }

            $readmeContent = file_get_contents($readmePath);
            if ($readmeContent === false) {
                throw new Exception('Failed to read README.md');
            }

            $quoteMarkdown = $this->generateQuoteMarkdown($selectedQuote);

            // Use specific markers to replace only the quote section
            $updatedContent = preg_replace(
                '/<!-- QUOTE:START -->.*?<!-- QUOTE:END -->/s',
                "<!-- QUOTE:START -->\n" . $quoteMarkdown . "\n<!-- QUOTE:END -->",
                $readmeContent
            );

            if ($updatedContent === null || $updatedContent === $readmeContent) {
                throw new Exception('Failed to replace quote section');
            }

            if (file_put_contents($readmePath, $updatedContent) === false) {
                throw new Exception('Failed to write to README.md');
            }

            // Update index.html quote container using stable comment markers
            $this->updateIndexHtml($selectedQuote);

            // Increment hits in database
            $this->updateQuoteHits($selectedQuote);

            return $selectedQuote;
        } catch (Exception $e) {
            error_log("Error in updateReadme: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Updates the index.html quote container using comment markers.
     * @param array $quote The quote data.
     */
    public function updateIndexHtml($quote)
    {
        try {
            $htmlPath = $this->basePath . "index.html";
            if (!file_exists($htmlPath)) {
                return;
            }
            $htmlContent = file_get_contents($htmlPath);
            if ($htmlContent === false) {
                return;
            }
            $quoteHtml = $this->generateQuoteHtml($quote);
            $updatedHtml = preg_replace(
                '/<!-- QUOTE_CONTAINER:START -->.*?<!-- QUOTE_CONTAINER:END -->/s',
                "<!-- QUOTE_CONTAINER:START -->\n" . $quoteHtml . "\n<!-- QUOTE_CONTAINER:END -->",
                $htmlContent
            );
            if ($updatedHtml !== null && $updatedHtml !== $htmlContent) {
                file_put_contents($htmlPath, $updatedHtml);
            }
        } catch (Exception $e) {
            error_log("Error in updateIndexHtml: " . $e->getMessage());
        }
    }

    private function updateQuoteHits($quote)
    {
        try {
            $db = new SQLite3($this->dbFile);
            $stmt = $db->prepare('UPDATE quotes SET hits = hits + 1 WHERE id = :id');
            $stmt->bindValue(':id', $quote['id'], SQLITE3_INTEGER);
            $stmt->execute();
            $db->close();
        } catch (Exception $e) {
            error_log("Failed to update quote hits: " . $e->getMessage());
        }
    }

    /**
     * Generates Markdown representation of a quote.
     * @param array $quote The quote data.
     * @return string The Markdown representation of the quote.
     */
    public function generateQuoteMarkdown($quote)
    {
        try {
            if (!isset($quote['quote']) || !isset($quote['author'])) {
                throw new Exception('Invalid quote data');
            }

            // Comprehensive HTML/Entity cleaning (same as HTML generation):
            // 1. Decode HTML entities
            // 2. Remove escaped slashes
            // 3. Strip all HTML tags
            // 4. Remove any remaining HTML-like patterns
            // 5. Normalize whitespace
            // 6. Trim whitespace

            $cleanQuote = $quote['quote'];
            $cleanQuote = html_entity_decode($cleanQuote, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cleanQuote = str_replace('\\/', '/', $cleanQuote);
            $cleanQuote = strip_tags($cleanQuote);
            $cleanQuote = preg_replace('/<[^>]*>/', '', $cleanQuote);
            $cleanQuote = preg_replace('/\s+/', ' ', $cleanQuote);
            $cleanQuote = trim($cleanQuote);

            $cleanAuthor = $quote['author'];
            $cleanAuthor = html_entity_decode($cleanAuthor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cleanAuthor = str_replace('\\/', '/', $cleanAuthor);
            $cleanAuthor = strip_tags($cleanAuthor);
            $cleanAuthor = preg_replace('/<[^>]*>/', '', $cleanAuthor);
            $cleanAuthor = preg_replace('/\s+/', ' ', $cleanAuthor);
            $cleanAuthor = trim($cleanAuthor);
            $cleanAuthor = preg_replace('/^[—\-\s]+/', '', $cleanAuthor);

            $quoteMarkdown = PHP_EOL . "# " . $cleanQuote . PHP_EOL . PHP_EOL . "- " . $cleanAuthor . PHP_EOL . PHP_EOL;
            if (isset($quote['image'])) {
                $quoteMarkdown .= "![" . $cleanAuthor . "](" . $quote['image'] . ")" . PHP_EOL . PHP_EOL;
            }
            return $quoteMarkdown;
        } catch (Exception $e) {
            error_log("Error generating markdown: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generates HTML representation of a quote.
     * @param array $quote The quote data.
     * @return string The HTML representation of the quote.
     */
    public function generateQuoteHtml($quote)
    {
        try {
            if (!isset($quote['quote']) || !isset($quote['author']) || !isset($quote['hits'])) {
                throw new Exception('Invalid quote data for HTML generation');
            }

            // Comprehensive HTML/Entity cleaning:
            // 1. Decode HTML entities to actual characters
            // 2. Strip all HTML tags
            // 3. Remove escaped forward slashes from URLs
            // 4. Normalize whitespace (including newlines)
            // 5. Trim leading/trailing whitespace

            $cleanQuote = $quote['quote'];
            // First, decode all HTML entities
            $cleanQuote = html_entity_decode($cleanQuote, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Remove escaped slashes from URLs like \/
            $cleanQuote = str_replace('\\/', '/', $cleanQuote);
            // Strip all HTML tags completely
            $cleanQuote = strip_tags($cleanQuote);
            // Remove any remaining HTML-like patterns (in case of malformed tags)
            $cleanQuote = preg_replace('/<[^>]*>/', '', $cleanQuote);
            // Normalize all whitespace (including newlines, tabs, multiple spaces)
            $cleanQuote = preg_replace('/\s+/', ' ', $cleanQuote);
            // Trim leading/trailing whitespace
            $cleanQuote = trim($cleanQuote);

            $cleanAuthor = $quote['author'];
            // Apply same cleaning to author
            $cleanAuthor = html_entity_decode($cleanAuthor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cleanAuthor = str_replace('\\/', '/', $cleanAuthor);
            $cleanAuthor = strip_tags($cleanAuthor);
            $cleanAuthor = preg_replace('/<[^>]*>/', '', $cleanAuthor);
            $cleanAuthor = preg_replace('/\s+/', ' ', $cleanAuthor);
            $cleanAuthor = trim($cleanAuthor);
            // Remove leading dashes/em-dashes that sometimes appear
            $cleanAuthor = preg_replace('/^[—\-\s]+/', '', $cleanAuthor);

            $html = '
        <div class="flex flex-col items-center animate-slide-up">
            <div class="quote-card w-full rounded-2xl p-8 md:p-10 mb-6 border-r-4 border-amber-500 dark:border-amber-600">
                <div class="quote-text text-2xl md:text-3xl font-bold text-gray-800 dark:text-amber-50 mb-7 text-center leading-loose">
                    ' . htmlspecialchars($cleanQuote, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '
                </div>
                <div class="author-text text-lg md:text-xl font-semibold text-amber-700 dark:text-amber-400 text-center">
                    — ' . htmlspecialchars($cleanAuthor, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500 italic">
                <span>🎯</span>
                <span>المشاهدات: ' . (int)$quote['hits'] . '</span>
            </div>
        </div>';

            return $html;
        } catch (Exception $e) {
            error_log('Error generating quote HTML: ' . $e->getMessage());
            return '';
        }
    }
}

// Initialize database if running as main script or via CLI
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    try {
        $quoteManager = new QuoteManager();

        // Check for cleanup command
        if ($argc > 1 && $argv[1] === 'cleanup') {
            echo "🧹 Cleaning up HTML tags from quotes in database...\n";
            $cleaned = $quoteManager->cleanupQuotesInDatabase();
            echo "✅ Cleaned $cleaned quotes\n";
        } else {
            // Default: update quote
            $updatedQuote = $quoteManager->updateReadme();
            if ($updatedQuote) {
                echo "✅ Quote updated successfully!\n";
                // echo json_encode($updatedQuote, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "❌ Failed to update quote.\n";
            }
        }
    } catch (Exception $e) {
        error_log("Error in main execution: " . $e->getMessage());
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
