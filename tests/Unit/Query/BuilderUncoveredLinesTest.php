<?php

namespace Tests\Unit\Query;

use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Database\Model;
use Bob\Query\Builder;
use Bob\Query\Grammar;
use Bob\Query\Processor;
use Bob\Query\JoinClause;
use Mockery;
use PDO;
use stdClass;

afterEach(function () {
    Mockery::close();
});

it('handles insertUsing with closure', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users');

    $grammar->shouldReceive('compileInsertUsing')
        ->once()
        ->andReturn('INSERT INTO users (name, email) SELECT name, email FROM other_users');

    $connection->shouldReceive('affectingStatement')
        ->once()
        ->with('INSERT INTO users (name, email) SELECT name, email FROM other_users', [])
        ->andReturn(5);

    $result = $builder->insertUsing(['name', 'email'], function ($query) {
        $query->from('other_users');
        return $query;
    });

    expect($result)->toBe(5);
});

it('handles delete with ID parameter', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users');

    $grammar->shouldReceive('compileDelete')->once()->andReturn('DELETE FROM users WHERE id = ?');
    $connection->shouldReceive('delete')
        ->once()
        ->with('DELETE FROM users WHERE id = ?', [123])
        ->andReturn(1);

    $result = $builder->delete(123);

    expect($result)->toBe(1);
});

it('handles upsert with empty values', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users');

    $result = $builder->upsert([]);

    expect($result)->toBe(0);
});

it('handles upsert normalizing single row to array', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users');

    $grammar->shouldReceive('compileUpsert')
        ->once()
        ->andReturn('INSERT INTO users ... ON DUPLICATE KEY UPDATE ...');

    $connection->shouldReceive('affectingStatement')
        ->once()
        ->andReturn(1);

    // Pass a single row (not an array of arrays)
    $result = $builder->upsert(['name' => 'John', 'email' => 'john@example.com'], ['email']);

    expect($result)->toBe(1);
});

it('handles existsOr when exists returns true', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users');

    $grammar->shouldReceive('compileExists')->once()->andReturn('SELECT EXISTS(...) as exists');
    $connection->shouldReceive('select')
        ->once()
        ->andReturn([['exists' => 1]]);
    $processor->shouldReceive('processSelect')
        ->once()
        ->andReturn([['exists' => 1]]);

    $callbackCalled = false;
    $result = $builder->existsOr(function () use (&$callbackCalled) {
        $callbackCalled = true;
        return false;
    });

    expect($result)->toBeTrue();
    expect($callbackCalled)->toBeFalse();
});

it('handles doesntExistOr when doesnt exist returns true', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users');

    $grammar->shouldReceive('compileExists')->once()->andReturn('SELECT EXISTS(...) as exists');
    $connection->shouldReceive('select')
        ->once()
        ->andReturn([]);
    $processor->shouldReceive('processSelect')
        ->once()
        ->andReturn([]);

    $callbackCalled = false;
    $result = $builder->doesntExistOr(function () use (&$callbackCalled) {
        $callbackCalled = true;
        return false;
    });

    expect($result)->toBeTrue();
    expect($callbackCalled)->toBeFalse();
});

it('uses filterOrdersExcluding when reorder is called with existing orders', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users');
    $builder->orderBy('name');
    $builder->orderBy('email');

    // Now reorder with a specific column to exclude
    $result = $builder->reorder('name', 'desc');

    // Should have only the new 'name' order since reorder clears all existing orders when column is specified
    $orders = $result->getOrders();
    expect($orders)->toHaveCount(1);
    expect($orders[0]['column'])->toBe('name');
    expect($orders[0]['direction'])->toBe('desc');
});

it('handles executeTruncateStatements with single string', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $connection->shouldReceive('statement')
        ->once()
        ->with('TRUNCATE TABLE users');

    $builder = new Builder($connection, $grammar, $processor);

    // Use reflection to call protected method directly with a string
    $reflection = new \ReflectionClass($builder);
    $method = $reflection->getMethod('executeTruncateStatements');
    $method->setAccessible(true);

    $method->invoke($builder, 'TRUNCATE TABLE users');
});

it('handles rightJoinSub method', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users');

    $subQuery = (new Builder($connection, $grammar, $processor))->from('posts')->select('user_id');

    // Add grammar expectations for the subquery's toSql call and wrap
    $grammar->shouldReceive('compileSelect')->andReturn('SELECT user_id FROM posts');
    $grammar->shouldReceive('wrap')->with('p')->andReturn('`p`');

    $result = $builder->rightJoinSub($subQuery, 'p', 'users.id', '=', 'p.user_id');

    expect($result)->toBeInstanceOf(Builder::class);
    expect($builder->getJoins())->toHaveCount(1);
    expect($builder->getJoins()[0]->type)->toBe('right');
});

it('handles setBindings with non-existent type', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);

    // Set bindings for a type that doesn't exist yet
    $builder->setBindings([1, 2, 3], 'custom');

    expect($builder->getBindings('custom'))->toBe([1, 2, 3]);
});

it('handles eagerLoadRelations with empty models', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->with('posts');

    // Use reflection to call protected method
    $reflection = new \ReflectionClass($builder);
    $method = $reflection->getMethod('eagerLoadRelations');
    $method->setAccessible(true);

    $result = $method->invoke($builder, []);

    expect($result)->toBe([]);
});

it('handles eagerLoadRelation when model doesnt have method', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $model = Mockery::mock(Model::class);
    $model->shouldReceive('newQuery')->andReturn(new Builder($connection, $grammar, $processor));

    $builder = new Builder($connection, $grammar, $processor);
    $builder->setModel($model);

    // Use reflection to call protected method
    $reflection = new \ReflectionClass($builder);
    $method = $reflection->getMethod('eagerLoadRelation');
    $method->setAccessible(true);

    $models = [$model];

    // This should return early without throwing an error
    $method->invoke($builder, $models, 'nonExistentRelation');

    expect($models)->toHaveCount(1);
});

it('handles getFirstModel with empty models array', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);

    // Use reflection to call protected method
    $reflection = new \ReflectionClass($builder);
    $method = $reflection->getMethod('getFirstModel');
    $method->setAccessible(true);

    $result = $method->invoke($builder, []);

    expect($result)->toBeNull();
});

it('handles clone method', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users')->where('active', true);

    $cloned = $builder->clone();

    expect($cloned)->not->toBe($builder);
    expect($cloned->getFrom())->toBe('users');
    expect($cloned->getWheres())->toBe($builder->getWheres());
});

it('handles orOn method in join clause', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection, $grammar, $processor);
    $builder->from('users')
        ->join('posts', function ($join) {
            $join->on('users.id', '=', 'posts.user_id')
                 ->orOn('users.id', '=', 'posts.author_id');
        });

    $joins = $builder->getJoins();
    expect($joins)->toHaveCount(1);
    expect($joins[0])->toBeInstanceOf(JoinClause::class);

    // The JoinClause should have two where conditions
    $wheres = $joins[0]->wheres;
    expect($wheres)->toHaveCount(2);
    expect($wheres[1]['boolean'])->toBe('or');
});