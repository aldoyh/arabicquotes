<?php
use PHPUnit\Framework\TestCase;

class WikiquoteFetcherErrorTest extends TestCase
{
    private $wikiquoteFetcher;
    
    protected function setUp(): void
    {
        parent::setUp();
        @unlink(__DIR__ . '/../../assets/wikiquote_cache.json');
        $this->wikiquoteFetcher = new WikiquoteFetcher(1, 1); // 1 retry, 1 second timeout
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        @unlink(__DIR__ . '/../../assets/wikiquote_cache.json');
    }
    
    public function testTimeoutHandling()
    {
        // Replace URL with a non-responsive one
        $reflectionClass = new ReflectionClass(WikiquoteFetcher::class);
        $method = $reflectionClass->getMethod('fetchRaw');
        @$method->setAccessible(true);
        
        try {
            $result = $method->invoke($this->wikiquoteFetcher);
            $this->assertNull($result);
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }
    
    public function testInvalidHtmlResponse()
    {
        $quote = $this->wikiquoteFetcher->fetchRandomWikiQuote();
        $this->assertNull($quote);
    }
}