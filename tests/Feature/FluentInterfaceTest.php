<?php

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Query\Builder;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            age INTEGER,
            active INTEGER DEFAULT 1
        )
    ');

    $this->builder = $this->connection->table('users');
});

it('supports method chaining', function () {
    $result = $this->builder
        ->select('name', 'email')
        ->where('age', '>', 18)
        ->where('active', 1)
        ->orderBy('name')
        ->limit(10);

    expect($result)->toBeInstanceOf(Builder::class);
    expect($result)->toBe($this->builder);
});

it('builds complex queries fluently', function () {
    $sql = $this->builder
        ->select('users.name', 'users.email')
        ->where('age', '>=', 18)
        ->where('active', 1)
        ->whereIn('email', ['john@example.com', 'jane@example.com'])
        ->orWhere('age', '<', 16)
        ->orderBy('name', 'desc')
        ->limit(10)
        ->offset(5)
        ->toSql();

    expect($sql)->toContain('select');
    expect($sql)->toContain('where');
    expect($sql)->toContain('order by');
    expect($sql)->toContain('limit');
});

it('can clone queries for variations', function () {
    $baseQuery = $this->builder
        ->select('*')
        ->where('active', 1);

    $adminQuery = $baseQuery->clone()->where('role', 'admin');
    $userQuery = $baseQuery->clone()->where('role', 'user');

    expect($adminQuery)->not->toBe($baseQuery);
    expect($userQuery)->not->toBe($baseQuery);
    expect($adminQuery)->not->toBe($userQuery);

    expect($adminQuery->toSql())->toContain('role');
    expect($userQuery->toSql())->toContain('role');
    expect($baseQuery->toSql())->not->toContain('role');
});

it('resets properly for new queries', function () {
    $this->builder
        ->select('name')
        ->where('age', '>', 18)
        ->orderBy('name');

    $sql1 = $this->builder->toSql();

    // New query on same builder
    $this->builder = $this->connection->table('users');
    $this->builder
        ->select('email')
        ->where('active', 1);

    $sql2 = $this->builder->toSql();

    expect($sql1)->not->toBe($sql2);
    expect($sql2)->not->toContain('age');
    expect($sql2)->not->toContain('name');
});

it('handles subqueries fluently', function () {
    $subquery = $this->connection->table('users')
        ->select('id')
        ->where('age', '>', 30);

    $results = $this->builder
        ->whereIn('id', $subquery)
        ->get();

    expect($results)->toBeArray();
});

it('supports raw expressions in fluent interface', function () {
    $sql = $this->builder
        ->select($this->connection->raw('COUNT(*) as total'))
        ->where('created_at', '>', $this->connection->raw("datetime('now', '-1 day')"))
        ->toSql();

    expect($sql)->toContain('COUNT(*) as total');
    expect($sql)->toContain("datetime('now', '-1 day')");
});

it('handles null values fluently', function () {
    $this->builder
        ->whereNull('deleted_at')
        ->whereNotNull('email')
        ->orWhereNull('phone');

    $sql = $this->builder->toSql();

    expect($sql)->toContain('is null');
    expect($sql)->toContain('is not null');
});

it('supports all comparison operators', function () {
    $operators = ['=', '!=', '<>', '<', '<=', '>', '>=', 'like', 'not like'];

    foreach ($operators as $operator) {
        $builder = $this->connection->table('users');
        $sql = $builder->where('age', $operator, 25)->toSql();
        expect($sql)->toContain('where');
    }
});

it('chains aggregate functions', function () {
    $this->builder->insert([
        ['name' => 'John', 'age' => 30],
        ['name' => 'Jane', 'age' => 25],
        ['name' => 'Bob', 'age' => 35],
    ]);

    $count = $this->builder->where('age', '>', 25)->count();
    $sum = $this->builder->where('age', '>', 25)->sum('age');
    $avg = $this->builder->where('age', '>', 25)->avg('age');

    expect($count)->toBe(2);
    expect($sum)->toBe(65);
    expect($avg)->toBe(32.5);
});
