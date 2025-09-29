<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Database\Migrations\Migration;
use Bob\Database\Migrations\MigrationRepository;
use Bob\Database\Migrations\MigrationRunner;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    Schema::setConnection($this->connection);

    $this->repository = new MigrationRepository($this->connection);
    $this->migrationPath = __DIR__ . '/stubs';
    $this->runner = new MigrationRunner($this->connection, $this->repository, [$this->migrationPath]);

    // Create migration stubs directory
    if (!is_dir($this->migrationPath)) {
        mkdir($this->migrationPath, 0755, true);
    }

    cleanupMigrationsRunner($this->migrationPath);
});

afterEach(function () {
    cleanupMigrationsRunner($this->migrationPath);
});

function cleanupMigrationsRunner(string $migrationPath): void
{
    // Drop all test tables
    Schema::dropIfExists('test_table');
    Schema::dropIfExists('users');
    Schema::dropIfExists('posts');
    Schema::dropIfExists('migrations');

    // Clean up migration files
    if (is_dir($migrationPath)) {
        array_map('unlink', glob($migrationPath . '/*.php'));
    }
}

function createMigrationFile(string $migrationPath, string $name, string $className, callable $content): void
{
    $filePath = $migrationPath . '/' . $name . '.php';
    $content = $content();
    // Replace all known class names with the provided className
    $content = str_replace('CreateTestTable', $className, $content);
    $content = str_replace('CreateUsersTable', $className, $content);
    $content = str_replace('CreatePostsTable', $className, $content);
    $content = str_replace('TransactionalMigration', $className, $content);
    file_put_contents($filePath, $content);
}

function getSimpleTableMigration(): string
{
    return <<<'PHP'
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

class CreateTestTable extends Migration
{
    public function up(): void
    {
        Schema::create('test_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_table');
    }
}
PHP;
}

function getUsersTableMigration(): string
{
    return <<<'PHP'
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}
PHP;
}

function getPostsTableMigration(): string
{
    return <<<'PHP'
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

class CreatePostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
}
PHP;
}

test('create migration repository', function () {
    expect($this->repository->repositoryExists())->toBeFalse();

    $this->repository->createRepository();

    expect($this->repository->repositoryExists())->toBeTrue();
    expect(Schema::hasTable('migrations'))->toBeTrue();
})->group('feature', 'migrations');

test('run single migration', function () {
    // Create a migration file
    createMigrationFile($this->migrationPath, '2024_01_01_000001_create_test_table', 'CreateTestTable', function() {
        return getSimpleTableMigration();
    });

    // Run migrations
    $migrations = $this->runner->run();

    expect($migrations)->toHaveCount(1);
    expect(Schema::hasTable('test_table'))->toBeTrue();

    // Check migration was logged
    $ran = $this->repository->getRan();
    expect($ran)->toContain('2024_01_01_000001_create_test_table');
})->group('feature', 'migrations');

test('run multiple migrations', function () {
    // Create multiple migration files
    createMigrationFile($this->migrationPath, '2024_01_01_000001_create_users_table', 'CreateUsersTable', function() {
        return getUsersTableMigration();
    });

    createMigrationFile($this->migrationPath, '2024_01_01_000002_create_posts_table', 'CreatePostsTable', function() {
        return getPostsTableMigration();
    });

    // Run migrations
    $migrations = $this->runner->run();

    expect($migrations)->toHaveCount(2);
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasTable('posts'))->toBeTrue();

    // Check both migrations were logged
    $ran = $this->repository->getRan();
    expect($ran)->toHaveCount(2);
})->group('feature', 'migrations');

test('rollback last batch', function () {
    createMigrationFile($this->migrationPath, '2024_01_01_000001_create_test_table', 'CreateTestTable', function() {
        return getSimpleTableMigration();
    });

    // Run migration
    $this->runner->run();
    expect(Schema::hasTable('test_table'))->toBeTrue();

    // Rollback
    $rolled = $this->runner->rollback();
    expect($rolled)->toHaveCount(1);
    expect(Schema::hasTable('test_table'))->toBeFalse();

    // Check migration was removed from repository
    $ran = $this->repository->getRan();
    expect($ran)->toHaveCount(0);
})->group('feature', 'migrations');

test('rollback by steps', function () {
    // Create and run 3 migrations in different batches
    createMigrationFile($this->migrationPath, '2024_01_01_000001_create_users_table', 'CreateUsersTable', function() {
        return getUsersTableMigration();
    });
    $this->runner->run(); // Batch 1

    createMigrationFile($this->migrationPath, '2024_01_01_000002_create_posts_table', 'CreatePostsTable', function() {
        return getPostsTableMigration();
    });
    $this->runner->run(); // Batch 2

    createMigrationFile($this->migrationPath, '2024_01_01_000003_create_test_table', 'CreateTestTable3', function() {
        return getSimpleTableMigration();
    });
    $this->runner->run(); // Batch 3

    // Rollback 2 steps
    $rolled = $this->runner->rollback(['step' => 2]);

    expect($rolled)->toHaveCount(2);
    expect(Schema::hasTable('users'))->toBeTrue(); // Should still exist
    expect(Schema::hasTable('posts'))->toBeFalse(); // Should be rolled back
    expect(Schema::hasTable('test_table'))->toBeFalse(); // Should be rolled back
})->group('feature', 'migrations');

test('reset all migrations', function () {
    createMigrationFile($this->migrationPath, '2024_01_01_000001_create_users_table', 'CreateUsersTable', function() {
        return getUsersTableMigration();
    });

    createMigrationFile($this->migrationPath, '2024_01_01_000002_create_posts_table', 'CreatePostsTable', function() {
        return getPostsTableMigration();
    });

    // Run migrations
    $this->runner->run();
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasTable('posts'))->toBeTrue();

    // Reset all
    $rolled = $this->runner->reset();

    expect($rolled)->toHaveCount(2);
    expect(Schema::hasTable('users'))->toBeFalse();
    expect(Schema::hasTable('posts'))->toBeFalse();

    // Check no migrations in repository
    $ran = $this->repository->getRan();
    expect($ran)->toHaveCount(0);
})->group('feature', 'migrations');

test('refresh migrations', function () {
    createMigrationFile($this->migrationPath, '2024_01_01_000001_create_users_table', 'CreateUsersTable', function() {
        return getUsersTableMigration();
    });

    // Run initial migration
    $this->runner->run();

    // Insert some data
    $this->connection->table('users')->insert(['name' => 'John', 'email' => 'john@example.com']);
    expect($this->connection->table('users')->count())->toBe(1);

    // Refresh (rollback and re-run)
    $this->runner->refresh();

    // Table should exist but be empty
    expect(Schema::hasTable('users'))->toBeTrue();
    expect($this->connection->table('users')->count())->toBe(0);
})->group('feature', 'migrations');

test('migration status', function () {
    createMigrationFile($this->migrationPath, '2024_01_01_000001_create_users_table', 'CreateUsersTable', function() {
        return getUsersTableMigration();
    });

    createMigrationFile($this->migrationPath, '2024_01_01_000002_create_posts_table', 'CreatePostsTable', function() {
        return getPostsTableMigration();
    });

    // Check status before running
    $status = $this->runner->status();
    expect($status['ran'])->toHaveCount(0);
    expect($status['pending'])->toHaveCount(2);

    // Run first migration
    $this->runner->run(['batch' => 1]);

    // Check status after partial run
    $status = $this->runner->status();
    expect($status['ran'])->toHaveCount(2);
    expect($status['pending'])->toHaveCount(0);
})->group('feature', 'migrations');

test('migration with transaction', function () {
    createMigrationFile($this->migrationPath, '2024_01_01_000001_transactional_migration', 'TransactionalMigration', function() {
        return <<<'PHP'
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

class TransactionalMigration extends Migration
{
    protected bool $withinTransaction = true;

    public function up(): void
    {
        Schema::create('test_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // This would normally fail if table doesn't exist
        // But transaction will rollback everything
        if (!Schema::hasTable('non_existent_table')) {
            throw new Exception('Intentional failure');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('test_table');
    }
}
PHP;
    });

    // Migration should fail and rollback
    expect(fn() => $this->runner->run())
        ->toThrow(Exception::class);

    // Table should not exist due to transaction rollback
    expect(Schema::hasTable('test_table'))->toBeFalse();
})->group('feature', 'migrations');

test('pretend mode', function () {
    createMigrationFile($this->migrationPath, '2024_01_01_000001_create_test_table', 'CreateTestTable', function() {
        return getSimpleTableMigration();
    });

    // Capture output
    $output = [];
    $this->runner->setOutput(function($message) use (&$output) {
        $output[] = $message;
    });

    // Run in pretend mode
    $this->runner->run(['pretend' => true]);

    // Table should NOT be created
    expect(Schema::hasTable('test_table'))->toBeFalse();

    // But output should indicate what would happen
    expect(implode("\n", $output))->toContain('Would run');
})->group('feature', 'migrations');

test('get migration files', function () {
    createMigrationFile($this->migrationPath, '2024_01_01_000001_first', 'First', function() {
        return getSimpleTableMigration();
    });

    createMigrationFile($this->migrationPath, '2024_01_01_000003_third', 'Third', function() {
        return getSimpleTableMigration();
    });

    createMigrationFile($this->migrationPath, '2024_01_01_000002_second', 'Second', function() {
        return getSimpleTableMigration();
    });

    $files = $this->runner->getMigrationFiles();

    // Should be sorted by name
    $keys = array_keys($files);
    expect($keys[0])->toBe('2024_01_01_000001_first');
    expect($keys[1])->toBe('2024_01_01_000002_second');
    expect($keys[2])->toBe('2024_01_01_000003_third');
})->group('feature', 'migrations');

test('migration batches', function () {
    createMigrationFile($this->migrationPath, '2024_01_01_000001_batch_one', 'BatchOne', function() {
        return getSimpleTableMigration();
    });

    // Run first batch
    $this->runner->run();
    $batch1 = $this->repository->getLastBatchNumber();

    createMigrationFile($this->migrationPath, '2024_01_01_000002_batch_two', 'BatchTwo', function() {
        return getUsersTableMigration();
    });

    // Run second batch
    $this->runner->run();
    $batch2 = $this->repository->getLastBatchNumber();

    expect($batch2)->toBe($batch1 + 1);

    // Get migrations by batch
    $batch1Migrations = $this->repository->getBatch($batch1);
    $batch2Migrations = $this->repository->getBatch($batch2);

    expect($batch1Migrations)->toHaveCount(1);
    expect($batch2Migrations)->toHaveCount(1);
})->group('feature', 'migrations');

