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
    
    public function __construct($dbFile = 'quotes.db')
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
     * Gets a random quote from the database.
     * @return array|null The quote data or null on error.
     */
    public function getRandomQuote()
    {
        try {
            $db = new SQLite3($this->dbFile);
            $result = $db->query("SELECT * FROM quotes ORDER BY RANDOM() LIMIT 1");
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
     * Updates the README with a new random quote.
     * @return array|null The updated quote or null on error.
     */
    public function updateReadme()
    {
        try {
            $selectedQuote = $this->getRandomQuote();
            if (!$selectedQuote) {
                throw new Exception('Failed to get random quote');
            }

            $selectedQuote['hits']++;
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

            // Update hits in database
            $this->updateQuoteHits($selectedQuote);

            return $selectedQuote;
        } catch (Exception $e) {
            error_log("Error in updateReadme: " . $e->getMessage());
            return null;
        }
    }

    private function updateQuoteHits($quote)
    {
        try {
            $db = new SQLite3($this->dbFile);
            $stmt = $db->prepare('UPDATE quotes SET hits = :hits WHERE id = :id');
            $stmt->bindValue(':hits', $quote['hits'], SQLITE3_INTEGER);
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
            // Clean up quote text by removing newlines and extra spaces
            $cleanQuote = preg_replace('/\s+/', ' ', trim($quote['quote']));
            $cleanAuthor = preg_replace('/\s+/', ' ', trim($quote['author']));
            
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
            // Clean up quote text by removing newlines and extra spaces
            $cleanQuote = preg_replace('/\s+/', ' ', trim($quote['quote']));
            $cleanAuthor = preg_replace('/\s+/', ' ', trim($quote['author']));
            
            $html = '<div class="flex flex-col items-center">
                    <div class="w-full max-w-2xl quote-card rounded-xl p-8 mb-6 border-r-4 border-amber-500 dark:border-amber-600">
                        <div class="text-3xl font-bold text-gray-800 dark:text-amber-50 mb-6 text-center leading-relaxed quote-text">
                            ' . htmlspecialchars($cleanQuote) . '
                        </div>
                        <div class="text-xl font-semibold text-amber-700 dark:text-amber-300 text-center author-text">
                            — ' . htmlspecialchars($cleanAuthor) . '
                        </div>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 italic">
                        <p>اليوم: ' . date('l jS \of F Y - H:i') . ' 🎯 المشاهدات: ' . $quote['hits'] . '</p>
                    </div>
                </div>';
            return $html;
        } catch (Exception $e) {
            error_log("Error generating HTML: " . $e->getMessage());
            return null;
        }
    }
}

// Initialize database if running as main script
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    try {
        $quoteManager = new QuoteManager();
        $updatedQuote = $quoteManager->updateReadme();
        if ($updatedQuote) {
            echo "✅ Quote updated successfully!\n";
            // echo json_encode($updatedQuote, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "❌ Failed to update quote.\n";
        }
    } catch (Exception $e) {
        error_log("Error in main execution: " . $e->getMessage());
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}