#!/bin/bash

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "Installing composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    php -r "unlink('composer-setup.php');"
fi

# Install dependencies
composer install

# Verify installation
echo "Verifying PHPUnit installation..."
./vendor/bin/phpunit --version