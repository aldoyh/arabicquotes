<?php

// File to save the calendar data (replace with your actual file path)
$json_file = 'calendar_data.json';

// Function to validate the received data
function validateCalendarData($data) {
    // Check if 'action' exists and is 'publish'
    if (!isset($data['action']) || $data['action'] !== 'publish') {
        return false;
    }

    // Check if 'calendar_data' exists and is an array
    if (!isset($data['calendar_data']) || !is_array($data['calendar_data'])) {
        return false;
    }

    // Basic checks for required fields within 'calendar_data'
    $required_fields = ['title', 'start_date', 'end_date']; // Add more as needed
    foreach ($required_fields as $field) {
        if (!isset($data['calendar_data'][$field])) {
            return false;
        }
    }

    // You can add more specific validation based on your requirements

    return true;
}

// Check if data was sent via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $raw_data = file_get_contents('php://input');

    // Attempt to decode the JSON data
    $data = json_decode($raw_data, true);

    // Check if decoding was successful and validate the data
    if ($data !== null && validateCalendarData($data)) {
        // Read existing data from the JSON file (if it exists)
        if (file_exists($json_file)) {
            $existing_data = json_decode(file_get_contents($json_file), true);
            if ($existing_data === null) {
                $existing_data = []; // Handle invalid JSON in the file
            }
        } else {
            $existing_data = [];
        }

        // Append the new calendar data
        $existing_data[] = $data['calendar_data'];

        // Encode the updated data and write it to the file
        if (file_put_contents($json_file, json_encode($existing_data, JSON_PRETTY_PRINT)) !== false) {
            // Success response (you might want to send a more specific HTTP status code)
            echo 'Calendar data saved successfully.';
        } else {
            // Error response
            http_response_code(500); // Internal Server Error
            echo 'Error saving calendar data.';
        }
    } else {
        // Invalid data response
        http_response_code(400); // Bad Request
        echo 'Invalid calendar data.';
    }
} else {
    // Method not allowed response
    http_response_code(405); // Method Not Allowed
    echo 'Method not allowed.';
}