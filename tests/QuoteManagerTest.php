<?php
use PHPUnit\Framework\TestCase;

class QuoteManagerTest extends TestCase
{
    private $quoteManager;
    private $testDbFile = 'test_quotes.db';
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->quoteManager = new QuoteManager(TEST_DB_FILE);
        // Test database is handled in bootstrap.php
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->testDbFile)) {
            unlink($this->testDbFile);
        }
    }
    
    public function testGetRandomQuote()
    {
        // Test getting a random quote
        $quote = $this->quoteManager->getRandomQuote();
        $this->assertIsArray($quote);
        $this->assertArrayHasKey('quote', $quote);
        $this->assertArrayHasKey('author', $quote);
        $this->assertArrayHasKey('hits', $quote);
    }
    
    public function testLogQuoteUpdate()
    {
        // Test logging quote updates
        $logMessage = "Test log message";
        $result = $this->quoteManager->logQuoteUpdate($logMessage);
        $this->assertTrue($result);
    }
    
    public function testGenerateQuoteMarkdown()
    {
        // Test markdown generation
        $quote = [
            'quote' => 'Test quote',
            'author' => 'Test author',
            'hits' => 1,
            'image' => 'test.jpg'
        ];
        
        $markdown = $this->quoteManager->generateQuoteMarkdown($quote);
        $this->assertStringContainsString('Test quote', $markdown);
        $this->assertStringContainsString('Test author', $markdown);
    }
}