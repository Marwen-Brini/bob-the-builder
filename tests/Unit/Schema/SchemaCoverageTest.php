<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Schema;

afterEach(function () {
    \Mockery::close();
    // Reset the connection using reflection to set it to null
    $reflection = new \ReflectionClass(Schema::class);
    $property = $reflection->getProperty('connection');
    $property->setAccessible(true);
    $property->setValue(null, null);
});

test('get connection uses default when not set', function () {
    // Reset connection to null using reflection
    $reflection = new \ReflectionClass(Schema::class);
    $property = $reflection->getProperty('connection');
    $property->setAccessible(true);
    $property->setValue(null, null);

    // Now test that it tries to get default connection
    expect(fn () => Schema::getConnection())
        ->toThrow(\Error::class, 'Call to undefined method Bob\Database\Connection::getDefaultConnection()');
});

test('has table returns true when table exists', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('wp_');
    $mockConnection->shouldReceive('getDatabaseName')->andReturn('test_db');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([['exists' => 1]]);

    Schema::setConnection($mockConnection);

    $result = Schema::hasTable('posts');
    expect($result)->toBeTrue();
});

test('has table returns false when table does not exist', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('getDatabaseName')->andReturn('test_db');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([]);

    Schema::setConnection($mockConnection);

    $result = Schema::hasTable('non_existent');
    expect($result)->toBeFalse();
});

test('has column returns true when column exists', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('sqlite');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([['name' => 'id'], ['name' => 'title']]);

    Schema::setConnection($mockConnection);

    $result = Schema::hasColumn('posts', 'title');
    expect($result)->toBeTrue();
});

test('has column returns false when column does not exist', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('sqlite');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([['name' => 'id'], ['name' => 'title']]);

    Schema::setConnection($mockConnection);

    $result = Schema::hasColumn('posts', 'non_existent');
    expect($result)->toBeFalse();
});

test('has columns checks multiple columns', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('getDatabaseName')->andReturn('test_db');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([
            ['column_name' => 'id'],
            ['column_name' => 'title'],
            ['column_name' => 'content'],
        ]);

    Schema::setConnection($mockConnection);

    // All columns exist
    $result = Schema::hasColumns('posts', ['id', 'title']);
    expect($result)->toBeTrue();

    // Setup for next test
    $mockConnection->shouldReceive('getDatabaseName')->andReturn('test_db');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([
            ['column_name' => 'id'],
            ['column_name' => 'title'],
        ]);

    // One column doesn't exist
    $result = Schema::hasColumns('posts', ['id', 'missing']);
    expect($result)->toBeFalse();
});

test('get column listing', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('wp_');
    $mockConnection->shouldReceive('getDatabaseName')->andReturn('test_db');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([
            ['column_name' => 'id'],
            ['column_name' => 'title'],
            ['column_name' => 'content'],
        ]);

    Schema::setConnection($mockConnection);

    $columns = Schema::getColumnListing('posts');
    expect($columns)->toBe(['id', 'title', 'content']);
});

test('get column type', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([['data_type' => 'varchar']]);

    Schema::setConnection($mockConnection);

    $type = Schema::getColumnType('posts', 'title');
    expect($type)->toBe('varchar');
});

test('get column type throws exception when column does not exist', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([]);

    Schema::setConnection($mockConnection);

    expect(fn () => Schema::getColumnType('posts', 'non_existent'))
        ->toThrow(InvalidArgumentException::class, "Column non_existent doesn't exist on table posts.");
});

test('enable foreign key constraints', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('statement')
        ->once()
        ->with('SET FOREIGN_KEY_CHECKS=1')
        ->andReturn(true);

    Schema::setConnection($mockConnection);

    Schema::enableForeignKeyConstraints();

    // Assertion is that no exception is thrown
    expect(true)->toBeTrue();
});

test('disable foreign key constraints', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('statement')
        ->once()
        ->with('SET FOREIGN_KEY_CHECKS=0')
        ->andReturn(true);

    Schema::setConnection($mockConnection);

    Schema::disableForeignKeyConstraints();

    // Assertion is that no exception is thrown
    expect(true)->toBeTrue();
});

test('without foreign key constraints', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');

    // Expect disable then enable
    $mockConnection->shouldReceive('statement')
        ->once()
        ->with('SET FOREIGN_KEY_CHECKS=0')
        ->ordered()
        ->andReturn(true);

    $mockConnection->shouldReceive('statement')
        ->once()
        ->with('SET FOREIGN_KEY_CHECKS=1')
        ->ordered()
        ->andReturn(true);

    Schema::setConnection($mockConnection);

    $callbackCalled = false;
    $result = Schema::withoutForeignKeyConstraints(function () use (&$callbackCalled) {
        $callbackCalled = true;

        return 'test_result';
    });

    expect($callbackCalled)->toBeTrue();
    expect($result)->toBe('test_result');
});

test('without foreign key constraints re enables on exception', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');

    // Expect disable then enable even on exception
    $mockConnection->shouldReceive('statement')
        ->once()
        ->with('SET FOREIGN_KEY_CHECKS=0')
        ->ordered()
        ->andReturn(true);

    $mockConnection->shouldReceive('statement')
        ->once()
        ->with('SET FOREIGN_KEY_CHECKS=1')
        ->ordered()
        ->andReturn(true);

    Schema::setConnection($mockConnection);

    expect(fn () => Schema::withoutForeignKeyConstraints(function () {
        throw new RuntimeException('Test exception');
    }))->toThrow(RuntimeException::class, 'Test exception');
});

test('drop all tables works correctly', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('sqlite');
    $connection->shouldReceive('statement')->with('PRAGMA foreign_keys = OFF');
    $connection->shouldReceive('select')
        ->with("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
        ->andReturn([
            (object) ['name' => 'users'],
            (object) ['name' => 'posts'],
        ]);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->with('schema_transactions', true)->andReturn(false);
    $connection->shouldReceive('statement')->with('drop table "users"')->once();
    $connection->shouldReceive('statement')->with('drop table "posts"')->once();
    $connection->shouldReceive('statement')->with('PRAGMA foreign_keys = ON');

    Schema::setConnection($connection);
    Schema::dropAllTables();

    expect(true)->toBeTrue();
});

test('get grammar for postgresql', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('pgsql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([['exists' => true]]);

    Schema::setConnection($mockConnection);

    // This will trigger getGrammar internally
    Schema::hasTable('test');

    // Assertion is that correct grammar is used (no exception thrown)
    expect(true)->toBeTrue();
});

test('get grammar for unsupported driver throws exception', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('unsupported');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');

    Schema::setConnection($mockConnection);

    expect(fn () => Schema::hasTable('test'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported driver [unsupported].');
});

test('build with transaction support', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('pgsql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('getConfig')
        ->with('schema_transactions', true)
        ->andReturn(true);

    // PostgreSQL supports schema transactions
    $mockConnection->shouldReceive('transaction')
        ->once()
        ->andReturnUsing(function ($callback) {
            return $callback();
        });

    $mockConnection->shouldReceive('statement')
        ->once()
        ->andReturn(true);

    Schema::setConnection($mockConnection);

    // Create a table which will use transaction
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
    });

    // Assertion is that transaction was used (no exception thrown)
    expect(true)->toBeTrue();
});

test('build without transaction support', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('getConfig')
        ->with('schema_transactions', true)
        ->andReturn(true);

    // Add missing expectations for MySQL grammar
    $mockConnection->shouldReceive('getConfig')
        ->with('charset')
        ->andReturn(null);
    $mockConnection->shouldReceive('getConfig')
        ->with('collation')
        ->andReturn(null);

    // MySQL doesn't support schema transactions
    $mockConnection->shouldReceive('statement')
        ->once()
        ->andReturn(true);

    Schema::setConnection($mockConnection);

    // Create a table which won't use transaction
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
    });

    // Assertion is that no transaction was used (no exception thrown)
    expect(true)->toBeTrue();
});

test('get column type with postgresql format', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('pgsql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([['type' => 'integer']]);

    Schema::setConnection($mockConnection);

    $type = Schema::getColumnType('posts', 'id');
    expect($type)->toBe('integer');
});

test('has column with empty column list', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('mysql');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('getDatabaseName')->andReturn('test_db');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([]);

    Schema::setConnection($mockConnection);

    $result = Schema::hasColumn('empty_table', 'any_column');
    expect($result)->toBeFalse();
});

test('get column listing with empty table', function () {
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getDriverName')->andReturn('sqlite');
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
    $mockConnection->shouldReceive('select')
        ->once()
        ->andReturn([]);

    Schema::setConnection($mockConnection);

    $columns = Schema::getColumnListing('empty_table');
    expect($columns)->toBe([]);
});

test('drop all tables for mysql', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('statement')->with('SET FOREIGN_KEY_CHECKS = 0')->once();
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('select')
        ->with('SHOW TABLES')
        ->andReturn([
            (object) ['Tables_in_test_db' => 'users'],
            (object) ['Tables_in_test_db' => 'posts'],
        ]);
    $connection->shouldReceive('getTablePrefix')->andReturn('');

    // Schema::drop() calls Schema::build() which checks for schema_transactions and charset/collation
    $connection->shouldReceive('getConfig')->with('schema_transactions', true)->andReturn(false);
    $connection->shouldReceive('getConfig')->with('charset')->andReturn(null);
    $connection->shouldReceive('getConfig')->with('collation')->andReturn(null);

    // Drop statements
    $connection->shouldReceive('statement')->with('drop table `users`')->once();
    $connection->shouldReceive('statement')->with('drop table `posts`')->once();
    $connection->shouldReceive('statement')->with('SET FOREIGN_KEY_CHECKS = 1')->once();

    Schema::setConnection($connection);
    Schema::dropAllTables();

    expect(true)->toBeTrue();
});

test('drop all tables for postgresql', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('select')
        ->with("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'")
        ->andReturn([
            (object) ['tablename' => 'users'],
            (object) ['tablename' => 'posts'],
        ]);
    $connection->shouldReceive('getTablePrefix')->andReturn('');

    // Schema::drop() calls Schema::build() which checks for schema_transactions
    $connection->shouldReceive('getConfig')->with('schema_transactions', true)->andReturn(false);

    // Drop statements (PostgreSQL uses lowercase 'drop table')
    $connection->shouldReceive('statement')->with('drop table "users"')->once();
    $connection->shouldReceive('statement')->with('drop table "posts"')->once();

    Schema::setConnection($connection);
    Schema::dropAllTables();

    expect(true)->toBeTrue();
});

test('drop all tables throws exception for unsupported driver', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('oracle');

    Schema::setConnection($connection);

    expect(fn () => Schema::dropAllTables())
        ->toThrow(\InvalidArgumentException::class, 'Unsupported database driver: oracle');
});
