<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Database\Migrations\DefaultMigrationLoader;
use Bob\Database\Migrations\Migration;
use Bob\Database\Migrations\MigrationLoaderInterface;
use Bob\Database\Migrations\MigrationRepository;
use Bob\Database\Migrations\MigrationRunner;

beforeEach(function () {
    // Create an in-memory SQLite database for testing
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $this->repository = new MigrationRepository($this->connection, 'migrations');
    $this->migrationPath = __DIR__ . '/test_migrations';

    // Create test migration directory
    if (!is_dir($this->migrationPath)) {
        mkdir($this->migrationPath, 0777, true);
    }

    $this->runner = new MigrationRunner(
        $this->connection,
        $this->repository,
        [$this->migrationPath]
    );
});

afterEach(function () {
    // Clean up test migrations
    if (is_dir($this->migrationPath)) {
        array_map('unlink', glob($this->migrationPath . '/*.php'));
        rmdir($this->migrationPath);
    }
});

function createTestMigration(string $className, string $migrationPath): void
{
    $file = $migrationPath . '/' . strtolower($className) . '.php';
    $content = "<?php
use Bob\Database\Migrations\Migration;
use Bob\Schema\Blueprint;
use Bob\Schema\Schema;

class {$className} extends Migration
{
    public function up(): void
    {
        Schema::create('test_table', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('test_table');
    }
}";
    file_put_contents($file, $content);
}

function createFailingMigration(string $migrationPath): void
{
    $file = $migrationPath . '/failing_migration.php';
    $content = '<?php
use Bob\Database\Migrations\Migration;

class FailingMigration extends Migration
{
    public function up(): void
    {
        throw new \Exception("Migration failed!");
    }

    public function down(): void
    {
        // Nothing
    }
}';
    file_put_contents($file, $content);
}

test('custom migration loader', function () {
    // Create a custom loader
    $customLoader = new class implements MigrationLoaderInterface {
        public array $loadedFiles = [];

        public function load(string $file): string
        {
            $this->loadedFiles[] = $file;
            require_once $file;
            return $this->extractClassName($file);
        }

        public function extractClassName(string $file): string
        {
            return 'TestMigration';
        }

        public function isValidMigration(string $file): bool
        {
            return true;
        }
    };

    // Set the custom loader
    $this->runner->setLoader($customLoader);

    // Create a test migration
    createTestMigration('TestMigration', $this->migrationPath);

    // Run migrations
    $this->runner->run();

    // Verify the custom loader was used
    expect($customLoader->loadedFiles)->toHaveCount(1);
    expect($customLoader->loadedFiles[0])->toContain('testmigration.php');
})->group('feature', 'migrations');

test('default migration loader', function () {
    $loader = new DefaultMigrationLoader();

    // Test extractClassName with date prefix
    $className = $loader->extractClassName('2024_01_01_000000_create_users_table.php');
    expect($className)->toBe('CreateUsersTable');

    // Test extractClassName without date prefix
    $className = $loader->extractClassName('create_posts_table.php');
    expect($className)->toBe('CreatePostsTable');

    // Test isValidMigration
    $validFile = $this->migrationPath . '/test.php';
    file_put_contents($validFile, '<?php class TestMigration {}');
    expect($loader->isValidMigration($validFile))->toBeTrue();
    unlink($validFile);

    // Test invalid migration (not a PHP file)
    expect($loader->isValidMigration('test.txt'))->toBeFalse();
})->group('feature', 'migrations');

test('gmt timestamps', function () {
    // Create and run a migration
    createTestMigration('TimestampTest', $this->migrationPath);
    $this->runner->run();

    // Check that the timestamp is in GMT
    $logs = $this->connection->table('migrations')->get();
    expect($logs)->toHaveCount(1);

    $timestamp = $logs[0]->executed_at;
    expect($timestamp)->not->toBeNull();

    // Verify it's a valid datetime
    $date = \DateTime::createFromFormat('Y-m-d H:i:s', $timestamp, new \DateTimeZone('UTC'));
    expect($date)->toBeInstanceOf(\DateTime::class);
})->group('feature', 'migrations');

test('error handler', function () {
    $errorHandlerCalled = false;
    $capturedError = null;
    $capturedFile = null;

    // Set a custom error handler
    $this->runner->setErrorHandler(function (Exception $e, string $file) use (&$errorHandlerCalled, &$capturedError, &$capturedFile) {
        $errorHandlerCalled = true;
        $capturedError = $e;
        $capturedFile = $file;
    });

    // Create a migration that will fail
    createFailingMigration($this->migrationPath);

    // Run migrations and expect an exception
    expect(fn() => $this->runner->run())
        ->toThrow(Exception::class, 'Migration failed!');

    // Verify error handler was called
    expect($errorHandlerCalled)->toBeTrue();
    expect($capturedError)->toBeInstanceOf(Exception::class);
    expect($capturedFile)->toContain('failing_migration');
})->group('feature', 'migrations');

test('lifecycle hooks', function () {
    // Create a custom runner with lifecycle hooks
    $customRunner = new class($this->connection, $this->repository, [$this->migrationPath]) extends MigrationRunner {
        public bool $beforeRunCalled = false;
        public bool $afterRunCalled = false;
        public array $migrationsRun = [];

        protected function beforeRun(): void
        {
            $this->beforeRunCalled = true;
        }

        protected function afterRun(array $migrations): void
        {
            $this->afterRunCalled = true;
            $this->migrationsRun = $migrations;
        }
    };

    // Create a test migration
    createTestMigration('LifecycleTest', $this->migrationPath);

    // Run migrations
    $customRunner->run();

    // Verify lifecycle hooks were called
    expect($customRunner->beforeRunCalled)->toBeTrue();
    expect($customRunner->afterRunCalled)->toBeTrue();
    expect($customRunner->migrationsRun)->toHaveCount(1);
})->group('feature', 'migrations');

test('error hook', function () {
    // Create a custom runner with error hook
    $customRunner = new class($this->connection, $this->repository, [$this->migrationPath]) extends MigrationRunner {
        public bool $errorHookCalled = false;
        public ?Exception $capturedError = null;
        public ?string $capturedMigration = null;

        protected function onError(Exception $e, string $migration): void
        {
            $this->errorHookCalled = true;
            $this->capturedError = $e;
            $this->capturedMigration = $migration;
        }
    };

    // Create a failing migration
    createFailingMigration($this->migrationPath);

    // Run migrations and expect an exception
    expect(fn() => $customRunner->run())
        ->toThrow(Exception::class, 'Migration failed!');

    // Verify error hook was called
    expect($customRunner->errorHookCalled)->toBeTrue();
    expect($customRunner->capturedError)->toBeInstanceOf(Exception::class);
    expect($customRunner->capturedMigration)->toContain('failing_migration');
})->group('feature', 'migrations');

test('loader validation', function () {
    $loader = new DefaultMigrationLoader();

    // Create a PHP file without a class
    $invalidFile = $this->migrationPath . '/no_class.php';
    file_put_contents($invalidFile, '<?php // No class here');
    expect($loader->isValidMigration($invalidFile))->toBeFalse();
    unlink($invalidFile);

    // Create a valid PHP file with a class
    $validFile = $this->migrationPath . '/valid.php';
    file_put_contents($validFile, '<?php class ValidMigration {}');
    expect($loader->isValidMigration($validFile))->toBeTrue();
    unlink($validFile);

    // Test non-existent file
    expect($loader->isValidMigration('/non/existent/file.php'))->toBeFalse();
})->group('feature', 'migrations');

test('loader class detection', function () {
    $loader = new DefaultMigrationLoader();

    // Create a migration file
    $file = $this->migrationPath . '/test_migration.php';
    $content = '<?php
namespace Tests\Feature\Migrations;

use Bob\Database\Migrations\Migration;
use Bob\Schema\Blueprint;
use Bob\Schema\Schema;

class TestClassDetection extends Migration
{
    public function up(): void
    {
        Schema::create("test_table", function (Blueprint $table) {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::drop("test_table");
    }
}';
    file_put_contents($file, $content);

    // Load the file
    $className = $loader->load($file);
    expect($className)->toBe('Tests\Feature\Migrations\TestClassDetection');

    // Clean up
    unlink($file);
})->group('feature', 'migrations');

