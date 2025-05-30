<?php
/**
 * Comprehensive Wikiquote API Fetcher
 * 
 * This script fetches all quotes (4000+) from Wikiquote's API and saves them to the database.
 * It uses pagination, error handling, and duplicate detection for efficient processing.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0); // No time limit
ini_set('memory_limit', '1G');    // Increase memory limit

require_once __DIR__ . '/index.php';

/**
 * Class to handle the bulk fetching of quotes from Wikiquote API
 */
class ComprehensiveWikiquoteFetcher
{
    private $apiUrl = 'https://ar.wikiquote.org/w/api.php';
    private $baseUrl = 'https://ar.wikiquote.org';
    private $db;
    private $fetchedQuotes = 0;
    private $duplicates = 0;
    private $errors = 0;
    private $startTime;
    private $processedPages = [];
    private $rateLimitDelay = 500000; // 0.5 seconds between requests
    private $quoteCategories = [
        'تصنيف:مقولات_بحسب_الموضوع',
        'تصنيف:مقولات_بحسب_الشخص',
        'تصنيف:أمثال_عربية',
        'تصنيف:حكم',
        'تصنيف:أقوال_مأثورة'
    ];
    
    /**
     * Constructor - initialize database connection
     */
    public function __construct()
    {
        $this->db = new SQLite3(__DIR__ . '/quotes.db');
        if (!$this->db) {
            die("Failed to connect to the database\n");
        }
        
        // Check if the quotes table exists, create it if not
        $this->db->exec('CREATE TABLE IF NOT EXISTS quotes (
            id INTEGER PRIMARY KEY, 
            quote TEXT, 
            author TEXT, 
            image TEXT, 
            hits INTEGER DEFAULT 0
        )');
        
        // Create indexes for performance
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_quote ON quotes(quote)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_author ON quotes(author)');
        
        $this->startTime = microtime(true);
    }
    
    /**
     * Main method to orchestrate the fetching process
     */
    public function fetchAllQuotes($testMode = false)
    {
        echo "Starting comprehensive quote fetch process...\n";
        
        // Begin transaction for better performance
        $this->db->exec('BEGIN TRANSACTION');
        
        try {
            // For test mode, only fetch a few quotes
            if ($testMode) {
                echo "RUNNING IN TEST MODE - Limited fetch\n";
                $this->rateLimitDelay = 100000; // 0.1 seconds between requests in test mode
            }
            
            // Step 1: Fetch all categories related to quotes
            echo "Fetching categories...\n";
            $allCategories = $this->getAllCategories();
            echo "Found " . count($allCategories) . " categories\n";
            
            // Step 2: Fetch all pages in those categories
            echo "Fetching pages from categories...\n";
            $allPages = $this->getAllPagesInCategories($allCategories);
            echo "Found " . count($allPages) . " pages\n";
            
            // In test mode, limit the number of pages
            if ($testMode) {
                $allPages = array_slice($allPages, 0, 5, true);
                echo "Test mode: Limited to 5 pages\n";
            }
            
            // Step 3: Fetch quotes from each page
            echo "Fetching quotes from pages...\n";
            $this->fetchQuotesFromPages($allPages);
            
            // Step 4: Fetch quotes from special pages like featured quotes, etc.
            echo "Fetching quotes from special pages...\n";
            $this->fetchQuotesFromSpecialPages();
            
            // Commit the transaction
            $this->db->exec('COMMIT');
            
            // Final statistics
            $this->printStats();
        } catch (Exception $e) {
            // Rollback if there's an error
            $this->db->exec('ROLLBACK');
            echo "Error: " . $e->getMessage() . "\n";
        }
        
        // Close the database connection
        $this->db->close();
    }
    
    /**
     * Get all categories and subcategories related to quotes
     */
    private function getAllCategories()
    {
        $allCategories = [];
        $categoriesToProcess = $this->quoteCategories;
        $processedCategories = [];
        
        while (!empty($categoriesToProcess)) {
            $category = array_shift($categoriesToProcess);
            
            if (in_array($category, $processedCategories)) {
                continue; // Skip if already processed
            }
            
            $processedCategories[] = $category;
            $allCategories[] = $category;
            
            echo "Processing category: $category\n";
            
            // Get subcategories
            $subcategories = $this->getSubcategories($category);
            foreach ($subcategories as $subcategory) {
                if (!in_array($subcategory, $processedCategories) && !in_array($subcategory, $categoriesToProcess)) {
                    $categoriesToProcess[] = $subcategory;
                }
            }
            
            // Add delay to avoid rate limiting
            usleep($this->rateLimitDelay);
        }
        
        return $allCategories;
    }
    
    /**
     * Get subcategories for a given category
     */
    private function getSubcategories($category)
    {
        $subcategories = [];
        $categoryName = str_replace('تصنيف:', '', $category);
        
        $params = [
            'action' => 'query',
            'list' => 'categorymembers',
            'cmtitle' => $category,
            'cmtype' => 'subcat',
            'cmlimit' => 500,
            'format' => 'json',
            'utf8' => 1
        ];
        
        $response = $this->makeApiRequest($params);
        
        if (isset($response['query']['categorymembers'])) {
            foreach ($response['query']['categorymembers'] as $member) {
                if (isset($member['title']) && strpos($member['title'], 'تصنيف:') === 0) {
                    $subcategories[] = $member['title'];
                }
            }
        }
        
        return $subcategories;
    }
    
    /**
     * Get all pages from a list of categories
     */
    private function getAllPagesInCategories($categories)
    {
        $allPages = [];
        
        foreach ($categories as $category) {
            echo "Getting pages from category: $category\n";
            
            $params = [
                'action' => 'query',
                'list' => 'categorymembers',
                'cmtitle' => $category,
                'cmtype' => 'page',
                'cmlimit' => 500,
                'format' => 'json',
                'utf8' => 1
            ];
            
            $response = $this->makeApiRequest($params);
            
            if (isset($response['query']['categorymembers'])) {
                foreach ($response['query']['categorymembers'] as $member) {
                    if (isset($member['title']) && strpos($member['title'], 'تصنيف:') !== 0) {
                        $allPages[$member['pageid']] = $member['title'];
                    }
                }
            }
            
            // Handle pagination with 'continue'
            while (isset($response['continue'])) {
                $continueParams = array_merge($params, $response['continue']);
                $response = $this->makeApiRequest($continueParams);
                
                if (isset($response['query']['categorymembers'])) {
                    foreach ($response['query']['categorymembers'] as $member) {
                        if (isset($member['title']) && strpos($member['title'], 'تصنيف:') !== 0) {
                            $allPages[$member['pageid']] = $member['title'];
                        }
                    }
                }
                
                // Add delay to avoid rate limiting
                usleep($this->rateLimitDelay);
            }
            
            // Add delay to avoid rate limiting
            usleep($this->rateLimitDelay);
        }
        
        return $allPages;
    }
    
    /**
     * Fetch quotes from a list of pages
     */
    private function fetchQuotesFromPages($pages)
    {
        $count = 0;
        $total = count($pages);
        
        foreach ($pages as $pageId => $pageTitle) {
            $count++;
            
            // Skip if we've already processed this page
            if (in_array($pageId, $this->processedPages)) {
                continue;
            }
            
            $this->processedPages[] = $pageId;
            
            echo "[$count/$total] Processing page: $pageTitle (ID: $pageId)\n";
            
            try {
                // Get the page content
                $params = [
                    'action' => 'parse',
                    'pageid' => $pageId,
                    'prop' => 'text',
                    'format' => 'json',
                    'utf8' => 1
                ];
                
                $response = $this->makeApiRequest($params);
                
                if (isset($response['parse']['text']['*'])) {
                    $content = $response['parse']['text']['*'];
                    
                    // Extract quotes from the page content
                    $this->extractAndSaveQuotes($content, $pageTitle);
                }
            } catch (Exception $e) {
                echo "Error processing page $pageTitle: " . $e->getMessage() . "\n";
                $this->errors++;
            }
            
            // Commit every 50 pages to avoid large transactions
            if ($count % 50 === 0) {
                $this->db->exec('COMMIT');
                $this->db->exec('BEGIN TRANSACTION');
                $this->printStats();
            }
            
            // Add delay to avoid rate limiting
            usleep($this->rateLimitDelay);
        }
    }
    
    /**
     * Fetch quotes from special pages (featured quotes, main page, etc.)
     */
    private function fetchQuotesFromSpecialPages()
    {
        $specialPages = [
            'الصفحة_الرئيسية',
            'ويكي_اقتباس:اقتباس_اليوم',
            'ويكي_اقتباس:اقتباسات_مختارة'
        ];
        
        foreach ($specialPages as $pageTitle) {
            echo "Processing special page: $pageTitle\n";
            
            try {
                // Get the page content
                $params = [
                    'action' => 'parse',
                    'page' => $pageTitle,
                    'prop' => 'text',
                    'format' => 'json',
                    'utf8' => 1
                ];
                
                $response = $this->makeApiRequest($params);
                
                if (isset($response['parse']['text']['*'])) {
                    $content = $response['parse']['text']['*'];
                    
                    // Extract quotes from the page content
                    $this->extractAndSaveQuotes($content, $pageTitle);
                }
            } catch (Exception $e) {
                echo "Error processing special page $pageTitle: " . $e->getMessage() . "\n";
                $this->errors++;
            }
            
            // Add delay to avoid rate limiting
            usleep($this->rateLimitDelay);
        }
    }
    
    /**
     * Extract quotes from HTML content and save to database
     */
    private function extractAndSaveQuotes($content, $pageTitle)
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // Suppress HTML5 parsing errors
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Different patterns to match quotes
        $patterns = [
            // Quotes in blockquotes - high confidence
            '//blockquote',
            // Table cells in the featured quotes tables
            '//table[contains(@class, "wikitable")]//td',
            // List items that contain quotes
            '//ul/li[contains(., "—") or contains(., "-")]',
            // Paragraphs that look like quotes (with attribution)
            '//p[contains(., "—") or contains(., "-")]',
            // Quotes in div with class mw-parser-output - more careful validation needed
            '//div[@class="mw-parser-output"]/p[not(contains(., "مشاريع")) and not(contains(., "ويكي"))]'
        ];
        
        $quotesFound = 0;
        
        foreach ($patterns as $pattern) {
            $elements = $xpath->query($pattern);
            
            foreach ($elements as $element) {
                $text = trim($element->textContent);
                
                // Skip if too short or too long
                if (mb_strlen($text) < 15 || mb_strlen($text) > 1000) {
                    continue;
                }
                
                // Skip navigation elements, edit links, etc.
                if (strpos($text, '[عدل]') !== false || 
                    strpos($text, 'تصفح') !== false || 
                    strpos($text, 'انتقل') !== false) {
                    continue;
                }
                
                // Try to separate quote from author
                $author = $pageTitle;
                $quote = $text;
                
                // If the text contains a dash or em dash, it might separate the quote from the author
                if (mb_strpos($text, '—') !== false) {
                    $parts = explode('—', $text);
                    if (count($parts) >= 2) {
                        $quote = trim($parts[0]);
                        // Take the last part as author if there are multiple dashes
                        $author = trim(end($parts));
                    }
                } elseif (mb_strpos($text, '-') !== false) {
                    $parts = explode('-', $text);
                    if (count($parts) >= 2) {
                        $quote = trim($parts[0]);
                        // Take the last part as author if there are multiple dashes
                        $author = trim(end($parts));
                    }
                }
                
                // Clean up the author and quote
                $author = $this->cleanText($author);
                $quote = $this->cleanText($quote);
                
                // Skip if the author name is extremely long (likely not an author)
                if (mb_strlen($author) > 50) {
                    $author = $pageTitle;
                }
                
                // Skip if too short after cleaning
                if (mb_strlen($quote) < 15) {
                    continue;
                }
                
                // Skip obvious non-quotes (like headings or navigation)
                if ($this->isNonQuote($quote)) {
                    continue;
                }
                
                // Save to database
                $this->saveQuote($quote, $author);
                $quotesFound++;
            }
        }
        
        echo "Found $quotesFound potential quotes on page: $pageTitle\n";
    }
    
    /**
     * Save a quote to the database
     */
    private function saveQuote($quote, $author)
    {
        // Check if the quote already exists
        $stmt = $this->db->prepare('SELECT id FROM quotes WHERE quote = :quote');
        $stmt->bindValue(':quote', $quote, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($result->fetchArray()) {
            $this->duplicates++;
            return;
        }
        
        // Insert the new quote
        $stmt = $this->db->prepare('INSERT INTO quotes (quote, author, hits) VALUES (:quote, :author, 0)');
        $stmt->bindValue(':quote', $quote, SQLITE3_TEXT);
        $stmt->bindValue(':author', $author, SQLITE3_TEXT);
        $stmt->execute();
        
        $this->fetchedQuotes++;
    }
    
    /**
     * Clean up text (remove extra whitespace, HTML entities, etc.)
     */
    private function cleanText($text)
    {
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Remove square brackets and content inside (like [citation needed])
        $text = preg_replace('/\[.*?\]/', '', $text);
        
        // Remove any URLs
        $text = preg_replace('/https?:\/\/\S+/', '', $text);
        
        // Replace multiple whitespace with a single space
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove non-quote-like parts (e.g., navigation elements)
        $text = preg_replace('/صفحات مختارة.*?أبجدية/s', '', $text);
        $text = preg_replace('/قائمة الصفحات.*?تصانيف/s', '', $text);
        
        // Trim leading/trailing whitespace and special characters
        $text = trim($text, " \t\n\r\0\x0B.,;:\"'()[]{}");
        
        return $text;
    }
    
    /**
     * Check if text is not a quote
     */
    private function isNonQuote($text)
    {
        $nonQuotePatterns = [
            '/^(تصفح|انتقل)/',
            '/^(القائمة|الصفحة)/',
            '/\[\[/',
            '/\]\]/',
            '/تعديل/',
            '/^http/',
            '/^www\./',
            '/ويك/', // Any wiki reference
            '/كومنز/', // Commons reference
            '/^مشاريع شقيقة/', // Sister projects
            '/^اللغات/', // Languages section
            '/^تصنيف:/', // Category labels
            '/^قالب:/', // Template labels
            '/^مساعدة:/', // Help labels
            '/صفحات مختارة/', // Selected pages
            '/قائمة الصفحات/', // Page list
            '/اقتباسات حسب ترتيب/', // Quotes by order
            '/الترتيب حسب/', // Order by
            '/أقوال حسب/', // Quotes by
            '/انظر أيضا/', // See also
            '/وصلات خارجية/', // External links
            '/مراجع/' // References
        ];
        
        foreach ($nonQuotePatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        // Check if the text is too short or doesn't look like a quote
        if (mb_strlen($text) < 15) {
            return true;
        }
        
        // Check if the text has common punctuation that usually appears in quotes
        if (!preg_match('/[.،؛!؟:]/', $text)) {
            return true;
        }
        
        // Check if the text is primarily navigation or menu items
        if (mb_substr_count($text, '|') > 1) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Make a request to the Wikiquote API
     */
    private function makeApiRequest($params)
    {
        $url = $this->apiUrl . '?' . http_build_query($params);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: ArabicQuotesFetcher/1.0 (https://github.com/aldoyh/arabicquotes)',
                'timeout' => 30
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to fetch data from API");
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse API response: " . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Print statistics about the fetching process
     */
    private function printStats()
    {
        $elapsed = microtime(true) - $this->startTime;
        $hours = floor($elapsed / 3600);
        $minutes = floor(($elapsed % 3600) / 60);
        $seconds = floor($elapsed % 60);
        
        echo "\n==== STATISTICS ====\n";
        echo "Quotes fetched: {$this->fetchedQuotes}\n";
        echo "Duplicates skipped: {$this->duplicates}\n";
        echo "Errors encountered: {$this->errors}\n";
        echo "Time elapsed: {$hours}h {$minutes}m {$seconds}s\n";
        echo "====================\n\n";
    }
}

// Run the fetcher if this script is executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $testMode = isset($argv[1]) && $argv[1] === 'test';
    $fetcher = new ComprehensiveWikiquoteFetcher();
    $fetcher->fetchAllQuotes($testMode);
}
