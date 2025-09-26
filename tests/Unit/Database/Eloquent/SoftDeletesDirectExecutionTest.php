<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Database\Eloquent\SoftDeletes;
use Bob\Database\Eloquent\SoftDeletingScope;
use Bob\Query\Builder;

/**
 * Direct execution tests for uncovered SoftDeletes lines
 * Targeting lines 71-73 and 86-105
 */

class DirectExecutionModel extends Model
{
    use SoftDeletes;

    protected string $table = 'direct_test';
    protected string $primaryKey = 'id';
    public bool $timestamps = true;

    // Add properties to support dynamic property access
    public $id;
    public $deleted_at;
    public $updated_at;

    // Override problematic methods to make execution possible
    protected function fireModelEvent($event, $halt = true): mixed
    {
        // Just return true to allow execution to continue
        return !$halt;
    }

    protected function setKeysForSaveQuery($query)
    {
        // Add where clause for primary key
        $query->where($this->getTable() . '.' . $this->primaryKey, $this->getAttribute($this->primaryKey));
        return $query;
    }

    public function getAttribute(string $key, $default = null)
    {
        return $this->$key ?? $default;
    }

    public function syncOriginal(): Model
    {
        // Just return self to allow chaining
        return $this;
    }

    public function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    public function fromDateTime($value): string
    {
        return is_string($value) ? $value : date('Y-m-d H:i:s');
    }

    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    public function getUpdatedAtColumn(): string
    {
        return 'updated_at';
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    // Override newQuery to return a working builder
    public function newQuery(): Builder
    {
        $connection = Model::getConnection();
        if (!$connection) {
            $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
            Model::setConnection($connection);
        }
        $builder = $connection->table($this->table);
        $builder->setModel($this);
        return $builder;
    }
}

beforeEach(function () {
    // Setup a real database connection for tests
    $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    $connection->statement('CREATE TABLE direct_test (id INTEGER PRIMARY KEY, deleted_at TEXT, updated_at TEXT, name TEXT)');
    Model::setConnection($connection);
});

test('performDeleteOnModel with forceDeleting=true executes lines 71-73', function () {
    // Insert a test record
    $connection = Model::getConnection();
    $connection->table('direct_test')->insert(['id' => 1, 'name' => 'Test']);

    $model = new DirectExecutionModel();
    $model->id = 1;

    // Use reflection to set forceDeleting and call performDeleteOnModel
    $reflection = new ReflectionClass($model);

    // Set forceDeleting to true
    $prop = $reflection->getProperty('forceDeleting');
    $prop->setAccessible(true);
    $prop->setValue($model, true);

    // Get the protected method
    $method = $reflection->getMethod('performDeleteOnModel');
    $method->setAccessible(true);

    // Execute performDeleteOnModel - this will run lines 70-73
    // Line 70: if ($this->forceDeleting) {
    // Lines 71-73: return $this->setKeysForSaveQuery($this->newQuery())
    //                 ->withoutGlobalScope(SoftDeletingScope::class)
    //                 ->delete();
    $result = $method->invoke($model);

    // The result could be true (bool) or 1 (int) depending on the delete implementation
    expect($result)->toBeGreaterThan(0);

    // Verify the record was actually deleted
    $remaining = $connection->table('direct_test')->where('id', 1)->count();
    expect($remaining)->toBe(0);
});

test('runSoftDelete executes full logic lines 86-105', function () {
    // Insert a test record
    $connection = Model::getConnection();
    $connection->table('direct_test')->insert(['id' => 2, 'name' => 'Test2', 'deleted_at' => null, 'updated_at' => null]);

    $model = new DirectExecutionModel();
    $model->id = 2;

    // Use reflection to call the protected runSoftDelete method
    $reflection = new ReflectionClass($model);
    $method = $reflection->getMethod('runSoftDelete');
    $method->setAccessible(true);

    // Capture the current time for comparison
    $timeBefore = date('Y-m-d H:i:s');

    // Execute runSoftDelete - this runs lines 86-105
    // Line 86-87: $query = $this->setKeysForSaveQuery($this->newQuery())->withoutGlobalScope(SoftDeletingScope::class);
    // Line 89: $time = $this->freshTimestamp();
    // Line 91: $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];
    // Line 93: $this->{$this->getDeletedAtColumn()} = $time;
    // Line 95-99: if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) { ... }
    // Line 101: $query->update($columns);
    // Line 103: $this->syncOriginal();
    // Line 105: return true;
    $result = $method->invoke($model);

    // Should return true
    expect($result)->toBe(true);

    // Model properties should be updated
    expect($model->deleted_at)->not()->toBeNull();
    expect($model->updated_at)->not()->toBeNull();

    // Database should be updated
    $record = $connection->table('direct_test')->where('id', 2)->first();
    expect($record)->not()->toBeNull();
    expect($record->deleted_at)->not()->toBeNull();
    expect($record->updated_at)->not()->toBeNull();

    // Both timestamps should be the same (set to $time)
    expect($record->deleted_at)->toBe($record->updated_at);
});

test('runSoftDelete without timestamps skips lines 95-99', function () {
    // Insert a test record
    $connection = Model::getConnection();
    $connection->table('direct_test')->insert(['id' => 3, 'name' => 'Test3', 'deleted_at' => null, 'updated_at' => null]);

    $model = new DirectExecutionModel();
    $model->id = 3;
    $model->timestamps = false; // Disable timestamps

    // Use reflection to call runSoftDelete
    $reflection = new ReflectionClass($model);
    $method = $reflection->getMethod('runSoftDelete');
    $method->setAccessible(true);

    // Execute runSoftDelete with timestamps disabled
    $result = $method->invoke($model);

    expect($result)->toBe(true);

    // Only deleted_at should be set
    expect($model->deleted_at)->not()->toBeNull();

    // Database should only have deleted_at updated
    $record = $connection->table('direct_test')->where('id', 3)->first();
    expect($record->deleted_at)->not()->toBeNull();
    expect($record->updated_at)->toBeNull(); // Should remain null
});

test('performDeleteOnModel with forceDeleting=false calls runSoftDelete', function () {
    // Insert a test record
    $connection = Model::getConnection();
    $connection->table('direct_test')->insert(['id' => 4, 'name' => 'Test4']);

    $model = new DirectExecutionModel();
    $model->id = 4;

    // Use reflection to ensure forceDeleting is false and call performDeleteOnModel
    $reflection = new ReflectionClass($model);

    // Set forceDeleting to false
    $prop = $reflection->getProperty('forceDeleting');
    $prop->setAccessible(true);
    $prop->setValue($model, false);

    // Get the protected method
    $method = $reflection->getMethod('performDeleteOnModel');
    $method->setAccessible(true);

    // Execute performDeleteOnModel - this will run line 76 (return $this->runSoftDelete();)
    $result = $method->invoke($model);

    // Should return true (from runSoftDelete)
    expect($result)->toBe(true);

    // Record should be soft deleted, not hard deleted
    $record = $connection->table('direct_test')->where('id', 4)->first();
    expect($record)->not()->toBeNull(); // Record still exists
    expect($record->deleted_at)->not()->toBeNull(); // But is soft deleted
});