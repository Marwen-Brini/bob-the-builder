<?php

namespace Tests\Unit\Query;

use Bob\Database\Connection;
use Bob\Query\Builder;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Processor;
use Mockery as m;

beforeEach(function () {
    $this->grammar = new MySQLGrammar;
    $this->processor = new Processor;

    $this->connection = m::mock(Connection::class);
    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new Builder($this->connection);
});

afterEach(function () {
    m::close();
});

test('exists() returns false when no records match the query', function () {
    // The SQL query will be: SELECT EXISTS(SELECT * FROM users WHERE name = ?) AS `exists`
    // This should return [['exists' => 0]] when no records match

    $this->connection->shouldReceive('select')
        ->once()
        ->with(
            'select exists(select * from `users` where `name` = ?) as `exists`',
            ['NonExistent']
        )
        ->andReturn([['exists' => 0]]);  // No records found, EXISTS returns 0

    $result = $this->builder->from('users')->where('name', 'NonExistent')->exists();

    // EXPECTED: false (no records match)
    // ACTUAL BUG: true (because count([['exists' => 0]]) > 0 returns true)
    expect($result)->toBeFalse();
});

test('exists() returns true when records match the query', function () {
    // When records exist, the query returns [['exists' => 1]]

    $this->connection->shouldReceive('select')
        ->once()
        ->with(
            'select exists(select * from `users` where `name` = ?) as `exists`',
            ['John']
        )
        ->andReturn([['exists' => 1]]);  // Records found, EXISTS returns 1

    $result = $this->builder->from('users')->where('name', 'John')->exists();

    expect($result)->toBeTrue();
});

test('exists() returns false for empty table', function () {
    // Test with no WHERE clause - checking if table has ANY records

    $this->connection->shouldReceive('select')
        ->once()
        ->with(
            'select exists(select * from `users`) as `exists`',
            []
        )
        ->andReturn([['exists' => 0]]);  // Empty table, EXISTS returns 0

    $result = $this->builder->from('users')->exists();

    expect($result)->toBeFalse();
});

test('exists() handles both array and object results from database', function () {
    // Some database drivers return objects instead of arrays

    // Test with stdClass object result
    $resultObject = new \stdClass;
    $resultObject->exists = 0;

    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([$resultObject]);

    $result = $this->builder->from('users')->where('id', 999)->exists();

    expect($result)->toBeFalse();
});

test('doesntExist() returns opposite of exists()', function () {
    // Test that doesntExist() properly inverts the exists() result

    // First test: no records exist
    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([['exists' => 0]]);

    $result = $this->builder->from('users')->where('name', 'NonExistent')->doesntExist();

    expect($result)->toBeTrue();  // Should be true when no records exist

    // Second test: records exist
    $builder2 = new Builder($this->connection);
    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([['exists' => 1]]);

    $result2 = $builder2->from('users')->where('name', 'John')->doesntExist();

    expect($result2)->toBeFalse();  // Should be false when records exist
});

test('exists() with complex WHERE conditions', function () {
    $this->connection->shouldReceive('select')
        ->once()
        ->with(
            'select exists(select * from `posts` where `status` = ? and `author_id` = ? and `created_at` > ?) as `exists`',
            ['published', 5, '2024-01-01']
        )
        ->andReturn([['exists' => 0]]);

    $result = $this->builder->from('posts')
        ->where('status', 'published')
        ->where('author_id', 5)
        ->where('created_at', '>', '2024-01-01')
        ->exists();

    expect($result)->toBeFalse();
});

test('exists() correctly applies global scopes', function () {
    // Create a builder with a global scope
    $this->builder->from('posts');

    // Add a global scope
    $this->builder->where('deleted_at', null);  // Soft delete scope

    $this->connection->shouldReceive('select')
        ->once()
        ->with(
            'select exists(select * from `posts` where `deleted_at` is null and `id` = ?) as `exists`',
            [123]
        )
        ->andReturn([['exists' => 0]]);

    $result = $this->builder->where('id', 123)->exists();

    expect($result)->toBeFalse();
});

test('exists() handles empty result set from database', function () {
    // Edge case: database returns empty array instead of [['exists' => 0]]

    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([]);  // Empty result set

    $result = $this->builder->from('users')->exists();

    // Should handle gracefully and return false
    expect($result)->toBeFalse();
});

test('exists() with processor that modifies results', function () {
    // Test that processor is properly applied to results
    $customProcessor = m::mock(Processor::class);
    $customProcessor->shouldReceive('processSelect')
        ->once()
        ->andReturnUsing(function ($query, $results) {
            // Processor could convert array to object or vice versa
            return $results;
        });

    // Create a new mock connection with custom processor
    $customConnection = m::mock(Connection::class);
    $customConnection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $customConnection->shouldReceive('getPostProcessor')->andReturn($customProcessor);

    $builder = new Builder($customConnection);

    $customConnection->shouldReceive('select')
        ->once()
        ->andReturn([['exists' => 0]]);

    $result = $builder->from('users')->exists();

    expect($result)->toBeFalse();
});

// Integration test with real SQLite to demonstrate the bug
test('exists() handles unexpected result format', function () {
    // Test the fallback case where result is neither array nor object
    // This covers line 1235: return false;

    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn(['unexpected_string_value']);  // Not an array or object

    $result = $this->builder->from('users')->exists();

    // Should handle gracefully and return false for unexpected format
    expect($result)->toBeFalse();
});

test('exists() bug demonstration with real database', function () {
    // Create a real SQLite connection for integration testing
    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    $connection = new Connection(['driver' => 'sqlite'], null, $pdo);

    // Create test table
    $connection->statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT)');

    // Insert a test record
    $connection->statement('INSERT INTO test_users (name) VALUES (?)', ['John']);

    $builder = new Builder($connection);

    // Test 1: Record exists - should return true
    $exists = $builder->from('test_users')->where('name', 'John')->exists();
    expect($exists)->toBeTrue();

    // Test 2: Record doesn't exist - should return false
    // THIS IS WHERE THE BUG OCCURS
    $notExists = (new Builder($connection))->from('test_users')->where('name', 'NonExistent')->exists();
    expect($notExists)->toBeFalse();  // BUG: This will fail with current implementation

    // Test 3: Empty table check
    $connection->statement('DELETE FROM test_users');
    $emptyExists = (new Builder($connection))->from('test_users')->exists();
    expect($emptyExists)->toBeFalse();  // BUG: This will also fail
});
