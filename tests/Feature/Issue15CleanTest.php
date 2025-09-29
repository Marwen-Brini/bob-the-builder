<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;

/**
 * Clean test to reproduce Issue #15 precisely
 */

class SimpleModel extends Model
{
    protected string $table = 'simple_table';
    protected string $primaryKey = 'id';
    public bool $timestamps = false;
    protected array $fillable = ['name']; // 'extra_field' is NOT fillable
}

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create a simple table WITHOUT the extra_field column
    $this->connection->unprepared('
        CREATE TABLE simple_table (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )
    ');

    // Insert test data
    $this->connection->table('simple_table')->insert([
        ['id' => 1, 'name' => 'Test Record'],
    ]);

    Model::setConnection($this->connection);
});

test('ISSUE #15: Assigning non-existent column should fail gracefully', function () {
    $model = SimpleModel::find(1);

    // Manually add an attribute that doesn't exist in the database
    $model->setAttribute('extra_field', 'some_value');

    expect($model->isDirty())->toBeTrue();
    expect($model->isDirty('extra_field'))->toBeTrue();

    // Enable query logging
    $this->connection->enableQueryLog();

    // This should either:
    // 1. Throw an exception (preferred)
    // 2. Return false
    // 3. Filter out non-existent columns
    // But NOT return true while silently failing

    try {
        $result = $model->save();

        $queries = $this->connection->getQueryLog();

        if (!empty($queries)) {
        }

        // If we get here, save() didn't throw - check what actually happened
        $reloaded = SimpleModel::find(1);

        // This is the bug: save() returns true but the extra_field update failed silently
        expect($result)->toBeFalse(); // This is what SHOULD happen

    } catch (\Exception $e) {

        // This is actually the preferred behavior
        expect($e)->toBeInstanceOf(\Exception::class);
    }
});

test('ISSUE #15: Save should only update existing columns', function () {
    $model = SimpleModel::find(1);

    // Mix of valid and invalid fields
    $model->name = 'Updated Name';           // Valid field
    $model->setAttribute('fake_field', 'fake_value');  // Invalid field

    $this->connection->enableQueryLog();

    try {
        $result = $model->save();

        $queries = $this->connection->getQueryLog();

        if (!empty($queries)) {
            $sql = $queries[0]['query'];

            // The SQL should only include valid columns
            expect($sql)->toContain('name');
            expect($sql)->not->toContain('fake_field');
        }

        // Check that valid field was updated
        $reloaded = SimpleModel::find(1);
        expect($reloaded->name)->toBe('Updated Name');

    } catch (\Exception $e) {
        // Either approach is acceptable - throw or filter
    }
});