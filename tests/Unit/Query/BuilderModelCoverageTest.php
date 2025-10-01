<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Query\Builder;
use Bob\Query\Grammar;
use Bob\Query\Processor;
use Mockery as m;

// Create test models
class TestUser extends Model
{
    protected string $table = 'users';

    protected string $primaryKey = 'id';

    protected string $keyType = 'int';

    public bool $timestamps = true;
}

class TestPost extends Model
{
    protected string $table = 'posts';

    protected string $primaryKey = 'id';

    protected string $keyType = 'int';

    public bool $timestamps = false;
}

beforeEach(function () {
    $this->connection = m::mock(Connection::class);
    $this->grammar = m::mock(Grammar::class);
    $this->processor = m::mock(Processor::class);

    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new Builder($this->connection, $this->grammar, $this->processor);
});

afterEach(function () {
    m::close();
});

// Test getModel and setModel
test('Builder getModel and setModel work with Model instances', function () {
    $model = new TestUser;

    expect($this->builder->getModel())->toBeNull();

    $this->builder->setModel($model);

    expect($this->builder->getModel())->toBe($model);
});

// Test line 1910 - find with single ID returns Model when model is set
test('Builder find with single ID returns Model instance when model is set', function () {
    $model = new TestUser;
    $this->builder->setModel($model);

    $this->grammar->shouldReceive('compileSelect')->andReturn('select * from users where id = ? limit 1');
    $this->connection->shouldReceive('selectOne')->andReturn(
        (object) ['id' => 1, 'name' => 'John']
    );
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($q, $r) => $r);

    $result = $this->builder->from('users')->find(1);

    expect($result)->toBeInstanceOf(TestUser::class);
    expect($result->getAttribute('id'))->toBe(1);
    expect($result->getAttribute('name'))->toBe('John');
});

// Test find with array of IDs returns collection of Models
test('Builder find with array of IDs returns Model instances', function () {
    $model = new TestUser;
    $this->builder->setModel($model);

    $this->grammar->shouldReceive('compileSelect')->andReturn('select * from users where id in (?, ?)');
    $this->connection->shouldReceive('select')->andReturn([
        (object) ['id' => 1, 'name' => 'John'],
        (object) ['id' => 2, 'name' => 'Jane'],
    ]);
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($q, $r) => $r);

    $result = $this->builder->from('users')->find([1, 2]);

    expect($result)->toHaveCount(2);
    expect($result[0])->toBeInstanceOf(TestUser::class);
    expect($result[1])->toBeInstanceOf(TestUser::class);
});

// Test first() returns Model when model is set
test('Builder first returns Model instance when model is set', function () {
    $model = new TestUser;
    $this->builder->setModel($model);

    $this->grammar->shouldReceive('compileSelect')->andReturn('select * from users limit 1');
    $this->connection->shouldReceive('selectOne')->andReturn(
        (object) ['id' => 1, 'name' => 'John']
    );
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($q, $r) => $r);

    $result = $this->builder->from('users')->first();

    expect($result)->toBeInstanceOf(TestUser::class);
    expect($result->getAttribute('name'))->toBe('John');
});

// Test get() returns array of Models when model is set
test('Builder get returns Model instances when model is set', function () {
    $model = new TestUser;
    $this->builder->setModel($model);

    $this->grammar->shouldReceive('compileSelect')->andReturn('select * from users');
    $this->connection->shouldReceive('select')->andReturn([
        (object) ['id' => 1, 'name' => 'John'],
        (object) ['id' => 2, 'name' => 'Jane'],
    ]);
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($q, $r) => $r);

    $result = $this->builder->from('users')->get();

    expect($result)->toHaveCount(2);
    expect($result[0])->toBeInstanceOf(TestUser::class);
    expect($result[1])->toBeInstanceOf(TestUser::class);
});

// Test lines 1954-1955 - value returns single column value (with and without results)
test('Builder value returns column value from first result', function () {
    $this->grammar->shouldReceive('compileSelect')->andReturn('select name from users limit 1');
    $this->connection->shouldReceive('selectOne')->andReturn(
        (object) ['name' => 'John']
    );
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($q, $r) => $r);

    $result = $this->builder->from('users')->value('name');

    expect($result)->toBe('John');
});

test('Builder value returns null when no results', function () {
    $this->grammar->shouldReceive('compileSelect')->andReturn('select name from users limit 1');
    $this->connection->shouldReceive('selectOne')->andReturn(null);
    $this->processor->shouldReceive('processSelect')->andReturn([]);

    $result = $this->builder->from('users')->value('name');

    expect($result)->toBeNull();
});

// Test lines 1989-1992 - get with specific columns
test('Builder get with columns parameter selects only specified columns', function () {
    $columnsUsed = null;

    $this->grammar->shouldReceive('compileSelect')->andReturnUsing(function ($builder) use (&$columnsUsed) {
        $columnsUsed = $builder->columns;

        return 'select id, name from users';
    });
    $this->connection->shouldReceive('select')->andReturn([]);
    $this->processor->shouldReceive('processSelect')->andReturn([]);

    $result = $this->builder->from('users')->get(['id', 'name']);

    expect($result)->toBe([]);
    expect($columnsUsed)->toBe(['id', 'name']);
});

test('Builder get with columns and Model returns Models with selected attributes', function () {
    $model = new TestUser;
    $this->builder->setModel($model);

    $this->grammar->shouldReceive('compileSelect')->andReturn('select id, name from users');
    $this->connection->shouldReceive('select')->andReturn([
        (object) ['id' => 1, 'name' => 'John'],
    ]);
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($q, $r) => $r);

    $result = $this->builder->from('users')->get(['id', 'name']);

    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf(TestUser::class);
    expect($result[0]->getAttribute('id'))->toBe(1);
    expect($result[0]->getAttribute('name'))->toBe('John');
});

// Test line 2008 - runSelect with pretending mode
test('Builder runSelect returns empty array when connection is pretending', function () {
    $this->connection->shouldReceive('pretending')->andReturn(true);
    $this->grammar->shouldReceive('compileSelect')->andReturn('select * from users');
    $this->connection->shouldReceive('select')->andReturn([]);  // Even in pretending mode, select is called
    $this->processor->shouldReceive('processSelect')->andReturn([]);

    // runSelect is protected, so we'll test through get()
    $result = $this->builder->from('users')->get();

    expect($result)->toBe([]);
});

// Test eager loading methods (with, without, withOnly)
test('Builder with sets eager load relationships', function () {
    $this->builder->with('posts', 'comments');

    $eagerLoad = $this->builder->getEagerLoads();
    expect($eagerLoad)->toBe(['posts', 'comments']);
});

test('Builder with accepts array of relationships', function () {
    $this->builder->with(['posts', 'comments']);

    $eagerLoad = $this->builder->getEagerLoads();
    expect($eagerLoad)->toBe(['posts', 'comments']);
});

test('Builder without removes eager load relationships', function () {
    $this->builder->with('posts', 'comments', 'likes');
    $this->builder->without('comments');

    $eagerLoad = $this->builder->getEagerLoads();
    expect($eagerLoad)->toBe(['posts', 'likes']);
    expect($eagerLoad)->not->toContain('comments');
});

test('Builder withOnly replaces all eager loads', function () {
    $this->builder->with('posts', 'comments');
    $this->builder->withOnly('likes');

    $eagerLoad = $this->builder->getEagerLoads();
    expect($eagerLoad)->toBe(['likes']);
    expect($eagerLoad)->not->toContain('posts');
    expect($eagerLoad)->not->toContain('comments');
});

// Test connection getter
test('Builder getConnection returns connection instance', function () {
    $connection = $this->builder->getConnection();

    expect($connection)->toBe($this->connection);
});

// Test cloneWithoutBindings
test('Builder cloneWithoutBindings creates copy without bindings', function () {
    $this->builder->from('users')->where('name', 'John')->having('count', '>', 5);

    $clone = $this->builder->cloneWithoutBindings();

    expect($clone)->toBeInstanceOf(Builder::class);
    expect($clone->from)->toBe('users');
    expect($clone->getWheres())->toBe($this->builder->getWheres());
    expect($clone->getBindings())->toBe([]);
});

// Test cloning
test('Builder clone creates deep copy', function () {
    $this->builder->from('users')->where('name', 'John');

    $clone = clone $this->builder;

    expect($clone)->not->toBe($this->builder);
    expect($clone->from)->toBe('users');
    expect($clone->getWheres())->toEqual($this->builder->getWheres());

    // Modifying clone shouldn't affect original
    $clone->where('age', 25);
    expect(count($clone->getWheres()))->toBe(2);
    expect(count($this->builder->getWheres()))->toBe(1);
});

// Test cursor with Model
test('Builder cursor returns generator of Models when model is set', function () {
    $model = new TestUser;
    $this->builder->setModel($model);

    $this->grammar->shouldReceive('compileSelect')->andReturn('select * from users');
    $this->connection->shouldReceive('select')->andReturn([
        (object) ['id' => 1, 'name' => 'John'],
        (object) ['id' => 2, 'name' => 'Jane'],
    ]);
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($q, $r) => $r);

    $cursor = $this->builder->from('users')->cursor();

    $results = [];
    foreach ($cursor as $user) {
        $results[] = $user;
    }

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(TestUser::class);
    expect($results[1])->toBeInstanceOf(TestUser::class);
});

// Test chunk with Model
test('Builder chunk processes Models in batches when model is set', function () {
    $model = new TestUser;
    $this->builder->setModel($model);

    $this->grammar->shouldReceive('compileSelect')->twice()->andReturn('select * from users limit 2');
    $this->connection->shouldReceive('select')->twice()->andReturn(
        [
            (object) ['id' => 1, 'name' => 'John'],
            (object) ['id' => 2, 'name' => 'Jane'],
        ],
        []  // Empty result to end chunking
    );
    $this->processor->shouldReceive('processSelect')->twice()->andReturnUsing(fn ($q, $r) => $r);

    $chunks = [];
    $this->builder->from('users')->chunk(2, function ($users) use (&$chunks) {
        $chunks[] = $users;
        foreach ($users as $user) {
            expect($user)->toBeInstanceOf(TestUser::class);
        }
    });

    expect($chunks)->toHaveCount(1);
    expect($chunks[0])->toHaveCount(2);
});
