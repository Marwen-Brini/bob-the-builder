<?php

declare(strict_types=1);

use Bob\Database\Migrations\Migration;

beforeEach(function () {
    $this->migration = new TestMigrationForCoverage();
});

// Test for lines 42-43: getConnection() method
test('getConnection returns connection name', function () {
    // Initially should be null
    expect($this->migration->getConnection())->toBeNull();

    // After setting connection
    $this->migration->setConnection('test_connection');
    expect($this->migration->getConnection())->toBe('test_connection');
})->group('unit', 'migrations');

// Test for lines 48-50: setConnection() method
test('setConnection sets connection name', function () {
    // Test setting a connection name
    $this->migration->setConnection('custom_connection');
    expect($this->migration->getConnection())->toBe('custom_connection');

    // Test setting null
    $this->migration->setConnection(null);
    expect($this->migration->getConnection())->toBeNull();

    // Test setting empty string
    $this->migration->setConnection('');
    expect($this->migration->getConnection())->toBe('');
})->group('unit', 'migrations');

// Test for line 114: version() method
test('version returns default version', function () {
    expect($this->migration->version())->toBe('1.0.0');
})->group('unit', 'migrations');

// Additional tests to ensure other methods work correctly
test('default migration properties and methods', function () {
    // Test shouldRun() default
    expect($this->migration->shouldRun())->toBeTrue();

    // Test description() default
    expect($this->migration->description())->toBe('');

    // Test dependencies() default
    expect($this->migration->dependencies())->toBe([]);

    // Test withinTransaction() default
    expect($this->migration->withinTransaction())->toBeTrue();
})->group('unit', 'migrations');

// Test lifecycle methods exist and can be called
test('lifecycle methods can be called', function () {
    // These methods should exist and not throw errors when called
    $this->migration->before();
    $this->migration->after();

    expect(true)->toBeTrue(); // If we get here, the methods executed without errors
})->group('unit', 'migrations');

/**
 * Test migration class for coverage testing
 */
class TestMigrationForCoverage extends Migration
{
    public function up(): void
    {
        // Empty implementation for testing
    }

    public function down(): void
    {
        // Empty implementation for testing
    }

    public function getQueries(string $direction): array
    {
        return [];
    }
}