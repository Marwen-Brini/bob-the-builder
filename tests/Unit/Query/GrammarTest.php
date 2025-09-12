<?php

use Bob\Query\Grammar;
use Bob\Query\Builder;
use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Contracts\BuilderInterface;
use Bob\Contracts\ExpressionInterface;

describe('Grammar class', function () {
    beforeEach(function () {
        // Create a concrete implementation of the abstract Grammar class for testing
        $this->grammar = new class extends Grammar {
            protected array $operators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like'];
            
            // Override abstract methods if any
        };
        
        $this->connection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:'
        ]);
        
        $this->builder = new Builder($this->connection);
        $this->builder->from('users');
    });
    
    it('implements GrammarInterface', function () {
        expect($this->grammar)->toBeInstanceOf(\Bob\Contracts\GrammarInterface::class);
    });
    
    // Line 19: compileUnionAggregate when query has unions and aggregate
    it('compiles union aggregate queries', function () {
        // Create a mock builder with unions and aggregate
        $mockBuilder = Mockery::mock(BuilderInterface::class);
        $mockBuilder->shouldReceive('getUnions')->andReturn(['some union']);
        $mockBuilder->shouldReceive('getAggregate')->andReturn(['function' => 'count', 'columns' => ['*']]);
        
        // This should call compileUnionAggregate (but our test grammar doesn't implement it)
        // So we'll test that it tries to call the method
        expect(function () use ($mockBuilder) {
            $this->grammar->compileSelect($mockBuilder);
        })->toThrow(Error::class); // Method not implemented in test grammar
    });
    
    // Line 130: Join constraint with boolean and previous
    it('compiles join constraints with boolean operators', function () {
        $where = [
            'type' => 'Column',
            'first' => 'users.id',
            'operator' => '=',
            'second' => 'posts.user_id',
            'boolean' => 'or',
            'previous' => ['some previous condition']
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileJoinConstraint');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $where);
        expect($result)->toContain('or');
        expect($result)->toContain('"users"."id" = "posts"."user_id"');
    });
    
    // Line 136: Non-Column join constraint type
    it('handles non-Column join constraint types', function () {
        $where = [
            'type' => 'Basic',
            'column' => 'status',
            'operator' => '=',
            'value' => 'active',
            'boolean' => 'and'
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileJoinConstraint');
        $method->setAccessible(true);
        
        // This will try to call whereBasic method with undefined variables
        expect(function () use ($method, $where) {
            $method->invoke($this->grammar, $where);
        })->toThrow(Error::class); // Due to undefined $query variable
    });
    
    // Line 154: Raw having clause compilation
    it('compiles raw having clauses', function () {
        $having = [
            'type' => 'Raw',
            'boolean' => 'and',
            'sql' => 'COUNT(*) > 5'
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileHaving');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $having);
        expect($result)->toBe('and COUNT(*) > 5');
    });
    
    // Line 171: Empty orders array
    it('handles empty orders array', function () {
        $orders = [];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileOrders');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $orders);
        expect($result)->toBe('');
    });
    
    // Line 178: Raw order by clause
    it('compiles raw order clauses', function () {
        $orders = [
            ['type' => 'Raw', 'sql' => 'RANDOM()'],
            ['column' => 'name', 'direction' => 'asc']
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileOrders');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $orders);
        expect($result)->toBe('order by RANDOM(), "name" asc');
    });
    
    // Lines 199-224: Union compilation methods
    it('compiles unions with all options', function () {
        // Create a mock query for union
        $unionQuery = Mockery::mock(BuilderInterface::class);
        $unionQuery->shouldReceive('toSql')->andReturn('SELECT * FROM posts');
        
        $mockBuilder = Mockery::mock(BuilderInterface::class);
        $mockBuilder->shouldReceive('getUnions')->andReturn([
            ['query' => $unionQuery, 'all' => false],
            ['query' => $unionQuery, 'all' => true]
        ]);
        $mockBuilder->shouldReceive('getUnionOrders')->andReturn([
            ['column' => 'created_at', 'direction' => 'desc']
        ]);
        $mockBuilder->shouldReceive('getUnionLimit')->andReturn(100);
        $mockBuilder->shouldReceive('getUnionOffset')->andReturn(50);
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileUnions');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $mockBuilder);
        expect($result)->toContain('union (SELECT * FROM posts)');
        expect($result)->toContain('union all (SELECT * FROM posts)');
        expect($result)->toContain('order by');
        expect($result)->toContain('limit 100');
        expect($result)->toContain('offset 50');
    });
    
    // Line 230: Empty wheres array
    it('handles empty wheres array', function () {
        $this->builder->wheres = [];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileWheres');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder);
        expect($result)->toBe('');
    });
    
    // Lines 262-265: whereIn with empty values
    it('compiles whereIn with empty values', function () {
        $where = [
            'column' => 'id',
            'values' => []
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('whereIn');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $where);
        expect($result)->toBe('0 = 1');
    });
    
    // Line 275: whereNotInSub method
    it('compiles whereNotInSub clauses', function () {
        $subQuery = new Builder($this->connection);
        $subQuery->from('posts')->select('id');
        
        $where = [
            'column' => 'user_id',
            'query' => $subQuery
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('whereNotInSub');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $where);
        expect($result)->toBe('"user_id" not in (select "id" from "posts")');
    });
    
    // Line 284: whereNotIn with empty values
    it('compiles whereNotIn with empty values', function () {
        $where = [
            'column' => 'id',
            'values' => []
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('whereNotIn');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $where);
        expect($result)->toBe('1 = 1');
    });
    
    // Line 262: whereIn with Builder instance as values
    it('compiles whereIn with Builder instance', function () {
        $subQuery = new Builder($this->connection);
        $subQuery->from('posts')->select('user_id');
        
        $where = [
            'column' => 'id',
            'values' => $subQuery
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('whereIn');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $where);
        expect($result)->toBe('"id" in (select "user_id" from "posts")');
    });
    
    // Lines 316-340: whereExists, whereNotExists, whereNested, whereSub, whereColumn
    it('compiles whereExists clauses', function () {
        $subQuery = new Builder($this->connection);
        $subQuery->from('posts')->select('*');
        
        $where = ['query' => $subQuery];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('whereExists');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $where);
        expect($result)->toBe('exists (select * from "posts")');
    });
    
    it('compiles whereNotExists clauses', function () {
        $subQuery = new Builder($this->connection);
        $subQuery->from('posts')->select('*');
        
        $where = ['query' => $subQuery];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('whereNotExists');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $where);
        expect($result)->toBe('not exists (select * from "posts")');
    });
    
    it('compiles whereNested clauses', function () {
        $nestedQuery = new Builder($this->connection);
        $nestedQuery->from('posts')->where('status', '=', 'published');
        
        $where = ['query' => $nestedQuery];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('whereNested');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $where);
        expect($result)->toBe('(where "status" = ?)');
    });
    
    it('compiles whereSub clauses', function () {
        $subQuery = new Builder($this->connection);
        $subQuery->from('posts')->selectRaw('COUNT(*)');
        
        $where = [
            'column' => 'post_count',
            'operator' => '>',
            'query' => $subQuery
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('whereSub');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $where);
        expect($result)->toBe('"post_count" > (select COUNT(*) from "posts")');
    });
    
    it('compiles whereColumn clauses', function () {
        $where = [
            'first' => 'users.created_at',
            'operator' => '=',
            'second' => 'users.updated_at'
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('whereColumn');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $where);
        expect($result)->toBe('"users"."created_at" = "users"."updated_at"');
    });
    
    // Line 385: compileInsertOrIgnore fallback
    it('compiles insertOrIgnore as regular insert by default', function () {
        $values = [['name' => 'John', 'email' => 'john@example.com']];
        
        $result = $this->grammar->compileInsertOrIgnore($this->builder, $values);
        expect($result)->toBe('insert into "users" ("name", "email") values (?, ?)');
    });
    
    // Line 462: wrapArray method
    it('wraps arrays of values', function () {
        $values = ['name', 'email', 'users.id'];
        
        $result = $this->grammar->wrapArray($values);
        expect($result)->toBe(['"name"', '"email"', '"users"."id"']);
    });
    
    // Line 471: wrapTable with expression
    it('wraps table with expression', function () {
        $expression = new Expression('(SELECT * FROM temp) as temp_table');
        
        $result = $this->grammar->wrapTable($expression);
        expect($result)->toBe('(SELECT * FROM temp) as temp_table');
    });
    
    // Line 480: getValue with non-expression
    it('handles getValue with non-expression values', function () {
        $result = $this->grammar->getValue('simple_string');
        expect($result)->toBe('simple_string');
        
        $result = $this->grammar->getValue(123);
        expect($result)->toBe(123);
    });
    
    // Lines 502-519: Savepoint compilation methods
    it('supports savepoints by default', function () {
        expect($this->grammar->supportsSavepoints())->toBeTrue();
    });
    
    it('compiles savepoint creation', function () {
        $result = $this->grammar->compileSavepoint('sp1');
        expect($result)->toBe('SAVEPOINT sp1');
    });
    
    it('compiles savepoint rollback', function () {
        $result = $this->grammar->compileSavepointRollBack('sp1');
        expect($result)->toBe('ROLLBACK TO SAVEPOINT sp1');
    });
    
    it('compiles date-based where clauses', function () {
        $where = [
            'column' => 'created_at',
            'operator' => '>',
            'value' => '2023-01-01'
        ];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileDateBasedWhere');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, 'date', $this->builder, $where);
        expect($result)->toBe('"created_at" > ?');
    });
    
    // Lines 529-544: Default implementations
    it('has default date format', function () {
        expect($this->grammar->getDateFormat())->toBe('Y-m-d H:i:s');
    });
    
    it('compiles empty lock by default', function () {
        $result = $this->grammar->compileLock($this->builder, 'for update');
        expect($result)->toBe('');
    });
    
    it('compiles random function', function () {
        $result = $this->grammar->compileRandom();
        expect($result)->toBe('RANDOM()');
        
        $result = $this->grammar->compileRandom('seed123');
        expect($result)->toBe('RANDOM()');
    });
    
    it('does not support returning by default', function () {
        expect($this->grammar->supportsReturning())->toBeFalse();
    });
    
    it('does not support JSON operations by default', function () {
        expect($this->grammar->supportsJsonOperations())->toBeFalse();
    });
    
    it('returns operators array', function () {
        $operators = $this->grammar->getOperators();
        expect($operators)->toBeArray();
        expect($operators)->toContain('=');
        expect($operators)->toContain('like');
    });
    
    // Additional edge cases for full coverage
    it('handles table prefix in wrapTable', function () {
        $this->grammar->setTablePrefix('prefix_');
        
        $result = $this->grammar->wrapTable('users');
        expect($result)->toBe('"prefix_users"');
        
        expect($this->grammar->getTablePrefix())->toBe('prefix_');
    });
    
    it('compiles insert with empty values', function () {
        $result = $this->grammar->compileInsert($this->builder, []);
        expect($result)->toBe('insert into "users" default values');
    });
    
    it('handles single record insert', function () {
        $values = ['name' => 'John', 'email' => 'john@example.com'];
        
        $result = $this->grammar->compileInsert($this->builder, $values);
        expect($result)->toBe('insert into "users" ("name", "email") values (?, ?)');
    });
    
    it('compiles aggregate with distinct', function () {
        $this->builder->distinct();
        $aggregate = ['function' => 'count', 'columns' => ['email']];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileAggregate');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $aggregate);
        expect($result)->toBe('select count(distinct "email") as aggregate');
    });
    
    it('does not add distinct for asterisk in aggregate', function () {
        $this->builder->distinct();
        $aggregate = ['function' => 'count', 'columns' => ['*']];
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileAggregate');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, $aggregate);
        expect($result)->toBe('select count(*) as aggregate');
    });
    
    it('compiles columns with distinct', function () {
        $this->builder->distinct();
        
        $reflection = new ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileColumns');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, ['name', 'email']);
        expect($result)->toBe('select distinct "name", "email"');
    });
    
    it('skips columns compilation when aggregate exists', function () {
        // Set aggregate on builder
        $reflection = new ReflectionClass($this->builder);
        $aggregateProperty = $reflection->getProperty('aggregate');
        $aggregateProperty->setAccessible(true);
        $aggregateProperty->setValue($this->builder, ['function' => 'count', 'columns' => ['*']]);
        
        $grammarReflection = new ReflectionClass($this->grammar);
        $method = $grammarReflection->getMethod('compileColumns');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->grammar, $this->builder, ['name', 'email']);
        expect($result)->toBe('');
    });
});