<?php
/**
 * Quote Database Cleanup Utility
 * 
 * This script helps clean up the quotes database by:
 * 1. Removing duplicate quotes
 * 2. Normalizing author names
 * 3. Validating quotes for quality
 * 4. Removing inappropriate content
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

class QuoteDbCleaner
{
    private $db;
    private $statsRemoved = 0;
    private $statsFixed = 0;
    private $statsMerged = 0;
    
    public function __construct()
    {
        $this->db = new SQLite3('quotes.db');
        $this->db->exec('PRAGMA foreign_keys = ON');
    }
    
    public function __destruct()
    {
        $this->db->close();
    }
    
    /**
     * Run all cleanup tasks
     */
    public function runAll()
    {
        echo "Starting database cleanup...\n";
        
        // Backup database first
        echo "Creating backup...\n";
        copy('quotes.db', 'quotes.db.cleanup-backup-' . date('Y-m-d-H-i-s'));
        
        // Start transaction for faster operations
        $this->db->exec('BEGIN TRANSACTION');
        
        // Run tasks
        $this->removeDuplicates();
        $this->normalizeAuthors();
        $this->validateQuotes();
        $this->checkForInappropriateContent();
        $this->mergeSimilarQuotes();
        
        // Commit changes
        $this->db->exec('COMMIT');
        
        // Vacuum database to reclaim space
        echo "Optimizing database...\n";
        $this->db->exec('VACUUM');
        
        // Print summary
        echo "\nCleanup complete!\n";
        echo "- Removed: {$this->statsRemoved} quotes\n";
        echo "- Fixed: {$this->statsFixed} quotes\n";
        echo "- Merged: {$this->statsMerged} duplicate quotes\n";
        
        $countQuery = $this->db->query('SELECT COUNT(*) as count FROM quotes');
        $count = $countQuery->fetchArray(SQLITE3_ASSOC)['count'];
        echo "- Remaining: {$count} quotes\n";
    }
    
    /**
     * Remove duplicate quotes (exact matches)
     */
    private function removeDuplicates()
    {
        echo "Removing duplicate quotes...\n";
        
        // Find and remove exact duplicates
        $query = "
            DELETE FROM quotes 
            WHERE id NOT IN (
                SELECT MIN(id) 
                FROM quotes 
                GROUP BY quote
            )
        ";
        
        $result = $this->db->exec($query);
        $removed = $this->db->changes();
        $this->statsRemoved += $removed;
        
        echo "- Removed {$removed} exact duplicate quotes\n";
    }
    
    /**
     * Normalize author names
     */
    private function normalizeAuthors()
    {
        echo "Normalizing author names...\n";
        
        // Common misspellings and variations
        $authorMappings = [
            'علي بن أبي طالب' => ['علي ابن ابي طالب', 'علي ابن أبي طالب', 'الإمام علي', 'الامام علي', 'علي رضي الله عنه'],
            'عمر بن الخطاب' => ['عمر ابن الخطاب', 'الفاروق عمر', 'عمر الخطاب', 'عمر رضي الله عنه'],
            'أبو بكر الصديق' => ['ابو بكر', 'أبوبكر الصديق', 'ابوبكر'],
            'ابن سينا' => ['أبن سينا', 'إبن سينا', 'ابو علي ابن سينا', 'أبو علي ابن سينا'],
            'ابن خلدون' => ['إبن خلدون', 'أبن خلدون'],
            // Add more mappings as needed
        ];
        
        $fixed = 0;
        
        foreach ($authorMappings as $correctName => $variations) {
            foreach ($variations as $variation) {
                $query = "UPDATE quotes SET author = :correct WHERE author = :variation";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':correct', $correctName, SQLITE3_TEXT);
                $stmt->bindValue(':variation', $variation, SQLITE3_TEXT);
                $stmt->execute();
                $fixed += $this->db->changes();
            }
        }
        
        $this->statsFixed += $fixed;
        echo "- Fixed {$fixed} author names\n";
    }
    
    /**
     * Validate quotes for quality
     */
    private function validateQuotes()
    {
        echo "Validating quotes for quality...\n";
        
        // Remove quotes that are too short or too long
        $query = "DELETE FROM quotes WHERE LENGTH(quote) < 15 OR LENGTH(quote) > 500";
        $this->db->exec($query);
        $removed = $this->db->changes();
        $this->statsRemoved += $removed;
        
        echo "- Removed {$removed} quotes that were too short or too long\n";
        
        // Remove quotes that don't contain Arabic text
        $removed = 0;
        $result = $this->db->query("SELECT id, quote FROM quotes");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if (!$this->containsArabic($row['quote'])) {
                $stmt = $this->db->prepare("DELETE FROM quotes WHERE id = :id");
                $stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $stmt->execute();
                $removed++;
            }
        }
        
        $this->statsRemoved += $removed;
        echo "- Removed {$removed} quotes that don't contain Arabic text\n";
        
        // Remove quotes with common problems (HTML, markdown, etc.)
        $problemPatterns = [
            '/<\/?[a-z][\s\S]*>/i', // HTML tags
            '/\[.*?\]\(.*?\)/', // Markdown links
            '/http[s]?:\/\/\S+/', // URLs
            '/^\s*\d+\.\s+/', // Numbered lists
        ];
        
        $fixed = 0;
        $removed = 0;
        
        $result = $this->db->query("SELECT id, quote FROM quotes");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $originalQuote = $row['quote'];
            $fixedQuote = $originalQuote;
            
            foreach ($problemPatterns as $pattern) {
                $fixedQuote = preg_replace($pattern, '', $fixedQuote);
            }
            
            $fixedQuote = trim($fixedQuote);
            
            if (strlen($fixedQuote) < 15) {
                // If the fixed quote is too short, remove it
                $stmt = $this->db->prepare("DELETE FROM quotes WHERE id = :id");
                $stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $stmt->execute();
                $removed++;
            } elseif ($fixedQuote !== $originalQuote) {
                // Update the fixed quote
                $stmt = $this->db->prepare("UPDATE quotes SET quote = :quote WHERE id = :id");
                $stmt->bindValue(':quote', $fixedQuote, SQLITE3_TEXT);
                $stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $stmt->execute();
                $fixed++;
            }
        }
        
        $this->statsFixed += $fixed;
        $this->statsRemoved += $removed;
        
        echo "- Fixed {$fixed} quotes with formatting issues\n";
        echo "- Removed {$removed} quotes that couldn't be fixed\n";
    }
    
    /**
     * Check for and remove inappropriate content
     */
    private function checkForInappropriateContent()
    {
        echo "Checking for inappropriate content...\n";
        
        // List of patterns that might indicate inappropriate content
        $inappropriatePatterns = [
            '/\b(porn|xxx|sex|naked)\b/i',
            // Add more patterns as needed
        ];
        
        $removed = 0;
        $result = $this->db->query("SELECT id, quote FROM quotes");
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            foreach ($inappropriatePatterns as $pattern) {
                if (preg_match($pattern, $row['quote'])) {
                    $stmt = $this->db->prepare("DELETE FROM quotes WHERE id = :id");
                    $stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                    $stmt->execute();
                    $removed++;
                    break;
                }
            }
        }
        
        $this->statsRemoved += $removed;
        echo "- Removed {$removed} quotes with potentially inappropriate content\n";
    }
    
    /**
     * Merge similar quotes
     */
    private function mergeSimilarQuotes()
    {
        echo "Merging similar quotes...\n";
        
        $merged = 0;
        $result = $this->db->query("SELECT id, quote FROM quotes ORDER BY id");
        
        $processedQuotes = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $normalizedQuote = $this->normalizeQuote($row['quote']);
            
            foreach ($processedQuotes as $id => $quote) {
                $similarity = $this->calculateSimilarity($normalizedQuote, $quote);
                
                // If similarity is over 90%, consider it a duplicate
                if ($similarity > 90) {
                    $stmt = $this->db->prepare("DELETE FROM quotes WHERE id = :id");
                    $stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                    $stmt->execute();
                    $merged++;
                    break;
                }
            }
            
            // Add to processed quotes if not deleted
            if (!isset($similarity) || $similarity <= 90) {
                $processedQuotes[$row['id']] = $normalizedQuote;
            }
        }
        
        $this->statsMerged += $merged;
        echo "- Merged {$merged} similar quotes\n";
    }
    
    /**
     * Normalize quote for comparison
     */
    private function normalizeQuote($quote)
    {
        // Remove punctuation
        $quote = preg_replace('/[[:punct:]]/', '', $quote);
        
        // Remove extra spaces
        $quote = preg_replace('/\s+/', ' ', $quote);
        
        // Convert to lowercase (for non-Arabic parts)
        $quote = mb_strtolower($quote, 'UTF-8');
        
        return trim($quote);
    }
    
    /**
     * Calculate similarity between two strings (using Levenshtein distance)
     */
    private function calculateSimilarity($str1, $str2)
    {
        $lev = levenshtein($str1, $str2);
        $maxLen = max(strlen($str1), strlen($str2));
        
        if ($maxLen === 0) {
            return 100;
        }
        
        return (1 - $lev / $maxLen) * 100;
    }
    
    /**
     * Check if text contains Arabic characters
     */
    private function containsArabic($text)
    {
        return preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $text);
    }
}

// Main execution
try {
    echo "Quote Database Cleanup Utility\n";
    echo "=============================\n\n";
    
    $cleaner = new QuoteDbCleaner();
    $cleaner->runAll();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
