<?php

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Database\Migrations\Migration;
use Bob\Database\Migrations\MigrationLoaderInterface;
use Bob\Database\Migrations\MigrationRepository;
use Bob\Database\Migrations\MigrationRunner;

beforeEach(function () {
    $this->connection = Mockery::mock(Connection::class);
    $this->repository = Mockery::mock(MigrationRepository::class);
    $this->loader = Mockery::mock(MigrationLoaderInterface::class);

    $this->runner = new MigrationRunner($this->connection, $this->repository);
    $this->runner->setLoader($this->loader);
});

afterEach(function () {
    Mockery::close();
});

// Test for line 331: getBatch option in getMigrationsForRollback
test('rollback with batch option calls repository getBatch', function () {
    $batchMigrations = [
        (object) ['migration' => 'test_migration_1'],
        (object) ['migration' => 'test_migration_2'],
    ];

    $this->repository->shouldReceive('getBatch')
        ->with(5)
        ->once()
        ->andReturn($batchMigrations);

    // Use reflection to test protected method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('getMigrationsForRollback');
    $method->setAccessible(true);

    $result = $method->invoke($this->runner, ['batch' => 5]);

    expect($result)->toBe($batchMigrations);
})->group('unit', 'migrations');

// Test for line 394: resolve method when class already exists
test('resolve method with existing class', function () {
    // Create a test migration class in global scope
    if (! class_exists('TestExistingMigration')) {
        eval('
        class TestExistingMigration extends Bob\Database\Migrations\Migration {
            public function up(): void {}
            public function down(): void {}
            public function getQueries(string $direction): array { return []; }
        }
        ');
    }

    // Use reflection to access protected resolve method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('resolve');
    $method->setAccessible(true);

    $result = $method->invoke($this->runner, 'TestExistingMigration');

    expect($result)->toBeInstanceOf('TestExistingMigration');
})->group('unit', 'migrations');

// Test for line 401: Migration file not found exception
test('resolve method throws exception when migration file not found', function () {
    $this->runner->setPaths(['/non/existent/path']);

    // Use reflection to access protected resolve method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('resolve');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($this->runner, 'non_existent_migration'))
        ->toThrow(InvalidArgumentException::class, 'Migration [non_existent_migration] not found.');
})->group('unit', 'migrations');

// Test for line 408: Migration class not found in file exception
test('resolve method throws exception when class not found in file', function () {
    // Create a temporary migration file
    $tempDir = sys_get_temp_dir().'/migrations_test_'.uniqid();
    mkdir($tempDir, 0777, true);
    $migrationFile = $tempDir.'/test_migration.php';

    file_put_contents($migrationFile, '<?php // File exists but class does not');

    // Mock loader to return a non-existent class name
    $this->loader->shouldReceive('load')
        ->with($migrationFile)
        ->once()
        ->andReturn('NonExistentMigrationClass');

    $this->runner->setPaths([$tempDir]);

    // Use reflection to access protected resolve method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('resolve');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($this->runner, 'test_migration'))
        ->toThrow(InvalidArgumentException::class, 'Migration class [NonExistentMigrationClass] not found in file');

    // Cleanup
    unlink($migrationFile);
    rmdir($tempDir);
})->group('unit', 'migrations');

// Test for line 414: Class doesn't extend Migration exception
test('resolve method throws exception when class does not extend Migration', function () {
    // Create a temporary migration file
    $tempDir = sys_get_temp_dir().'/migrations_test_'.uniqid();
    mkdir($tempDir, 0777, true);
    $migrationFile = $tempDir.'/test_migration.php';

    file_put_contents($migrationFile, '<?php // File exists');

    // Mock loader to return stdClass (which exists but doesn't extend Migration)
    $this->loader->shouldReceive('load')
        ->with($migrationFile)
        ->once()
        ->andReturn('stdClass');

    $this->runner->setPaths([$tempDir]);

    // Use reflection to access protected resolve method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('resolve');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($this->runner, 'test_migration'))
        ->toThrow(InvalidArgumentException::class, 'Class [stdClass] must extend Migration.');

    // Cleanup
    unlink($migrationFile);
    rmdir($tempDir);
})->group('unit', 'migrations');

// Test for lines 432-447: getMigrationClass method
test('getMigrationClass method converts file names correctly', function () {
    // Use reflection to access protected getMigrationClass method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('getMigrationClass');
    $method->setAccessible(true);

    // Test with timestamp prefix
    $result = $method->invoke($this->runner, '2023_12_25_123456_create_users_table.php');
    expect($result)->toBe('CreateUsersTable');

    // Test with complex name
    $result = $method->invoke($this->runner, '2024_01_01_000000_add_email_to_user_profiles_table.php');
    expect($result)->toBe('AddEmailToUserProfilesTable');

    // Test without timestamp prefix
    $result = $method->invoke($this->runner, 'simple_migration_file.php');
    expect($result)->toBe('SimpleMigrationFile');
})->group('unit', 'migrations');

// Test for line 475: Early return in resolveDependency when already visited
test('resolveDependency returns early when migration already visited', function () {
    $migration = new TestMigrationWithDependencies(['dependency1']);
    $migrations = ['test_migration' => $migration];

    // Use reflection to access protected method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('resolveDependency');
    $method->setAccessible(true);

    $sorted = [];
    $visited = ['test_migration' => true]; // Already visited

    // This should return early and not add to sorted
    $method->invokeArgs($this->runner, ['test_migration', $migrations, &$sorted, &$visited]);

    expect($sorted)->toBeEmpty();
})->group('unit', 'migrations');

// Test for lines 482-483: Dependency resolution with valid dependency
test('resolveDependency resolves valid dependencies', function () {
    $migration1 = new TestMigrationWithDependencies(['migration2']);
    $migration2 = new TestMigrationWithDependencies([]);

    $migrations = [
        'migration1' => $migration1,
        'migration2' => $migration2,
    ];

    // Use reflection to access protected method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('resolveDependency');
    $method->setAccessible(true);

    $sorted = [];
    $visited = [];

    // Resolve migration1, which depends on migration2
    $method->invokeArgs($this->runner, ['migration1', $migrations, &$sorted, &$visited]);

    // migration2 should be resolved first, then migration1
    expect($sorted)->toHaveKey('migration2');
    expect($sorted)->toHaveKey('migration1');
    expect(array_keys($sorted))->toBe(['migration2', 'migration1']);
})->group('unit', 'migrations');

// Test for line 575: Migration description output in pretendToRun
test('pretendToRun outputs migration description', function () {
    $migration = new TestMigrationWithDescription;

    $outputMessages = [];
    $this->runner->setOutput(function ($message) use (&$outputMessages) {
        $outputMessages[] = $message;
    });

    // Use reflection to access protected pretendToRun method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('pretendToRun');
    $method->setAccessible(true);

    $method->invoke($this->runner, $migration, 'up');

    // Check that description was output
    $descriptionFound = false;
    foreach ($outputMessages as $message) {
        if (str_contains($message, 'Description: This migration creates the test table')) {
            $descriptionFound = true;
            break;
        }
    }

    expect($descriptionFound)->toBeTrue();
})->group('unit', 'migrations');

// Test for lines 636-644: getPaths and setPaths methods
test('paths getter and setter methods work correctly', function () {
    $paths = ['/path/to/migrations', '/another/path/to/migrations'];

    // Test setPaths
    $this->runner->setPaths($paths);

    // Test getPaths (line 636)
    expect($this->runner->getPaths())->toBe($paths);

    // Test addPath method doesn't duplicate paths
    $this->runner->addPath('/path/to/migrations'); // Already exists
    expect($this->runner->getPaths())->toHaveCount(2);

    // Test addPath with new path
    $this->runner->addPath('/new/path');
    expect($this->runner->getPaths())->toHaveCount(3);
    expect($this->runner->getPaths())->toContain('/new/path');
})->group('unit', 'migrations');

// Test for line 685: getRan method
test('getRan method returns ran migrations array', function () {
    // Initially should be empty
    expect($this->runner->getRan())->toBe([]);

    // Use reflection to set the ran array
    $reflection = new \ReflectionClass($this->runner);
    $property = $reflection->getProperty('ran');
    $property->setAccessible(true);
    $property->setValue($this->runner, ['migration1', 'migration2']);

    // Test getRan (line 685)
    expect($this->runner->getRan())->toBe(['migration1', 'migration2']);
})->group('unit', 'migrations');

// Additional test for findMigrationFile method (covers line 432 return null)
test('findMigrationFile returns null when file not found', function () {
    $this->runner->setPaths(['/non/existent/path']);

    // Use reflection to access protected method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('findMigrationFile');
    $method->setAccessible(true);

    $result = $method->invoke($this->runner, 'non_existent_file');

    expect($result)->toBeNull();
})->group('unit', 'migrations');

// Test migration with empty description (should NOT output description line)
test('pretendToRun skips empty description', function () {
    $migration = new TestMigrationWithEmptyDescription;

    $outputMessages = [];
    $this->runner->setOutput(function ($message) use (&$outputMessages) {
        $outputMessages[] = $message;
    });

    // Use reflection to access protected pretendToRun method
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('pretendToRun');
    $method->setAccessible(true);

    $method->invoke($this->runner, $migration, 'down');

    // Now we output: "Would run:", "Transaction:", and potentially other info
    // Main assertion: no description should be added
    expect($outputMessages[0])->toContain('Would run:');

    // Check no description message was added (line 575 should be skipped)
    $hasDescriptionMessage = false;
    foreach ($outputMessages as $message) {
        if (str_contains($message, '  Description: ')) { // Look for the specific format from line 575
            $hasDescriptionMessage = true;
            break;
        }
    }
    expect($hasDescriptionMessage)->toBeFalse();
})->group('unit', 'migrations');

/**
 * Test migration with description
 */
class TestMigrationWithDescription extends Migration
{
    public function up(): void {}

    public function down(): void {}

    public function description(): string
    {
        return 'This migration creates the test table';
    }

    public function getQueries(string $direction): array
    {
        return ['create table test (id int)'];
    }
}

/**
 * Test migration with empty description (returns empty string)
 */
class TestMigrationWithEmptyDescription extends Migration
{
    public function up(): void {}

    public function down(): void {}

    public function description(): string
    {
        return ''; // Empty string should be falsy in if condition
    }

    public function getQueries(string $direction): array
    {
        return ['create table test (id int)'];
    }
}

/**
 * Test migration with dependencies
 */
class TestMigrationWithDependencies extends Migration
{
    private array $deps;

    public function __construct(array $dependencies = [])
    {
        $this->deps = $dependencies;
    }

    public function up(): void {}

    public function down(): void {}

    public function dependencies(): array
    {
        return $this->deps;
    }

    public function getQueries(string $direction): array
    {
        return ['create table test (id int)'];
    }
}
// Test for lines 703-704: STATUS_CHECK event
test('status dispatches STATUS_CHECK event', function () {
    $dispatcher = Mockery::mock(\Bob\Events\EventDispatcherInterface::class);

    $this->repository->shouldReceive('repositoryExists')->andReturn(true);
    $this->repository->shouldReceive('getRan')->andReturn([]);
    $this->repository->shouldReceive('getMigrationBatches')->andReturn([]);
    $this->loader->shouldReceive('getMigrationFiles')->andReturn([]);

    $dispatcher->shouldReceive('dispatch')
        ->with(\Bob\Database\Migrations\MigrationEvents::STATUS_CHECK, Mockery::on(function ($payload) {
            return isset($payload['status']) &&
                   isset($payload['status']['ran']) &&
                   isset($payload['status']['pending']);
        }))
        ->once();

    $this->runner->setEventDispatcher($dispatcher);
    $this->runner->status();
})->group('unit', 'migrations');

// Test for lines 834-836: ERROR event in onError
test('onError dispatches ERROR event when set', function () {
    $dispatcher = Mockery::mock(\Bob\Events\EventDispatcherInterface::class);
    $exception = new \Exception('Test error');

    $dispatcher->shouldReceive('dispatch')
        ->with(\Bob\Database\Migrations\MigrationEvents::ERROR, Mockery::on(function ($payload) use ($exception) {
            return $payload['exception'] === $exception &&
                   $payload['migration'] === 'test_migration';
        }))
        ->once();

    $this->runner->setEventDispatcher($dispatcher);

    // Use reflection to call protected onError
    $reflection = new \ReflectionClass($this->runner);
    $method = $reflection->getMethod('onError');
    $method->setAccessible(true);
    $method->invoke($this->runner, $exception, 'test_migration');
})->group('unit', 'migrations');
