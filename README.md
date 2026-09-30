# PHPUnit DB Migration Helper

A PHPUnit trait that migrates a MySQL test database from plain `.sql` files and wraps every test in a transaction that is rolled back afterwards, so each test starts from the same state.

## Installation

```bash
composer require --dev vampyrian/phpunit-db-migration
```

Requires PHP 8.5, PHPUnit 13 and the `pdo_mysql` extension.

## 1. Configure credentials in `phpunit.xml`

The database connection is read from environment variables. Define them in the `<php>` section of your `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php">

    <php>
        <env name="DB_HOST" value="127.0.0.1"/>
        <env name="DB_PORT" value="3306"/>
        <env name="DB_DATABASE" value="testing"/>
        <env name="DB_USERNAME" value="testing"/>
        <env name="DB_PASSWORD" value="testing"/>
    </php>

    <testsuites>
        <testsuite name="integration">
            <directory>tests/integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

> Use a dedicated test database. Migrations and test data are written to it.

If you run tests from PhpStorm, set **Settings → PHP → Test Frameworks → Default configuration file** to your `phpunit.xml`, otherwise these variables are not loaded.

## 2. Add migration files

Put your migrations in a folder as `.sql` files. They run in filename order, so prefix them with a date:

```
tests/integration/custom_migration_folder/
├── 2026-09-29.sql
└── 2026-09-30.sql
```

```sql
-- 2026-09-29.sql
CREATE TABLE users (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   name VARCHAR(100) NOT NULL,
   email VARCHAR(255) NOT NULL UNIQUE
);
```

A file may contain several statements.

## 3. Use the trait in your test

Your test needs only two things: the trait and the path to your migrations folder.

```php
<?php

declare(strict_types=1);

namespace integration;

use PHPUnit\Framework\TestCase;
use Vampyrian\Migration\RefreshDatabaseTrait;

class UserTest extends TestCase
{
    use RefreshDatabaseTrait;

    protected static function migrationsPath(): string
    {
        return __DIR__ . '/custom_migration_folder';
    }

    public function testUserCanBeCreated(): void
    {
        self::$pdo
            ->prepare('INSERT INTO users (name, email) VALUES (:name, :email)')
            ->execute(['name' => 'John Doe', 'email' => 'john@example.com']);

        $count = self::$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        $this->assertSame(1, (int) $count);
    }
}
```

## What happens when tests run

**Before the test class** (`#[BeforeClass]`):

1. Connects to MySQL using the `DB_*` variables.
2. Creates the `migrations` table if it does not exist.
3. Runs every `.sql` file from `migrationsPath()` that is not yet recorded in `migrations`, and records it there. A missing or empty folder throws a `RuntimeException`.

**Around each test** (`#[Before]` / `#[After]`):

- A transaction is started before the test and rolled back after it, so data inserted, updated or deleted by one test never leaks into the next.

The `migrations` table tracks what already ran:

```
id  migration       batch
1   2026-09-29.sql  1
2   2026-09-30.sql  2
```

Adding a new file (e.g. `2026-10-01.sql`) runs just that file on the next test run.

## Using the connection in your application code

The connection is registered as a singleton `PDO` in [`vampyrian/container`](https://github.com/Vampyrian/container). Any class resolved through the container gets the same connection, so its writes are rolled back too:

```php
use function Vampyrian\Container\Container\container;

class UserRepository
{
    public function __construct(private PDO $pdo) {}
}

$repository = container()->get(UserRepository::class); // uses the test connection
```

Inside tests the same connection is also available as `self::$pdo`.

## Limitations

- **MySQL only.**
- **Only data changes are rolled back.** MySQL commits DDL (`CREATE`, `ALTER`, `DROP`, `TRUNCATE`) immediately, so schema changes made inside a test are permanent.
- **One connection only.** Code that creates its own `new PDO(...)` runs outside the test transaction; its writes persist and it cannot see uncommitted test data.
- **Migrations are identified by filename.** Renaming a file that already ran makes it run again; editing it does not.

## License

MIT
