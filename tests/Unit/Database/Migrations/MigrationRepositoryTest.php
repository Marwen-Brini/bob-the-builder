<?php

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Database\Migrations\MigrationRepository;
use Bob\Schema\Schema;

beforeEach(function () {
    // For most tests, use a mock connection
    $this->mockConnection = Mockery::mock(Connection::class);
    $this->repository = new MigrationRepository($this->mockConnection, 'migrations');

    // For actual database operations, use a real SQLite connection
    $this->realConnection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
});

afterEach(function () {
    Mockery::close();
});

// Test for line 185: setConnection() method
test('setConnection updates connection', function () {
    $newConnection = Mockery::mock(Connection::class);

    $this->repository->setConnection($newConnection);

    expect($this->repository->getConnection())->toBe($newConnection);
})->group('unit', 'migrations');

// Test for line 193: getConnection() method
test('getConnection returns connection', function () {
    expect($this->repository->getConnection())->toBe($this->mockConnection);
})->group('unit', 'migrations');

// Test for line 201: setTable() method
test('setTable updates table name', function () {
    $this->repository->setTable('custom_migrations');

    expect($this->repository->getTable())->toBe('custom_migrations');
})->group('unit', 'migrations');

// Test for line 209: getTable() method
test('getTable returns table name', function () {
    // Test default table name
    expect($this->repository->getTable())->toBe('migrations');

    // Test after setting custom table name
    $this->repository->setTable('my_migrations');
    expect($this->repository->getTable())->toBe('my_migrations');
})->group('unit', 'migrations');

// Test for lines 167-169: deleteRepository() method
test('deleteRepository drops migration table', function () {
    // Create a real repository with real connection for this test
    $realRepository = new MigrationRepository($this->realConnection, 'test_migrations');

    // First create the table to ensure it exists
    Schema::setConnection($this->realConnection);
    if (!Schema::hasTable('test_migrations')) {
        Schema::create('test_migrations', function ($table) {
            $table->id();
            $table->string('migration');
        });
    }

    // Verify table exists before deletion
    expect(Schema::hasTable('test_migrations'))->toBeTrue();

    // Now test deleteRepository
    $realRepository->deleteRepository();

    // Verify table no longer exists
    expect(Schema::hasTable('test_migrations'))->toBeFalse();
})->group('unit', 'migrations');

// Test deleteRepository with custom table name
test('deleteRepository drops custom migration table', function () {
    // Create a real repository with custom table name
    $realRepository = new MigrationRepository($this->realConnection, 'custom_migrations');

    // First create the table
    Schema::setConnection($this->realConnection);
    if (!Schema::hasTable('custom_migrations')) {
        Schema::create('custom_migrations', function ($table) {
            $table->id();
            $table->string('migration');
        });
    }

    // Verify table exists before deletion
    expect(Schema::hasTable('custom_migrations'))->toBeTrue();

    // Test deleteRepository
    $realRepository->deleteRepository();

    // Verify table no longer exists
    expect(Schema::hasTable('custom_migrations'))->toBeFalse();
})->group('unit', 'migrations');

// Additional comprehensive test to ensure all methods work together
test('repository properties can be configured', function () {
    $customConnection = Mockery::mock(Connection::class);

    // Test initial state
    expect($this->repository->getConnection())->toBe($this->mockConnection);
    expect($this->repository->getTable())->toBe('migrations');

    // Test setting new values
    $this->repository->setConnection($customConnection);
    $this->repository->setTable('my_custom_migrations');

    // Test updated state
    expect($this->repository->getConnection())->toBe($customConnection);
    expect($this->repository->getTable())->toBe('my_custom_migrations');
})->group('unit', 'migrations');