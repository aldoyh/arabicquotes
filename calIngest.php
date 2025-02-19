<?php

/**
 * Calendar data validation and ingestion handler
 */

$json_file = 'calendar_data.json';

/**
 * Validates calendar data structure and values
 * @param array $data The calendar data to validate
 * @return bool True if valid, false otherwise
 */
function validateCalendarData($data) {
    try {
        // Check if 'action' exists and is 'publish'
        if (!isset($data['action']) || $data['action'] !== 'publish') {
            throw new Exception("Missing or invalid action parameter");
        }

        // Check if 'calendar_data' exists and is an array
        if (!isset($data['calendar_data']) || !is_array($data['calendar_data'])) {
            throw new Exception("Invalid or missing calendar_data array");
        }
        
        if (isset($data['calendar_data']['date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['calendar_data']['date']);
            if (!$date || $date->format('Y-m-d') !== $data['calendar_data']['date']) {
                throw new Exception("Invalid date format - must be YYYY-MM-DD");
            }
        }

        // Basic checks for required fields within 'calendar_data'
        $required_fields = ['title', 'start_date', 'end_date']; 
        
        // Validate date formats
        $date_fields = ['start_date', 'end_date'];
        foreach ($date_fields as $field) {
            if (isset($data['calendar_data'][$field])) {
                $date = DateTime::createFromFormat('Y-m-d', $data['calendar_data'][$field]);
                if (!$date || $date->format('Y-m-d') !== $data['calendar_data'][$field]) {
                    throw new Exception("Invalid date format for {$field} - must be YYYY-MM-DD");
                }
            }
        }

        foreach ($required_fields as $field) {
            if (!isset($data['calendar_data'][$field])) {
                throw new Exception("Missing required field: " . $field);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Error validating calendar data: " . $e->getMessage());
        return false;
    }
}

// Main execution block
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = file_get_contents('php://input');
        if (!$input) {
            throw new Exception("No input data received");
        }

        $data = json_decode($input, true);
        if (!$data) {
            throw new Exception("Invalid JSON data");
        }

        if (!validateCalendarData($data)) {
            throw new Exception("Calendar data validation failed");
        }

        $existing_data = [];
        if (file_exists($json_file)) {
            $existing_content = file_get_contents($json_file);
            if ($existing_content !== false) {
                $existing_data = json_decode($existing_content, true);
                if ($existing_data === null) {
                    throw new Exception("Failed to parse existing calendar data");
                }
            }
        }

        $existing_data[] = $data['calendar_data'];

        if (file_put_contents($json_file, json_encode($existing_data, JSON_PRETTY_PRINT)) === false) {
            throw new Exception("Failed to write calendar data to file");
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        error_log("Calendar ingest error: " . $e->getMessage());
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}