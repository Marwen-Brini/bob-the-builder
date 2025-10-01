<?php

use Bob\Database\Connection;
use Bob\Logging\Log;
use Bob\Query\Builder;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Grammars\PostgreSQLGrammar;
use Bob\Query\Grammars\SQLiteGrammar;
use Bob\Query\Processor;

beforeEach(function () {
    // Clear any global loggers to ensure test isolation
    $reflection = new ReflectionClass(Log::class);

    $globalLogger = $reflection->getProperty('globalLogger');
    $globalLogger->setAccessible(true);
    $globalLogger->setValue(null, null);

    $globalQueryLogger = $reflection->getProperty('globalQueryLogger');
    $globalQueryLogger->setAccessible(true);
    $globalQueryLogger->setValue(null, null);

    $globalEnabled = $reflection->getProperty('globalEnabled');
    $globalEnabled->setAccessible(true);
    $globalEnabled->setValue(null, false);
});

afterEach(function () {
    // Clean up after each test
    $reflection = new ReflectionClass(Log::class);

    $globalLogger = $reflection->getProperty('globalLogger');
    $globalLogger->setAccessible(true);
    $globalLogger->setValue(null, null);

    $globalQueryLogger = $reflection->getProperty('globalQueryLogger');
    $globalQueryLogger->setAccessible(true);
    $globalQueryLogger->setValue(null, null);

    $globalEnabled = $reflection->getProperty('globalEnabled');
    $globalEnabled->setAccessible(true);
    $globalEnabled->setValue(null, false);
});

describe('Connection', function () {

    test('implements ConnectionInterface', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        expect($connection)->toBeInstanceOf(\Bob\Contracts\ConnectionInterface::class);
    });

    test('creates appropriate grammar for driver', function () {
        $mysqlConnection = new Connection(['driver' => 'mysql']);
        expect($mysqlConnection->getQueryGrammar())->toBeInstanceOf(MySQLGrammar::class);

        $sqliteConnection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        expect($sqliteConnection->getQueryGrammar())->toBeInstanceOf(SQLiteGrammar::class);

        $postgresConnection = new Connection(['driver' => 'pgsql']);
        expect($postgresConnection->getQueryGrammar())->toBeInstanceOf(PostgreSQLGrammar::class);
    });

    test('throws exception for unsupported driver', function () {
        expect(fn () => new Connection(['driver' => 'oracle']))
            ->toThrow(\InvalidArgumentException::class, 'Database driver [oracle] not supported');
    });

    test('creates processor', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        expect($connection->getPostProcessor())->toBeInstanceOf(Processor::class);
    });

    test('creates query builder', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $builder = $connection->table('users');

        expect($builder)->toBeInstanceOf(Builder::class);
        expect($builder->from)->toBe('users');
    });

    test('creates raw expression', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $raw = $connection->raw('COUNT(*)');

        expect($raw)->toBeInstanceOf(\Bob\Database\Expression::class);
        expect((string) $raw)->toBe('COUNT(*)');
    });

    test('handles table prefix', function () {
        $connection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => 'wp_',
        ]);

        expect($connection->getTablePrefix())->toBe('wp_');
    });

    test('pretend mode', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);

        $queries = $connection->pretend(function ($connection) {
            $connection->table('users')->insert(['name' => 'John']);
        });

        expect($queries)->toHaveCount(1);
        expect($queries[0]['query'])->toContain('insert into');
    });

    test('query logging', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);

        // Clear any existing logs and enable logging
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        // Create a table first
        $connection->statement('CREATE TABLE test (id INTEGER)');
        $connection->select('SELECT * FROM test');

        $log = $connection->getQueryLog();

        // Find the select query in the log
        $selectQuery = null;
        foreach ($log as $entry) {
            if (isset($entry['query']) && strpos($entry['query'], 'SELECT * FROM test') !== false) {
                $selectQuery = $entry;
                break;
            }
        }

        expect($selectQuery)->not->toBeNull();
        expect($selectQuery['query'])->toBe('SELECT * FROM test');

        $connection->flushQueryLog();
        expect($connection->getQueryLog())->toBe([]);
    });

    test('transactions', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);

        $connection->beginTransaction();
        expect($connection->transactionLevel())->toBe(1);

        $connection->commit();
        expect($connection->transactionLevel())->toBe(0);

        $connection->beginTransaction();
        $connection->rollBack();
        expect($connection->transactionLevel())->toBe(0);
    });

    test('transaction callbacks', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);

        $executed = false;
        $result = $connection->transaction(function () use (&$executed) {
            $executed = true;

            return 'success';
        });

        expect($executed)->toBeTrue();
        expect($result)->toBe('success');
    });

    test('reconnection', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);

        // Force connection to be established first
        $pdo = $connection->getPdo();
        expect($pdo)->toBeInstanceOf(PDO::class);

        $connection->disconnect();
        // After disconnect, getPdo() will reconnect automatically
        // so we can't test for null, instead we test that reconnect works

        $connection->reconnect();
        $newPdo = $connection->getPdo();
        expect($newPdo)->toBeInstanceOf(PDO::class);
    });
});
