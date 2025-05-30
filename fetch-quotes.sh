#!/bin/bash
set -e

echo "==================================="
echo "Arabic Quotes Mass Fetcher"
echo "==================================="
echo ""

# Make sure we're in the correct directory
cd "$(dirname "$0")"

# Check if database exists, if not create it
if [ ! -f "quotes.db" ]; then
    echo "Database does not exist. Creating it..."
    ./inc/db-update.sh
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "PHP is not installed. Please install PHP and try again."
    exit 1
fi

# Ask user what sources to fetch from
echo "Select sources to fetch quotes from:"
echo "1. All sources (recommended)"
echo "2. Wikiquote only"
echo "3. Goodreads only"
echo "4. Wikimedia Commons only"
echo "5. Custom combination"
read -p "Enter your choice (1-5): " choice

case $choice in
    1)
        sources=("wikiquote" "goodreads" "wikimedia")
        ;;
    2)
        sources=("wikiquote")
        ;;
    3)
        sources=("goodreads")
        ;;
    4)
        sources=("wikimedia")
        ;;
    5)
        sources=()
        read -p "Include Wikiquote? (y/n): " include_wikiquote
        if [[ $include_wikiquote =~ ^[Yy]$ ]]; then
            sources+=("wikiquote")
        fi
        
        read -p "Include Goodreads? (y/n): " include_goodreads
        if [[ $include_goodreads =~ ^[Yy]$ ]]; then
            sources+=("goodreads")
        fi
        
        read -p "Include Wikimedia Commons? (y/n): " include_wikimedia
        if [[ $include_wikimedia =~ ^[Yy]$ ]]; then
            sources+=("wikimedia")
        fi
        ;;
    *)
        echo "Invalid choice. Exiting."
        exit 1
        ;;
esac

# Backup database before starting
echo "Creating database backup..."
cp quotes.db "quotes.db.backup-$(date +%Y%m%d-%H%M%S)"
echo "Backup created."

# Start fetching
start_time=$(date +%s)

for source in "${sources[@]}"; do
    case $source in
        "wikiquote")
            echo ""
            echo "==================================="
            echo "Fetching quotes from Wikiquote..."
            echo "==================================="
            php fetch-all-quotes.php
            ;;
        "goodreads")
            echo ""
            echo "==================================="
            echo "Fetching quotes from Goodreads..."
            echo "==================================="
            php fetch-goodreads-quotes.php
            ;;
        "wikimedia")
            echo ""
            echo "==================================="
            echo "Fetching quotes from Wikimedia Commons..."
            echo "==================================="
            php fetch-wikimedia-quotes.php
            ;;
    esac
done

end_time=$(date +%s)
duration=$((end_time - start_time))

# Print summary
echo ""
echo "==================================="
echo "Fetching completed!"
echo "==================================="
echo "Time taken: $((duration / 60)) minutes and $((duration % 60)) seconds"

# Get database stats
quote_count=$(sqlite3 quotes.db "SELECT COUNT(*) FROM quotes;")
author_count=$(sqlite3 quotes.db "SELECT COUNT(DISTINCT author) FROM quotes;")
category_count=$(sqlite3 quotes.db "SELECT COUNT(DISTINCT category) FROM quotes;")

echo "Database now contains:"
echo "- $quote_count quotes"
echo "- $author_count unique authors"
echo "- $category_count categories"

# Optional: Export to JSON
read -p "Export quotes to JSON? (y/n): " export_json
if [[ $export_json =~ ^[Yy]$ ]]; then
    echo "Exporting to JSON..."
    sqlite3 -json quotes.db "SELECT * FROM quotes;" > assets/quotes.json
    echo "Export completed. File saved to assets/quotes.json"
fi

echo ""
echo "All done! You can now use these quotes in your application."
