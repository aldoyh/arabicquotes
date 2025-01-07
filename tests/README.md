# Test Suite Documentation

## Overview
This test suite covers the core functionality of the quote management system including:
- Database operations
- Quote retrieval and updates
- Calendar data validation
- WikiQuote fetching with retry mechanism

## Running Tests
1. Make sure you have PHP 7.4+ and Composer installed
2. Run `./run-tests.sh` from the project root
3. View coverage report in `coverage/index.html`

## Test Structure
- `QuoteManagerTest.php`: Tests for quote database operations
- `WikiquoteFetcherTest.php`: Tests for quote fetching with retries
- `CalIngestTest.php`: Tests for calendar data validation
- `db-utils-test.php`: Tests for database utility functions

## Adding New Tests
When adding new tests:
1. Create test class extending PHPUnit\Framework\TestCase
2. Add setUp() and tearDown() methods if needed
3. Prefix test methods with "test"
4. Use assertions to verify results
5. Clean up any test data in tearDown