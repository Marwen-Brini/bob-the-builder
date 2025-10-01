<?php

namespace Tests;

use Bob\Database\Connection;
use Bob\Database\Model;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected ?Connection $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset Model's static connection
        Model::clearConnection();
    }

    protected function tearDown(): void
    {
        if ($this->connection) {
            $this->connection = null;
        }

        \Mockery::close();

        parent::tearDown();
    }

    protected function createSQLiteConnection(): Connection
    {
        $connection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'fetch' => \PDO::FETCH_OBJ, // Set fetch mode to return objects
        ]);

        $this->connection = $connection;

        return $connection;
    }

    protected function createMySQLConnection(): Connection
    {
        $config = $this->getMySQLConfig();

        if (! $config) {
            $this->markTestSkipped('MySQL connection not configured');
        }

        $connection = new Connection($config);
        $this->connection = $connection;

        return $connection;
    }

    protected function getMySQLConfig(): ?array
    {
        // Check for environment variables first
        if (getenv('DB_HOST')) {
            return [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST'),
                'port' => getenv('DB_PORT') ?: 3306,
                'database' => getenv('DB_DATABASE'),
                'username' => getenv('DB_USERNAME'),
                'password' => getenv('DB_PASSWORD'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ];
        }

        // Check for local config file
        $configFile = __DIR__.'/config/database.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }

        return null;
    }
}
