<?php

namespace Tests\Unit\Database;

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Query\Builder;
use Mockery as m;

// Test model class
class TestDeleteModel extends Model
{
    protected string $table = 'test_models';

    protected string $primaryKey = 'id';

    protected array $fillable = ['id', 'name', 'email'];  // Include id for testing
}

beforeEach(function () {
    // Create mock connection
    $this->connection = m::mock(Connection::class);

    // Mock the query builder
    $this->builder = m::mock(Builder::class);

    // Set up connection to return builder
    $this->connection->shouldReceive('table')
        ->andReturn($this->builder);

    // Mock setModel method
    $this->builder->shouldReceive('setModel')
        ->andReturn($this->builder);

    // Set the global connection
    Model::setConnection($this->connection);
});

afterEach(function () {
    m::close();
    Model::clearConnection();
});

test('model exists() returns false after successful deletion', function () {
    // Create a model that "exists" in the database
    $model = new TestDeleteModel;
    $model->fill(['id' => 123, 'name' => 'John', 'email' => 'john@example.com']);

    // Simulate that the model was loaded from database
    $model->syncOriginal();

    // Verify model exists before deletion
    expect($model->exists())->toBeTrue();

    // Mock the delete query
    $this->builder->shouldReceive('withoutGlobalScopes')
        ->once()
        ->andReturn($this->builder);

    $this->builder->shouldReceive('where')
        ->once()
        ->with('id', 123)
        ->andReturn($this->builder);

    $this->builder->shouldReceive('delete')
        ->once()
        ->andReturn(1); // 1 row affected

    // Delete the model
    $result = $model->delete();

    expect($result)->toBeTrue();

    // BUG: This should return false but returns true!
    expect($model->exists())->toBeFalse();
});

test('model primary key is cleared after deletion', function () {
    $model = new TestDeleteModel;
    $model->fill(['id' => 456, 'name' => 'Jane']);
    $model->syncOriginal();

    // Mock the delete query
    $this->builder->shouldReceive('withoutGlobalScopes')
        ->once()
        ->andReturn($this->builder);

    $this->builder->shouldReceive('where')
        ->once()
        ->with('id', 456)
        ->andReturn($this->builder);

    $this->builder->shouldReceive('delete')
        ->once()
        ->andReturn(1);

    // Delete the model
    $model->delete();

    // Primary key should be removed from attributes
    expect($model->getAttribute('id'))->toBeNull();
    expect(isset($model->getAttributes()['id']))->toBeFalse();
});

test('model original data is cleared after deletion', function () {
    $model = new TestDeleteModel;
    $model->fill(['id' => 789, 'name' => 'Bob', 'email' => 'bob@example.com']);
    $model->syncOriginal();

    // Verify original data exists before deletion
    $original = $model->getOriginal();
    expect($original)->toHaveKey('id');
    expect($original)->toHaveKey('name');
    expect($original)->toHaveKey('email');

    // Mock the delete query
    $this->builder->shouldReceive('withoutGlobalScopes')
        ->once()
        ->andReturn($this->builder);

    $this->builder->shouldReceive('where')
        ->once()
        ->with('id', 789)
        ->andReturn($this->builder);

    $this->builder->shouldReceive('delete')
        ->once()
        ->andReturn(1);

    // Delete the model
    $model->delete();

    // Original array should be empty after deletion
    expect($model->getOriginal())->toBeEmpty();
});

test('cannot delete model that does not exist', function () {
    $model = new TestDeleteModel;
    $model->fill(['name' => 'New Model']); // No ID, not saved

    // Model doesn't exist (no original data)
    expect($model->exists())->toBeFalse();

    // Should not make any delete query
    $this->builder->shouldNotReceive('delete');

    // Attempt to delete
    $result = $model->delete();

    expect($result)->toBeFalse();
});

test('failed deletion does not clear model state', function () {
    $model = new TestDeleteModel;
    $model->fill(['id' => 999, 'name' => 'Test']);
    $model->syncOriginal();

    // Mock the delete query to fail
    $this->builder->shouldReceive('withoutGlobalScopes')
        ->once()
        ->andReturn($this->builder);

    $this->builder->shouldReceive('where')
        ->once()
        ->with('id', 999)
        ->andReturn($this->builder);

    $this->builder->shouldReceive('delete')
        ->once()
        ->andReturn(0); // No rows affected - deletion failed

    // Attempt deletion
    $result = $model->delete();

    expect($result)->toBeFalse();

    // Model should still exist since deletion failed
    expect($model->exists())->toBeTrue();
    expect($model->getAttribute('id'))->toBe(999);
    expect($model->getOriginal())->toHaveKey('id');
});

test('can attempt to delete already deleted model returns false', function () {
    $model = new TestDeleteModel;
    $model->fill(['id' => 321, 'name' => 'ToDelete']);
    $model->syncOriginal();

    // Mock first successful deletion
    $this->builder->shouldReceive('withoutGlobalScopes')
        ->once()
        ->andReturn($this->builder);

    $this->builder->shouldReceive('where')
        ->once()
        ->with('id', 321)
        ->andReturn($this->builder);

    $this->builder->shouldReceive('delete')
        ->once()
        ->andReturn(1);

    // First deletion succeeds
    expect($model->delete())->toBeTrue();

    // After fix, model should not exist
    expect($model->exists())->toBeFalse();

    // Second deletion attempt should return false without querying
    $this->builder->shouldNotReceive('where');
    $this->builder->shouldNotReceive('delete');

    expect($model->delete())->toBeFalse();
});

test('other attributes remain accessible after deletion except primary key', function () {
    $model = new TestDeleteModel;
    $model->fill(['id' => 654, 'name' => 'Alice', 'email' => 'alice@example.com']);
    $model->syncOriginal();

    // Mock the delete query
    $this->builder->shouldReceive('withoutGlobalScopes')
        ->once()
        ->andReturn($this->builder);

    $this->builder->shouldReceive('where')
        ->once()
        ->with('id', 654)
        ->andReturn($this->builder);

    $this->builder->shouldReceive('delete')
        ->once()
        ->andReturn(1);

    // Delete the model
    $model->delete();

    // Primary key should be gone
    expect($model->getAttribute('id'))->toBeNull();

    // But other attributes can still be accessed (useful for logging, etc.)
    expect($model->getAttribute('name'))->toBe('Alice');
    expect($model->getAttribute('email'))->toBe('alice@example.com');
});

test('isDirty behavior after deletion', function () {
    $model = new TestDeleteModel;
    $model->fill(['id' => 111, 'name' => 'Original']);
    $model->syncOriginal();

    // Make a change
    $model->setAttribute('name', 'Modified');
    expect($model->isDirty())->toBeTrue();
    expect($model->isDirty('name'))->toBeTrue();

    // Mock the delete query
    $this->builder->shouldReceive('withoutGlobalScopes')
        ->once()
        ->andReturn($this->builder);

    $this->builder->shouldReceive('where')
        ->once()
        ->with('id', 111)
        ->andReturn($this->builder);

    $this->builder->shouldReceive('delete')
        ->once()
        ->andReturn(1);

    // Delete the model
    $model->delete();

    // After deletion, with empty original, remaining attributes are considered dirty
    // This is correct behavior - there's no original to compare against
    expect($model->isDirty())->toBeTrue();  // 'name' still exists but no original
    expect($model->isDirty('name'))->toBeTrue();
    expect($model->isDirty('id'))->toBeFalse();  // id was removed
});

test('wasDeleted flag or method to check deletion status', function () {
    $model = new TestDeleteModel;
    $model->fill(['id' => 222, 'name' => 'Track']);
    $model->syncOriginal();

    // Mock the delete query
    $this->builder->shouldReceive('withoutGlobalScopes')
        ->once()
        ->andReturn($this->builder);

    $this->builder->shouldReceive('where')
        ->once()
        ->with('id', 222)
        ->andReturn($this->builder);

    $this->builder->shouldReceive('delete')
        ->once()
        ->andReturn(1);

    // Before deletion
    expect($model->exists())->toBeTrue();

    // Delete the model
    $model->delete();

    // After deletion - exists should return false
    expect($model->exists())->toBeFalse();

    // Could also check if model tracks deletion
    // This is a design choice - some ORMs have a wasDeleted() method
});

// Integration test with real SQLite database
test('integration test - model state after deletion with real database', function () {
    // Create a real SQLite connection
    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    $connection = new Connection(['driver' => 'sqlite'], null, $pdo);

    // Create test table
    $connection->statement('CREATE TABLE test_models (
        id INTEGER PRIMARY KEY,
        name TEXT,
        email TEXT
    )');

    // Insert a test record
    $connection->statement('INSERT INTO test_models (id, name, email) VALUES (?, ?, ?)',
        [1, 'Test User', 'test@example.com']);

    // Set the connection for the model
    Model::setConnection($connection);

    // Create and load the model
    $model = new TestDeleteModel;
    $result = $connection->table('test_models')->where('id', 1)->first();

    // Manually hydrate the model (simulating find())
    foreach ((array) $result as $key => $value) {
        $model->setAttribute($key, $value);
    }
    $model->syncOriginal();

    // Verify model exists
    expect($model->exists())->toBeTrue();
    expect($model->getAttribute('id'))->toBe(1);

    // Delete the model
    $deleteResult = $model->delete();

    expect($deleteResult)->toBeTrue();

    // BUG DEMONSTRATION: Model should not exist after deletion
    expect($model->exists())->toBeFalse(); // This will fail without the fix
    expect($model->getAttribute('id'))->toBeNull();
    expect($model->getOriginal())->toBeEmpty();

    // Verify record is actually deleted from database
    $count = $connection->table('test_models')->where('id', 1)->count();
    expect($count)->toBe(0);

    // Cleanup
    Model::clearConnection();
});
