<?php

require_once __DIR__ . '/../../inc/db-utils.php';

class QuoteUpdater
{
    private $dbFile;
    private $basePath;

    public function __construct($dbFile = 'quotes.db')
    {
        $this->dbFile = $dbFile;
        $this->basePath = __DIR__ . '/../../';
        if (!file_exists($this->dbFile)) {
            throw new Exception("Database file not found: " . $this->dbFile);
        }
    }

    public function updateQuoteOfTheDay()
    {
        try {
            $quoteData = $this->getRandomQuote();
            if (!$quoteData) {
                throw new Exception('Failed to get random quote');
            }

            $this->updateIndexHtml($quoteData);
            $this->updateReadme($quoteData);
            $this->logQuoteUpdate($quoteData);

            return $quoteData;
        } catch (Exception $e) {
            error_log("Error in updateQuoteOfTheDay: " . $e->getMessage());
            return null;
        }
    }

    private function getRandomQuote()
    {
        $db = new SQLite3($this->dbFile);
        $result = $db->query("SELECT * FROM quotes ORDER BY RANDOM() LIMIT 1");
        $quote = $result->fetchArray(SQLITE3_ASSOC);
        $db->close();
        return $quote;
    }

    private function updateIndexHtml($quote)
    {
        $htmlPath = $this->basePath . "index.html";
        $htmlContent = file_get_contents($htmlPath);
        if ($htmlContent === false) {
            throw new Exception('Failed to read index.html');
        }

        $quoteHtml = $this->generateQuoteHtml($quote);

        // Replace the content inside the quote-container div
        $updatedHtml = preg_replace(
            '/(<div id="quote-container">).*?(<\/div>)/s',
            '$1' . $quoteHtml . '$2',
            $htmlContent
        );

        if ($updatedHtml === null || $updatedHtml === $htmlContent) {
            throw new Exception('Failed to replace quote section in index.html');
        }

        if (file_put_contents($htmlPath, $updatedHtml) === false) {
            throw new Exception('Failed to write to index.html');
        }
    }

    private function updateReadme($quote)
    {
        $readmePath = $this->basePath . "README.md";
        $readmeContent = file_get_contents($readmePath);
        if ($readmeContent === false) {
            throw new Exception('Failed to read README.md');
        }

        $quoteMarkdown = $this->generateQuoteMarkdown($quote);

        $updatedContent = preg_replace(
            '/<!-- QUOTE:START -->.*?<!-- QUOTE:END -->/s',
            "<!-- QUOTE:START -->\n" . $quoteMarkdown . "\n<!-- QUOTE:END -->",
            $readmeContent
        );

        if ($updatedContent === null || $updatedContent === $readmeContent) {
            throw new Exception('Failed to replace quote section in README.md');
        }

        if (file_put_contents($readmePath, $updatedContent) === false) {
            throw new Exception('Failed to write to README.md');
        }
    }
    
    private function generateQuoteHtml($quote)
    {
        $cleanQuote = $this->cleanTextContent($quote['quote']);
        $cleanAuthor = $this->cleanTextContent($quote['author']);
        return '
        <div class="flex flex-col items-center animate-slide-up">
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
    }

    private function generateQuoteMarkdown($quote)
    {
        $cleanQuote = $this->cleanTextContent($quote['quote']);
        $cleanAuthor = $this->cleanTextContent($quote['author']);
        return "\n# " . $cleanQuote . "\n\n- " . $cleanAuthor . "\n";
    }

    private function cleanTextContent($text)
    {
        // Remove HTML tags
        $text = strip_tags($text);
        // Remove any remaining encoded HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));
        // Remove leading dashes and clean up
        $text = preg_replace('/^[—\-\s]+/', '', $text);
        return $text;
    }

    private function logQuoteUpdate($quote)
    {
        try {
            $cleanQuote = $this->cleanTextContent($quote['quote']);
            $cleanAuthor = $this->cleanTextContent($quote['author']);
            $logMessage = $cleanQuote . " — " . $cleanAuthor;
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
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    try {
        $updater = new QuoteUpdater();
        $updatedQuote = $updater->updateQuoteOfTheDay();
        if ($updatedQuote) {
            echo "✅ Quote of the day updated successfully!\n";
            echo json_encode($updatedQuote, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "❌ Failed to update quote of the day.\n";
        }
    } catch (Exception $e) {
        error_log("Error in main execution: " . $e->getMessage());
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}