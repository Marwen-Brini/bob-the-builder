<?php

use Bob\Database\Relations\HasMany;
use Bob\Database\Model;
use Bob\Query\Builder;
use Bob\Database\Connection;
use Mockery as m;

describe('HasMany Tests', function () {

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

        // Create HasMany relation instance
        $this->relation = new HasMany($this->query, $this->parent, 'user_id', 'id');
    });

    afterEach(function () {
        m::close();
    });

    test('HasMany extends HasOneOrMany', function () {
        expect($this->relation)->toBeInstanceOf(\Bob\Database\Relations\HasOneOrMany::class);
    });

    test('getResults returns array when parent key is null', function () {
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(null);

        $query = m::mock(Builder::class);
        $related = m::mock(Model::class);
        $query->shouldReceive('getModel')->andReturn($related);
        $query->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $query->shouldReceive('whereNotNull')->withAnyArgs()->andReturnSelf();

        $relation = new HasMany($query, $parent, 'user_id', 'id');

        $result = $relation->getResults();
        expect($result)->toBe([]);
    });

    test('getResults returns query results when parent key exists', function () {
        $models = [
            ['id' => 1, 'name' => 'Post 1'],
            ['id' => 2, 'name' => 'Post 2']
        ];

        $this->query->shouldReceive('get')->once()->andReturn($models);

        $result = $this->relation->getResults();
        expect($result)->toBe($models);
    });

    test('getResults with empty results', function () {
        $this->query->shouldReceive('get')->once()->andReturn([]);

        $result = $this->relation->getResults();
        expect($result)->toBe([]);
    });

    test('initRelation sets empty array relation on models', function () {
        $model1 = m::mock(Model::class);
        $model1->shouldReceive('setRelation')->with('posts', [])->once();
        $model2 = m::mock(Model::class);
        $model2->shouldReceive('setRelation')->with('posts', [])->once();

        $models = [$model1, $model2];

        $result = $this->relation->initRelation($models, 'posts');
        expect($result)->toBe($models);
    });

    test('initRelation with empty models array', function () {
        $models = [];

        $result = $this->relation->initRelation($models, 'posts');
        expect($result)->toBe([]);
    });

    test('initRelation with single model', function () {
        $model = m::mock(Model::class);
        $model->shouldReceive('setRelation')->with('comments', [])->once();

        $models = [$model];

        $result = $this->relation->initRelation($models, 'comments');
        expect($result)->toBe($models);
    });

    test('match calls matchMany to match results', function () {
        // Create parent models
        $parent1 = m::mock(Model::class);
        $parent1->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $parent1->shouldReceive('setAttribute')->withAnyArgs();
        $parent1->shouldReceive('setRelation')->withAnyArgs();
        $parent1->id = 1;

        $parent2 = m::mock(Model::class);
        $parent2->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $parent2->shouldReceive('setAttribute')->withAnyArgs();
        $parent2->shouldReceive('setRelation')->withAnyArgs();
        $parent2->id = 2;

        // Create result models
        $result1 = m::mock(Model::class);
        $result1->shouldReceive('getAttribute')->with('user_id')->andReturn(1);
        $result1->shouldReceive('setAttribute')->withAnyArgs();
        $result1->user_id = 1;

        $result2 = m::mock(Model::class);
        $result2->shouldReceive('getAttribute')->with('user_id')->andReturn(1);
        $result2->shouldReceive('setAttribute')->withAnyArgs();
        $result2->user_id = 1;

        $result3 = m::mock(Model::class);
        $result3->shouldReceive('getAttribute')->with('user_id')->andReturn(2);
        $result3->shouldReceive('setAttribute')->withAnyArgs();
        $result3->user_id = 2;

        $models = [$parent1, $parent2];
        $results = [$result1, $result2, $result3];

        $matched = $this->relation->match($models, $results, 'posts');
        expect($matched)->toBe($models);
    });

    test('match with no results', function () {
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $parent->shouldReceive('setAttribute')->withAnyArgs();
        $parent->id = 1;

        $models = [$parent];
        $results = [];

        $matched = $this->relation->match($models, $results, 'posts');
        expect($matched)->toBe($models);
    });

    test('match with no matching results', function () {
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $parent->shouldReceive('setAttribute')->withAnyArgs();
        $parent->id = 1;

        $result = m::mock(Model::class);
        $result->shouldReceive('getAttribute')->with('user_id')->andReturn(999);
        $result->shouldReceive('setAttribute')->withAnyArgs();
        $result->user_id = 999;

        $models = [$parent];
        $results = [$result];

        $matched = $this->relation->match($models, $results, 'posts');
        expect($matched)->toBe($models);
    });

    test('HasMany handles full relationship flow', function () {
        // Set up a complete flow test
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(100);

        $query = m::mock(Builder::class);
        $related = m::mock(Model::class);

        $query->shouldReceive('getModel')->andReturn($related);
        $query->shouldReceive('where')->with('user_id', '=', 100)->once()->andReturnSelf();
        $query->shouldReceive('whereNotNull')->with('user_id')->once()->andReturnSelf();

        $results = [
            ['id' => 1, 'user_id' => 100, 'title' => 'Post 1'],
            ['id' => 2, 'user_id' => 100, 'title' => 'Post 2'],
            ['id' => 3, 'user_id' => 100, 'title' => 'Post 3']
        ];

        $query->shouldReceive('get')->once()->andReturn($results);

        $relation = new HasMany($query, $parent, 'user_id', 'id');

        $result = $relation->getResults();
        expect($result)->toBe($results);
        expect($result)->toHaveCount(3);
    });

    test('HasMany with complex parent key', function () {
        $parent = m::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('uuid')->andReturn('abc-123-def');

        $query = m::mock(Builder::class);
        $related = m::mock(Model::class);

        $query->shouldReceive('getModel')->andReturn($related);
        $query->shouldReceive('where')->with('parent_uuid', '=', 'abc-123-def')->once()->andReturnSelf();
        $query->shouldReceive('whereNotNull')->with('parent_uuid')->once()->andReturnSelf();
        $query->shouldReceive('get')->once()->andReturn([]);

        $relation = new HasMany($query, $parent, 'parent_uuid', 'uuid');

        $result = $relation->getResults();
        expect($result)->toBe([]);
    });

    test('getResults is called multiple times returns consistent results', function () {
        $results = [['id' => 1], ['id' => 2]];

        $this->query->shouldReceive('get')->twice()->andReturn($results);

        $result1 = $this->relation->getResults();
        $result2 = $this->relation->getResults();

        expect($result1)->toBe($results);
        expect($result2)->toBe($results);
    });

});