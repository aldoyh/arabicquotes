<?php

require_once __DIR__ . '/../inc/db-utils.php';
require_once __DIR__ . '/../src/Migrator.php';

use ArabicQuotes\Migrator;

$args = array_slice($argv, 1);
$command = 'migrate';
$seed = false;
$customDbPath = null;

foreach ($args as $arg) {
    if ($arg === 'migrate' || $arg === 'seed' || $arg === 'fresh' || $arg === 'status') {
        $command = $arg;
    } elseif ($arg === '--seed') {
        $seed = true;
    } elseif (str_starts_with($arg, '--db=')) {
        $customDbPath = substr($arg, 5);
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Arabic Quotes Migration Utility\n\n";
        echo "Usage:\n";
        echo "  php scripts/migrate.php [command] [options]\n\n";
        echo "Commands:\n";
        echo "  migrate        Run pending database migrations (default)\n";
        echo "  fresh          Drop all tables and rerun all migrations\n";
        echo "  seed           Seed quotes table from assets/quotes.json\n";
        echo "  status         Show status of all migrations\n\n";
        echo "Options:\n";
        echo "  --seed         Seed database after migrate/fresh\n";
        echo "  --db=<path>    Specify custom SQLite database file path\n";
        echo "  --help, -h     Show this help message\n\n";
        exit(0);
    }
}

try {
    $migrator = new Migrator($customDbPath);
    $dbPath = $migrator->getDbPath();

    echo "Using database: {$dbPath}\n";

    switch ($command) {
        case 'status':
            $status = $migrator->status();
            if (empty($status)) {
                echo "No migration files found.\n";
                exit(0);
            }
            echo sprintf("%-35s %-10s %-20s\n", "Migration", "Status", "Applied At");
            echo str_repeat("-", 70) . "\n";
            foreach ($status as $row) {
                $statusText = $row['applied'] ? "✅ Applied" : "⏳ Pending";
                $appliedAt = $row['applied_at'] ?: "-";
                echo sprintf("%-35s %-10s %-20s\n", $row['migration'], $statusText, $appliedAt);
            }
            break;

        case 'fresh':
            echo "Resetting database and running all migrations...\n";
            $result = $migrator->fresh($seed);
            $appliedCount = count($result['applied_migrations']);
            echo "✅ Applied {$appliedCount} migrations.\n";
            foreach ($result['applied_migrations'] as $m) {
                echo "   - {$m}\n";
            }
            if ($seed) {
                echo "✅ Seeded {$result['seeded_count']} quotes from JSON.\n";
            }
            break;

        case 'seed':
            echo "Seeding database from JSON...\n";
            $count = $migrator->seed();
            echo "✅ Seeded {$count} quotes.\n";
            break;

        case 'migrate':
        default:
            $pending = $migrator->getPendingMigrations();
            if (empty($pending)) {
                echo "Database is already up to date. No pending migrations.\n";
            } else {
                echo "Applying " . count($pending) . " pending migration(s)...\n";
                $applied = $migrator->migrate();
                foreach ($applied as $m) {
                    echo "   ✅ Applied: {$m}\n";
                }
            }

            if ($seed || $migrator->isQuotesTableEmpty()) {
                echo "Seeding database...\n";
                $seededCount = $migrator->seed();
                echo "✅ Seeded {$seededCount} quotes.\n";
            }
            break;
    }

    $db = $migrator->getDb();
    $tableCheck = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='quotes'");
    if ($tableCheck) {
        $totalQuotes = (int) $db->querySingle("SELECT count(*) FROM quotes");
        $totalAuthors = (int) $db->querySingle("SELECT count(DISTINCT author) FROM quotes");
        echo "Current Database Stats: {$totalQuotes} quotes across {$totalAuthors} authors.\n";
    } else {
        echo "Current Database Stats: quotes table does not exist yet (run migrate).\n";
    }

} catch (\Throwable $e) {
    fwrite(STDERR, "❌ Error: " . $e->getMessage() . "\n");
    exit(1);
}
