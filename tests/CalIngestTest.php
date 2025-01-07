<?php
use PHPUnit\Framework\TestCase;

class CalIngestTest extends TestCase
{
    private $testJsonFile = 'test_calendar_data.json';
    
    protected function setUp(): void
    {
        parent::setUp();
        if (file_exists($this->testJsonFile)) {
            unlink($this->testJsonFile);
        }
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->testJsonFile)) {
            unlink($this->testJsonFile);
        }
    }
    
    public function testValidateCalendarData()
    {
        // Test valid data
        $validData = [
            'action' => 'publish',
            'calendar_data' => [
                'title' => 'Test Event',
                'date' => '2023-12-25',
                'description' => 'Test Description'
            ]
        ];
        
        $validData = [
            'action' => 'publish',
            'calendar_data' => [
                'title' => 'Test Event',
                'start_date' => '2023-12-25',
                'end_date' => '2023-12-26',
                'description' => 'Test Description'
            ]
        ];
        $this->assertTrue(validateCalendarData($validData));
        
        // Test invalid data
        $invalidData = [
            'action' => 'wrong_action',
            'calendar_data' => []
        ];
        
        $this->assertFalse(validateCalendarData($invalidData));
    }
}