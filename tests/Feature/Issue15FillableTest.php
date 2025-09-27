<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;

/**
 * Test Issue #15 with fillable restrictions on existing columns
 */

class ModelWithFillable extends Model
{
    protected string $table = 'test_table';
    protected string $primaryKey = 'id';
    public bool $timestamps = false;

    // Only 'name' is fillable, 'status' is not
    protected array $fillable = ['name'];
}

class ModelWithGuarded extends Model
{
    protected string $table = 'test_table';
    protected string $primaryKey = 'id';
    public bool $timestamps = false;

    // 'status' is guarded (protected)
    protected array $guarded = ['status'];
}

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create table with both fillable and non-fillable columns
    $this->connection->unprepared('
        CREATE TABLE test_table (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            status TEXT DEFAULT "active"
        )
    ');

    // Insert test data
    $this->connection->table('test_table')->insert([
        ['id' => 1, 'name' => 'Test Record', 'status' => 'active'],
    ]);

    Model::setConnection($this->connection);
});

test('ISSUE #15: Direct assignment to non-fillable existing column', function () {
    $model = ModelWithFillable::find(1);

    expect($model->name)->toBe('Test Record');
    expect($model->status)->toBe('active');

    // Assign to fillable field - should work
    $model->name = 'Updated Name';

    // Assign to non-fillable but existing field - what happens?
    $model->status = 'inactive';

    echo "\n=== FILLABLE TEST ===\n";
    echo "After assignments:\n";
    echo "- name (fillable): " . $model->name . "\n";
    echo "- status (non-fillable): " . $model->status . "\n";
    echo "- isDirty(): " . ($model->isDirty() ? 'true' : 'false') . "\n";
    echo "- isDirty('name'): " . ($model->isDirty('name') ? 'true' : 'false') . "\n";
    echo "- isDirty('status'): " . ($model->isDirty('status') ? 'true' : 'false') . "\n";
    echo "- getDirty(): " . json_encode($model->getDirty()) . "\n";

    $this->connection->enableQueryLog();

    $result = $model->save();

    $queries = $this->connection->getQueryLog();
    echo "\nSave result: " . ($result ? 'true' : 'false') . "\n";

    if (!empty($queries)) {
        echo "SQL: " . $queries[0]['query'] . "\n";
        echo "Bindings: " . json_encode($queries[0]['bindings']) . "\n";
    }

    // Check what was actually saved
    $reloaded = ModelWithFillable::find(1);
    echo "\nAfter reload:\n";
    echo "- name: " . $reloaded->name . "\n";
    echo "- status: " . $reloaded->status . "\n";

    // This might be the issue: status gets saved even though it's not fillable
    // OR status doesn't get saved but save() returns true
});

test('ISSUE #15: Mass assignment vs direct assignment behavior', function () {
    // Test the difference between mass assignment and direct assignment

    echo "\n=== MASS ASSIGNMENT TEST ===\n";

    $model = ModelWithFillable::find(1);

    // Mass assignment should respect fillable
    $model->fill(['name' => 'Mass Assigned Name', 'status' => 'mass_assigned']);

    echo "After mass assignment:\n";
    echo "- name: " . $model->name . "\n";
    echo "- status: " . $model->status . "\n"; // Should still be 'active'
    echo "- isDirty('name'): " . ($model->isDirty('name') ? 'true' : 'false') . "\n";
    echo "- isDirty('status'): " . ($model->isDirty('status') ? 'true' : 'false') . "\n";

    // Now try direct assignment
    $model = ModelWithFillable::find(1);
    $model->name = 'Direct Name';
    $model->status = 'direct_status';

    echo "\nAfter direct assignment:\n";
    echo "- name: " . $model->name . "\n";
    echo "- status: " . $model->status . "\n";
    echo "- isDirty('name'): " . ($model->isDirty('name') ? 'true' : 'false') . "\n";
    echo "- isDirty('status'): " . ($model->isDirty('status') ? 'true' : 'false') . "\n";

    // The question: Does direct assignment ignore fillable restrictions?
    // If yes, then both should be dirty and both should save
    // If no, then status assignment should be ignored
});

test('ISSUE #15: Test guarded behavior', function () {
    $model = ModelWithGuarded::find(1);

    $model->name = 'New Name';      // Not guarded, should work
    $model->status = 'guarded_value'; // Guarded, what happens?

    echo "\n=== GUARDED TEST ===\n";
    echo "After assignment to guarded field:\n";
    echo "- name: " . $model->name . "\n";
    echo "- status: " . $model->status . "\n";
    echo "- isDirty('name'): " . ($model->isDirty('name') ? 'true' : 'false') . "\n";
    echo "- isDirty('status'): " . ($model->isDirty('status') ? 'true' : 'false') . "\n";

    $result = $model->save();
    echo "Save result: " . ($result ? 'true' : 'false') . "\n";

    $reloaded = ModelWithGuarded::find(1);
    echo "Reloaded status: " . $reloaded->status . "\n";
});