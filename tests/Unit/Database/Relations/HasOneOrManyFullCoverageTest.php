<?php

use Bob\Database\Relations\HasOneOrMany;
use Bob\Database\Relations\HasOne;
use Bob\Database\Model;
use Bob\Query\Builder;
use Bob\Database\Connection;
use Mockery as m;

describe('HasOneOrMany Full Coverage Tests', function () {

    beforeEach(function () {
        $this->connection = m::mock(Connection::class);
        $this->query = m::mock(Builder::class);
        $this->parent = m::mock(Model::class);
        $this->related = m::mock(Model::class);

        // Setup basic mocks
        $this->query->shouldReceive('getModel')->andReturn($this->related);
        $this->parent->shouldReceive('getAttribute')->with('id')->andReturn(123);

        // Mock the query methods that addConstraints() calls
        $this->query->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $this->query->shouldReceive('whereNotNull')->withAnyArgs()->andReturnSelf();

        // Create concrete implementation for testing
        $this->relation = new class($this->query, $this->parent, 'user_id', 'id') extends HasOneOrMany {
            public function getResults() {
                return $this->query->get();
            }
            public function initRelation(array $models, string $relation): array {
                return $models;
            }
            public function match(array $models, array $results, string $relation): array {
                return $models;
            }
        };
    });

    afterEach(function () {
        m::close();
    });

    // Test firstOrCreate when record doesn't exist (lines 186-188)
    test('firstOrCreate creates new model when not found', function () {
        $attributes = ['email' => 'new@example.com'];
        $values = ['name' => 'John Doe'];

        // Then first() is called on the query (where is already mocked in beforeEach)
        $this->query->shouldReceive('first')
            ->once()
            ->andReturn(null);

        // When first() returns null, the create() method is called which uses newInstance
        $mergedAttributes = array_merge($attributes, $values);
        $createdInstance = m::mock(Model::class);
        $createdInstance->shouldReceive('setAttribute')->with('user_id', 123)->once()->andReturnSelf();
        $createdInstance->shouldReceive('save')->once()->andReturn(true);

        $this->related->shouldReceive('newInstance')
            ->with($mergedAttributes)
            ->once()
            ->andReturn($createdInstance);

        $result = $this->relation->firstOrCreate($attributes, $values);

        expect($result)->toBe($createdInstance);
    });

    // Test firstOrCreate when record exists (line 186, condition is false)
    test('firstOrCreate returns existing model when found', function () {
        $attributes = ['email' => 'existing@example.com'];

        // Create existing model
        $existingModel = m::mock(Model::class);

        // First is called and returns the existing model
        $this->query->shouldReceive('first')
            ->once()
            ->andReturn($existingModel);

        $result = $this->relation->firstOrCreate($attributes);

        expect($result)->toBe($existingModel);
    });

    // Test firstOrCreate with empty values array
    test('firstOrCreate creates with only attributes when values empty', function () {
        $attributes = ['email' => 'test@example.com'];
        $values = [];

        // First returns null
        $this->query->shouldReceive('first')
            ->once()
            ->andReturn(null);

        // Create the model instance
        $createdInstance = m::mock(Model::class);
        $createdInstance->shouldReceive('setAttribute')->with('user_id', 123)->once()->andReturnSelf();
        $createdInstance->shouldReceive('save')->once()->andReturn(true);

        $this->related->shouldReceive('newInstance')
            ->with($attributes)
            ->once()
            ->andReturn($createdInstance);

        $result = $this->relation->firstOrCreate($attributes, $values);

        expect($result)->toBe($createdInstance);
    });

    // Test with both attributes and values for complete coverage
    test('firstOrCreate merges attributes and values correctly', function () {
        $attributes = ['email' => 'user@example.com'];
        $values = ['name' => 'User Name', 'status' => 'active'];

        // Not found, so will create
        $this->query->shouldReceive('first')
            ->once()
            ->andReturn(null);

        // Should merge attributes and values
        $expectedData = ['email' => 'user@example.com', 'name' => 'User Name', 'status' => 'active'];
        $createdInstance = m::mock(Model::class);
        $createdInstance->shouldReceive('setAttribute')->with('user_id', 123)->once()->andReturnSelf();
        $createdInstance->shouldReceive('save')->once()->andReturn(true);

        $this->related->shouldReceive('newInstance')
            ->with($expectedData)
            ->once()
            ->andReturn($createdInstance);

        $result = $this->relation->firstOrCreate($attributes, $values);

        expect($result)->toBe($createdInstance);
    });

    // Additional test to ensure the if condition on line 186 is properly tested
    test('firstOrCreate handles null check properly', function () {
        $attributes = ['key' => 'value'];

        // Return null to enter the if block
        $this->query->shouldReceive('first')
            ->once()
            ->andReturn(null);

        $newInstance = m::mock(Model::class);
        $newInstance->shouldReceive('setAttribute')->with('user_id', 123)->once()->andReturnSelf();
        $newInstance->shouldReceive('save')->once()->andReturn(true);

        $this->related->shouldReceive('newInstance')
            ->with($attributes)
            ->once()
            ->andReturn($newInstance);

        $result = $this->relation->firstOrCreate($attributes);

        expect($result)->toBe($newInstance);
    });
});