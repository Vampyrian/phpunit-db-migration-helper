<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

class PhpunitTest extends TestCase
{
    public function testConfigurationIsLoading(): void
    {
        $this->assertEquals('127.0.0.1', $_ENV['DB_HOST']);
        $this->assertEquals('3306', $_ENV['DB_PORT']);
        $this->assertEquals('testing', $_ENV['DB_DATABASE']);
        $this->assertEquals('testing', $_ENV['DB_USERNAME']);
        $this->assertEquals('testing', $_ENV['DB_PASSWORD']);
    }
}
