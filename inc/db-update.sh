#!/bin/bash
set -e

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "Running automated database migrations & seeding..."
php "$ROOT_DIR/scripts/migrate.php" fresh --seed

echo "✅ Database update & migration completed successfully!"