#!/bin/bash
set -e

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "Setting up test environment..."
"$ROOT_DIR/setup.sh"

echo "Creating test database..."
"$ROOT_DIR/inc/db-update.sh"

echo "Running test suites..."
"$ROOT_DIR/run-tests.sh"

# Clean up
if [ -f "$ROOT_DIR/test_quotes.db" ]; then
    rm "$ROOT_DIR/test_quotes.db"
fi

echo "All tests completed!"
