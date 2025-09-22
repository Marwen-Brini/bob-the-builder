<?php

use Bob\Query\Builder;
use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Query\Grammar;
use Bob\Query\Processor;
use Mockery as m;

beforeEach(function () {
    $this->connection = m::mock(Connection::class);
    $this->grammar = m::mock(Grammar::class)->makePartial();
    $this->processor = m::mock(Processor::class);

    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new Builder($this->connection, $this->grammar, $this->processor);
});

afterEach(function () {
    m::close();
});

test('where with closure calls whereNested', function () {
    $builder = $this->builder->from('users');

    $builder->where(function ($query) {
        $query->where('name', 'John')
              ->orWhere('name', 'Jane');
    });

    $wheres = $builder->wheres;
    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['type'])->toBe('Nested');
});

test('where with invalid operator adjusts to equals', function () {
    $builder = $this->builder->from('users');

    // When operator is actually the value (2-param form)
    $builder->where('age', 25);

    $wheres = $builder->wheres;
    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['operator'])->toBe('=');
    expect($wheres[0]['value'])->toBe(25);
});

test('where with null value calls whereNull', function () {
    $builder = $this->builder->from('users');

    $builder->where('deleted_at', null);

    $wheres = $builder->wheres;
    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['type'])->toBe('Null');
});

test('where with not equals null calls whereNotNull', function () {
    $builder = $this->builder->from('users');

    $builder->where('deleted_at', '!=', null);

    $wheres = $builder->wheres;
    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['type'])->toBe('NotNull');
});

test('pluck returns array of values', function () {
    $this->processor->shouldReceive('processSelect')
        ->once()
        ->andReturn([
            (object) ['name' => 'John', 'email' => 'john@example.com'],
            (object) ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([
            (object) ['name' => 'John', 'email' => 'john@example.com'],
            (object) ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

    $builder = $this->builder->from('users');
    $names = $builder->pluck('name');

    expect($names)->toBe(['John', 'Jane']);
});

test('pluck with key returns associative array', function () {
    $this->processor->shouldReceive('processSelect')
        ->once()
        ->andReturn([
            (object) ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            (object) ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([
            (object) ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            (object) ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

    $builder = $this->builder->from('users');
    $names = $builder->pluck('name', 'email');

    expect($names)->toBe([
        'john@example.com' => 'John',
        'jane@example.com' => 'Jane',
    ]);
});

test('pluck handles arrays instead of objects', function () {
    $this->processor->shouldReceive('processSelect')
        ->once()
        ->andReturn([
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

    $builder = $this->builder->from('users');
    $names = $builder->pluck('name');

    expect($names)->toBe(['John', 'Jane']);
});

test('chunk processes results in batches', function () {
    $this->processor->shouldReceive('processSelect')->times(3)->andReturn(
        [
            (object) ['id' => 1, 'name' => 'User 1'],
            (object) ['id' => 2, 'name' => 'User 2'],
        ],
        [
            (object) ['id' => 3, 'name' => 'User 3'],
            (object) ['id' => 4, 'name' => 'User 4'],
        ],
        []
    );

    $this->connection->shouldReceive('select')->times(3)->andReturn(
        [
            (object) ['id' => 1, 'name' => 'User 1'],
            (object) ['id' => 2, 'name' => 'User 2'],
        ],
        [
            (object) ['id' => 3, 'name' => 'User 3'],
            (object) ['id' => 4, 'name' => 'User 4'],
        ],
        []
    );

    $builder = $this->builder->from('users');

    $chunks = [];
    $result = $builder->chunk(2, function ($users) use (&$chunks) {
        $chunks[] = count($users);
    });

    expect($result)->toBeTrue();
    expect($chunks)->toBe([2, 2]);
});

test('chunk stops when callback returns false', function () {
    $this->processor->shouldReceive('processSelect')->once()->andReturn([
        (object) ['id' => 1, 'name' => 'User 1'],
        (object) ['id' => 2, 'name' => 'User 2'],
    ]);

    $this->connection->shouldReceive('select')->once()->andReturn([
        (object) ['id' => 1, 'name' => 'User 1'],
        (object) ['id' => 2, 'name' => 'User 2'],
    ]);

    $builder = $this->builder->from('users');

    $processed = 0;
    $result = $builder->chunk(2, function ($users) use (&$processed) {
        $processed++;
        return false; // Stop chunking
    });

    expect($result)->toBeFalse();
    expect($processed)->toBe(1);
});

test('sum returns numeric result', function () {
    $this->grammar->shouldReceive('compileSelect')->once()->andReturn('select sum("amount") as aggregate from "orders"');
    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([(object) ['aggregate' => 500]]);

    $this->processor->shouldReceive('processSelect')
        ->once()
        ->andReturn([(object) ['aggregate' => 500]]);

    $builder = $this->builder->from('orders');
    $result = $builder->sum('amount');

    expect($result)->toBe(500);
});

test('reorder with multiple columns', function () {
    $builder = $this->builder->from('users');
    $builder->orderBy('name')->orderBy('email');

    expect($builder->orders)->toHaveCount(2);

    $builder->reorder('id', 'desc');

    expect($builder->orders)->toHaveCount(1);
    expect($builder->orders[0]['column'])->toBe('id');
    expect($builder->orders[0]['direction'])->toBe('desc');
});

test('getBindings returns bindings array', function () {
    $builder = $this->builder->from('users');
    $builder->where('name', 'John');

    $bindings = $builder->getBindings();

    expect($bindings)->toBeArray();
    // Check for where bindings
    if (isset($bindings['where'])) {
        expect($bindings['where'])->toBe(['John']);
    }
});

test('whereIn with array', function () {
    $builder = $this->builder->from('users');
    $builder->whereIn('id', [1, 2, 3]);

    $wheres = $builder->wheres;
    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['type'])->toBe('In');
    expect($wheres[0]['values'])->toBe([1, 2, 3]);
});

test('whereNotIn with array', function () {
    $builder = $this->builder->from('users');
    $builder->whereNotIn('id', [4, 5, 6]);

    $wheres = $builder->wheres;
    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['type'])->toBe('NotIn');
    expect($wheres[0]['values'])->toBe([4, 5, 6]);
});

test('whereBetween adds between condition', function () {
    $builder = $this->builder->from('users');
    $builder->whereBetween('age', [18, 65]);

    $wheres = $builder->wheres;
    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['type'])->toBe('Between');
    expect($wheres[0]['values'])->toBe([18, 65]);
});

test('join with closure', function () {
    $builder = $this->builder->from('users');

    $builder->join('posts', function ($join) {
        $join->on('users.id', '=', 'posts.user_id')
             ->where('posts.published', '=', true);
    });

    $joins = $builder->joins;
    expect($joins)->toHaveCount(1);
    expect($joins[0]->type)->toBe('inner');
    expect($joins[0]->table)->toBe('posts');
});

test('cursor method returns generator', function () {
    $this->grammar->shouldReceive('compileSelect')->once()->andReturn('select * from "users"');

    $this->processor->shouldReceive('processSelect')
        ->once()
        ->andReturn([
            (object) ['id' => 1, 'name' => 'User 1'],
            (object) ['id' => 2, 'name' => 'User 2'],
        ]);

    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([
            (object) ['id' => 1, 'name' => 'User 1'],
            (object) ['id' => 2, 'name' => 'User 2'],
        ]);

    $builder = $this->builder->from('users');
    $cursor = $builder->cursor();

    $results = [];
    foreach ($cursor as $user) {
        $results[] = $user->name;
    }

    expect($results)->toBe(['User 1', 'User 2']);
});

test('increment updates and returns affected rows', function () {
    $this->grammar->shouldReceive('compileUpdate')->once()->andReturn('update "users" set "votes" = "votes" + ?');
    $this->connection->shouldReceive('raw')->once()->andReturn(new Expression('"votes" + ?'));
    $this->connection->shouldReceive('update')
        ->once()
        ->andReturn(5);

    $builder = $this->builder->from('users');
    $result = $builder->increment('votes');

    expect($result)->toBe(5);
});

test('decrement updates and returns affected rows', function () {
    $this->grammar->shouldReceive('compileUpdate')->once()->andReturn('update "users" set "votes" = "votes" - ?');
    $this->connection->shouldReceive('raw')->once()->andReturn(new Expression('"votes" - ?'));
    $this->connection->shouldReceive('update')
        ->once()
        ->andReturn(3);

    $builder = $this->builder->from('users');
    $result = $builder->decrement('votes');

    expect($result)->toBe(3);
});

test('raw creates expression', function () {
    $this->connection->shouldReceive('raw')
        ->once()
        ->with('count(*)')
        ->andReturn(new Expression('count(*)'));

    $builder = $this->builder->from('users');
    $expression = $builder->raw('count(*)');

    expect($expression)->toBeInstanceOf(Expression::class);
    expect($expression->getValue())->toBe('count(*)');
});

test('newQuery creates new builder instance', function () {
    $builder = $this->builder->from('users');
    $newBuilder = $builder->newQuery();

    expect($newBuilder)->toBeInstanceOf(Builder::class);
    expect($newBuilder)->not->toBe($builder);
    expect($newBuilder->from)->toBeNull();
});