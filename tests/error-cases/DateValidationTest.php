<?php
use PHPUnit\Framework\TestCase;

class DateValidationTest extends TestCase
{
    public function testStartDateFormat()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid date format for start_date - must be YYYY-MM-DD");
        
        $invalidData = [
            'action' => 'publish',
            'calendar_data' => [
                'title' => 'Test Event',
                'start_date' => '2023.12.25',
                'end_date' => '2023-12-26'
            ]
        ];
        validateCalendarData($invalidData);
    }
    
    public function testEndDateFormat()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid date format for end_date - must be YYYY-MM-DD");
        
        $invalidData = [
            'action' => 'publish',
            'calendar_data' => [
                'title' => 'Test Event',
                'start_date' => '2023-12-25',
                'end_date' => '20231226'
            ]
        ];
        validateCalendarData($invalidData);
    }
}