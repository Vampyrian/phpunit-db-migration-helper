<?php

declare(strict_types=1);

namespace integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Vampyrian\Migration\RefreshDatabaseTrait;

use function Vampyrian\Container\Container\container;

class TransactionTest extends TestCase
{
    use RefreshDatabaseTrait;

    protected static function migrationsPath(): string
    {
        return __DIR__ . '/custom_migration_folder';
    }

    public function testTransaction(): void
    {
        $pdo = container()->get(PDO::class);

        $count = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(0, (int) $count);

        $pdo
            ->prepare('INSERT INTO users (name, email) VALUES (:name, :email)')
            ->execute(['name' => 'John Doe', 'email' => 'john@example.com']);

        $statement = $pdo->prepare('SELECT name, email FROM users WHERE email = :email');
        $statement->execute(['email' => 'john@example.com']);

        $this->assertSame(
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            $statement->fetch(),
        );
    }

    public function testContainerSharesTransactionConnection(): void
    {
        $pdo = container()->get(PDO::class);

        $this->assertSame(self::$pdo, $pdo);
        $this->assertTrue($pdo->inTransaction());

        $pdo->prepare('INSERT INTO users (name, email) VALUES (:name, :email)')
            ->execute(['name' => 'John Doe', 'email' => 'john@example.com']);

        $this->assertSame(1, (int) self::$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }
}
