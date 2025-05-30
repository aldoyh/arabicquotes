<?php
/**
 * Goodreads Quotes Fetcher
 * 
 * This script retrieves Arabic quotes from the Goodreads API and saves them to the database.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0); // No time limit for execution
ini_set('memory_limit', '512M');  // Increase memory limit

/**
 * Goodreads API Quote Fetcher
 */
class GoodreadsQuoteFetcher
{
    private $apiUrl = "https://www.goodreads.com/quotes/tag/";
    private $tags = [
        'arabic-quotes',
        'arabic-wisdom',
        'arabic-literature',
        'arabic-poetry',
        'arabic-proverbs',
        'islamic-quotes',
        'arabic-philosophy',
        'middle-eastern-literature',
        'middle-eastern-wisdom'
    ];
    private $fetchedQuotes = 0;
    
    public function fetchAllQuotes()
    {
        echo "Starting Goodreads quote fetching process...\n";
        
        $db = new SQLite3('quotes.db');
        $db->exec('BEGIN TRANSACTION');
        
        foreach ($this->tags as $tag) {
            echo "Processing tag: " . $tag . "\n";
            
            $page = 1;
            $hasMorePages = true;
            
            while ($hasMorePages) {
                echo "Fetching page " . $page . " of tag " . $tag . "...\n";
                
                $url = $this->apiUrl . urlencode($tag) . "?page=" . $page;
                $html = $this->fetchUrl($url);
                
                if (!$html) {
                    echo "Failed to fetch page " . $page . " of tag " . $tag . ".\n";
                    break;
                }
                
                $dom = new DOMDocument();
                @$dom->loadHTML($html);
                $xpath = new DOMXPath($dom);
                
                // Get quote elements
                $quoteElements = $xpath->query('//div[contains(@class, "quoteText")]');
                
                // If no quotes found on this page, move to next tag
                if ($quoteElements->length === 0) {
                    $hasMorePages = false;
                    continue;
                }
                
                foreach ($quoteElements as $quoteElement) {
                    $quoteText = $quoteElement->textContent;
                    
                    // Check if the quote contains Arabic text
                    if (!$this->containsArabic($quoteText)) {
                        continue;
                    }
                    
                    // Clean up the quote
                    $quoteText = $this->cleanText($quoteText);
                    
                    // Extract author
                    $authorElements = $xpath->query('.//span[@class="authorOrTitle"]', $quoteElement);
                    $author = 'Unknown';
                    if ($authorElements->length > 0) {
                        $author = trim($authorElements->item(0)->textContent);
                        $author = str_replace(',', '', $author);
                    }
                    
                    // Skip if the quote already exists in the database
                    $existingQuote = $db->querySingle("SELECT id FROM quotes WHERE quote = '" . SQLite3::escapeString($quoteText) . "'");
                    if ($existingQuote) {
                        continue;
                    }
                    
                    // Insert the quote into the database
                    $stmt = $db->prepare('INSERT INTO quotes (quote, author, hits, category) VALUES (:quote, :author, 0, :category)');
                    $stmt->bindValue(':quote', $quoteText, SQLITE3_TEXT);
                    $stmt->bindValue(':author', $author, SQLITE3_TEXT);
                    $stmt->bindValue(':category', $tag, SQLITE3_TEXT);
                    $stmt->execute();
                    
                    $this->fetchedQuotes++;
                }
                
                // Check if there's a next page
                $nextPageElements = $xpath->query('//a[@class="next_page"]');
                if ($nextPageElements->length === 0) {
                    $hasMorePages = false;
                } else {
                    $page++;
                    
                    // Add a delay to avoid rate limiting
                    sleep(2);
                    
                    // Commit every few pages to avoid large transactions
                    if ($page % 5 === 0) {
                        $db->exec('COMMIT');
                        $db->exec('BEGIN TRANSACTION');
                    }
                }
            }
        }
        
        $db->exec('COMMIT');
        $db->close();
        
        echo "Goodreads fetching process completed. Total quotes fetched: " . $this->fetchedQuotes . "\n";
    }
    
    /**
     * Check if text contains Arabic characters
     */
    private function containsArabic($text)
    {
        return preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $text);
    }
    
    /**
     * Clean up text
     */
    private function cleanText($text)
    {
        // Remove citation info
        $text = preg_replace('/^"(.+)"\s*―.*$/s', '$1', $text);
        
        // Remove extra spaces and newlines
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove leading/trailing spaces
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Fetch URL content with error handling
     */
    private function fetchUrl($url)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
        
        try {
            $html = file_get_contents($url, false, $context);
            return $html;
        } catch (Exception $e) {
            echo "Error fetching URL " . $url . ": " . $e->getMessage() . "\n";
            return null;
        }
    }
}

// Main execution
try {
    echo "Goodreads Arabic Quotes Fetcher\n";
    echo "=============================\n\n";
    
    // Backup the database first
    echo "Backing up the database...\n";
    copy('quotes.db', 'quotes.db.goodreads-backup-' . date('Y-m-d-H-i-s'));
    
    // Start fetching
    $fetcher = new GoodreadsQuoteFetcher();
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
