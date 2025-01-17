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
            $quoteMarkdown = PHP_EOL . "# " . $quote['quote'] . PHP_EOL . PHP_EOL . "- " . $quote['author'] . PHP_EOL . PHP_EOL;
            if (isset($quote['image'])) {
                $quoteMarkdown .= "![" . $quote['author'] . "](" . $quote['image'] . ")" . PHP_EOL . PHP_EOL;
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
            $html = '<div class="quote-wrapper" style="text-align: center; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">
                        <div class="quote-text" style="font-size: 24px; color: #333; margin-bottom: 10px;">
                            ' . htmlspecialchars($quote['quote']) . '
                        </div>
                        <div class="quote-author" style="font-size: 18px; color: #666;">
                            - ' . htmlspecialchars($quote['author']) . '
                        </div>
                        <div class="quote-meta" style="margin-top: 20px; color: #999;">
                            <p class="quote-date" style="font-size: smaller;">اليوم: ' . date('l jS \of F Y - H:i') . ' 🎯 المشاهدات: ' . $quote['hits'] . '</p>
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
            echo json_encode($updatedQuote, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "❌ Failed to update quote.\n";
        }
    } catch (Exception $e) {
        error_log("Error in main execution: " . $e->getMessage());
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}