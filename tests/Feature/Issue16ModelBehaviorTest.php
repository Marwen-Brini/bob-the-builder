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
    echo "\n=== SAVE RETURN TYPE TEST ===\n";

    // Test Case A: New model save
    $model1 = new TestModel(['name' => 'New Model 1', 'value' => 'test1']);
    $result1 = $model1->save();

    echo "New model save #1:\n";
    echo "- save() returned: " . gettype($result1) . " = " . var_export($result1, true) . "\n";
    echo "- Model ID after save: " . ($model1->id ?? 'NULL') . "\n";
    echo "- exists(): " . ($model1->exists ? 'true' : 'false') . "\n";
    echo "- wasRecentlyCreated(): " . ($model1->wasRecentlyCreated ? 'true' : 'false') . "\n";

    // Test Case B: Another new model save
    $model2 = new TestModel(['name' => 'New Model 2', 'value' => 'test2']);
    $result2 = $model2->save();

    echo "\nNew model save #2:\n";
    echo "- save() returned: " . gettype($result2) . " = " . var_export($result2, true) . "\n";
    echo "- Model ID after save: " . ($model2->id ?? 'NULL') . "\n";
    echo "- exists(): " . ($model2->exists ? 'true' : 'false') . "\n";
    echo "- wasRecentlyCreated(): " . ($model2->wasRecentlyCreated ? 'true' : 'false') . "\n";

    // Expected: Both should return same type (bool true for success)
    // Actual: May be inconsistent
    expect(gettype($result1))->toBe(gettype($result2));
    expect($result1)->toBe($result2);
});

test('ISSUE #16: save() return type consistency for existing models', function () {
    echo "\n=== EXISTING MODEL SAVE TEST ===\n";

    // Test Case C: Update existing model
    $existingModel = TestModel::find(1);
    $existingModel->value = 'updated_value';
    $updateResult = $existingModel->save();

    echo "Existing model save:\n";
    echo "- save() returned: " . gettype($updateResult) . " = " . var_export($updateResult, true) . "\n";
    echo "- Model ID: " . ($existingModel->id ?? 'NULL') . "\n";
    echo "- exists(): " . ($existingModel->exists ? 'true' : 'false') . "\n";
    echo "- wasRecentlyCreated(): " . ($existingModel->wasRecentlyCreated ? 'true' : 'false') . "\n";

    // Compare with new model save type
    $newModel = new TestModel(['name' => 'Compare Model', 'value' => 'compare']);
    $newResult = $newModel->save();

    echo "\nComparison new model save:\n";
    echo "- save() returned: " . gettype($newResult) . " = " . var_export($newResult, true) . "\n";

    // Expected: Both should return same type
    // The issue: May return different types (bool vs int vs 0)
});

test('ISSUE #16: Model with fake ID behavior', function () {
    echo "\n=== FAKE ID MODEL TEST ===\n";

    // Test Case D: Model with fake/non-existent ID
    $fakeModel = new TestModel(['name' => 'Fake Model', 'value' => 'fake']);
    $fakeModel->id = 999; // Non-existent ID

    echo "Before save:\n";
    echo "- Model ID: " . $fakeModel->id . "\n";
    echo "- exists(): " . ($fakeModel->exists ? 'true' : 'false') . "\n";
    echo "- wasRecentlyCreated(): " . ($fakeModel->wasRecentlyCreated ? 'true' : 'false') . "\n";

    // This should detect the ID doesn't exist and do an INSERT
    $result = $fakeModel->save();

    echo "\nAfter save:\n";
    echo "- save() returned: " . gettype($result) . " = " . var_export($result, true) . "\n";
    echo "- Model ID: " . ($fakeModel->id ?? 'NULL') . "\n";
    echo "- exists(): " . ($fakeModel->exists ? 'true' : 'false') . "\n";
    echo "- wasRecentlyCreated(): " . ($fakeModel->wasRecentlyCreated ? 'true' : 'false') . "\n";

    // Check if it was actually saved
    $checkModel = TestModel::find($fakeModel->id);
    echo "- Found in database: " . ($checkModel ? 'YES' : 'NO') . "\n";
    if ($checkModel) {
        echo "- Database name: " . $checkModel->name . "\n";
    }
});

test('ISSUE #16: Failed operation model state', function () {
    echo "\n=== FAILED OPERATION STATE TEST ===\n";

    // Test Case E: Operation that should fail
    $model = new TestModel();
    // Don't set required 'name' field - should fail

    try {
        $result = $model->save();

        echo "Unexpected success:\n";
        echo "- save() returned: " . gettype($result) . " = " . var_export($result, true) . "\n";
        echo "- Model ID: " . ($model->id ?? 'NULL') . "\n";
        echo "- exists(): " . ($model->exists ? 'true' : 'false') . "\n";
        echo "- wasRecentlyCreated(): " . ($model->wasRecentlyCreated ? 'true' : 'false') . "\n";

    } catch (\Exception $e) {
        echo "Expected exception occurred:\n";
        echo "- Exception: " . get_class($e) . "\n";
        echo "- Message: " . $e->getMessage() . "\n";
        echo "- Model ID after failure: " . ($model->id ?? 'NULL') . "\n";
        echo "- exists(): " . ($model->exists ? 'true' : 'false') . "\n";
        echo "- wasRecentlyCreated(): " . ($model->wasRecentlyCreated ? 'true' : 'false') . "\n";

        // Model state after failed operation should be consistent
        expect($model->exists)->toBeFalse();
        expect($model->wasRecentlyCreated)->toBeFalse();
    }
});

test('ISSUE #16: Complex operation sequence consistency', function () {
    echo "\n=== OPERATION SEQUENCE TEST ===\n";

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
            'was_recently_created' => $model->wasRecentlyCreated
        ];

        echo "Iteration $i:\n";
        echo "- save() returned: " . gettype($result) . " = " . var_export($result, true) . "\n";
        echo "- Model ID: " . ($model->id ?? 'NULL') . "\n";
        echo "- State: exists=" . ($model->exists ? 'T' : 'F') . ", recent=" . ($model->wasRecentlyCreated ? 'T' : 'F') . "\n\n";
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
        expect($result['exists'])->toBeTrue("Model should exist after successful save");
        expect($result['was_recently_created'])->toBeTrue("Model should be marked as recently created");
        expect($result['model_id'])->not->toBe('NULL', "Model should have valid ID after save");
    }
});