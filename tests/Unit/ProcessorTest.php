<?php

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Query\Builder;
use Bob\Query\Processor;

beforeEach(function () {
    $this->processor = new Processor;
});

test('processes select results', function () {
    $results = [
        ['id' => 1, 'name' => 'John'],
        ['id' => 2, 'name' => 'Jane'],
    ];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $results
    );

    expect($processed)->toBe($results);
});

test('processes empty select results', function () {
    $results = [];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $results
    );

    expect($processed)->toBe([]);
});

test('processes insert get id', function () {
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('lastInsertId')->with(null)->andReturn('123');
    $pdo->shouldReceive('lastInsertId')->with('users_id_seq')->andReturn('456');

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('insert')->twice();
    $connection->shouldReceive('getPdo')->andReturn($pdo)->twice();

    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('getConnection')->andReturn($connection)->times(4); // Called twice per processInsertGetId

    // Test without sequence
    $id = $this->processor->processInsertGetId($builder, 'INSERT INTO users', [], null);
    expect($id)->toBe(123);

    // Test with sequence (PostgreSQL style)
    $id = $this->processor->processInsertGetId($builder, 'INSERT INTO users', [], 'users_id_seq');
    expect($id)->toBe(456);
});

test('processes columns listing', function () {
    $results = [
        ['column_name' => 'id', 'data_type' => 'int'],
        ['column_name' => 'name', 'data_type' => 'varchar'],
        ['column_name' => 'created_at', 'data_type' => 'timestamp'],
    ];

    $processed = $this->processor->processColumnListing($results);

    // The base processor just returns results as-is
    expect($processed)->toBe($results);
});

test('processes empty columns listing', function () {
    $results = [];

    $processed = $this->processor->processColumnListing($results);

    expect($processed)->toBe([]);
});

test('processes single column select results', function () {
    $results = [
        ['count' => 10],
    ];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $results
    );

    expect($processed)->toBe($results);
});

test('processes aggregate results', function () {
    $results = [
        ['aggregate' => 42],
    ];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $results
    );

    expect($processed)->toBe($results);
});

test('handles null values in results', function () {
    $results = [
        ['id' => 1, 'name' => 'John', 'email' => null],
        ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
    ];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $results
    );

    expect($processed)->toBe($results);
});

test('handles mixed data types in results', function () {
    $results = [
        [
            'id' => 1,
            'name' => 'Product',
            'price' => 19.99,
            'in_stock' => true,
            'tags' => '["electronics", "gadgets"]',
            'metadata' => null,
        ],
    ];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $results
    );

    expect($processed)->toBe($results);
});

test('processes results with special characters', function () {
    $results = [
        ['content' => 'This is a "quoted" text with \'apostrophes\''],
        ['content' => 'Line 1\nLine 2\rLine 3'],
    ];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $results
    );

    expect($processed)->toBe($results);
});

test('processes large result sets efficiently', function () {
    // Generate a large result set
    $results = [];
    for ($i = 1; $i <= 1000; $i++) {
        $results[] = [
            'id' => $i,
            'name' => "User $i",
            'email' => "user$i@example.com",
        ];
    }

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $results
    );

    expect($processed)->toHaveCount(1000);
    expect($processed[0])->toBe(['id' => 1, 'name' => 'User 1', 'email' => 'user1@example.com']);
    expect($processed[999])->toBe(['id' => 1000, 'name' => 'User 1000', 'email' => 'user1000@example.com']);
});

test('processes columns with various naming conventions', function () {
    $results = [
        ['column_name' => 'user_id', 'data_type' => 'int'],
        ['column_name' => 'firstName', 'data_type' => 'varchar'],
        ['column_name' => 'last-name', 'data_type' => 'varchar'],
        ['column_name' => 'email.address', 'data_type' => 'varchar'],
    ];

    $processed = $this->processor->processColumnListing($results);

    // The base processor just returns results as-is
    expect($processed)->toBe($results);
});

test('handles database specific return values', function () {
    // MySQL style
    $mysqlResults = [
        ['COUNT(*)' => 10],
    ];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $mysqlResults
    );

    expect($processed)->toBe($mysqlResults);

    // PostgreSQL style
    $pgResults = [
        ['count' => '10'],
    ];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $pgResults
    );

    expect($processed)->toBe($pgResults);
});

test('preserves data types from database', function () {
    $results = [
        [
            'int_col' => 42,
            'float_col' => 3.14,
            'string_col' => 'text',
            'bool_col' => true,
            'null_col' => null,
        ],
    ];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $results
    );

    expect($processed[0]['int_col'])->toBeInt();
    expect($processed[0]['float_col'])->toBeFloat();
    expect($processed[0]['string_col'])->toBeString();
    expect($processed[0]['bool_col'])->toBeBool();
    expect($processed[0]['null_col'])->toBeNull();
});

test('processes nested arrays if present', function () {
    $results = [
        [
            'id' => 1,
            'data' => ['nested' => 'value'],
        ],
    ];

    $processed = $this->processor->processSelect(
        Mockery::mock(Builder::class),
        $results
    );

    expect($processed)->toBe($results);
});

afterEach(function () {
    Mockery::close();
});
