#!/bin/bash
set -e

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "Making initialization script executable..."
chmod +x "$ROOT_DIR/scripts/initialize.sh"

echo "Running database initialization..."
"$ROOT_DIR/scripts/initialize.sh"

echo "Done! Now you can run: php index.php"
