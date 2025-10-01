<?php

use Bob\Contracts\BuilderInterface;
use Bob\Database\Connection;
use Bob\Query\Processor;
use Mockery as m;

describe('Processor Tests', function () {

    beforeEach(function () {
        $this->processor = new Processor;
    });

    afterEach(function () {
        m::close();
    });

    test('Processor implements ProcessorInterface', function () {
        expect($this->processor)->toBeInstanceOf(Bob\Contracts\ProcessorInterface::class);
    });

    test('processSelect returns results as-is', function () {
        $query = m::mock(BuilderInterface::class);
        $results = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
            ['id' => 3, 'name' => 'Bob'],
        ];

        $processed = $this->processor->processSelect($query, $results);

        expect($processed)->toBe($results);
    });

    test('processSelect with empty results', function () {
        $query = m::mock(BuilderInterface::class);
        $results = [];

        $processed = $this->processor->processSelect($query, $results);

        expect($processed)->toBe([]);
    });

    test('processSelect preserves array structure', function () {
        $query = m::mock(BuilderInterface::class);
        $results = [
            ['id' => 1, 'data' => ['nested' => 'value']],
            ['id' => 2, 'data' => null],
        ];

        $processed = $this->processor->processSelect($query, $results);

        expect($processed)->toBe($results);
        expect($processed[0]['data'])->toBe(['nested' => 'value']);
        expect($processed[1]['data'])->toBeNull();
    });

    test('processInsertGetId executes insert and returns numeric ID', function () {
        $pdo = m::mock(PDO::class);
        $pdo->shouldReceive('lastInsertId')->with(null)->once()->andReturn('42');

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('insert')->with('INSERT INTO users', ['name' => 'John'])->once();
        $connection->shouldReceive('getPdo')->once()->andReturn($pdo);

        $query = m::mock(BuilderInterface::class);
        $query->shouldReceive('getConnection')->twice()->andReturn($connection);

        $id = $this->processor->processInsertGetId($query, 'INSERT INTO users', ['name' => 'John']);

        expect($id)->toBe(42);
    });

    test('processInsertGetId with custom sequence', function () {
        $pdo = m::mock(PDO::class);
        $pdo->shouldReceive('lastInsertId')->with('users_id_seq')->once()->andReturn('100');

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('insert')->with('INSERT INTO users', ['name' => 'Jane'])->once();
        $connection->shouldReceive('getPdo')->once()->andReturn($pdo);

        $query = m::mock(BuilderInterface::class);
        $query->shouldReceive('getConnection')->twice()->andReturn($connection);

        $id = $this->processor->processInsertGetId($query, 'INSERT INTO users', ['name' => 'Jane'], 'users_id_seq');

        expect($id)->toBe(100);
    });

    test('processInsertGetId returns non-numeric ID as string', function () {
        $pdo = m::mock(PDO::class);
        $pdo->shouldReceive('lastInsertId')->with(null)->once()->andReturn('abc-123-def');

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('insert')->with('INSERT INTO items', ['uuid' => 'test'])->once();
        $connection->shouldReceive('getPdo')->once()->andReturn($pdo);

        $query = m::mock(BuilderInterface::class);
        $query->shouldReceive('getConnection')->twice()->andReturn($connection);

        $id = $this->processor->processInsertGetId($query, 'INSERT INTO items', ['uuid' => 'test']);

        expect($id)->toBe('abc-123-def');
    });

    test('processInsertGetId with zero ID', function () {
        $pdo = m::mock(PDO::class);
        $pdo->shouldReceive('lastInsertId')->with(null)->once()->andReturn('0');

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('insert')->once();
        $connection->shouldReceive('getPdo')->once()->andReturn($pdo);

        $query = m::mock(BuilderInterface::class);
        $query->shouldReceive('getConnection')->twice()->andReturn($connection);

        $id = $this->processor->processInsertGetId($query, 'INSERT INTO test', []);

        expect($id)->toBe(0);
    });

    test('processColumnListing returns results as-is', function () {
        $columns = ['id', 'name', 'email', 'created_at', 'updated_at'];

        $processed = $this->processor->processColumnListing($columns);

        expect($processed)->toBe($columns);
    });

    test('processColumnListing with empty array', function () {
        $columns = [];

        $processed = $this->processor->processColumnListing($columns);

        expect($processed)->toBe([]);
    });

    test('processColumnListing preserves column order', function () {
        $columns = ['z_column', 'a_column', 'm_column'];

        $processed = $this->processor->processColumnListing($columns);

        expect($processed)->toBe($columns);
        expect($processed[0])->toBe('z_column');
        expect($processed[1])->toBe('a_column');
        expect($processed[2])->toBe('m_column');
    });

    test('processColumnTypeListing returns results as-is', function () {
        $types = [
            ['column' => 'id', 'type' => 'integer'],
            ['column' => 'name', 'type' => 'varchar'],
            ['column' => 'email', 'type' => 'varchar'],
            ['column' => 'created_at', 'type' => 'timestamp'],
        ];

        $processed = $this->processor->processColumnTypeListing($types);

        expect($processed)->toBe($types);
    });

    test('processColumnTypeListing with empty array', function () {
        $types = [];

        $processed = $this->processor->processColumnTypeListing($types);

        expect($processed)->toBe([]);
    });

    test('processColumnTypeListing with complex types', function () {
        $types = [
            ['column' => 'data', 'type' => 'json', 'nullable' => true],
            ['column' => 'amount', 'type' => 'decimal(10,2)', 'default' => '0.00'],
            ['column' => 'is_active', 'type' => 'boolean', 'default' => false],
        ];

        $processed = $this->processor->processColumnTypeListing($types);

        expect($processed)->toBe($types);
        expect($processed[0]['nullable'])->toBeTrue();
        expect($processed[1]['default'])->toBe('0.00');
        expect($processed[2]['default'])->toBeFalse();
    });

});
