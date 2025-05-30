#!/bin/bash
# fetch-comprehensive-quotes.sh
# This script runs the comprehensive quote fetcher and logs the output

# Set up variables
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="${SCRIPT_DIR}/assets"
LOG_FILE="${LOG_DIR}/quote-fetch-$(date +%Y%m%d-%H%M%S).log"
PHP_SCRIPT="${SCRIPT_DIR}/fetch-comprehensive-quotes.php"
LOCK_FILE="/tmp/quote-fetcher.lock"

# Ensure the script is not already running
if [ -f "$LOCK_FILE" ]; then
    echo "Another instance is already running. Exiting."
    exit 1
fi

# Create lock file
touch "$LOCK_FILE"

# Ensure we clean up on exit
trap 'rm -f $LOCK_FILE; echo "Script terminated at $(date)"; exit' EXIT INT TERM

# Create log directory if it doesn't exist
mkdir -p "$LOG_DIR"

# Function to log messages
log() {
    echo "[$(date +"%Y-%m-%d %H:%M:%S")] $1"
    echo "[$(date +"%Y-%m-%d %H:%M:%S")] $1" >> "$LOG_FILE"
}

# Start the fetching process
log "Starting comprehensive quote fetching process..."
log "Logging to $LOG_FILE"

# Run the PHP script and capture output
{
    time php "$PHP_SCRIPT" 2>&1
    PHP_EXIT_CODE=$?
} | tee -a "$LOG_FILE"

# Check if the PHP script was successful
if [ $PHP_EXIT_CODE -eq 0 ]; then
    log "Quote fetching completed successfully!"
    
    # Count quotes in the database
    QUOTE_COUNT=$(sqlite3 "${SCRIPT_DIR}/quotes.db" "SELECT COUNT(*) FROM quotes;")
    log "Total quotes in database: $QUOTE_COUNT"
    
    # Update README with the new quote count
    if [ -f "${SCRIPT_DIR}/README.md" ]; then
        sed -i "" "s/with [0-9,]\\+ quotes/with $QUOTE_COUNT quotes/g" "${SCRIPT_DIR}/README.md"
        log "Updated README.md with new quote count: $QUOTE_COUNT"
    fi
else
    log "Quote fetching failed with exit code $PHP_EXIT_CODE"
fi

# Generate stats
log "Generating stats..."
echo "Quote Stats:" > "${LOG_DIR}/quote-stats.txt"
echo "Total Quotes: $QUOTE_COUNT" >> "${LOG_DIR}/quote-stats.txt"
echo "Last Updated: $(date)" >> "${LOG_DIR}/quote-stats.txt"

# Top 10 authors by quote count
echo -e "\nTop 10 Authors:" >> "${LOG_DIR}/quote-stats.txt"
sqlite3 "${SCRIPT_DIR}/quotes.db" "SELECT author, COUNT(*) as count FROM quotes GROUP BY author ORDER BY count DESC LIMIT 10;" >> "${LOG_DIR}/quote-stats.txt"

log "Stats generated at ${LOG_DIR}/quote-stats.txt"

# Hydrate the index page with a new quote
log "Updating index.html with a new quote..."
php "${SCRIPT_DIR}/hydrate-quote.php" >> "$LOG_FILE" 2>&1

log "Process completed at $(date)"
echo "=== Fetch process completed! ==="
echo "Check the log at $LOG_FILE for details."

# Remove lock file (also handled by trap, this is just for clarity)
rm -f "$LOCK_FILE"
