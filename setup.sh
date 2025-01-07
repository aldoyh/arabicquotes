#!/bin/bash
set -e

# Create necessary directories
mkdir -p coverage
mkdir -p assets

# Set permissions
chmod +x *.sh
chmod +x tests/*.php tests/error-cases/*.php tests/*.php tests/error-cases/*.php

# Initialize Git if not already initialized
if [ ! -d ".git" ]; then
    git init
fi

# Install dependencies
./run-composer.sh

echo "Setup completed successfully!"