#!/bin/bash
set -e

# Create necessary directories
mkdir -p coverage
mkdir -p assets

# Set permissions
chmod +x scripts/*.sh inc/*.sh
chmod +x tests/*.php tests/error-cases/*.php

# Initialize Git if not already initialized
if [ ! -d ".git" ]; then
    git init
fi

# Install dependencies
if [ -f "scripts/run-composer.sh" ]; then
    ./scripts/run-composer.sh
elif [ -f "inc/run-composer.sh" ]; then
    ./inc/run-composer.sh
fi

echo "Setup completed successfully!"
