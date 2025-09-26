<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Database\Eloquent\SoftDeletingScope;
use Bob\Query\Builder;
use Mockery as m;

class SoftDeletingScopeTestModel extends Model
{
    protected string $table = 'test_table';

    public function getQualifiedDeletedAtColumn(): string
    {
        return 'test_table.deleted_at';
    }

    public function getDeletedAtColumn(): string
    {
        return 'deleted_at';
    }
}

afterEach(function () {
    m::close();
});

test('apply adds whereNull constraint', function () {
    $scope = new SoftDeletingScope();
    $model = new SoftDeletingScopeTestModel();

    // Use a real builder instead of mock to avoid method not found issues
    $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    $builder = $connection->table('test_table');

    $scope->apply($builder, $model);

    // Check that whereNull was applied
    $wheres = $builder->wheres;
    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['type'])->toBe('Null');
    expect($wheres[0]['column'])->toBe('test_table.deleted_at');
});

test('extend adds builder extensions', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);

    // The extend method should add macros and onDelete callback
    $builder->shouldReceive('macro')->times(6);
    $builder->shouldReceive('onDelete')->once();

    $scope->extend($builder);
});

test('getDeletedAtColumn gets column from model', function () {
    $scope = new SoftDeletingScope();

    $model = new SoftDeletingScopeTestModel(); // Use real model that has the method

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')
        ->atLeast()->once()
        ->andReturn($model);

    // Use reflection to test protected method
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('getDeletedAtColumn');
    $method->setAccessible(true);

    $result = $method->invoke($scope, $builder);

    expect($result)->toBe('deleted_at');
});

test('getDeletedAtColumn returns default when no model', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')
        ->once()
        ->andReturn(null);

    // Use reflection to test protected method
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('getDeletedAtColumn');
    $method->setAccessible(true);

    $result = $method->invoke($scope, $builder);

    expect($result)->toBe('deleted_at');
});

test('addRestore adds restore macro', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')
        ->once()
        ->with('restore', m::type('Closure'));

    // Use reflection to test protected method
    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addRestore');
    $method->setAccessible(true);

    $method->invoke($scope, $builder);
});

test('addWithTrashed adds withTrashed macro', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')
        ->once()
        ->with('withTrashed', m::type('Closure'));

    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addWithTrashed');
    $method->setAccessible(true);

    $method->invoke($scope, $builder);
});

test('addWithoutTrashed adds withoutTrashed macro', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')
        ->once()
        ->with('withoutTrashed', m::type('Closure'));

    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addWithoutTrashed');
    $method->setAccessible(true);

    $method->invoke($scope, $builder);
});

test('addOnlyTrashed adds onlyTrashed macro', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')
        ->once()
        ->with('onlyTrashed', m::type('Closure'));

    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addOnlyTrashed');
    $method->setAccessible(true);

    $method->invoke($scope, $builder);
});

test('addRestoreOrCreate adds restoreOrCreate macro', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')
        ->once()
        ->with('restoreOrCreate', m::type('Closure'));

    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addRestoreOrCreate');
    $method->setAccessible(true);

    $method->invoke($scope, $builder);
});

test('addCreateOrRestore adds createOrRestore macro', function () {
    $scope = new SoftDeletingScope();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('macro')
        ->once()
        ->with('createOrRestore', m::type('Closure'));

    $reflection = new ReflectionClass($scope);
    $method = $reflection->getMethod('addCreateOrRestore');
    $method->setAccessible(true);

    $method->invoke($scope, $builder);
});

test('extensions property contains all extensions', function () {
    $scope = new SoftDeletingScope();

    $reflection = new ReflectionClass($scope);
    $prop = $reflection->getProperty('extensions');
    $prop->setAccessible(true);

    $extensions = $prop->getValue($scope);

    expect($extensions)->toContain('Restore');
    expect($extensions)->toContain('RestoreOrCreate');
    expect($extensions)->toContain('CreateOrRestore');
    expect($extensions)->toContain('WithTrashed');
    expect($extensions)->toContain('WithoutTrashed');
    expect($extensions)->toContain('OnlyTrashed');
    expect($extensions)->toHaveCount(6);
});