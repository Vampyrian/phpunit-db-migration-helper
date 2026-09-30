<?php

declare(strict_types=1);

namespace Vampyrian\Migration;

use PDO;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\BeforeClass;
use RuntimeException;

use function Vampyrian\Container\Container\container;

trait RefreshDatabaseTrait
{
    protected static ?PDO $pdo = null;

    #[BeforeClass]
    public static function runMigrations(): void
    {
        container()->singleton(PDO::class, fn () => self::connect());
        self::$pdo = container()->get(PDO::class);

//        self::dropAllTables(self::$pdo);
        self::addMigrationTableIfNotExists();

        $ran = self::$pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $batch = (int) self::$pdo->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn() + 1;

        $insert = self::$pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)');

        foreach (self::migrationFiles() as $file) {
            $migration = basename($file);

            if (in_array($migration, $ran, true)) {
                continue;
            }

            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new RuntimeException("Cannot read migration file: {$file}");
            }

            self::$pdo->exec($sql);

            $insert->execute(['migration' => $migration, 'batch' => $batch]);
            $batch++;
        }
    }

    #[Before]
    public function beginDatabaseTransaction(): void
    {
        self::$pdo->beginTransaction();
    }

    #[After]
    public function rollBackDatabaseTransaction(): void
    {
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
    }

    protected static function migrationsPath(): string
    {
        return __DIR__ . '/migrations';
    }

    protected static function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'],
            $_ENV['DB_PORT'],
            $_ENV['DB_DATABASE'],
        );

        return new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private static function dropAllTables(PDO $pdo): void
    {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE `{$table}`");
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected static function addMigrationTableIfNotExists(): void
    {
        self::$pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL
            )'
        );
    }

    /**
     * @return list<string>
     */
    private static function migrationFiles(): array
    {
        $path = static::migrationsPath();
        $files = glob($path . '/*.sql');

        if ($files === false || $files === []) {
            throw new RuntimeException("No migration files found in: {$path}");
        }

        sort($files);

        return $files;
    }
}
