<?php

use Bob\Database\Relations\HasOne;
use Bob\Database\Model;
use Bob\Query\Builder;
use Bob\Database\Connection;
use Mockery as m;

describe('HasOne Tests', function () {

    beforeEach(function () {
        $this->connection = m::mock(Connection::class);
        $this->query = m::mock(Builder::class);
        $this->parent = m::mock(Model::class);
        $this->related = m::mock(Model::class);

        // Mock the getModel method that the Relation constructor calls
        $this->query->shouldReceive('getModel')->andReturn($this->related);

        // Mock the getAttribute method that getParentKey() calls
        $this->parent->shouldReceive('getAttribute')->with('id')->andReturn(123);

        // Mock the query methods called by addConstraints() during construction
        $this->query->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $this->query->shouldReceive('whereNotNull')->withAnyArgs()->andReturnSelf();
        $this->query->shouldReceive('whereIn')->withAnyArgs()->andReturnSelf();
        $this->query->shouldReceive('first')->andReturn(null);

        // Create HasOne relation instance
        $this->relation = new HasOne($this->query, $this->parent, 'user_id', 'id');
    });

    afterEach(function () {
        m::close();
    });

    test('HasOne extends HasOneOrMany', function () {
        expect($this->relation)->toBeInstanceOf(\Bob\Database\Relations\HasOneOrMany::class);
    });

    test('getResults returns default when parent key is null', function () {
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(null);

        $query = m::mock(Builder::class);
        $related = m::mock(Model::class);
        $query->shouldReceive('getModel')->andReturn($related);
        $query->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $query->shouldReceive('whereNotNull')->withAnyArgs()->andReturnSelf();

        $relation = new HasOne($query, $parent, 'user_id', 'id');

        $result = $relation->getResults();
        expect($result)->toBeNull();
    });

    test('getResults returns query result when parent key exists', function () {
        // Create a fresh relation without the constructor calling first()
        $query = m::mock(Builder::class);
        $parent = m::mock(Model::class);
        $related = m::mock(Model::class);

        $query->shouldReceive('getModel')->andReturn($related);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(123);
        $query->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $query->shouldReceive('whereNotNull')->withAnyArgs()->andReturnSelf();

        $model = m::mock(Model::class);
        $query->shouldReceive('first')->once()->andReturn($model);

        $relation = new HasOne($query, $parent, 'user_id', 'id');

        $result = $relation->getResults();
        expect($result)->toBe($model);
    });

    test('getResults returns null when query returns no result', function () {
        $this->query->shouldReceive('first')->andReturn(null);

        $result = $this->relation->getResults();
        expect($result)->toBeNull();
    });

    test('shouldReturnDefaultValue returns true when parent key is null', function () {
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(null);

        $query = m::mock(Builder::class);
        $related = m::mock(Model::class);
        $query->shouldReceive('getModel')->andReturn($related);
        $query->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $query->shouldReceive('whereNotNull')->withAnyArgs()->andReturnSelf();

        $relation = new HasOne($query, $parent, 'user_id', 'id');

        $reflection = new ReflectionClass($relation);
        $method = $reflection->getMethod('shouldReturnDefaultValue');
        $method->setAccessible(true);

        $result = $method->invoke($relation);
        expect($result)->toBeTrue();
    });

    test('shouldReturnDefaultValue returns false when parent key exists', function () {
        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('shouldReturnDefaultValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation);
        expect($result)->toBeFalse();
    });

    test('executeQuery returns processed query result', function () {
        // Create fresh mocks to avoid conflicts with beforeEach setup
        $query = m::mock(Builder::class);
        $parent = m::mock(Model::class);
        $related = m::mock(Model::class);
        $model = m::mock(Model::class);

        $query->shouldReceive('getModel')->andReturn($related);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(123);
        $query->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $query->shouldReceive('whereNotNull')->withAnyArgs()->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn($model); // Only once when executeQuery is called

        $relation = new HasOne($query, $parent, 'user_id', 'id');

        $reflection = new ReflectionClass($relation);
        $method = $reflection->getMethod('executeQuery');
        $method->setAccessible(true);

        $result = $method->invoke($relation);
        expect($result)->toBe($model);
    });

    test('getQueryResult calls first on query', function () {
        // Create fresh mocks to avoid conflicts with beforeEach setup
        $query = m::mock(Builder::class);
        $parent = m::mock(Model::class);
        $related = m::mock(Model::class);
        $model = m::mock(Model::class);

        $query->shouldReceive('getModel')->andReturn($related);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(123);
        $query->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $query->shouldReceive('whereNotNull')->withAnyArgs()->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn($model); // Only once when we call the method

        $relation = new HasOne($query, $parent, 'user_id', 'id');

        $reflection = new ReflectionClass($relation);
        $method = $reflection->getMethod('getQueryResult');
        $method->setAccessible(true);

        $result = $method->invoke($relation);
        expect($result)->toBe($model);
    });

    test('processQueryResult returns result when not null', function () {
        $model = m::mock(Model::class);

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('processQueryResult');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation, $model);
        expect($result)->toBe($model);
    });

    test('processQueryResult returns default when result is null', function () {
        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('processQueryResult');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation, null);
        expect($result)->toBeNull();
    });

    test('initRelation sets default relation on models', function () {
        $model1 = m::mock(Model::class);
        $model1->shouldReceive('setRelation')->with('user', null)->once();
        $model2 = m::mock(Model::class);
        $model2->shouldReceive('setRelation')->with('user', null)->once();

        $models = [$model1, $model2];

        $result = $this->relation->initRelation($models, 'user');
        expect($result)->toBe($models);
    });

    test('initializeRelationOnModel sets relation on single model', function () {
        $model = m::mock(Model::class);
        $model->shouldReceive('setRelation')->with('user', null)->once();

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('initializeRelationOnModel');
        $method->setAccessible(true);

        $method->invoke($this->relation, $model, 'user');
    });

    test('match calls matchOne to match results', function () {
        $model = m::mock(Model::class);
        $model->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $model->shouldReceive('setRelation')->withAnyArgs();
        $model->shouldReceive('setAttribute')->withAnyArgs(); // Add this expectation
        $model->id = 1;

        $result = m::mock(Model::class);
        $result->shouldReceive('getAttribute')->with('user_id')->andReturn(1);
        $result->shouldReceive('setAttribute')->withAnyArgs(); // Add this expectation
        $result->user_id = 1;

        $models = [$model];
        $results = [$result];

        $matched = $this->relation->match($models, $results, 'user');
        expect($matched)->toBe($models);
    });

    test('getDefaultFor returns null', function () {
        $parent = m::mock(Model::class);

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('getDefaultFor');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation, $parent);
        expect($result)->toBeNull();
    });

    test('newRelatedInstanceFor creates new related instance', function () {
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(456);
        $parent->shouldReceive('setAttribute')->withAnyArgs(); // Add this expectation
        $parent->id = 456;

        $newInstance = m::mock(Model::class);
        $newInstance->shouldReceive('setAttribute')->with('user_id', 456)->once();

        $this->related->shouldReceive('newInstance')->andReturn($newInstance);

        $result = $this->relation->newRelatedInstanceFor($parent);
        expect($result)->toBe($newInstance);
    });

    test('buildNewRelatedInstance creates and configures instance', function () {
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(789);
        $parent->shouldReceive('setAttribute')->withAnyArgs(); // Add this expectation
        $parent->id = 789;

        $newInstance = m::mock(Model::class);
        $newInstance->shouldReceive('setAttribute')->with('user_id', 789)->once();

        $this->related->shouldReceive('newInstance')->andReturn($newInstance);

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('buildNewRelatedInstance');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation, $parent);
        expect($result)->toBe($newInstance);
    });

    test('createNewRelatedInstance calls newInstance on related', function () {
        $newInstance = m::mock(Model::class);
        $this->related->shouldReceive('newInstance')->once()->andReturn($newInstance);

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('createNewRelatedInstance');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation);
        expect($result)->toBe($newInstance);
    });

    test('setForeignKeyOnInstance sets foreign key attribute', function () {
        $instance = m::mock(Model::class);
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(321);
        $parent->shouldReceive('setAttribute')->withAnyArgs(); // Add this expectation
        $parent->id = 321;

        $instance->shouldReceive('setAttribute')->with('user_id', 321)->once();

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('setForeignKeyOnInstance');
        $method->setAccessible(true);

        $method->invoke($this->relation, $instance, $parent);
    });

    test('HasOne properly handles edge cases', function () {
        // Test with empty results
        $this->query->shouldReceive('first')->andReturn(null);
        $result = $this->relation->getResults();
        expect($result)->toBeNull();

        // Test with multiple init
        $models = [];
        $result = $this->relation->initRelation($models, 'user');
        expect($result)->toBe([]);

        // Test match with empty models
        $result = $this->relation->match([], [], 'user');
        expect($result)->toBe([]);
    });

    test('HasOne handles the full relationship flow', function () {
        // Set up a complete flow test
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(100);

        $query = m::mock(Builder::class);
        $related = m::mock(Model::class);
        $relatedInstance = m::mock(Model::class);

        $query->shouldReceive('getModel')->andReturn($related);
        $query->shouldReceive('where')->with('user_id', '=', 100)->once()->andReturnSelf();
        $query->shouldReceive('whereNotNull')->with('user_id')->once()->andReturnSelf();
        $query->shouldReceive('first')->andReturn($relatedInstance);

        $relation = new HasOne($query, $parent, 'user_id', 'id');

        $result = $relation->getResults();
        expect($result)->toBe($relatedInstance);
    });

});