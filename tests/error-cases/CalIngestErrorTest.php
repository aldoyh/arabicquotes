<?php
use PHPUnit\Framework\TestCase;

class CalIngestErrorTest extends TestCase
{
    public function testInvalidAction()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing or invalid action parameter");
        
        $invalidData = [
            'action' => 'invalid',
            'calendar_data' => []
        ];
        validateCalendarData($invalidData);
    }
    
    public function testInvalidDate()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid date format - must be YYYY-MM-DD");
        
        $invalidData = [
            'action' => 'publish',
            'calendar_data' => [
                'date' => '2023/12/25', // Wrong format
                'title' => 'Test'
            ]
        ];
        validateCalendarData($invalidData);
    }
    
    public function testMissingRequiredField()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing required field: title");
        
        $invalidData = [
            'action' => 'publish',
            'calendar_data' => [
                'date' => '2023-12-25'
                // Missing title
            ]
        ];
        validateCalendarData($invalidData);
    }
}