#!/bin/bash
set -e

echo "Making initialization script executable..."
chmod +x initialize.sh

echo "Running database initialization..."
./initialize.sh

echo "Done! Now you can run: php index.php"