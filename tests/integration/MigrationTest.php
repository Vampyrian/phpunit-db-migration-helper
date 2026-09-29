<?php

declare(strict_types=1);

namespace integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Vampyrian\Migration\RefreshDatabaseTrait;

class MigrationTest extends TestCase
{
    use RefreshDatabaseTrait;

    protected static function migrationsPath(): string
    {
        return __DIR__ . '/custom_migration_folder';
    }

    public function testMigration(): void
    {
        $tables = self::$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('migrations', $tables);
        $this->assertContains('users', $tables);
        $this->assertContains('roles', $tables);
    }
}
