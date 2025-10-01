<?php

use Bob\Database\Connection;
use Bob\Database\Model;

class ExistingIdTestModel extends Model
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

    $this->connection->unprepared('
        CREATE TABLE test_models (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            value TEXT
        )
    ');

    // Insert an existing record
    $this->connection->table('test_models')->insert([
        'id' => 1,
        'name' => 'Existing Record',
        'value' => 'original',
    ]);

    Model::setConnection($this->connection);
});

test('Model with existing ID in database updates instead of insert', function () {
    // Create a new model instance with an ID that exists in the database
    // but without loading it first (no original data)
    $model = new ExistingIdTestModel;
    $model->id = 1; // This ID exists in the database
    $model->name = 'Updated Name';
    $model->value = 'updated';

    // The model has an ID but no original data (wasn't loaded from DB)
    expect($model->exists())->toBeFalse(); // No original data
    expect($model->id)->toBe(1);

    // When we save, it should check the database, find the existing record,
    // populate original data, and perform an UPDATE instead of INSERT
    $result = $model->save();

    expect($result)->toBeTrue();

    // Verify it was updated, not inserted
    $records = $this->connection->table('test_models')->get();
    expect($records)->toHaveCount(1); // Still only 1 record

    $record = $records[0];
    expect($record->id)->toBe(1);
    expect($record->name)->toBe('Updated Name');
    expect($record->value)->toBe('updated');

    // The model should now have original data
    expect($model->getOriginal())->not->toBeEmpty();
    expect($model->exists())->toBeTrue();
});

test('Model with non-existing ID inserts new record with that ID', function () {
    // Create a model with an ID that doesn't exist
    $model = new ExistingIdTestModel;
    $model->id = 999; // This ID doesn't exist
    $model->name = 'New Record';
    $model->value = 'new';

    $result = $model->save();

    expect($result)->toBeTrue();

    // Should have inserted a new record with ID 999
    $record = $this->connection->table('test_models')->where('id', 999)->first();
    expect($record)->not->toBeNull();
    expect($record->name)->toBe('New Record');

    // Should now have 2 records total
    $records = $this->connection->table('test_models')->get();
    expect($records)->toHaveCount(2);
});
