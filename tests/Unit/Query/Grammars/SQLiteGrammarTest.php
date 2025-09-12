<?php

use Bob\Query\Grammars\SQLiteGrammar;
use Bob\Query\Builder;
use Bob\Database\Connection;

beforeEach(function () {
    $this->grammar = new SQLiteGrammar();
    $this->connection = Mockery::mock(Connection::class);
    $this->processor = Mockery::mock(Bob\Query\Processor::class);
    
    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);
    
    $this->builder = new Builder($this->connection);
});

it('compiles insert or ignore', function () {
    $sql = $this->grammar->compileInsertOrIgnore($this->builder->from('users'), [
        'name' => 'John',
        'email' => 'john@example.com'
    ]);
    
    expect($sql)->toBe('insert or ignore into "users" ("name", "email") values (?, ?)');
});

it('compiles truncate with delete statements', function () {
    $sql = $this->grammar->compileTruncate($this->builder->from('users'));
    
    expect($sql)->toBe([
        'delete from sqlite_sequence where name = ?' => ['users'],
        'delete from "users"' => [],
    ]);
});

it('returns empty string for lock clauses', function () {
    $sql = $this->grammar->compileLock($this->builder, true);
    expect($sql)->toBe('');
    
    $sql = $this->grammar->compileLock($this->builder, false);
    expect($sql)->toBe('');
    
    $sql = $this->grammar->compileLock($this->builder, 'any lock string');
    expect($sql)->toBe('');
});

it('wraps union queries', function () {
    $method = new ReflectionMethod($this->grammar, 'wrapUnion');
    $method->setAccessible(true);
    
    $sql = $method->invoke($this->grammar, 'select * from users');
    expect($sql)->toBe('select * from (select * from users)');
});

it('compiles upsert as insert or replace', function () {
    $sql = $this->grammar->compileUpsert(
        $this->builder->from('users'),
        [
            ['email' => 'john@example.com', 'name' => 'John', 'votes' => 1],
            ['email' => 'jane@example.com', 'name' => 'Jane', 'votes' => 2],
        ],
        ['email'],
        ['name', 'votes']
    );
    
    expect($sql)->toBe('insert or replace into "users" ("email", "name", "votes") values (?, ?, ?), (?, ?, ?)');
});

it('does not support savepoints', function () {
    expect($this->grammar->supportsSavepoints())->toBeFalse();
});

it('compiles date based where clauses', function () {
    $this->builder->from('users');
    
    $method = new ReflectionMethod($this->grammar, 'compileDateBasedWhere');
    $method->setAccessible(true);
    
    // Day
    $where = ['column' => 'created_at', 'operator' => '=', 'value' => 15, 'type' => 'Day'];
    $sql = $method->invoke($this->grammar, 'Day', $this->builder, $where);
    expect($sql)->toBe("strftime('%d', \"created_at\") = cast(? as text)");
    
    // Month
    $where['value'] = 7;
    $sql = $method->invoke($this->grammar, 'Month', $this->builder, $where);
    expect($sql)->toBe("strftime('%m', \"created_at\") = cast(? as text)");
    
    // Year
    $where['value'] = 2024;
    $sql = $method->invoke($this->grammar, 'Year', $this->builder, $where);
    expect($sql)->toBe("strftime('%Y', \"created_at\") = cast(? as text)");
    
    // Date
    $where['value'] = '2024-01-15';
    $sql = $method->invoke($this->grammar, 'Date', $this->builder, $where);
    expect($sql)->toBe('date("created_at") = ?');
    
    // Time
    $where['value'] = '14:30:00';
    $sql = $method->invoke($this->grammar, 'Time', $this->builder, $where);
    expect($sql)->toBe('time("created_at") = ?');
});

it('compiles json length', function () {
    $method = new ReflectionMethod($this->grammar, 'compileJsonLength');
    $method->setAccessible(true);
    
    $sql = $method->invoke($this->grammar, 'data->items', '>', '5');
    expect($sql)->toBe('json_array_length("data", \'$.items\') > 5');
    
    $sql = $method->invoke($this->grammar, 'tags', '=', '3');
    expect($sql)->toBe('json_array_length("tags") = 3');
});

it('wraps json field and path correctly', function () {
    $method = new ReflectionMethod($this->grammar, 'wrapJsonFieldAndPath');
    $method->setAccessible(true);
    
    [$field, $path] = $method->invoke($this->grammar, 'data->user->name');
    expect($field)->toBe('"data"');
    expect($path)->toBe(", '$.user.name'");
    
    [$field, $path] = $method->invoke($this->grammar, 'settings');
    expect($field)->toBe('"settings"');
    expect($path)->toBe('');
});

it('wraps json path correctly', function () {
    $method = new ReflectionMethod($this->grammar, 'wrapJsonPath');
    $method->setAccessible(true);
    
    $path = $method->invoke($this->grammar, 'user->profile->avatar');
    expect($path)->toBe("'$.user.profile.avatar'");
    
    $path = $method->invoke($this->grammar, 'tags');
    expect($path)->toBe("'$.tags'");
});

it('compiles random function', function () {
    $sql = $this->grammar->compileRandom();
    expect($sql)->toBe('random()');
    
    $sql = $this->grammar->compileRandom('123');
    expect($sql)->toBe('abs(random() / 123)');
});

it('has SQLite specific operators', function () {
    $operators = $this->grammar->getOperators();
    
    expect($operators)->toContain('like');
    expect($operators)->toContain('not like');
    expect($operators)->toContain('ilike');
    expect($operators)->not->toContain('@>');
    expect($operators)->not->toContain('~');
});

it('handles glob pattern matching', function () {
    $operators = $this->grammar->getOperators();
    
    // SQLite doesn't have as many operators as PostgreSQL
    expect(count($operators))->toBeLessThan(20);
    expect($operators)->toContain('&');
    expect($operators)->toContain('|');
    expect($operators)->toContain('<<');
    expect($operators)->toContain('>>');
});

it('compiles complex queries with SQLite syntax', function () {
    $this->builder->from('users')
        ->select(['id', 'name'])
        ->where('created_at', '>', '2024-01-01')
        ->orderBy('name');
    
    $sql = $this->grammar->compileSelect($this->builder);
    expect($sql)->toBe('select "id", "name" from "users" where "created_at" > ? order by "name" asc');
});

it('handles multiple inserts correctly', function () {
    $values = [
        ['name' => 'John', 'email' => 'john@example.com'],
        ['name' => 'Jane', 'email' => 'jane@example.com'],
    ];
    
    $sql = $this->grammar->compileInsert($this->builder->from('users'), $values);
    expect($sql)->toBe('insert into "users" ("name", "email") values (?, ?), (?, ?)');
});

it('handles empty insert with default values', function () {
    $sql = $this->grammar->compileInsert($this->builder->from('users'), []);
    expect($sql)->toBe('insert into "users" default values');
});

it('compiles exists query', function () {
    $this->builder->from('users')->where('active', true);
    $sql = $this->grammar->compileExists($this->builder);
    
    expect($sql)->toContain('select exists(');
    expect($sql)->toContain('where "active" = ?');
});