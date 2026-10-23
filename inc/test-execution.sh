#!/bin/bash
set -e

# Make scripts executable
chmod +x run-tests.sh

# Install dependencies
composer install

# Run the test suite
./run-tests.sh

# Check test results
if [ -f "coverage.txt" ]; then
    echo "Test coverage summary:"
    cat coverage.txt
else
    echo "Coverage report not generated"
fi

# Check if any tests failed
if [ $? -eq 0 ]; then
    echo "All tests passed successfully!"
else
    echo "Some tests failed. Check the logs above."
    exit 1
fi