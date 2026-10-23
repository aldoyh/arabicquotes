#!/bin/bash

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "Installing composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    php -r "unlink('composer-setup.php');"
fi

# Install dependencies
cd "$ROOT_DIR"
composer install

# Verify installation
echo "Verifying PHPUnit installation..."
"$ROOT_DIR/vendor/bin/phpunit" --version
