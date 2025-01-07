#!/bin/bash
set -e

echo "Setting up test environment..."
./setup.sh

echo "Creating test database..."
./db-update.sh

echo "Running test suites..."
./run-tests.sh

# Clean up
if [ -f "test_quotes.db" ]; then
    rm test_quotes.db
fi

echo "All tests completed!"