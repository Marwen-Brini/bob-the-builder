<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Database\Relations\BelongsTo;
use Bob\Query\Builder;
use Mockery as m;

describe('BelongsTo Tests', function () {

    beforeEach(function () {
        $this->connection = m::mock(Connection::class);
        $this->query = m::mock(Builder::class);
        $this->child = m::mock(Model::class);
        $this->related = m::mock(Model::class);

        // Mock the getModel method that the Relation constructor calls
        $this->query->shouldReceive('getModel')->andReturn($this->related);

        // Mock common methods
        $this->related->shouldReceive('getTable')->andReturn('users');
        $this->child->shouldReceive('qualifyColumn')->with('user_id')->andReturn('posts.user_id');
        $this->related->shouldReceive('qualifyColumn')->with('id')->andReturn('users.id');

        // Mock attribute access for the constructor's addConstraints call
        $this->child->shouldReceive('getAttribute')->with('user_id')->andReturn(123);

        // Mock general model methods that may be called
        $this->child->shouldReceive('setRelation')->withAnyArgs()->andReturnSelf();
        $this->child->shouldReceive('unsetRelation')->withAnyArgs()->andReturnSelf();
        $this->child->shouldReceive('setAttribute')->withAnyArgs();

        // Mock query methods for constructor constraints
        $this->query->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $this->query->shouldReceive('first')->andReturn(null);
        $this->query->shouldReceive('whereIn')->withAnyArgs()->andReturnSelf();

        // Create BelongsTo relation instance
        $this->relation = new BelongsTo($this->query, $this->child, 'user_id', 'id', 'user');
    });

    afterEach(function () {
        m::close();
    });

    test('BelongsTo constructor sets properties correctly', function () {
        expect($this->relation->getForeignKeyName())->toBe('user_id');
        expect($this->relation->getOwnerKeyName())->toBe('id');
        expect($this->relation->getRelationName())->toBe('user');
        expect($this->relation->getChild())->toBe($this->child);
    });

    test('getResults method returns null when foreign key is null', function () {
        $this->child->shouldReceive('getAttribute')->with('user_id')->andReturn(null);
        $this->child->user_id = null;

        $result = $this->relation->getResults();
        expect($result)->toBeNull();
    });

    test('getResults method returns query result when foreign key exists', function () {
        $model = m::mock(Model::class);

        // Create a fresh relation to avoid global mock conflicts
        $query = m::mock(Builder::class);
        $child = m::mock(Model::class);
        $related = m::mock(Model::class);

        $query->shouldReceive('getModel')->andReturn($related);
        $related->shouldReceive('getTable')->andReturn('users');
        $child->shouldReceive('qualifyColumn')->with('user_id')->andReturn('posts.user_id');
        $related->shouldReceive('qualifyColumn')->with('id')->andReturn('users.id');
        $child->shouldReceive('getAttribute')->with('user_id')->andReturn(123);
        $child->shouldReceive('setAttribute')->withAnyArgs();
        $query->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $query->shouldReceive('first')->andReturn($model);

        $relation = new BelongsTo($query, $child, 'user_id', 'id', 'user');

        $result = $relation->getResults();
        expect($result)->toBe($model);
    });

    test('getResults method returns null when query returns no result', function () {
        $this->child->shouldReceive('getAttribute')->with('user_id')->andReturn(123);
        $this->child->user_id = 123;
        $this->query->shouldReceive('first')->andReturn(null);

        $result = $this->relation->getResults();
        expect($result)->toBeNull();
    });

    test('addConstraints method adds where clause when constraints enabled', function () {
        // Enable constraints via reflection
        $reflection = new ReflectionClass(BelongsTo::class);
        $constraintsProperty = $reflection->getProperty('constraints');
        $constraintsProperty->setAccessible(true);
        $constraintsProperty->setValue(null, true);

        // Create fresh mocks to test specific constraint behavior
        $query = m::mock(Builder::class);
        $child = m::mock(Model::class);
        $related = m::mock(Model::class);

        $query->shouldReceive('getModel')->andReturn($related);
        $related->shouldReceive('getTable')->andReturn('users');
        $child->shouldReceive('getAttribute')->with('user_id')->andReturn(456);
        $child->shouldReceive('setAttribute')->withAnyArgs();
        $query->shouldReceive('where')->with('users.id', '=', 456)->twice()->andReturnSelf(); // Called in constructor and explicit call

        $relation = new BelongsTo($query, $child, 'user_id', 'id', 'user');

        // Constructor already calls addConstraints, but let's test it explicitly
        $relation->addConstraints();
    });

    test('addEagerConstraints method adds whereIn clause', function () {
        // Create fresh mocks to avoid global mock conflicts
        $query = m::mock(Builder::class);
        $child = m::mock(Model::class);
        $related = m::mock(Model::class);

        $query->shouldReceive('getModel')->andReturn($related);
        $related->shouldReceive('getTable')->andReturn('users');
        $child->shouldReceive('qualifyColumn')->with('user_id')->andReturn('posts.user_id');
        $related->shouldReceive('qualifyColumn')->with('id')->andReturn('users.id');
        $child->shouldReceive('getAttribute')->with('user_id')->andReturn(123);
        $child->shouldReceive('setAttribute')->withAnyArgs();
        $query->shouldReceive('where')->withAnyArgs()->andReturnSelf();

        $model1 = m::mock(Model::class);
        $model1->shouldReceive('getAttribute')->with('user_id')->andReturn(1);
        $model1->shouldReceive('setAttribute')->withAnyArgs();
        $model2 = m::mock(Model::class);
        $model2->shouldReceive('getAttribute')->with('user_id')->andReturn(2);
        $model2->shouldReceive('setAttribute')->withAnyArgs();

        $models = [$model1, $model2];

        $query->shouldReceive('whereIn')->with('users.id', [1, 2])->once()->andReturnSelf();

        $relation = new BelongsTo($query, $child, 'user_id', 'id', 'user');
        $relation->addEagerConstraints($models);
    });

    test('getEagerModelKeys method extracts unique sorted keys', function () {
        $model1 = m::mock(Model::class);
        $model1->shouldReceive('getAttribute')->with('user_id')->andReturn(3);
        $model1->shouldReceive('setAttribute')->withAnyArgs();
        $model1->user_id = 3;
        $model2 = m::mock(Model::class);
        $model2->shouldReceive('getAttribute')->with('user_id')->andReturn(1);
        $model2->shouldReceive('setAttribute')->withAnyArgs();
        $model2->user_id = 1;
        $model3 = m::mock(Model::class);
        $model3->shouldReceive('getAttribute')->with('user_id')->andReturn(3); // Duplicate
        $model3->shouldReceive('setAttribute')->withAnyArgs();
        $model3->user_id = 3;
        $model4 = m::mock(Model::class);
        $model4->shouldReceive('getAttribute')->with('user_id')->andReturn(null);
        $model4->shouldReceive('setAttribute')->withAnyArgs();
        $model4->user_id = null;

        $models = [$model1, $model2, $model3, $model4];

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('getEagerModelKeys');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation, $models);
        expect($result)->toBe([1, 3]); // Sorted and unique, nulls excluded
    });

    test('initRelation method sets default relation on models', function () {
        $model1 = m::mock(Model::class);
        $model1->shouldReceive('setRelation')->with('user', null)->once();
        $model2 = m::mock(Model::class);
        $model2->shouldReceive('setRelation')->with('user', null)->once();

        $models = [$model1, $model2];

        $result = $this->relation->initRelation($models, 'user');
        expect($result)->toBe($models);
    });

    test('match method sets relations from dictionary', function () {
        $result1 = m::mock(Model::class);
        $result1->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $result1->shouldReceive('setAttribute')->withAnyArgs();
        $result1->id = 1;
        $result2 = m::mock(Model::class);
        $result2->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $result2->shouldReceive('setAttribute')->withAnyArgs();
        $result2->id = 2;

        $model1 = m::mock(Model::class);
        $model1->shouldReceive('getAttribute')->with('user_id')->andReturn(1);
        $model1->shouldReceive('setRelation')->with('user', $result1)->once();
        $model1->shouldReceive('setAttribute')->withAnyArgs();
        $model1->user_id = 1;
        $model2 = m::mock(Model::class);
        $model2->shouldReceive('getAttribute')->with('user_id')->andReturn(3); // No match
        $model2->shouldReceive('setAttribute')->withAnyArgs();
        $model2->user_id = 3;

        $models = [$model1, $model2];
        $results = [$result1, $result2];

        $result = $this->relation->match($models, $results, 'user');
        expect($result)->toBe($models);
    });

    test('buildDictionary method creates dictionary keyed by owner key', function () {
        $result1 = m::mock(Model::class);
        $result1->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $result1->shouldReceive('setAttribute')->withAnyArgs();
        $result1->id = 1;
        $result2 = m::mock(Model::class);
        $result2->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $result2->shouldReceive('setAttribute')->withAnyArgs();
        $result2->id = 2;

        $results = [$result1, $result2];

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('buildDictionary');
        $method->setAccessible(true);

        $dictionary = $method->invoke($this->relation, $results);

        expect($dictionary[1])->toBe($result1);
        expect($dictionary[2])->toBe($result2);
    });

    test('associate method with Model instance', function () {
        $parentModel = m::mock(Model::class);
        $parentModel->shouldReceive('getAttribute')->with('id')->andReturn(456);

        $result = $this->relation->associate($parentModel);
        expect($result)->toBe($this->child);
    });

    test('associate method with scalar value', function () {
        $result = $this->relation->associate(789);
        expect($result)->toBe($this->child);
    });

    test('dissociate method removes association', function () {
        $result = $this->relation->dissociate();
        expect($result)->toBe($this->child);
    });

    test('getRelationExistenceQuery method for different tables', function () {
        $query = m::mock(Builder::class);
        $parentQuery = m::mock(Builder::class);

        $query->from = 'users';
        $parentQuery->from = 'posts';

        $query->shouldReceive('select')->with('*')->once()->andReturnSelf();
        $query->shouldReceive('whereColumn')->with('posts.user_id', '=', 'users.id')->once()->andReturnSelf();

        $result = $this->relation->getRelationExistenceQuery($query, $parentQuery, '*');
        expect($result)->toBe($query);
    });

    test('getRelationExistenceQuery method for same table (self-relation)', function () {
        $query = m::mock(Builder::class);
        $parentQuery = m::mock(Builder::class);
        $model = m::mock(Model::class);

        $query->from = 'users';
        $parentQuery->from = 'users';

        $query->shouldReceive('getModel')->andReturn($model);
        $model->shouldReceive('getTable')->andReturn('users');
        $model->shouldReceive('setTable')->with(m::pattern('/laravel_reserved_\d+/'))->once();
        $query->shouldReceive('select')->with('*')->once()->andReturnSelf();
        $query->shouldReceive('from')->with(m::pattern('/users as laravel_reserved_\d+/'))->once()->andReturnSelf();
        $query->shouldReceive('whereColumn')->with(m::pattern('/laravel_reserved_\d+\.id/'), '=', 'posts.user_id')->once()->andReturnSelf();

        $result = $this->relation->getRelationExistenceQuery($query, $parentQuery, '*');
        expect($result)->toBe($query);
    });

    test('getRelationCountHash method increments counter', function () {
        $hash1 = $this->relation->getRelationCountHash();
        $hash2 = $this->relation->getRelationCountHash();

        expect($hash1)->toMatch('/laravel_reserved_\d+/');
        expect($hash2)->toMatch('/laravel_reserved_\d+/');
        expect($hash1)->not->toBe($hash2); // Should be different due to incrementing counter
    });

    test('relationHasIncrementingId method returns true for incrementing integer key', function () {
        $this->related->shouldReceive('getIncrementing')->andReturn(true);
        $this->related->shouldReceive('getKeyType')->andReturn('int');

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('relationHasIncrementingId');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation);
        expect($result)->toBeTrue();
    });

    test('relationHasIncrementingId method returns false for non-incrementing key', function () {
        $this->related->shouldReceive('getIncrementing')->andReturn(false);

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('relationHasIncrementingId');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation);
        expect($result)->toBeFalse();
    });

    test('relationHasIncrementingId method returns false for non-integer key type', function () {
        $this->related->shouldReceive('getIncrementing')->andReturn(true);
        $this->related->shouldReceive('getKeyType')->andReturn('string');

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('relationHasIncrementingId');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation);
        expect($result)->toBeFalse();
    });

    test('newRelatedInstanceFor method creates new related instance', function () {
        $parent = m::mock(Model::class);
        $newInstance = m::mock(Model::class);

        $this->related->shouldReceive('newInstance')->andReturn($newInstance);

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('newRelatedInstanceFor');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation, $parent);
        expect($result)->toBe($newInstance);
    });

    test('getQualifiedForeignKeyName method returns qualified foreign key', function () {
        $result = $this->relation->getQualifiedForeignKeyName();
        expect($result)->toBe('posts.user_id');
    });

    test('getQualifiedOwnerKeyName method returns qualified owner key', function () {
        $result = $this->relation->getQualifiedOwnerKeyName();
        expect($result)->toBe('users.id');
    });

    test('getRelation method returns relation name', function () {
        $result = $this->relation->getRelation();
        expect($result)->toBe('user');
    });

    test('getDefaultFor method returns null', function () {
        $parent = m::mock(Model::class);

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('getDefaultFor');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation, $parent);
        expect($result)->toBeNull();
    });

    test('whereInMethod method returns whereIn', function () {
        $model = m::mock(Model::class);

        $reflection = new ReflectionClass($this->relation);
        $method = $reflection->getMethod('whereInMethod');
        $method->setAccessible(true);

        $result = $method->invoke($this->relation, $model, 'id');
        expect($result)->toBe('whereIn');
    });

});
