<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;

/**
 * Test Issue #16: Inconsistent Model Behavior After Operations
 *
 * Testing model state consistency after save/update operations
 */
class TestModel extends Model
{
    protected string $table = 'test_models';

    protected string $primaryKey = 'id';

    public bool $timestamps = false;

    protected array $fillable = ['name', 'value'];
}

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create test table
    $this->connection->unprepared('
        CREATE TABLE test_models (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            value TEXT
        )
    ');

    // Insert test data
    $this->connection->table('test_models')->insert([
        ['id' => 1, 'name' => 'Existing Model', 'value' => 'initial'],
    ]);

    Model::setConnection($this->connection);
});

test('ISSUE #16: save() return type consistency for new models', function () {
    // Test Case A: New model save
    $model1 = new TestModel(['name' => 'New Model 1', 'value' => 'test1']);
    $result1 = $model1->save();

    // Test Case B: Another new model save
    $model2 = new TestModel(['name' => 'New Model 2', 'value' => 'test2']);
    $result2 = $model2->save();

    // Expected: Both should return same type (bool true for success)
    // Actual: May be inconsistent
    expect(gettype($result1))->toBe(gettype($result2));
    expect($result1)->toBe($result2);
});

test('ISSUE #16: save() return type consistency for existing models', function () {
    // Test Case C: Update existing model
    $existingModel = TestModel::find(1);
    $existingModel->value = 'updated_value';
    $updateResult = $existingModel->save();

    // Compare with new model save type
    $newModel = new TestModel(['name' => 'Compare Model', 'value' => 'compare']);
    $newResult = $newModel->save();

    // Expected: Both should return same type
    // The issue: May return different types (bool vs int vs 0)
    expect(gettype($updateResult))->toBe(gettype($newResult));
});

test('ISSUE #16: Model with fake ID behavior', function () {
    // Test Case D: Model with fake/non-existent ID
    $fakeModel = new TestModel(['name' => 'Fake Model', 'value' => 'fake']);
    $fakeModel->id = 999; // Non-existent ID

    // This should detect the ID doesn't exist and do an INSERT
    $result = $fakeModel->save();

    // Check if it was actually saved
    $checkModel = TestModel::find($fakeModel->id);
    expect($checkModel)->not->toBeNull();
    expect($checkModel->name)->toBe('Fake Model');
    expect($result)->toBeTrue();
});

test('ISSUE #16: Failed operation model state', function () {
    // Test Case E: Operation that should fail
    $model = new TestModel;
    // Don't set required 'name' field - should fail

    try {
        $result = $model->save();
        // If it doesn't throw an exception, that's fine - just check the result
        expect($result)->toBeTrue();
    } catch (\Exception $e) {
        // Model state after failed operation should be consistent
        expect($model->exists)->toBeFalse();
        expect($model->wasRecentlyCreated)->toBeFalse();
    }
});

test('ISSUE #16: Complex operation sequence consistency', function () {
    $results = [];

    // Create multiple models and track all return types
    for ($i = 1; $i <= 5; $i++) {
        $model = new TestModel(['name' => "Model $i", 'value' => "value_$i"]);
        $result = $model->save();

        $results[] = [
            'iteration' => $i,
            'result_type' => gettype($result),
            'result_value' => $result,
            'model_id' => $model->id ?? 'NULL',
            'exists' => $model->exists,
            'was_recently_created' => $model->wasRecentlyCreated,
        ];
    }

    // All save operations should return consistent types
    $firstType = $results[0]['result_type'];
    foreach ($results as $result) {
        expect($result['result_type'])->toBe($firstType,
            "All save() operations should return the same type, but iteration {$result['iteration']} returned {$result['result_type']} instead of $firstType"
        );
    }

    // All successful saves should have similar state
    foreach ($results as $result) {
        expect($result['exists'])->toBeTrue('Model should exist after successful save');
        expect($result['was_recently_created'])->toBeTrue('Model should be marked as recently created');
        expect($result['model_id'])->not->toBe('NULL', 'Model should have valid ID after save');
    }
});
