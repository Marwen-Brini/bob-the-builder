<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Database\Eloquent\SoftDeletingScope;
use Bob\Query\Builder;
use Mockery as m;

/**
 * Tests for executing the actual code paths in SoftDeletingScope
 * to improve coverage of lines 49-54, 81-83, 96-100, 113-119, 132-138, 151-153, 166-168
 */

class ScopeExecutionTestModel extends Model
{
    protected string $table = 'test_table';

    public function getDeletedAtColumn(): string
    {
        return 'deleted_at';
    }

    public function getQualifiedDeletedAtColumn(): string
    {
        return 'test_table.deleted_at';
    }

    public function freshTimestampString(): string
    {
        return '2024-01-01 00:00:00';
    }
}

afterEach(function () {
    m::close();
});

test('onDelete callback code path', function () {
    // The onDelete method doesn't exist in Bob's Builder class
    // We'll test that the extend method tries to set it
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);

    // Mock all the macro calls for the extensions
    $builder->shouldReceive('macro')->times(6); // For all 6 extensions

    // Mock the onDelete call - it will try to call it even if method doesn't exist
    $builder->shouldReceive('onDelete')->once()->with(m::type('Closure'))->andReturnSelf();

    // This will execute lines 42-54 including the onDelete registration
    $scope->extend($builder);

    // Verify the extensions were attempted to be added
    expect(true)->toBeTrue();
});

test('restore macro executes restoration logic', function () {
    $scope = new SoftDeletingScope();

    // Test that the restore macro is properly defined
    $restoreClosure = null;
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')->with('restore', m::type('Closure'))->once()->andReturnUsing(function($name, $closure) use (&$restoreClosure) {
        $restoreClosure = $closure;
    });

    // Call addRestore directly to test it
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addRestore');
    $method->setAccessible(true);
    $method->invoke($scope, $builder);

    // Verify the closure was created
    expect($restoreClosure)->toBeCallable();

    // Test the closure logic - covers lines 81-83
    $mockBuilder = m::mock(Builder::class);
    $mockModel = m::mock(Model::class);
    $mockModel->shouldReceive('getDeletedAtColumn')->once()->andReturn('deleted_at');
    $mockBuilder->shouldReceive('withTrashed')->once()->andReturnSelf();
    $mockBuilder->shouldReceive('getModel')->once()->andReturn($mockModel);
    $mockBuilder->shouldReceive('update')->once()->with(['deleted_at' => null])->andReturn(1);

    $result = $restoreClosure($mockBuilder);
    expect($result)->toBe(1);
});

test('withTrashed macro with false calls withoutTrashed', function () {
    $scope = new SoftDeletingScope();

    // Create mock builder
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')->with('withTrashed', m::type('Closure'))->once()->andReturnUsing(function ($name, $closure) use ($builder) {
        // Execute the closure immediately to test it
        $mockBuilder = m::mock(Builder::class);
        $mockBuilder->shouldReceive('withoutTrashed')->once()->andReturnSelf();

        // Execute with false parameter - covers lines 96-98
        $result = $closure($mockBuilder, false);

        expect($result)->toBe($mockBuilder);
    });

    // Add the macro
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addWithTrashed');
    $method->setAccessible(true);
    $method->invoke($scope, $builder);
});

test('withTrashed macro with true removes global scope', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')->with('withTrashed', m::type('Closure'))->once()->andReturnUsing(function ($name, $closure) use ($scope) {
        $mockBuilder = m::mock(Builder::class);
        $mockBuilder->shouldReceive('withoutGlobalScope')->once()->with($scope)->andReturnSelf();

        // Execute with true parameter - covers line 100
        $result = $closure($mockBuilder, true);

        expect($result)->toBe($mockBuilder);
    });

    // Add the macro
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addWithTrashed');
    $method->setAccessible(true);
    $method->invoke($scope, $builder);
});

test('withoutTrashed macro adds whereNull constraint', function () {
    $scope = new SoftDeletingScope();

    // Test that the withoutTrashed macro is properly defined
    $withoutTrashedClosure = null;
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')->with('withoutTrashed', m::type('Closure'))->once()->andReturnUsing(function($name, $closure) use (&$withoutTrashedClosure) {
        $withoutTrashedClosure = $closure;
    });

    // Call addWithoutTrashed directly
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addWithoutTrashed');
    $method->setAccessible(true);
    $method->invoke($scope, $builder);

    // Test the closure logic - covers lines 113-119
    $mockBuilder = m::mock(Builder::class);
    $mockModel = m::mock(Model::class);
    $mockModel->shouldReceive('getQualifiedDeletedAtColumn')->once()->andReturn('test_table.deleted_at');
    $mockBuilder->shouldReceive('getModel')->once()->andReturn($mockModel);
    $mockBuilder->shouldReceive('withoutGlobalScope')->once()->with($scope)->andReturnSelf();
    $mockBuilder->shouldReceive('whereNull')->once()->with('test_table.deleted_at')->andReturnSelf();

    $result = $withoutTrashedClosure($mockBuilder);
    expect($result)->toBe($mockBuilder);
});

test('onlyTrashed macro adds whereNotNull constraint', function () {
    $scope = new SoftDeletingScope();

    // Test that the onlyTrashed macro is properly defined
    $onlyTrashedClosure = null;
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')->with('onlyTrashed', m::type('Closure'))->once()->andReturnUsing(function($name, $closure) use (&$onlyTrashedClosure) {
        $onlyTrashedClosure = $closure;
    });

    // Call addOnlyTrashed directly
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addOnlyTrashed');
    $method->setAccessible(true);
    $method->invoke($scope, $builder);

    // Test the closure logic - covers lines 132-138
    $mockBuilder = m::mock(Builder::class);
    $mockModel = m::mock(Model::class);
    $mockModel->shouldReceive('getQualifiedDeletedAtColumn')->once()->andReturn('test_table.deleted_at');
    $mockBuilder->shouldReceive('getModel')->once()->andReturn($mockModel);
    $mockBuilder->shouldReceive('withoutGlobalScope')->once()->with($scope)->andReturnSelf();
    $mockBuilder->shouldReceive('whereNotNull')->once()->with('test_table.deleted_at')->andReturnSelf();

    $result = $onlyTrashedClosure($mockBuilder);
    expect($result)->toBe($mockBuilder);
});

test('restoreOrCreate macro calls firstOrCreate with trashed', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')->with('restoreOrCreate', m::type('Closure'))->once()->andReturnUsing(function ($name, $closure) {
        $mockBuilder = m::mock(Builder::class);
        $mockBuilder->shouldReceive('withTrashed')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('firstOrCreate')
            ->once()
            ->with(['id' => 1], ['name' => 'test'])
            ->andReturn((object)['id' => 1, 'name' => 'test']);

        // Execute the closure - covers lines 151-153
        $result = $closure($mockBuilder, ['id' => 1], ['name' => 'test']);

        expect($result)->toBeObject();
        expect($result->id)->toBe(1);
    });

    // Add the macro
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addRestoreOrCreate');
    $method->setAccessible(true);
    $method->invoke($scope, $builder);
});

test('createOrRestore macro calls createOrFirst with trashed', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')->with('createOrRestore', m::type('Closure'))->once()->andReturnUsing(function ($name, $closure) {
        $mockBuilder = m::mock(Builder::class);
        $mockBuilder->shouldReceive('withTrashed')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('createOrFirst')
            ->once()
            ->with(['id' => 1], ['name' => 'test'])
            ->andReturn((object)['id' => 1, 'name' => 'test']);

        // Execute the closure - covers lines 166-168
        $result = $closure($mockBuilder, ['id' => 1], ['name' => 'test']);

        expect($result)->toBeObject();
        expect($result->id)->toBe(1);
    });

    // Add the macro
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addCreateOrRestore');
    $method->setAccessible(true);
    $method->invoke($scope, $builder);
});