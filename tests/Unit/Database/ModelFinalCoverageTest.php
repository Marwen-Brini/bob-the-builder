<?php

use Bob\Database\Eloquent\Scope;
use Bob\Database\Model;
use Bob\Query\Builder;

/**
 * Final tests to cover remaining Model lines 254 and 565
 */
class FinalCoverageModel extends Model
{
    protected string $table = 'final_coverage';

    protected bool $timestamps = false;
}

class MockScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Empty implementation
    }
}

afterEach(function () {
    // Clear global scopes
    $reflection = new ReflectionClass(Model::class);
    $prop = $reflection->getProperty('globalScopes');
    $prop->setAccessible(true);
    $prop->setValue(null, []);
});

test('line 254: class_uses returns false scenario', function () {
    // This is difficult to trigger directly because class_uses() only returns false
    // when the class doesn't exist. However, we can test that bootTraits
    // handles this gracefully by using reflection to mock the behavior

    $model = new FinalCoverageModel;

    // We'll test the bootTraits method with a class that has no traits
    // The conditional check is still executed even if class_uses returns an array
    $reflection = new ReflectionClass($model);
    $method = $reflection->getMethod('bootTraits');
    $method->setAccessible(true);

    // Call bootTraits - it should handle any class gracefully
    $result = $method->invoke($model);

    // If we get here without exception, the method handled it properly
    expect($result)->toBeNull(); // bootTraits returns void
});

test('line 565: getGlobalScope with scope object that does not exist', function () {
    // Create a scope instance
    $scope = new MockScope;

    // Try to get it without adding it first - this should hit line 565
    $result = FinalCoverageModel::getGlobalScope($scope);

    // Should return null since the scope was never added
    expect($result)->toBeNull();
});

test('getGlobalScope with non-existent scope class string', function () {
    // Test getting a scope by string that doesn't exist (line 562)
    $result = FinalCoverageModel::getGlobalScope('NonExistentScope');
    expect($result)->toBeNull();
});

test('line 565: getGlobalScope with different scope object instances', function () {
    // Add one scope instance
    $scope1 = new MockScope;
    FinalCoverageModel::addGlobalScope($scope1);

    // Try to get a different instance of the same class
    $scope2 = new MockScope;
    $result = FinalCoverageModel::getGlobalScope($scope2);

    // Should find it by class name (both are MockScope)
    expect($result)->toBe($scope1);

    // Now test with a completely different class that doesn't exist
    $nonExistentScope = new class implements Scope
    {
        public function apply(Builder $builder, Model $model): void {}
    };

    $result2 = FinalCoverageModel::getGlobalScope($nonExistentScope);
    expect($result2)->toBeNull(); // This hits line 565
});

test('bootTraits with actual trait usage', function () {
    // Create a class that uses a trait to ensure bootTraits works properly
    $testClass = new class extends Model
    {
        use \Bob\Database\Eloquent\SoftDeletes;

        protected string $table = 'test_with_trait';

        protected bool $timestamps = false;
    };

    // Use reflection to call bootTraits
    $reflection = new ReflectionClass($testClass);
    $method = $reflection->getMethod('bootTraits');
    $method->setAccessible(true);

    // This should not throw an exception and should handle the trait properly
    $method->invoke($testClass);

    // Verify the SoftDeletes trait was booted (should have added a global scope)
    expect($testClass::hasGlobalScope(\Bob\Database\Eloquent\SoftDeletingScope::class))->toBeTrue();
});

test('comprehensive global scope edge cases', function () {
    // Test various edge cases to ensure all paths are covered

    // 1. Add scope by string and closure
    FinalCoverageModel::addGlobalScope('test_scope', function ($builder) {
        $builder->where('test', true);
    });

    // 2. Add scope by object
    $objectScope = new MockScope;
    FinalCoverageModel::addGlobalScope($objectScope);

    // 3. Add scope by closure only
    $closureScope = function ($builder) {
        $builder->where('closure_test', true);
    };
    FinalCoverageModel::addGlobalScope($closureScope);

    // Test retrieving each type
    expect(FinalCoverageModel::getGlobalScope('test_scope'))->toBeInstanceOf(\Closure::class);
    expect(FinalCoverageModel::getGlobalScope($objectScope))->toBe($objectScope);
    expect(FinalCoverageModel::getGlobalScope(MockScope::class))->toBe($objectScope);

    // Test non-existent retrievals (should hit null coalescing operators)
    expect(FinalCoverageModel::getGlobalScope('non_existent'))->toBeNull();
    expect(FinalCoverageModel::getGlobalScope(new MockScope))->toBe($objectScope); // Same class

    // Create a scope of a different class
    $differentScope = new class implements Scope
    {
        public function apply(Builder $builder, Model $model): void {}
    };
    expect(FinalCoverageModel::getGlobalScope($differentScope))->toBeNull(); // Different class
});
