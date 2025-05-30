<?php
/**
 * Arabic Wikimedia Commons API Quote Fetcher
 * 
 * This script retrieves quotes from the Arabic Wikimedia Commons API and saves them to the database.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0); // No time limit for execution
ini_set('memory_limit', '512M');  // Increase memory limit

/**
 * Wikimedia Commons API Quote Fetcher
 */
class WikimediaCommonsQuoteFetcher
{
    private $apiUrl = 'https://commons.wikimedia.org/w/api.php';
    private $searchTerms = [
        'أقوال مأثورة',
        'حكم عربية',
        'أمثال عربية',
        'مقولات',
        'أمثال شعبية',
        'حكمة',
        'أقوال الفلاسفة',
        'أقوال العلماء',
        'أقوال الشعراء',
        'أقوال الأدباء'
    ];
    private $fetchedQuotes = 0;
    
    public function fetchAllQuotes()
    {
        echo "Starting Wikimedia Commons quote fetching process...\n";
        
        $db = new SQLite3('quotes.db');
        $db->exec('BEGIN TRANSACTION');
        
        foreach ($this->searchTerms as $term) {
            echo "Processing search term: " . $term . "\n";
            
            $pages = $this->searchPages($term);
            echo "Found " . count($pages) . " pages for term " . $term . "\n";
            
            foreach ($pages as $pageId => $pageTitle) {
                echo "Processing page: " . $pageTitle . "\n";
                
                $quotes = $this->getQuotesFromPage($pageId);
                
                foreach ($quotes as $quote) {
                    // Skip if the quote already exists in the database
                    $existingQuote = $db->querySingle("SELECT id FROM quotes WHERE quote = '" . SQLite3::escapeString($quote['quote']) . "'");
                    if ($existingQuote) {
                        continue;
                    }
                    
                    // Insert the quote into the database
                    $stmt = $db->prepare('INSERT INTO quotes (quote, author, hits, category) VALUES (:quote, :author, 0, :category)');
                    $stmt->bindValue(':quote', $quote['quote'], SQLITE3_TEXT);
                    $stmt->bindValue(':author', $quote['author'], SQLITE3_TEXT);
                    $stmt->bindValue(':category', $term, SQLITE3_TEXT);
                    $stmt->execute();
                    
                    $this->fetchedQuotes++;
                }
                
                // Commit every few pages to avoid large transactions
                if ($this->fetchedQuotes % 100 === 0) {
                    $db->exec('COMMIT');
                    $db->exec('BEGIN TRANSACTION');
                    echo "Inserted " . $this->fetchedQuotes . " quotes so far...\n";
                }
            }
        }
        
        $db->exec('COMMIT');
        $db->close();
        
        echo "Wikimedia Commons fetching process completed. Total quotes fetched: " . $this->fetchedQuotes . "\n";
    }
    
    /**
     * Search for pages containing quotes
     */
    private function searchPages($term)
    {
        $params = [
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $term,
            'srnamespace' => 0, // Main namespace
            'srlimit' => 100,
            'format' => 'json'
        ];
        
        $response = $this->makeApiRequest($params);
        
        $pages = [];
        if (isset($response['query']['search'])) {
            foreach ($response['query']['search'] as $page) {
                $pages[$page['pageid']] = $page['title'];
            }
        }
        
        return $pages;
    }
    
    /**
     * Get quotes from a specific page
     */
    private function getQuotesFromPage($pageId)
    {
        $params = [
            'action' => 'query',
            'prop' => 'extracts',
            'pageids' => $pageId,
            'explaintext' => true,
            'format' => 'json'
        ];
        
        $response = $this->makeApiRequest($params);
        
        $quotes = [];
        if (isset($response['query']['pages'][$pageId]['extract'])) {
            $content = $response['query']['pages'][$pageId]['extract'];
            
            // Extract quotes from content
            $quotes = $this->extractQuotesFromContent($content);
        }
        
        return $quotes;
    }
    
    /**
     * Extract quotes from page content
     */
    private function extractQuotesFromContent($content)
    {
        $quotes = [];
        
        // Split content into lines
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines or very short text
            if (empty($line) || mb_strlen($line) < 15) {
                continue;
            }
            
            // Check if this line contains Arabic text and looks like a quote
            if ($this->containsArabic($line) && $this->isLikelyQuote($line)) {
                // Try to extract author
                $author = 'Unknown';
                $quote = $line;
                
                // Look for common author attribution patterns
                if (preg_match('/^(.+?)\s*[-–—]\s*(.+)$/u', $line, $matches)) {
                    $quote = trim($matches[1]);
                    $author = trim($matches[2]);
                } elseif (preg_match('/^(.+?)\s*[,:]\s*(.+)$/u', $line, $matches)) {
                    // Format: "Author: Quote" or "Author, Quote"
                    $author = trim($matches[1]);
                    $quote = trim($matches[2]);
                }
                
                // Clean up the quote
                $quote = $this->cleanText($quote);
                
                // Add to quotes list if it's a reasonable length
                if (mb_strlen($quote) >= 15 && mb_strlen($quote) <= 500) {
                    $quotes[] = [
                        'quote' => $quote,
                        'author' => $author
                    ];
                }
            }
        }
        
        return $quotes;
    }
    
    /**
     * Check if text contains Arabic characters
     */
    private function containsArabic($text)
    {
        return preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $text);
    }
    
    /**
     * Check if text is likely a quote
     */
    private function isLikelyQuote($text)
    {
        // This is a simple heuristic - you might need to improve it
        if (mb_strlen($text) < 15 || mb_strlen($text) > 500) {
            return false;
        }
        
        // If it contains punctuation typical of quotes
        if (strpos($text, '«') !== false || strpos($text, '»') !== false || 
            strpos($text, '"') !== false || strpos($text, '"') !== false ||
            strpos($text, ':') !== false || strpos($text, '—') !== false ||
            strpos($text, '-') !== false) {
            return true;
        }
        
        // If it ends with a period, question mark, or exclamation mark
        if (preg_match('/[.?!]$/u', $text)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Clean up text
     */
    private function cleanText($text)
    {
        // Remove citation references [1], [2], etc.
        $text = preg_replace('/\[\d+\]/', '', $text);
        
        // Remove extra spaces and newlines
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove leading/trailing spaces
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Make API request
     */
    private function makeApiRequest($params)
    {
        $url = $this->apiUrl . '?' . http_build_query($params);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'QuoteFetcher/1.0 (https://github.com/aldoyh/arabicquotes)'
            ]
        ]);
        
        try {
            $response = file_get_contents($url, false, $context);
            return json_decode($response, true);
        } catch (Exception $e) {
            echo "API request error: " . $e->getMessage() . "\n";
            return [];
        }
    }
}

// Main execution
try {
    echo "Wikimedia Commons Arabic Quotes Fetcher\n";
    echo "====================================\n\n";
    
    // Backup the database first
    echo "Backing up the database...\n";
    copy('quotes.db', 'quotes.db.wikimedia-backup-' . date('Y-m-d-H-i-s'));
    
    // Start fetching
    $fetcher = new WikimediaCommonsQuoteFetcher();
    $startTime = microtime(true);
    $fetcher->fetchAllQuotes();
    $endTime = microtime(true);
    
    echo "\nFetching completed in " . round($endTime - $startTime, 2) . " seconds\n";
    
    // Print database stats
    $db = new SQLite3('quotes.db');
    $quoteCount = $db->querySingle("SELECT COUNT(*) FROM quotes");
    $authorCount = $db->querySingle("SELECT COUNT(DISTINCT author) FROM quotes");
    $db->close();
    
    echo "Database now contains:\n";
    echo "- $quoteCount total quotes\n";
    echo "- $authorCount unique authors\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
