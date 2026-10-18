<?php

namespace ArabicQuotes;

use Exception;
use SQLite3;

require_once __DIR__ . '/../inc/db-utils.php';

class Migrator
{
    private SQLite3 $db;
    private string $dbPath;
    private string $migrationsDir;
    private string $jsonPath;
    private bool $ownsConnection = false;

    public function __construct(
        $dbOrPath = null,
        ?string $migrationsDir = null,
        ?string $jsonPath = null
    ) {
        $root = dirname(__DIR__);
        $this->migrationsDir = $migrationsDir ?: ($root . '/database/migrations');
        $this->jsonPath = $jsonPath ?: ($root . '/assets/quotes.json');

        if ($dbOrPath instanceof SQLite3) {
            $this->db = $dbOrPath;
            $this->dbPath = ':memory:';
            $this->ownsConnection = false;
        } else {
            $path = $dbOrPath ?: (defined('DB_NAME') ? DB_NAME : ($root . '/assets/QuotesDB.db'));
            $this->dbPath = (string) $path;
            $dir = dirname($this->dbPath);
            if (!empty($dir) && !is_dir($dir) && $this->dbPath !== ':memory:') {
                mkdir($dir, 0775, true);
            }
            $this->db = new SQLite3($this->dbPath);
            $this->db->busyTimeout(5000);
            $this->ownsConnection = true;
        }

        $this->ensureMigrationsTable();
    }

    public function close(): void
    {
        try {
            @$this->db->close();
        } catch (\Throwable $e) {
            // Ignore closing errors
        }
    }

    public function getDb(): SQLite3
    {
        return $this->db;
    }

    public function getDbPath(): string
    {
        return $this->dbPath;
    }

    public function ensureMigrationsTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    /**
     * @return array<int, array{migration: string, applied_at: string}>
     */
    public function getAppliedMigrations(): array
    {
        $result = $this->db->query('SELECT migration, applied_at FROM schema_migrations ORDER BY id ASC');
        $applied = [];
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $applied[] = [
                    'migration' => (string) $row['migration'],
                    'applied_at' => (string) $row['applied_at'],
                ];
            }
        }
        return $applied;
    }

    /**
     * @return array<int, string>
     */
    public function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files = glob($this->migrationsDir . '/*.sql');
        if ($files === false) {
            return [];
        }

        $names = array_map('basename', $files);
        sort($names, SORT_NATURAL);
        return $names;
    }

    /**
     * @return array<int, string>
     */
    public function getPendingMigrations(): array
    {
        $all = $this->getAllMigrationFiles();
        $applied = array_column($this->getAppliedMigrations(), 'migration');
        $appliedMap = array_flip($applied);

        $pending = [];
        foreach ($all as $file) {
            if (!isset($appliedMap[$file])) {
                $pending[] = $file;
            }
        }
        return $pending;
    }

    /**
     * Run all pending migrations.
     * @return array<int, string> List of applied migration filenames
     */
    public function migrate(): array
    {
        $pending = $this->getPendingMigrations();
        if (empty($pending)) {
            return [];
        }

        $applied = [];
        foreach ($pending as $migrationFile) {
            $fullPath = $this->migrationsDir . '/' . $migrationFile;
            $sql = file_get_contents($fullPath);
            if ($sql === false) {
                throw new Exception("Failed to read migration file: {$migrationFile}");
            }

            $this->db->exec('BEGIN TRANSACTION');
            try {
                if (trim($sql) !== '') {
                    $execResult = $this->db->exec($sql);
                    if ($execResult === false) {
                        throw new Exception("Failed to execute SQL in {$migrationFile}: " . $this->db->lastErrorMsg());
                    }
                }

                $stmt = $this->db->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
                $stmt->bindValue(':migration', $migrationFile, SQLITE3_TEXT);
                $stmt->execute();
                $this->db->exec('COMMIT');
                $applied[] = $migrationFile;
            } catch (\Throwable $e) {
                $this->db->exec('ROLLBACK');
                throw new Exception("Migration failed [{$migrationFile}]: " . $e->getMessage(), 0, $e);
            }
        }

        return $applied;
    }

    /**
     * Seed quotes from JSON file into quotes table.
     * @param string|null $customJsonPath
     * @return int Number of quotes inserted or updated
     */
    public function seed(?string $customJsonPath = null): int
    {
        $jsonPath = $customJsonPath ?: $this->jsonPath;
        if (!file_exists($jsonPath)) {
            return 0;
        }

        $content = file_get_contents($jsonPath);
        if ($content === false) {
            throw new Exception("Unable to read seed file: {$jsonPath}");
        }

        $quotes = json_decode($content, true);
        if (!is_array($quotes)) {
            throw new Exception("Invalid JSON format in seed file: {$jsonPath}");
        }

        $this->db->exec('BEGIN TRANSACTION');
        try {
            $stmt = $this->db->prepare(
                'INSERT OR REPLACE INTO quotes
                (id, head, quote, author, hits, created_at, category, source, quote_key, author_key)
                VALUES (:id, :head, :quote, :author, :hits, :created_at, :category, :source, :quote_key, :author_key)'
            );

            $count = 0;
            foreach ($quotes as $row) {
                $prepared = prepareQuoteRow($row);
                if ($prepared === null) {
                    continue;
                }

                $id = isset($row['id']) ? (int) $row['id'] : null;
                if ($id !== null && $id > 0) {
                    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                } else {
                    $stmt->bindValue(':id', null, SQLITE3_NULL);
                }

                $stmt->bindValue(':head', $prepared['head'], SQLITE3_TEXT);
                $stmt->bindValue(':quote', $prepared['quote'], SQLITE3_TEXT);
                $stmt->bindValue(':author', $prepared['author'], SQLITE3_TEXT);
                $stmt->bindValue(':hits', (int) ($row['hits'] ?? $prepared['hits'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':created_at', $row['created_at'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
                $stmt->bindValue(':category', $prepared['category'], SQLITE3_TEXT);
                $stmt->bindValue(':source', $prepared['source'], SQLITE3_TEXT);
                $stmt->bindValue(':quote_key', $prepared['quote_key'], SQLITE3_TEXT);
                $stmt->bindValue(':author_key', $prepared['author_key'], SQLITE3_TEXT);

                $stmt->execute();
                $stmt->reset();
                $count++;
            }

            $this->db->exec('COMMIT');
            return $count;
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Check if quotes table is empty.
     */
    public function isQuotesTableEmpty(): bool
    {
        $check = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='quotes'");
        if (!$check || !$check->fetchArray()) {
            return true;
        }
        $count = (int) $this->db->querySingle('SELECT COUNT(*) FROM quotes');
        return $count === 0;
    }

    /**
     * Automatically run pending migrations, and seed if quotes table is empty.
     * @return array{applied_migrations: array<int, string>, seeded_count: int}
     */
    public function autoMigrateAndSeedIfNeeded(): array
    {
        $applied = $this->migrate();
        $seeded = 0;
        if ($this->isQuotesTableEmpty() && file_exists($this->jsonPath)) {
            $seeded = $this->seed();
        }
        return [
            'applied_migrations' => $applied,
            'seeded_count' => $seeded,
        ];
    }

    /**
     * Reset the database by dropping existing tables and re-migrating.
     * @param bool $seed
     * @return array{applied_migrations: array<int, string>, seeded_count: int}
     */
    public function fresh(bool $seed = true): array
    {
        $this->db->exec('PRAGMA foreign_keys = OFF');
        $tables = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $tableNames = [];
        if ($tables) {
            while ($row = $tables->fetchArray(SQLITE3_ASSOC)) {
                $tableNames[] = $row['name'];
            }
            $tables->finalize();
        }
        foreach ($tableNames as $tableName) {
            $this->db->exec("DROP TABLE IF EXISTS \"{$tableName}\"");
        }
        $this->db->exec('PRAGMA foreign_keys = ON');

        $this->ensureMigrationsTable();
        $applied = $this->migrate();
        $seeded = 0;
        if ($seed && file_exists($this->jsonPath)) {
            $seeded = $this->seed();
        }

        return [
            'applied_migrations' => $applied,
            'seeded_count' => $seeded,
        ];
    }

    /**
     * Get detailed status of all migrations.
     * @return array<int, array{migration: string, applied: bool, applied_at: string|null}>
     */
    public function status(): array
    {
        $all = $this->getAllMigrationFiles();
        $applied = $this->getAppliedMigrations();
        $appliedMap = [];
        foreach ($applied as $row) {
            $appliedMap[$row['migration']] = $row['applied_at'];
        }

        $result = [];
        foreach ($all as $file) {
            $isApplied = isset($appliedMap[$file]);
            $result[] = [
                'migration' => $file,
                'applied' => $isApplied,
                'applied_at' => $isApplied ? $appliedMap[$file] : null,
            ];
        }
        return $result;
    }
}

// Global class alias for convenience
if (!class_exists('Migrator', false)) {
    class_alias(Migrator::class, 'Migrator');
}
