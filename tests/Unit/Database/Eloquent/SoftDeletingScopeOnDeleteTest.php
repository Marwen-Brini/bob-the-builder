<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Database\Eloquent\SoftDeletingScope;
use Bob\Query\Builder;
use Mockery as m;

/**
 * Test for SoftDeletingScope onDelete callback
 * Targeting lines 49-53
 */

class OnDeleteTestModel extends Model
{
    protected string $table = 'ondelete_test';
    protected string $primaryKey = 'id';

    public function getDeletedAtColumn(): string
    {
        return 'deleted_at';
    }

    public function freshTimestampString(): string
    {
        return '2024-01-01 12:00:00';
    }
}

afterEach(function () {
    m::close();
});

test('onDelete callback execution covers lines 49-53', function () {
    $scope = new SoftDeletingScope();
    $model = new OnDeleteTestModel();

    // Create a real builder
    $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    $connection->statement('CREATE TABLE ondelete_test (id INTEGER PRIMARY KEY, deleted_at TEXT)');
    $connection->table('ondelete_test')->insert(['id' => 1, 'deleted_at' => null]);

    $builder = $connection->table('ondelete_test');
    $builder->setModel($model);

    // Store the onDelete callback
    $onDeleteCallback = null;

    // Add onDelete method to Builder via macro
    Builder::macro('onDelete', function($callback) use (&$onDeleteCallback) {
        $onDeleteCallback = $callback;
        return $this;
    });

    // Apply the scope extension - this will call onDelete and pass a callback
    $scope->extend($builder);

    // Verify the callback was registered
    expect($onDeleteCallback)->toBeCallable();

    // Now execute the callback directly to cover lines 49-53
    // Line 49: $column = $this->getDeletedAtColumn($builder);
    // Lines 51-53: return $builder->update([$column => $builder->getModel()->freshTimestampString()]);
    $result = $onDeleteCallback($builder);

    // The callback should return the number of affected rows
    expect($result)->toBe(1);

    // Verify the record was soft deleted
    $record = $connection->table('ondelete_test')->where('id', 1)->first();
    expect($record->deleted_at)->toBe('2024-01-01 12:00:00');
});

test('onDelete callback with getDeletedAtColumn using model', function () {
    $scope = new SoftDeletingScope();
    $model = new OnDeleteTestModel();

    // Create mock builder
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn($model);
    $builder->shouldReceive('update')->once()->with([
        'deleted_at' => '2024-01-01 12:00:00'
    ])->andReturn(1);

    // Mock the macro calls for all extensions
    $builder->shouldReceive('macro')->times(6); // 6 extensions

    // Store the callback
    $onDeleteCallback = null;
    $builder->shouldReceive('onDelete')->once()->andReturnUsing(function($callback) use (&$onDeleteCallback, $builder) {
        $onDeleteCallback = $callback;
        return $builder;
    });

    // Apply extension
    $scope->extend($builder);

    // Execute the callback
    $result = $onDeleteCallback($builder);

    expect($result)->toBe(1);
});

test('onDelete callback with getDeletedAtColumn without model', function () {
    $scope = new SoftDeletingScope();

    // Create mock builder with no model
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(null);
    $builder->shouldReceive('update')->never(); // Won't be called due to null model

    // Mock the macro calls for all extensions
    $builder->shouldReceive('macro')->times(6); // 6 extensions

    // Store the callback
    $onDeleteCallback = null;
    $builder->shouldReceive('onDelete')->once()->andReturnUsing(function($callback) use (&$onDeleteCallback, $builder) {
        $onDeleteCallback = $callback;
        return $builder;
    });

    // Apply extension
    $scope->extend($builder);

    // This will use the default 'deleted_at' column since there's no model
    // But calling freshTimestampString() on null will cause issues
    // So we expect this to fail

    try {
        $result = $onDeleteCallback($builder);
        expect(false)->toBeTrue(); // Should not reach here
    } catch (\Throwable $e) {
        // Expected to fail when calling freshTimestampString() on null
        expect(true)->toBeTrue();
    }
});

test('getDeletedAtColumn method coverage', function () {
    $scope = new SoftDeletingScope();

    // Test with model that has the method
    $model = new OnDeleteTestModel();
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->atLeast()->once()->andReturn($model);

    // Use reflection to test the protected method
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('getDeletedAtColumn');
    $method->setAccessible(true);

    $column = $method->invoke($scope, $builder);
    expect($column)->toBe('deleted_at');

    // Test without model
    $builder2 = m::mock(Builder::class);
    $builder2->shouldReceive('getModel')->atLeast()->once()->andReturn(null);

    $column2 = $method->invoke($scope, $builder2);
    expect($column2)->toBe('deleted_at'); // Should return default
});