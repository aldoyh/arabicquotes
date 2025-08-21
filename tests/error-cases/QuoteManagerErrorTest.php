<?php
use PHPUnit\Framework\TestCase;

class QuoteManagerErrorTest extends TestCase
{
    private $quoteManager;
    private $invalidDbFile = 'invalid.db';
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->quoteManager = new QuoteManager($this->invalidDbFile);
    }
    
    public function testInvalidDatabaseAccess()
    {
        $quote = $this->quoteManager->getRandomQuote();
        $this->assertNull($quote);
    }
    
    public function testLogFileError()
    {
        // Set invalid path for logging
        $reflectionClass = new ReflectionClass(QuoteManager::class);
        $property = $reflectionClass->getProperty('basePath');
        $property->setAccessible(true);
        $property->setValue($this->quoteManager, sys_get_temp_dir() . '/invalid_path/');
        
        $result = $this->quoteManager->logQuoteUpdate('Test message');
        $this->assertFalse($result);
    }
}