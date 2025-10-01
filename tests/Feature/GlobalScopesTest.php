<?php

use Bob\Database\Connection;
use Bob\Query\Builder;

beforeEach(function () {
    $this->connection = Mockery::mock(Connection::class);
    $this->grammar = new \Bob\Query\Grammars\MySQLGrammar;
    $this->processor = new \Bob\Query\Processor;

    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new Builder($this->connection);
});

afterEach(function () {
    Mockery::close();
});

test('addGlobalScope adds a closure scope', function () {
    $scope = function (Builder $builder) {
        $builder->where('active', true);
    };

    $this->builder->addGlobalScope('active', $scope);

    $scopes = $this->builder->getGlobalScopes();
    expect($scopes)->toHaveKey('active');
    expect($scopes['active'])->toBe($scope);
});

test('addGlobalScope adds a scope class', function () {
    $scope = new class
    {
        public function apply(Builder $builder, $model)
        {
            $builder->where('published', true);
        }
    };

    $this->builder->addGlobalScope('published', $scope);

    $scopes = $this->builder->getGlobalScopes();
    expect($scopes)->toHaveKey('published');
    expect($scopes['published'])->toBe($scope);
});

test('withoutGlobalScope removes a specific scope', function () {
    $scope1 = function (Builder $builder) {
        $builder->where('active', true);
    };
    $scope2 = function (Builder $builder) {
        $builder->where('published', true);
    };

    $this->builder->addGlobalScope('active', $scope1);
    $this->builder->addGlobalScope('published', $scope2);

    $this->builder->withoutGlobalScope('active');

    $scopes = $this->builder->getGlobalScopes();
    expect($scopes)->not->toHaveKey('active');
    expect($scopes)->toHaveKey('published');
});

test('withoutGlobalScopes removes all scopes when called without arguments', function () {
    $scope1 = function (Builder $builder) {
        $builder->where('active', true);
    };
    $scope2 = function (Builder $builder) {
        $builder->where('published', true);
    };

    $this->builder->addGlobalScope('active', $scope1);
    $this->builder->addGlobalScope('published', $scope2);

    $this->builder->withoutGlobalScopes();

    $scopes = $this->builder->getGlobalScopes();
    expect($scopes)->toBeEmpty();
});

test('withoutGlobalScopes removes specified scopes', function () {
    $scope1 = function (Builder $builder) {
        $builder->where('active', true);
    };
    $scope2 = function (Builder $builder) {
        $builder->where('published', true);
    };
    $scope3 = function (Builder $builder) {
        $builder->where('visible', true);
    };

    $this->builder->addGlobalScope('active', $scope1);
    $this->builder->addGlobalScope('published', $scope2);
    $this->builder->addGlobalScope('visible', $scope3);

    $this->builder->withoutGlobalScopes(['active', 'visible']);

    $scopes = $this->builder->getGlobalScopes();
    expect($scopes)->not->toHaveKey('active');
    expect($scopes)->toHaveKey('published');
    expect($scopes)->not->toHaveKey('visible');
});

test('applyScopes applies closure scopes', function () {
    $this->builder->from('posts');

    $scope = function (Builder $builder) {
        $builder->where('active', true)
            ->where('published', true);
    };

    $this->builder->addGlobalScope('filters', $scope);
    $this->builder->applyScopes();

    expect($this->builder->wheres)->toHaveCount(2);
    expect($this->builder->wheres[0])->toBe([
        'type' => 'Basic',
        'column' => 'active',
        'operator' => '=',
        'value' => true,
        'boolean' => 'and',
    ]);
    expect($this->builder->wheres[1])->toBe([
        'type' => 'Basic',
        'column' => 'published',
        'operator' => '=',
        'value' => true,
        'boolean' => 'and',
    ]);
});

test('applyScopes applies scope classes', function () {
    $this->builder->from('posts');

    $scope = new class
    {
        public function apply(Builder $builder, $model)
        {
            $builder->where('status', 'active')
                ->orderBy('created_at', 'desc');
        }
    };

    $this->builder->addGlobalScope('status', $scope);
    $this->builder->applyScopes();

    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0])->toBe([
        'type' => 'Basic',
        'column' => 'status',
        'operator' => '=',
        'value' => 'active',
        'boolean' => 'and',
    ]);
    expect($this->builder->orders)->toHaveCount(1);
    expect($this->builder->orders[0])->toBe([
        'column' => 'created_at',
        'direction' => 'desc',
    ]);
});

test('applyScopes does not apply removed scopes', function () {
    $this->builder->from('posts');

    $scope1 = function (Builder $builder) {
        $builder->where('active', true);
    };
    $scope2 = function (Builder $builder) {
        $builder->where('published', true);
    };

    $this->builder->addGlobalScope('active', $scope1);
    $this->builder->addGlobalScope('published', $scope2);
    $this->builder->withoutGlobalScope('active');
    $this->builder->applyScopes();

    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0])->toBe([
        'type' => 'Basic',
        'column' => 'published',
        'operator' => '=',
        'value' => true,
        'boolean' => 'and',
    ]);
});

test('get applies global scopes before execution', function () {
    $this->builder->from('posts');

    $scope = function (Builder $builder) {
        $builder->where('active', true);
    };

    $this->builder->addGlobalScope('active', $scope);

    // Mock the connection to verify the SQL includes the scope
    $this->connection->shouldReceive('select')
        ->once()
        ->with(
            'select * from `posts` where `active` = ?',
            [true],
            true
        )
        ->andReturn([]);

    $results = $this->builder->get();

    expect($results)->toBeArray();
    expect($results)->toBeEmpty();
});

test('first applies global scopes before execution', function () {
    $this->builder->from('posts');

    $scope = function (Builder $builder) {
        $builder->where('status', 'published');
    };

    $this->builder->addGlobalScope('status', $scope);

    // Mock the connection to verify the SQL includes the scope
    $this->connection->shouldReceive('selectOne')
        ->once()
        ->with(
            'select * from `posts` where `status` = ? limit 1',
            ['published'],
            true
        )
        ->andReturn(null);

    $result = $this->builder->first();

    expect($result)->toBeNull();
});

test('exists applies global scopes before execution', function () {
    $this->builder->from('posts');

    $scope = function (Builder $builder) {
        $builder->where('deleted_at', null);
    };

    $this->builder->addGlobalScope('soft_delete', $scope);

    // Mock the connection to verify the SQL includes the scope
    $this->connection->shouldReceive('select')
        ->once()
        ->with(
            'select exists(select * from `posts` where `deleted_at` is null) as `exists`',
            []
        )
        ->andReturn([['exists' => 1]]);

    $result = $this->builder->exists();

    expect($result)->toBeTrue();
});

test('delete applies global scopes before execution', function () {
    $this->builder->from('posts');

    $scope = function (Builder $builder) {
        $builder->where('user_id', 123);
    };

    $this->builder->addGlobalScope('user', $scope);

    // Mock the connection to verify the SQL includes the scope
    $this->connection->shouldReceive('delete')
        ->once()
        ->with(
            'delete from `posts` where `user_id` = ?',
            [123]
        )
        ->andReturn(5);

    $result = $this->builder->delete();

    expect($result)->toBe(5);
});

test('update applies global scopes before execution', function () {
    $this->builder->from('posts');

    $scope = function (Builder $builder) {
        $builder->where('user_id', 123);
    };

    $this->builder->addGlobalScope('user', $scope);

    // Mock the connection to verify the SQL includes the scope
    $this->connection->shouldReceive('update')
        ->once()
        ->with(
            'update `posts` set `status` = ? where `user_id` = ?',
            ['archived', 123]
        )
        ->andReturn(3);

    $result = $this->builder->update(['status' => 'archived']);

    expect($result)->toBe(3);
});

test('multiple global scopes are applied in order', function () {
    $this->builder->from('posts');

    $scope1 = function (Builder $builder) {
        $builder->where('active', true);
    };
    $scope2 = function (Builder $builder) {
        $builder->where('published', true);
    };
    $scope3 = function (Builder $builder) {
        $builder->orderBy('created_at', 'desc');
    };

    $this->builder->addGlobalScope('active', $scope1);
    $this->builder->addGlobalScope('published', $scope2);
    $this->builder->addGlobalScope('recent', $scope3);

    $this->builder->applyScopes();

    expect($this->builder->wheres)->toHaveCount(2);
    expect($this->builder->orders)->toHaveCount(1);
});

test('withoutGlobalScope can remove scope by class name', function () {
    $scopeClass = new class
    {
        public function apply(Builder $builder, $model)
        {
            $builder->where('test', true);
        }
    };

    $className = get_class($scopeClass);

    $this->builder->addGlobalScope($className, $scopeClass);
    expect($this->builder->getGlobalScopes())->toHaveKey($className);

    $this->builder->withoutGlobalScope($scopeClass);
    expect($this->builder->getGlobalScopes())->not->toHaveKey($className);
});

test('global scopes work with model integration', function () {
    // Create a test model class
    $model = new class extends \Bob\Database\Model
    {
        protected string $table = 'posts';
    };

    $this->builder->from('posts');
    $this->builder->setModel($model);

    // Add a global scope
    $scope = function (Builder $builder) {
        $builder->where('post_type', 'post');
    };

    $this->builder->addGlobalScope('type', $scope);

    // Mock the connection
    $this->connection->shouldReceive('select')
        ->once()
        ->with(
            'select * from `posts` where `post_type` = ?',
            ['post'],
            true
        )
        ->andReturn([
            ['id' => 1, 'title' => 'Test Post', 'post_type' => 'post'],
        ]);

    $results = $this->builder->get();

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(\Bob\Database\Model::class);
    expect($results[0]->id)->toBe(1);
    expect($results[0]->title)->toBe('Test Post');
    expect($results[0]->post_type)->toBe('post');
});

test('chained scope operations work correctly', function () {
    $this->builder->from('posts');

    $scope1 = function (Builder $builder) {
        $builder->where('active', true);
    };
    $scope2 = function (Builder $builder) {
        $builder->where('published', true);
    };

    // Chain multiple scope operations
    $this->builder
        ->addGlobalScope('active', $scope1)
        ->addGlobalScope('published', $scope2)
        ->withoutGlobalScope('active');

    $this->builder->applyScopes();

    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('published');
});

test('scope with complex query modifications', function () {
    $this->builder->from('posts');

    $complexScope = function (Builder $builder) {
        $builder->where('status', 'active')
            ->where('created_at', '>=', '2024-01-01')
            ->whereNotNull('published_at')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc');
    };

    $this->builder->addGlobalScope('complex', $complexScope);
    $this->builder->applyScopes();

    expect($this->builder->wheres)->toHaveCount(3);
    expect($this->builder->orders)->toHaveCount(2);
});

test('applyScopes skips removed scopes when manually marked as removed', function () {
    $this->builder->from('posts');

    // Add scopes
    $this->builder->addGlobalScope('scope1', function (Builder $builder) {
        $builder->where('field1', 'value1');
    });

    $this->builder->addGlobalScope('scope2', function (Builder $builder) {
        $builder->where('field2', 'value2');
    });

    // Use reflection to manually add to removedScopes without unsetting from instanceGlobalScopes
    // This simulates the edge case where a scope is marked as removed but still exists
    $reflection = new ReflectionClass($this->builder);
    $removedScopesProperty = $reflection->getProperty('removedScopes');
    $removedScopesProperty->setAccessible(true);
    $removedScopes = $removedScopesProperty->getValue($this->builder);
    $removedScopes[] = 'scope2';
    $removedScopesProperty->setValue($this->builder, $removedScopes);

    // Apply scopes - this will hit the continue for scope2
    $this->builder->applyScopes();

    // Should only have 1 where clause (scope1)
    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('field1');
});
