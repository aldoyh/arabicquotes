<?php

use PHPUnit\Framework\TestCase;
use ArabicQuotes\Migrator;

class MigratorTest extends TestCase
{
    private string $tempDbFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDbFile = sys_get_temp_dir() . '/test_quotes_migrator_' . uniqid() . '.db';
        if (file_exists($this->tempDbFile)) {
            @unlink($this->tempDbFile);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->tempDbFile)) {
            @unlink($this->tempDbFile);
        }
    }

    public function testMigrationsExecuteSuccessfully()
    {
        $migrator = new Migrator($this->tempDbFile);
        
        $pending = $migrator->getPendingMigrations();
        $this->assertNotEmpty($pending);
        $this->assertContains('001_create_quotes_table.sql', $pending);
        $this->assertContains('002_create_quotes_indexes.sql', $pending);

        $applied = $migrator->migrate();
        $this->assertCount(count($pending), $applied);

        // Verify schema_migrations table
        $appliedRecords = $migrator->getAppliedMigrations();
        $this->assertCount(count($pending), $appliedRecords);

        // Verify quotes table exists
        $db = $migrator->getDb();
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='quotes'");
        $this->assertNotEmpty($result->fetchArray());

        // Verify columns
        $colsResult = $db->query("PRAGMA table_info(quotes)");
        $columns = [];
        while ($col = $colsResult->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $col['name'];
        }
        $this->assertContains('id', $columns);
        $this->assertContains('quote', $columns);
        $this->assertContains('author', $columns);
        $this->assertContains('hits', $columns);
        $this->assertContains('category', $columns);
        $this->assertContains('source', $columns);
        $this->assertContains('quote_key', $columns);
        $this->assertContains('author_key', $columns);

        // Verify unique index
        $indexResult = $db->query("PRAGMA index_list(quotes)");
        $indexes = [];
        while ($idx = $indexResult->fetchArray(SQLITE3_ASSOC)) {
            $indexes[] = $idx['name'];
        }
        $this->assertContains('idx_quotes_unique_keys', $indexes);
    }

    public function testIdempotentMigrations()
    {
        $migrator = new Migrator($this->tempDbFile);
        $firstRun = $migrator->migrate();
        $this->assertNotEmpty($firstRun);

        // Second run should apply 0 migrations and have 0 pending
        $secondRun = $migrator->migrate();
        $this->assertEmpty($secondRun);
        $this->assertEmpty($migrator->getPendingMigrations());
    }

    public function testMigrationStatusReporting()
    {
        $migrator = new Migrator($this->tempDbFile);
        $statusBefore = $migrator->status();
        foreach ($statusBefore as $item) {
            $this->assertFalse($item['applied']);
            $this->assertNull($item['applied_at']);
        }

        $migrator->migrate();

        $statusAfter = $migrator->status();
        foreach ($statusAfter as $item) {
            $this->assertTrue($item['applied']);
            $this->assertNotNull($item['applied_at']);
        }
    }

    public function testSeedingAndAutoMigrate()
    {
        $migrator = new Migrator($this->tempDbFile);
        $result = $migrator->autoMigrateAndSeedIfNeeded();

        $this->assertNotEmpty($result['applied_migrations']);
        $this->assertGreaterThan(0, $result['seeded_count']);

        $db = $migrator->getDb();
        $count = (int) $db->querySingle("SELECT COUNT(*) FROM quotes");
        $this->assertEquals($result['seeded_count'], $count);

        // Verify quote hashes were generated
        $sample = $db->query("SELECT quote, author, quote_key, author_key FROM quotes LIMIT 1")->fetchArray(SQLITE3_ASSOC);
        $this->assertNotEmpty($sample['quote_key']);
        $this->assertNotEmpty($sample['author_key']);
        $this->assertEquals(quoteHash($sample['quote']), $sample['quote_key']);
    }

    public function testFreshReset()
    {
        $migrator = new Migrator($this->tempDbFile);
        $migrator->autoMigrateAndSeedIfNeeded();

        // Fresh should drop and re-run migrations
        $freshResult = $migrator->fresh(false);
        $this->assertNotEmpty($freshResult['applied_migrations']);
        $this->assertEquals(0, $freshResult['seeded_count']);

        $db = $migrator->getDb();
        $count = (int) $db->querySingle("SELECT COUNT(*) FROM quotes");
        $this->assertEquals(0, $count);
    }
}
