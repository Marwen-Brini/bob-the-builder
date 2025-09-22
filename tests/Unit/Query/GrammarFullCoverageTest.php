<?php

use Bob\Query\Grammar;
use Bob\Query\Builder;
use Bob\Contracts\BuilderInterface;
use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Query\Processor;
use Mockery as m;

// Create a concrete Grammar implementation for testing protected methods
class ConcreteGrammar extends Grammar {
    public function testWhereRaw(BuilderInterface $query, array $where): string {
        return $this->whereRaw($query, $where);
    }

    public function testWhereExists(BuilderInterface $query, array $where): string {
        return $this->whereExists($query, $where);
    }

    public function testWhereNotExists(BuilderInterface $query, array $where): string {
        return $this->whereNotExists($query, $where);
    }

    public function testWhereNested(BuilderInterface $query, array $where): string {
        return $this->whereNested($query, $where);
    }

    public function testWhereColumn(BuilderInterface $query, array $where): string {
        return $this->whereColumn($query, $where);
    }

    public function testWhereSub(BuilderInterface $query, array $where): string {
        return $this->whereSub($query, $where);
    }

    public function testWhereInSub(BuilderInterface $query, array $where): string {
        return $this->whereInSub($query, $where);
    }

    public function testWhereNotInSub(BuilderInterface $query, array $where): string {
        return $this->whereNotInSub($query, $where);
    }

    public function testWhereNotIn(BuilderInterface $query, array $where): string {
        return $this->whereNotIn($query, $where);
    }

    public function testCompileAggregate(BuilderInterface $query, array $aggregate): string {
        return $this->compileAggregate($query, $aggregate);
    }

    public function testCompileColumns(BuilderInterface $query, array $columns): string {
        return $this->compileColumns($query, $columns);
    }

    public function testCompileJoins(BuilderInterface $query, array $joins): string {
        return $this->compileJoins($query, $joins);
    }

    public function testCompileJoinConstraint(array $where): string {
        return $this->compileJoinConstraint($where);
    }

    public function testCompileHaving(array $having): string {
        return $this->compileHaving($having);
    }

    public function testCompileOrders(BuilderInterface $query, array $orders): string {
        return $this->compileOrders($query, $orders);
    }

    public function testCompileUnions(BuilderInterface $query): string {
        return $this->compileUnions($query);
    }

    public function testCompileUnion(array $union): string {
        return $this->compileUnion($union);
    }

    public function testWhereBetween(BuilderInterface $query, array $where): string {
        return $this->whereBetween($query, $where);
    }
}

beforeEach(function () {
    $this->connection = m::mock(Connection::class);
    $this->processor = m::mock(Processor::class);
    $this->grammar = new ConcreteGrammar();

    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new Builder($this->connection, $this->grammar, $this->processor);
});

afterEach(function () {
    m::close();
});

// Test line 80 - distinct with non-* column in aggregate
test('Grammar compileAggregate with distinct', function () {
    $query = m::mock(BuilderInterface::class);
    $query->shouldReceive('getDistinct')->andReturn(true);

    $aggregate = [
        'function' => 'count',
        'columns' => ['email']
    ];

    $result = $this->grammar->testCompileAggregate($query, $aggregate);

    expect($result)->toBe('select count(distinct "email") as aggregate');
});

// Test line 89 - compileColumns with aggregate returns empty
test('Grammar compileColumns with aggregate returns empty', function () {
    $query = m::mock(BuilderInterface::class);
    $query->shouldReceive('getAggregate')->andReturn(['function' => 'count']);

    $result = $this->grammar->testCompileColumns($query, ['*']);

    expect($result)->toBe('');
});

// Test lines 104-123 - compileJoins with multiple joins
test('Grammar compileJoins with multiple joins', function () {
    $join1 = new stdClass();
    $join1->table = 'posts';
    $join1->type = 'inner';
    $join1->wheres = [
        ['type' => 'Column', 'first' => 'users.id', 'operator' => '=', 'second' => 'posts.user_id', 'boolean' => 'and']
    ];

    $join2 = new stdClass();
    $join2->table = 'comments';
    $join2->type = 'left';
    $join2->wheres = [
        ['type' => 'Column', 'first' => 'posts.id', 'operator' => '=', 'second' => 'comments.post_id', 'boolean' => 'and']
    ];

    $result = $this->grammar->testCompileJoins($this->builder, [$join1, $join2]);

    expect($result)->toContain('inner join "posts"');
    expect($result)->toContain('left join "comments"');
});

// Test line 130 - join constraint with boolean
test('Grammar compileJoinConstraint with boolean', function () {
    $where = [
        'type' => 'Column',
        'first' => 'users.id',
        'operator' => '=',
        'second' => 'posts.user_id',
        'boolean' => 'or',
        'previous' => true
    ];

    $result = $this->grammar->testCompileJoinConstraint($where);

    expect($result)->toBe('or "users"."id" = "posts"."user_id"');
});

// Test line 136 - join constraint returns raw sql for non-Column type
test('Grammar compileJoinConstraint with Raw type', function () {
    $where = [
        'type' => 'Raw',
        'sql' => 'users.active = 1',
        'boolean' => 'and'
    ];

    $result = $this->grammar->testCompileJoinConstraint($where);

    // For non-Column types, it returns the boolean and sql
    expect($result)->toBe('and users.active = 1');
});

// Test line 171 - compileHaving with Basic type
test('Grammar compileHaving with Basic type', function () {
    $having = [
        'type' => 'Basic',
        'column' => 'count(*)',
        'operator' => '>',
        'value' => 5,
        'boolean' => 'and'
    ];

    $result = $this->grammar->testCompileHaving($having);

    expect($result)->toBe('and "count(*)" > ?');
});

// Test line 178 - compileHaving with Raw type
test('Grammar compileHaving with Raw type', function () {
    $having = [
        'type' => 'Raw',
        'sql' => 'sum(amount) > 1000',
        'boolean' => 'and'
    ];

    $result = $this->grammar->testCompileHaving($having);

    expect($result)->toBe('and sum(amount) > 1000');
});

// Test lines 171 and 178 - empty orders and raw order type
test('Grammar compileOrders with empty orders', function () {
    $result = $this->grammar->testCompileOrders($this->builder, []);

    expect($result)->toBe('');
});

test('Grammar compileOrders with Raw type', function () {
    $orders = [
        ['type' => 'Raw', 'sql' => 'FIELD(status, "active", "pending", "inactive")']
    ];

    $result = $this->grammar->testCompileOrders($this->builder, $orders);

    expect($result)->toBe('order by FIELD(status, "active", "pending", "inactive")');
});

// Test lines 199-217 - compileUnions with orders, limit and offset
test('Grammar compileUnions with union orders', function () {
    $unionQuery = m::mock(BuilderInterface::class);
    $unionQuery->shouldReceive('toSql')->andReturn('select * from posts');

    $query = m::mock(BuilderInterface::class);
    $query->shouldReceive('getUnions')->andReturn([['query' => $unionQuery, 'all' => false]]);
    $query->shouldReceive('getUnionOrders')->andReturn([['column' => 'created_at', 'direction' => 'desc']]);
    $query->shouldReceive('getUnionLimit')->andReturn(10);
    $query->shouldReceive('getUnionOffset')->andReturn(5);

    $result = $this->grammar->testCompileUnions($query);

    expect($result)->toContain('union');
    expect($result)->toContain('order by');
    expect($result)->toContain('limit 10');
    expect($result)->toContain('offset 5');
});

// Test line 224 - compileUnion all flag
test('Grammar compileUnion with union all', function () {
    $unionQuery = m::mock(BuilderInterface::class);
    $unionQuery->shouldReceive('toSql')->andReturn('select * from posts');

    $result = $this->grammar->testCompileUnion(['query' => $unionQuery, 'all' => true]);

    expect($result)->toBe(' union all (select * from posts)');
});

// Test line 257-265 - whereIn with different scenarios
test('Grammar whereIn with subquery', function () {
    $subQuery = m::mock(BuilderInterface::class);

    $where = [
        'column' => 'user_id',
        'values' => $subQuery
    ];

    // Mock the compileSelect method
    $grammar = m::mock(ConcreteGrammar::class)->makePartial();
    $grammar->shouldReceive('compileSelect')->with($subQuery)->andReturn('select id from users');
    $grammar->shouldReceive('wrap')->with('user_id')->andReturn('"user_id"');

    $reflection = new ReflectionClass($grammar);
    $method = $reflection->getMethod('whereIn');
    $method->setAccessible(true);

    $result = $method->invoke($grammar, $this->builder, $where);

    expect($result)->toBe('"user_id" in (select id from users)');
});

test('Grammar whereIn with empty values returns false condition', function () {
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

// Test lines 268-276 - whereInSub and whereNotInSub
test('Grammar whereInSub', function () {
    $subQuery = m::mock(BuilderInterface::class);

    $where = [
        'column' => 'user_id',
        'query' => $subQuery
    ];

    // Mock the compileSelect method
    $grammar = m::mock(ConcreteGrammar::class)->makePartial();
    $grammar->shouldReceive('compileSelect')->with($subQuery)->andReturn('select id from users');
    $grammar->shouldReceive('wrap')->with('user_id')->andReturn('"user_id"');

    $result = $grammar->testWhereInSub($this->builder, $where);

    expect($result)->toBe('"user_id" in (select id from users)');
});

test('Grammar whereNotInSub', function () {
    $subQuery = m::mock(BuilderInterface::class);

    $where = [
        'column' => 'user_id',
        'query' => $subQuery
    ];

    // Mock the compileSelect method
    $grammar = m::mock(ConcreteGrammar::class)->makePartial();
    $grammar->shouldReceive('compileSelect')->with($subQuery)->andReturn('select id from users');
    $grammar->shouldReceive('wrap')->with('user_id')->andReturn('"user_id"');

    $result = $grammar->testWhereNotInSub($this->builder, $where);

    expect($result)->toBe('"user_id" not in (select id from users)');
});

// Test lines 280-284 - whereNotIn
test('Grammar whereNotIn with values', function () {
    $where = [
        'column' => 'status',
        'values' => ['pending', 'cancelled']
    ];

    $result = $this->grammar->testWhereNotIn($this->builder, $where);

    expect($result)->toBe('"status" not in (?, ?)');
});

test('Grammar whereNotIn with empty values returns true condition', function () {
    $where = [
        'column' => 'id',
        'values' => []
    ];

    $result = $this->grammar->testWhereNotIn($this->builder, $where);

    expect($result)->toBe('1 = 1');
});

// Test line 311-313 - whereRaw
test('Grammar whereRaw returns raw SQL', function () {
    $where = [
        'sql' => 'YEAR(created_at) = 2023',
        'boolean' => 'and'
    ];

    $result = $this->grammar->testWhereRaw($this->builder, $where);

    expect($result)->toBe('YEAR(created_at) = 2023');
});

// Test lines 314-322 - whereExists and whereNotExists
test('Grammar whereExists', function () {
    $subQuery = m::mock(BuilderInterface::class);

    $where = [
        'query' => $subQuery
    ];

    // Mock the compileSelect method
    $grammar = m::mock(ConcreteGrammar::class)->makePartial();
    $grammar->shouldReceive('compileSelect')->with($subQuery)->andReturn('select * from posts where user_id = users.id');

    $result = $grammar->testWhereExists($this->builder, $where);

    expect($result)->toBe('exists (select * from posts where user_id = users.id)');
});

test('Grammar whereNotExists', function () {
    $subQuery = m::mock(BuilderInterface::class);

    $where = [
        'query' => $subQuery
    ];

    // Mock the compileSelect method
    $grammar = m::mock(ConcreteGrammar::class)->makePartial();
    $grammar->shouldReceive('compileSelect')->with($subQuery)->andReturn('select * from posts where user_id = users.id');

    $result = $grammar->testWhereNotExists($this->builder, $where);

    expect($result)->toBe('not exists (select * from posts where user_id = users.id)');
});

// Test lines 324-329 - whereNested
test('Grammar whereNested', function () {
    $nestedQuery = m::mock(BuilderInterface::class);
    $nestedQuery->shouldReceive('getWheres')->andReturn([
        ['type' => 'Basic', 'column' => 'age', 'operator' => '>', 'value' => 18, 'boolean' => 'and']
    ]);

    $where = [
        'query' => $nestedQuery
    ];

    // Mock the compileWheres method using shouldAllowMockingProtectedMethods
    $grammar = m::mock(ConcreteGrammar::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $grammar->shouldReceive('compileWheres')->with($nestedQuery)->andReturn('"age" > ?');

    $result = $grammar->testWhereNested($this->builder, $where);

    expect($result)->toBe('("age" > ?)');
});

// Test lines 331-333 - whereColumn
test('Grammar whereColumn', function () {
    $where = [
        'first' => 'created_at',
        'operator' => '<',
        'second' => 'updated_at'
    ];

    $result = $this->grammar->testWhereColumn($this->builder, $where);

    expect($result)->toBe('"created_at" < "updated_at"');
});

// Test lines 390-392 - whereSub
test('Grammar whereSub', function () {
    $subQuery = m::mock(BuilderInterface::class);

    $where = [
        'column' => 'total',
        'operator' => '>',
        'query' => $subQuery
    ];

    // Mock the compileSelect method
    $grammar = m::mock(ConcreteGrammar::class)->makePartial();
    $grammar->shouldReceive('compileSelect')->with($subQuery)->andReturn('select count(*) from orders');
    $grammar->shouldReceive('wrap')->with('total')->andReturn('"total"');

    $result = $grammar->testWhereSub($this->builder, $where);

    expect($result)->toBe('"total" > (select count(*) from orders)');
});

// Test lines 299-301 - whereBetween with not flag
test('Grammar whereBetween with not flag', function () {
    $where = [
        'column' => 'age',
        'not' => true,
        'values' => [18, 65]
    ];

    $result = $this->grammar->testWhereBetween($this->builder, $where);

    expect($result)->toBe('"age" not between ? and ?');
});

// Test lines 477, 514, 523 - these are in MySQL/PostgreSQL grammars, not base Grammar
test('Grammar compileLock returns empty for base Grammar', function () {
    $result = $this->grammar->compileLock($this->builder, true);
    expect($result)->toBe('');

    $result = $this->grammar->compileLock($this->builder, 'for update');
    expect($result)->toBe('');
});

// Test lines 554 - wrapTable with alias
test('Grammar wrapTable with alias', function () {
    $wrapped = $this->grammar->wrapTable('users as u');

    expect($wrapped)->toBe('"users" as "u"');
});

test('Grammar wrapTable with Expression', function () {
    $expression = new Expression('(select * from users) as u');
    $wrapped = $this->grammar->wrapTable($expression);

    expect($wrapped)->toBe('(select * from users) as u');
});

// Test line 578 - getDateFormat returns default format
test('Grammar getDateFormat returns default format', function () {
    $format = $this->grammar->getDateFormat();

    expect($format)->toBe('Y-m-d H:i:s');
});

// Test line 232 - compileWheres with empty wheres returns empty string
test('Grammar compileWheres with empty wheres', function () {
    $query = m::mock(BuilderInterface::class);
    $query->wheres = []; // Public property in Builder

    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('compileWheres');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, $query);

    expect($result)->toBe('');
});

// Test line 479 - wrap with Expression
test('Grammar wrap with Expression calls getValue', function () {
    $expression = new Expression('CURRENT_TIMESTAMP');

    $result = $this->grammar->wrap($expression);

    expect($result)->toBe('CURRENT_TIMESTAMP');
});

// Test line 516 - wrapArray wraps each value
test('Grammar wrapArray wraps multiple values', function () {
    $values = ['users', 'posts', 'comments'];

    $result = $this->grammar->wrapArray($values);

    expect($result)->toBe(['"users"', '"posts"', '"comments"']);
});