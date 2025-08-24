<?php
/**
 * Wikiquote Bulk Fetcher
 * 
 * This script retrieves all quotes from Wikiquote and saves them to the database.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0); // No time limit for execution
ini_set('memory_limit', '512M');  // Increase memory limit

require_once __DIR__ . '/index.php';
require_once __DIR__ . '/.github/scripts/hourly.php';

/**
 * Fetches quotes from Wikiquote pages
 */
class WikiquoteBulkFetcher extends WikiquoteFetcher
{
    private $quoteManager;
    private $baseUrl = "https://ar.wikiquote.org";
    private $mainPageUrl = "/wiki/%D8%A7%D9%84%D8%B5%D9%81%D8%AD%D8%A9_%D8%A7%D9%84%D8%B1%D8%A6%D9%8A%D8%B3%D9%8A%D8%A9";
    private $categoryPageUrl = "/wiki/%D8%AA%D8%B5%D9%86%D9%8A%D9%81:%D9%85%D9%82%D9%88%D9%84%D8%A7%D8%AA_%D8%A8%D8%AD%D8%B3%D8%A8_%D8%A7%D9%84%D9%85%D9%88%D8%B6%D9%88%D8%B9";
    private $fetchedQuotes = 0;
    private $fetchedAuthors = [];
    private $fetchedPages = [];
    
    public function __construct()
    {
        $this->quoteManager = new QuoteManager();
    }
    
    /**
     * Main method to fetch all quotes
     */
    public function fetchAllQuotes()
    {
        echo "Starting the bulk quote fetching process...\n";
        
        // Step 1: Get category pages
        echo "Fetching category pages...\n";
        $categoryUrls = $this->getCategoryPages();
        echo "Found " . count($categoryUrls) . " category pages.\n";
        
        // Step 2: Get author pages from categories
        echo "Fetching author pages from categories...\n";
        $authorUrls = $this->getAuthorPages($categoryUrls);
        echo "Found " . count($authorUrls) . " author pages.\n";
        
        // Step 3: Get quotes from each author page
        echo "Fetching quotes from author pages...\n";
        $this->fetchQuotesFromAuthors($authorUrls);
        
        // Step 4: Get quotes from the main page
        echo "Fetching quotes from the main page...\n";
        $this->fetchQuotesFromMainPage();
        
        echo "Bulk fetching process completed. Total quotes fetched: " . $this->fetchedQuotes . "\n";
    }
    
    /**
     * Get all category pages
     */
    private function getCategoryPages()
    {
        $html = $this->fetchUrl($this->baseUrl . $this->categoryPageUrl);
        if (!$html) {
            echo "Failed to fetch category page.\n";
            return [];
        }
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Get all category links
        $categoryLinks = $xpath->query('//div[@class="mw-category-group"]//a');
        $urls = [];
        
        foreach ($categoryLinks as $link) {
            if ($link instanceof DOMElement) {
                $href = $link->getAttribute('href');
                if (strpos($href, '/wiki/') === 0) {
                    $urls[] = $href;
                }
            }
        }
        
        return $urls;
    }
    
    /**
     * Get author pages from category pages
     */
    private function getAuthorPages($categoryUrls)
    {
        $authorUrls = [];
        
        foreach ($categoryUrls as $categoryUrl) {
            echo "Processing category: " . $categoryUrl . "\n";
            
            $html = $this->fetchUrl($this->baseUrl . $categoryUrl);
            if (!$html) {
                echo "Failed to fetch category: " . $categoryUrl . "\n";
                continue;
            }
            
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            
            // Get all links to author pages
            $authorLinks = $xpath->query('//div[@id="mw-pages"]//a');
            
            foreach ($authorLinks as $link) {
                $href = $link->getAttribute('href');
                if (strpos($href, '/wiki/') === 0 && !in_array($href, $authorUrls)) {
                    $authorUrls[] = $href;
                }
            }
            
            // Look for the "next page" link if available
            $nextPageLinks = $xpath->query('//a[contains(text(), "الصفحة التالية")]');
            if ($nextPageLinks->length > 0) {
                $nextPageUrl = $nextPageLinks->item(0)->getAttribute('href');
                if (!in_array($nextPageUrl, $this->fetchedPages)) {
                    $this->fetchedPages[] = $nextPageUrl;
                    $subCategoryUrls = [$nextPageUrl];
                    $authorUrls = array_merge($authorUrls, $this->getAuthorPages($subCategoryUrls));
                }
            }
            
            // Add a small delay to avoid overwhelming the server
            usleep(500000); // 0.5 seconds
        }
        
        return array_unique($authorUrls);
    }
    
    /**
     * Fetch quotes from author pages
     */
    private function fetchQuotesFromAuthors($authorUrls)
    {
        $db = new SQLite3('quotes.db');
        
        foreach ($authorUrls as $authorUrl) {
            echo "Processing author: " . $authorUrl . "\n";
            
            $html = $this->fetchUrl($this->baseUrl . $authorUrl);
            if (!$html) {
                echo "Failed to fetch author: " . $authorUrl . "\n";
                continue;
            }
            
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            
            // Get author name
            $authorName = '';
            $titleElements = $xpath->query('//h1[@id="firstHeading"]');
            if ($titleElements->length > 0) {
                $authorName = trim($titleElements->item(0)->textContent);
            }
            
            if (empty($authorName)) {
                echo "Could not find author name for: " . $authorUrl . "\n";
                continue;
            }
            
            // Skip if we've already processed this author
            if (in_array($authorName, $this->fetchedAuthors)) {
                continue;
            }
            
            $this->fetchedAuthors[] = $authorName;
            echo "Found author: " . $authorName . "\n";
            
            // Get quotes
            $quotesElements = $xpath->query('//div[@class="mw-parser-output"]/ul/li | //div[@class="mw-parser-output"]/p');
            
            $quotesCount = 0;
            foreach ($quotesElements as $quoteElement) {
                $quoteText = trim($quoteElement->textContent);
                
                // Skip empty quotes or very short text (likely not a quote)
                if (empty($quoteText) || mb_strlen($quoteText) < 15) {
                    continue;
                }
                
                // Clean up the quote
                $quoteText = $this->cleanText($quoteText);
                
                // Skip if the quote already exists in the database
                $existingQuote = $db->querySingle("SELECT id FROM quotes WHERE quote = '" . SQLite3::escapeString($quoteText) . "'");
                if ($existingQuote) {
                    continue;
                }
                
                // Insert the quote into the database
                $stmt = $db->prepare('INSERT INTO quotes (quote, author, hits, category) VALUES (:quote, :author, 0, :category)');
                $stmt->bindValue(':quote', $quoteText, SQLITE3_TEXT);
                $stmt->bindValue(':author', $authorName, SQLITE3_TEXT);
                $stmt->bindValue(':category', $this->getCategoryFromUrl($authorUrl), SQLITE3_TEXT);
                $stmt->execute();
                
                $quotesCount++;
                $this->fetchedQuotes++;
            }
            
            echo "Added " . $quotesCount . " quotes from " . $authorName . "\n";
            
            // Add a small delay to avoid overwhelming the server
            usleep(500000); // 0.5 seconds
        }
        
        $db->close();
    }
    
    /**
     * Fetch quotes from the main page
     */
    private function fetchQuotesFromMainPage()
    {
        $html = $this->fetchUrl($this->baseUrl . $this->mainPageUrl);
        if (!$html) {
            echo "Failed to fetch main page.\n";
            return;
        }
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Get quote of the day
        $quoteElements = $xpath->query('//div[contains(@class, "quotation")]');
        
        $db = new SQLite3('quotes.db');
        
        foreach ($quoteElements as $quoteElement) {
            $quoteText = trim($quoteElement->textContent);
            $quoteText = $this->cleanText($quoteText);
            
            // Try to extract author from the quote
            $parts = explode('—', $quoteText);
            $actualQuote = trim($parts[0]);
            $author = isset($parts[1]) ? trim($parts[1]) : 'غير معروف';
            
            // Skip if the quote already exists in the database
            $existingQuote = $db->querySingle("SELECT id FROM quotes WHERE quote = '" . SQLite3::escapeString($actualQuote) . "'");
            if ($existingQuote) {
                continue;
            }
            
            // Insert the quote into the database
            $stmt = $db->prepare('INSERT INTO quotes (quote, author, hits, category) VALUES (:quote, :author, 0, "Featured")');
            $stmt->bindValue(':quote', $actualQuote, SQLITE3_TEXT);
            $stmt->bindValue(':author', $author, SQLITE3_TEXT);
            $stmt->execute();
            
            $this->fetchedQuotes++;
        }
        
        $db->close();
    }
    
    /**
     * Clean up text by removing extra spaces, newlines, etc.
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
     * Extract category from URL
     */
    private function getCategoryFromUrl($url)
    {
        $parts = explode(':', $url);
        if (count($parts) > 1) {
            return urldecode(end($parts));
        }
        
        return 'General';
    }
    
    /**
     * Fetch URL content with error handling
     */
    private function fetchUrl($url)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30, // 30 seconds timeout
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

/**
 * Alternate method that uses API to fetch quotes
 */
class WikiquoteApiFetcher
{
    private $apiUrl = 'https://ar.wikiquote.org/w/api.php';
    private $fetchedQuotes = 0;
    
    public function fetchAllQuotes()
    {
        echo "Starting API-based quote fetching process...\n";
        
        // Step 1: Get all categories
        echo "Fetching categories...\n";
        $categories = $this->getCategories();
        echo "Found " . count($categories) . " categories.\n";
        
        // Step 2: Get pages in each category
        echo "Fetching pages in categories...\n";
        $pages = $this->getPagesInCategories($categories);
        echo "Found " . count($pages) . " pages.\n";
        
        // Step 3: Get content from each page
        echo "Fetching content from pages...\n";
        $this->getQuotesFromPages($pages);
        
        echo "API fetching process completed. Total quotes fetched: " . $this->fetchedQuotes . "\n";
    }
    
    /**
     * Get all categories related to quotes
     */
    private function getCategories()
    {
        $params = [
            'action' => 'query',
            'list' => 'categorymembers',
            'cmtitle' => 'تصنيف:مقولات_بحسب_الموضوع',
            'cmlimit' => 500,
            'format' => 'json'
        ];
        
        $response = $this->makeApiRequest($params);
        
        $categories = [];
        if (isset($response['query']['categorymembers'])) {
            foreach ($response['query']['categorymembers'] as $category) {
                $categories[] = $category['title'];
            }
        }
        
        return $categories;
    }
    
    /**
     * Get all pages in given categories
     */
    private function getPagesInCategories($categories)
    {
        $allPages = [];
        
        foreach ($categories as $category) {
            echo "Processing category: " . $category . "\n";
            
            $params = [
                'action' => 'query',
                'list' => 'categorymembers',
                'cmtitle' => $category,
                'cmlimit' => 500,
                'format' => 'json'
            ];
            
            $response = $this->makeApiRequest($params);
            
            if (isset($response['query']['categorymembers'])) {
                foreach ($response['query']['categorymembers'] as $page) {
                    if ($page['ns'] === 0) { // Only include main namespace pages
                        $allPages[$page['pageid']] = $page['title'];
                    }
                }
            }
            
            // Add a small delay
            usleep(500000);
        }
        
        return $allPages;
    }
    
    /**
     * Get quotes from each page
     */
    private function getQuotesFromPages($pages)
    {
        $db = new SQLite3('quotes.db');
        $db->exec('BEGIN TRANSACTION');
        
        $count = 0;
        foreach ($pages as $pageId => $pageTitle) {
            echo "Processing page: " . $pageTitle . " (" . $pageId . ")\n";
            
            $params = [
                'action' => 'query',
                'prop' => 'extracts',
                'pageids' => $pageId,
                'explaintext' => true,
                'format' => 'json'
            ];
            
            $response = $this->makeApiRequest($params);
            
            if (isset($response['query']['pages'][$pageId]['extract'])) {
                $content = $response['query']['pages'][$pageId]['extract'];
                
                // Split content into potential quotes
                $lines = explode("\n", $content);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Skip empty lines or very short text
                    if (empty($line) || mb_strlen($line) < 15) {
                        continue;
                    }
                    
                    // Check if this looks like a quote
                    if ($this->isLikelyQuote($line)) {
                        // Clean up the quote
                        $line = $this->cleanText($line);
                        
                        // Try to extract author
                        $author = $pageTitle;
                        $parts = explode('—', $line);
                        if (count($parts) > 1) {
                            $line = trim($parts[0]);
                            // If there's an extracted author, use it, otherwise use page title
                            $extractedAuthor = trim($parts[1]);
                            if (!empty($extractedAuthor)) {
                                $author = $extractedAuthor;
                            }
                        }
                        
                        // Skip if the quote already exists in the database
                        $existingQuote = $db->querySingle("SELECT id FROM quotes WHERE quote = '" . SQLite3::escapeString($line) . "'");
                        if ($existingQuote) {
                            continue;
                        }
                        
                        // Insert the quote into the database
                        $stmt = $db->prepare('INSERT INTO quotes (quote, author, hits) VALUES (:quote, :author, 0)');
                        $stmt->bindValue(':quote', $line, SQLITE3_TEXT);
                        $stmt->bindValue(':author', $author, SQLITE3_TEXT);
                        $stmt->execute();
                        
                        $this->fetchedQuotes++;
                        $count++;
                        
                        if ($count % 100 === 0) {
                            $db->exec('COMMIT');
                            $db->exec('BEGIN TRANSACTION');
                            echo "Inserted " . $count . " quotes so far...\n";
                        }
                    }
                }
            }
            
            // Add a small delay
            usleep(500000);
        }
        
        $db->exec('COMMIT');
        $db->close();
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
            strpos($text, ':') !== false) {
            return true;
        }
        
        // If it ends with a period, question mark, or exclamation mark
        if (preg_match('/[.?!]$/', $text)) {
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
    echo "Arabic Quotes Bulk Fetcher\n";
    echo "=========================\n\n";
    
    // Backup the database first
    echo "Backing up the database...\n";
    copy('quotes.db', 'quotes.db.backup-' . date('Y-m-d-H-i-s'));
    
    // Ask the user which method to use
    echo "Choose fetching method:\n";
    echo "1. HTML parsing (slower but more accurate)\n";
    echo "2. API-based (faster but may miss some quotes)\n";
    echo "Your choice (1 or 2): ";
    $choice = trim(fgets(STDIN));
    
    if ($choice == '2') {
        $fetcher = new WikiquoteApiFetcher();
    } else {
        $fetcher = new WikiquoteBulkFetcher();
    }
    
    // Start fetching
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
