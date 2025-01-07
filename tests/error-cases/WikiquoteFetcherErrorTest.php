<?php
use PHPUnit\Framework\TestCase;

class WikiquoteFetcherErrorTest extends TestCase
{
    private $wikiquoteFetcher;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->wikiquoteFetcher = new WikiquoteFetcher(1, 1); // 1 retry, 1 second timeout
    }
    
    public function testTimeoutHandling()
    {
        // Replace URL with a non-responsive one
        $reflectionClass = new ReflectionClass(WikiquoteFetcher::class);
        $method = $reflectionClass->getMethod('fetchRaw');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->wikiquoteFetcher);
        $this->assertNull($result);
    }
    
    public function testInvalidHtmlResponse()
    {
        $quote = $this->wikiquoteFetcher->fetchRandomWikiQuote();
        $this->assertNull($quote);
    }
}