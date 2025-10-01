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

    $this->connection->enableQueryLog();

    $result = $model->save();

    $queries = $this->connection->getQueryLog();

    if (! empty($queries)) {
    }

    // Check what was actually saved
    $reloaded = ModelWithFillable::find(1);

    // This might be the issue: status gets saved even though it's not fillable
    // OR status doesn't get saved but save() returns true
});

test('ISSUE #15: Mass assignment vs direct assignment behavior', function () {
    // Test the difference between mass assignment and direct assignment

    $model = ModelWithFillable::find(1);

    // Mass assignment should respect fillable
    $model->fill(['name' => 'Mass Assigned Name', 'status' => 'mass_assigned']);

    // Now try direct assignment
    $model = ModelWithFillable::find(1);
    $model->name = 'Direct Name';
    $model->status = 'direct_status';

    // The question: Does direct assignment ignore fillable restrictions?
    // If yes, then both should be dirty and both should save
    // If no, then status assignment should be ignored
});

test('ISSUE #15: Test guarded behavior', function () {
    $model = ModelWithGuarded::find(1);

    $model->name = 'New Name';      // Not guarded, should work
    $model->status = 'guarded_value'; // Guarded, what happens?

    $result = $model->save();

    $reloaded = ModelWithGuarded::find(1);
});
