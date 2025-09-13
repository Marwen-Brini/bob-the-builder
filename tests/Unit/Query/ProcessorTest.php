<?php

use Bob\Query\Processor;
use Bob\Query\Builder;
use Bob\Database\Connection;

describe('Processor class', function () {
    beforeEach(function () {
        $this->processor = new Processor();
        
        // Create a mock connection for testing
        $this->connection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:'
        ]);
        
        $this->builder = new Builder($this->connection);
    });
    
    afterEach(function () {
        Mockery::close();
    });
    
    it('implements ProcessorInterface', function () {
        expect($this->processor)->toBeInstanceOf(\Bob\Contracts\ProcessorInterface::class);
    });
    
    it('processes select results without modification', function () {
        $results = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $processed = $this->processor->processSelect($this->builder, $results);

        expect($processed)->toHaveCount(2);
        expect($processed[0])->toBeInstanceOf(stdClass::class);
        expect($processed[0]->id)->toBe(1);
        expect($processed[0]->name)->toBe('John');
        expect($processed[1])->toBeInstanceOf(stdClass::class);
        expect($processed[1]->id)->toBe(2);
        expect($processed[1]->name)->toBe('Jane');
    });
    
    it('processes empty select results', function () {
        $results = [];
        
        $processed = $this->processor->processSelect($this->builder, $results);
        
        expect($processed)->toBe([]);
        expect($processed)->toHaveCount(0);
    });
    
    it('processes insertGetId with numeric ID', function () {
        // Create a test table
        $this->connection->statement('CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT)');
        
        // Test insertGetId
        $id = $this->processor->processInsertGetId(
            $this->builder, 
            'INSERT INTO test_table (name) VALUES (?)', 
            ['Test Name']
        );
        
        expect($id)->toBeInt();
        expect($id)->toBeGreaterThan(0);
    });
    
    it('processes insertGetId with string ID', function () {
        // Mock a connection that returns a string ID
        $mockConnection = Mockery::mock(Connection::class);
        $mockPdo = Mockery::mock(\PDO::class);
        
        $mockConnection->shouldReceive('insert')->once();
        $mockConnection->shouldReceive('getPdo')->andReturn($mockPdo);
        $mockPdo->shouldReceive('lastInsertId')->with(null)->andReturn('string-id-123');
        
        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('getConnection')->andReturn($mockConnection);
        
        $id = $this->processor->processInsertGetId(
            $mockBuilder,
            'INSERT INTO test (name) VALUES (?)',
            ['Test'],
            null
        );
        
        expect($id)->toBe('string-id-123');
        expect($id)->toBeString();
    });
    
    it('processes insertGetId with sequence parameter', function () {
        // Mock a connection that uses a sequence (like PostgreSQL)
        $mockConnection = Mockery::mock(Connection::class);
        $mockPdo = Mockery::mock(\PDO::class);
        
        $mockConnection->shouldReceive('insert')->once();
        $mockConnection->shouldReceive('getPdo')->andReturn($mockPdo);
        $mockPdo->shouldReceive('lastInsertId')->with('users_id_seq')->andReturn('42');
        
        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('getConnection')->andReturn($mockConnection);
        
        $id = $this->processor->processInsertGetId(
            $mockBuilder,
            'INSERT INTO users (name) VALUES (?)',
            ['Test User'],
            'users_id_seq'
        );
        
        expect($id)->toBe(42); // Should be converted to int since it's numeric
    });
    
    it('processes column listing results without modification', function () {
        $columnResults = [
            ['column_name' => 'id'],
            ['column_name' => 'name'],
            ['column_name' => 'email']
        ];
        
        $processed = $this->processor->processColumnListing($columnResults);
        
        expect($processed)->toBe($columnResults);
        expect($processed)->toHaveCount(3);
    });
    
    it('processes empty column listing', function () {
        $columnResults = [];
        
        $processed = $this->processor->processColumnListing($columnResults);
        
        expect($processed)->toBe([]);
        expect($processed)->toHaveCount(0);
    });
    
    // Line 31: processColumnTypeListing method (this was the uncovered line)
    it('processes column type listing results without modification', function () {
        $typeResults = [
            ['column_name' => 'id', 'data_type' => 'integer'],
            ['column_name' => 'name', 'data_type' => 'text'],
            ['column_name' => 'email', 'data_type' => 'varchar']
        ];
        
        $processed = $this->processor->processColumnTypeListing($typeResults);
        
        expect($processed)->toBe($typeResults);
        expect($processed)->toHaveCount(3);
        expect($processed[0])->toHaveKey('column_name');
        expect($processed[0])->toHaveKey('data_type');
    });
    
    it('processes empty column type listing', function () {
        $typeResults = [];
        
        $processed = $this->processor->processColumnTypeListing($typeResults);
        
        expect($processed)->toBe([]);
        expect($processed)->toHaveCount(0);
    });
    
    it('processes column type listing with complex data types', function () {
        $typeResults = [
            ['column_name' => 'id', 'data_type' => 'bigint', 'is_nullable' => 'NO'],
            ['column_name' => 'decimal_field', 'data_type' => 'decimal(10,2)', 'is_nullable' => 'YES'],
            ['column_name' => 'json_field', 'data_type' => 'json', 'is_nullable' => 'YES']
        ];
        
        $processed = $this->processor->processColumnTypeListing($typeResults);
        
        expect($processed)->toBe($typeResults);
        expect($processed)->toHaveCount(3);
        expect($processed[1]['data_type'])->toBe('decimal(10,2)');
    });
});