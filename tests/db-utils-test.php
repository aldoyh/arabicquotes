<?php
use PHPUnit\Framework\TestCase;

class DbUtilsTest extends TestCase
{
    private $testDb;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testDb = new SQLite3(TEST_DB_FILE);
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->testDb->close();
    }
    
    public function testCreateTable()
    {
        // Test table creation
        create_table($this->testDb);
        
        $result = $this->testDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name='quotes'");
        $this->assertNotFalse($result);
        $row = $result->fetchArray();
        $this->assertEquals('quotes', $row['name']);
    }
    
    public function testImportData()
    {
        // Test data import
        create_table($this->testDb);
        $testData = [
            ['quote' => 'Test Quote', 'author' => 'Test Author', 'hits' => 0]
        ];
        import_data($this->testDb, $testData);
        
        $result = $this->testDb->query("SELECT * FROM quotes WHERE author='Test Author'");
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $this->assertEquals('Test Quote', $row['quote']);
    }
}