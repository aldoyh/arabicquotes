#!/bin/bash

# Make all test files executable
chmod +x inc/test-execution.sh scripts/run-composer.sh 2>/dev/null || true
set -e  # Exit on error

# Install dependencies if vendor directory doesnt exist
if [ ! -d "vendor" ]; then
    composer install --no-interaction
fi

# Run tests with coverage
echo "Running all test suites..."
# Create test database first
./inc/db-update.sh

# Run the tests with coverage
./vendor/bin/phpunit --coverage-text --coverage-html coverage/

echo "Test coverage report has been generated."

# Check if tests passed
if [ $? -eq 0 ]; then
    echo "All tests passed successfully!"
    echo "Coverage report available in coverage/index.html"
else
    echo "Some tests failed. Please check the output above."
fi
