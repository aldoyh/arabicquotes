<?php
use PHPUnit\Framework\TestCase;

class WikiquoteFetcherTest extends TestCase
{
    private $wikiquoteFetcher;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->wikiquoteFetcher = new WikiquoteFetcher(2, 5); // 2 retries, 5 second timeout
    }
    
    public function testFetchRandomWikiQuote()
    {
        // Test fetching a random quote
        $quote = $this->wikiquoteFetcher->fetchRandomWikiQuote();
        $this->assertIsArray($quote);
        $this->assertArrayHasKey('quote', $quote);
        $this->assertArrayHasKey('author', $quote);
    }
    
    public function testFetchFromWiki()
    {
        // Test the private fetchFromWiki method using Reflection
        $method = new ReflectionMethod(WikiquoteFetcher::class, 'fetchFromWiki');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->wikiquoteFetcher);
        $this->assertNotEmpty($result);
    }
    
    public function testTimeout()
    {
        $slowFetcher = new WikiquoteFetcher(1, 1); // 1 retry, 1 second timeout
        $this->expectException(Exception::class);
        $method = new ReflectionMethod(WikiquoteFetcher::class, 'fetchRaw');
        $method->setAccessible(true);
        $method->invoke($slowFetcher);
    }
}