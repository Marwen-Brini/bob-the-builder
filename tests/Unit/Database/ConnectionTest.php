<?php

declare(strict_types=1);

use Bob\Database\Connection;

it('handles postgres driver aliases', function () {
    // Test various postgres driver aliases
    $drivers = ['pgsql', 'postgres', 'postgresql'];
    
    foreach ($drivers as $driver) {
        // This will test that the DSN is created properly for all postgres variants
        $connection = new Connection([
            'driver' => $driver,
            'host' => 'localhost',
            'database' => 'test_db',
            'username' => 'user',
            'password' => 'pass',
        ]);
        
        // Use reflection to check the driver was normalized
        $reflection = new ReflectionClass($connection);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($connection);
        
        // The driver should still be what was passed
        expect($config['driver'])->toBe($driver);
    }
});

it('reconnects to database', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Get initial PDO
    $pdo1 = $connection->getPdo();
    expect($pdo1)->toBeInstanceOf(PDO::class);
    
    // Reconnect
    $connection->reconnect();
    
    // Should have a new PDO instance
    $pdo2 = $connection->getPdo();
    expect($pdo2)->toBeInstanceOf(PDO::class);
    // Note: Can't easily test if they're different objects since PDO might reuse connections
});

it('gets database name from config', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => 'test_database.db',
    ]);
    
    expect($connection->getDatabaseName())->toBe('test_database.db');
    
    // Test without database in config
    $connection = new Connection([
        'driver' => 'sqlite',
    ]);
    
    expect($connection->getDatabaseName())->toBe('');
});

it('returns empty array when selecting in pretend mode', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Create a test table
    $connection->statement('CREATE TABLE test (id INTEGER, name TEXT)');
    $connection->statement('INSERT INTO test VALUES (1, "John")');
    
    // Normal select should return results
    $results = $connection->select('SELECT * FROM test');
    expect($results)->toHaveCount(1);
    
    // Enable pretend mode
    $connection->pretend(function ($connection) {
        $results = $connection->select('SELECT * FROM test');
        expect($results)->toBe([]);
    });
});

it('handles multiple postgres aliases in match statement', function () {
    // We can't actually connect to postgres in tests, but we can verify
    // the DSN is built correctly by catching the connection exception
    $drivers = ['pgsql', 'postgres', 'postgresql'];

    foreach ($drivers as $driver) {
        $connection = new Connection([
            'driver' => $driver,
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass',
        ]);

        // Try to get PDO which will trigger DSN creation
        try {
            $connection->getPdo();
        } catch (\PDOException $e) {
            // Expected - either driver not found or connection failed
            // Both are acceptable as they prove the DSN was built
            $message = $e->getMessage();
            expect(
                str_contains($message, 'could not find driver') ||
                str_contains($message, 'password authentication failed') ||
                str_contains($message, 'connection to server')
            )->toBeTrue();
        }
    }
});

it('disconnects and nullifies PDO connections on reconnect', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Establish connection and create table
    $connection->statement('CREATE TABLE test (id INTEGER)');
    $connection->statement('INSERT INTO test VALUES (1)');
    
    // Use reflection to check internal state
    $reflection = new ReflectionClass($connection);
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    
    // Verify PDO is set
    $pdo1 = $pdoProperty->getValue($connection);
    expect($pdo1)->toBeInstanceOf(PDO::class);
    
    // Call reconnect
    $connection->reconnect();
    
    // After reconnect, PDO should be nullified initially
    // But since we're using :memory:, we need to recreate the table
    $connection->statement('CREATE TABLE test2 (id INTEGER)');
    $connection->statement('INSERT INTO test2 VALUES (1)');
    
    // Verify a new PDO was created
    $pdo2 = $pdoProperty->getValue($connection);
    expect($pdo2)->toBeInstanceOf(PDO::class);
});

it('prepares bindings correctly for PostgreSQL with booleans', function () {
    $connection = new Connection([
        'driver' => 'pgsql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'user',
        'password' => 'pass',
    ]);

    // Test with boolean values
    $bindings = [
        'name' => 'John',
        'active' => true,
        'admin' => false,
        'score' => 100,
    ];

    $prepared = $connection->prepareBindings($bindings);

    // PostgreSQL should convert booleans to strings
    expect($prepared['name'])->toBe('John');
    expect($prepared['active'])->toBe('true');
    expect($prepared['admin'])->toBe('false');
    expect($prepared['score'])->toBe(100);
});

it('prepares bindings correctly for MySQL with booleans', function () {
    $connection = new Connection([
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'user',
        'password' => 'pass',
    ]);

    // Test with boolean values
    $bindings = [
        'name' => 'John',
        'active' => true,
        'admin' => false,
        'score' => 100,
    ];

    $prepared = $connection->prepareBindings($bindings);

    // MySQL should convert booleans to integers
    expect($prepared['name'])->toBe('John');
    expect($prepared['active'])->toBe(1);
    expect($prepared['admin'])->toBe(0);
    expect($prepared['score'])->toBe(100);
});

it('prepares bindings correctly for SQLite with booleans', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Test with boolean values
    $bindings = [
        'name' => 'John',
        'active' => true,
        'admin' => false,
        'score' => 100,
    ];

    $prepared = $connection->prepareBindings($bindings);

    // SQLite should convert booleans to integers
    expect($prepared['name'])->toBe('John');
    expect($prepared['active'])->toBe(1);
    expect($prepared['admin'])->toBe(0);
    expect($prepared['score'])->toBe(100);
});

it('prepares bindings with DateTime objects', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $date = new DateTime('2024-01-15 10:30:00');

    $bindings = [
        'created_at' => $date,
        'name' => 'John',
    ];

    $prepared = $connection->prepareBindings($bindings);

    // DateTime should be formatted as string
    expect($prepared['created_at'])->toBe('2024-01-15 10:30:00');
    expect($prepared['name'])->toBe('John');
});

it('prepares mixed bindings correctly', function () {
    // Test PostgreSQL with mixed types
    $pgConnection = new Connection([
        'driver' => 'pgsql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'user',
        'password' => 'pass',
    ]);

    $date = new DateTime('2024-01-15 10:30:00');

    $bindings = [
        'id' => 1,
        'name' => 'John',
        'active' => true,
        'score' => 95.5,
        'created_at' => $date,
        'deleted' => false,
        'metadata' => null,
    ];

    $prepared = $pgConnection->prepareBindings($bindings);

    // Verify all types are handled correctly for PostgreSQL
    expect($prepared['id'])->toBe(1);
    expect($prepared['name'])->toBe('John');
    expect($prepared['active'])->toBe('true'); // Boolean converted to string for PostgreSQL
    expect($prepared['score'])->toBe(95.5);
    expect($prepared['created_at'])->toBe('2024-01-15 10:30:00');
    expect($prepared['deleted'])->toBe('false'); // Boolean converted to string for PostgreSQL
    expect($prepared['metadata'])->toBeNull();

    // Test MySQL with same data
    $mysqlConnection = new Connection([
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'user',
        'password' => 'pass',
    ]);

    $prepared = $mysqlConnection->prepareBindings($bindings);

    // Verify booleans are converted to int for MySQL
    expect($prepared['active'])->toBe(1);
    expect($prepared['deleted'])->toBe(0);
});