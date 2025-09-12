<?php

use Bob\Query\Grammars\PostgreSQLGrammar;
use Bob\Query\Builder;
use Bob\Database\Connection;
use Bob\Database\Expression;

beforeEach(function () {
    $this->grammar = new PostgreSQLGrammar();
    $this->connection = Mockery::mock(Connection::class);
    $this->processor = Mockery::mock(Bob\Query\Processor::class);
    
    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);
    
    $this->builder = new Builder($this->connection);
});

it('compiles insert get id with returning clause', function () {
    $sql = $this->grammar->compileInsertGetId($this->builder->from('users'), [
        'name' => 'John',
        'email' => 'john@example.com'
    ]);
    
    expect($sql)->toBe('insert into "users" ("name", "email") values (?, ?) returning id');
    
    $sql = $this->grammar->compileInsertGetId($this->builder->from('users'), [
        'name' => 'John',
        'email' => 'john@example.com'
    ], 'user_id');
    
    expect($sql)->toBe('insert into "users" ("name", "email") values (?, ?) returning user_id');
});

it('compiles insert or ignore with on conflict', function () {
    $sql = $this->grammar->compileInsertOrIgnore($this->builder->from('users'), [
        'name' => 'John',
        'email' => 'john@example.com'
    ]);
    
    expect($sql)->toBe('insert into "users" ("name", "email") values (?, ?) on conflict do nothing');
});

it('compiles upsert queries', function () {
    $sql = $this->grammar->compileUpsert(
        $this->builder->from('users'),
        [
            ['email' => 'john@example.com', 'name' => 'John', 'votes' => 1],
            ['email' => 'jane@example.com', 'name' => 'Jane', 'votes' => 2],
        ],
        ['email'],
        ['name', 'votes']
    );
    
    expect($sql)->toBe('insert into "users" ("email", "name", "votes") values (?, ?, ?), (?, ?, ?) on conflict ("email") do update set "name" = excluded."name", "votes" = excluded."votes"');
});

it('compiles lock for update', function () {
    $sql = $this->grammar->compileLock($this->builder, true);
    expect($sql)->toBe(' for update');
    
    $sql = $this->grammar->compileLock($this->builder, false);
    expect($sql)->toBe(' for share');
    
    $sql = $this->grammar->compileLock($this->builder, 'for update nowait');
    expect($sql)->toBe('for update nowait');
});

it('compiles date based where clauses', function () {
    $this->builder->from('users');
    
    $method = new ReflectionMethod($this->grammar, 'compileDateBasedWhere');
    $method->setAccessible(true);
    
    // Day
    $where = ['column' => 'created_at', 'operator' => '=', 'value' => 15, 'type' => 'Day'];
    $sql = $method->invoke($this->grammar, 'Day', $this->builder, $where);
    expect($sql)->toBe('extract(day from "created_at") = ?');
    
    // Month
    $where['value'] = 7;
    $sql = $method->invoke($this->grammar, 'Month', $this->builder, $where);
    expect($sql)->toBe('extract(month from "created_at") = ?');
    
    // Year
    $where['value'] = 2024;
    $sql = $method->invoke($this->grammar, 'Year', $this->builder, $where);
    expect($sql)->toBe('extract(year from "created_at") = ?');
    
    // Date
    $where['value'] = '2024-01-15';
    $sql = $method->invoke($this->grammar, 'Date', $this->builder, $where);
    expect($sql)->toBe('"created_at"::date = ?');
    
    // Time
    $where['value'] = '14:30:00';
    $sql = $method->invoke($this->grammar, 'Time', $this->builder, $where);
    expect($sql)->toBe('"created_at"::time = ?');
});

it('compiles json contains', function () {
    $sql = $this->grammar->compileJsonContains('data->tags', '["php", "laravel"]');
    expect($sql)->toBe('"data"->\'tags\' @> ["php", "laravel"]');
    
    $sql = $this->grammar->compileJsonContains('settings', '{"dark_mode": true}');
    expect($sql)->toBe('"settings" @> {"dark_mode": true}');
});

it('compiles json contains key', function () {
    $sql = $this->grammar->compileJsonContainsKey('data->user->email');
    expect($sql)->toBe('"data"->\'user\'->\'email\' is not null');
    
    $sql = $this->grammar->compileJsonContainsKey('settings');
    expect($sql)->toBe('"settings" is not null');
});

it('compiles json length', function () {
    $method = new ReflectionMethod($this->grammar, 'compileJsonLength');
    $method->setAccessible(true);
    
    $sql = $method->invoke($this->grammar, 'data->items', '>', '5');
    expect($sql)->toBe('jsonb_array_length("data"->\'items\') > 5');
    
    $sql = $method->invoke($this->grammar, 'tags', '=', '3');
    expect($sql)->toBe('jsonb_array_length("tags") = 3');
});

it('compiles random function', function () {
    $sql = $this->grammar->compileRandom();
    expect($sql)->toBe('random()');
    
    $sql = $this->grammar->compileRandom('123');
    expect($sql)->toBe('random()');
});

it('compiles truncate with restart identity cascade', function () {
    $sql = $this->grammar->compileTruncate($this->builder->from('users'));
    expect($sql)->toBe(['truncate "users" restart identity cascade' => []]);
});

it('supports returning clause', function () {
    expect($this->grammar->supportsReturning())->toBeTrue();
});

it('supports json operations', function () {
    expect($this->grammar->supportsJsonOperations())->toBeTrue();
});

it('has PostgreSQL specific operators', function () {
    $operators = $this->grammar->getOperators();
    
    expect($operators)->toContain('ilike');
    expect($operators)->toContain('not ilike');
    expect($operators)->toContain('~');
    expect($operators)->toContain('@>');
    expect($operators)->toContain('?');
    expect($operators)->toContain('is distinct from');
});

it('wraps json field and path correctly', function () {
    $method = new ReflectionMethod($this->grammar, 'wrapJsonFieldAndPath');
    $method->setAccessible(true);
    
    [$field, $path] = $method->invoke($this->grammar, 'data->user->name');
    expect($field)->toBe('"data"');
    expect($path)->toBe("->\'user\'->\'name\'");
    
    [$field, $path] = $method->invoke($this->grammar, 'settings');
    expect($field)->toBe('"settings"');
    expect($path)->toBe('');
});

it('compiles complex json queries', function () {
    $this->builder->from('users')
        ->whereRaw('data @> ?', ['{"active": true}'])
        ->whereRaw('tags ? ?', ['php']);
    
    $sql = $this->grammar->compileSelect($this->builder);
    expect($sql)->toContain('where data @> ?');
});

it('handles array operators', function () {
    $operators = $this->grammar->getOperators();
    
    expect($operators)->toContain('&&'); // Array overlap
    expect($operators)->toContain('@>'); // Array contains
    expect($operators)->toContain('<@'); // Array is contained by
});

it('compiles update with conflict resolution', function () {
    $values = [
        ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
    ];
    
    $sql = $this->grammar->compileUpsert(
        $this->builder->from('users'),
        $values,
        ['id'],
        ['name', 'email']
    );
    
    expect($sql)->toContain('on conflict ("id") do update set');
    expect($sql)->toContain('"name" = excluded."name"');
    expect($sql)->toContain('"email" = excluded."email"');
});