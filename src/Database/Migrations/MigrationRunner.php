<?php

declare(strict_types=1);

namespace Bob\Database\Migrations;

use Bob\Database\Connection;
use Bob\Schema\Schema;
use Closure;
use Exception;
use InvalidArgumentException;

/**
 * Migration Runner
 *
 * Executes database migrations, handles rollbacks, and manages migration state.
 */
class MigrationRunner
{
    /**
     * The migration repository instance
     */
    protected MigrationRepository $repository;

    /**
     * The database connection
     */
    protected Connection $connection;

    /**
     * The migration loader instance
     */
    protected MigrationLoaderInterface $loader;

    /**
     * The paths to scan for migration files
     */
    protected array $paths = [];

    /**
     * The output handler for migration messages
     */
    protected ?Closure $output = null;

    /**
     * Optional error handler callback
     */
    protected ?Closure $errorHandler = null;

    /**
     * All of the migration files
     */
    protected array $files = [];

    /**
     * The migrations that have been run
     */
    protected array $ran = [];

    /**
     * Notes collected during migration run
     * @var string[]
     */
    protected array $notes = [];

    /**
     * Create a new migration runner instance
     */
    public function __construct(
        Connection $connection,
        MigrationRepository $repository,
        array $paths = []
    ) {
        $this->connection = $connection;
        $this->repository = $repository;
        $this->paths = $paths;
        $this->loader = new DefaultMigrationLoader();
    }

    /**
     * Set the migration loader
     */
    public function setLoader(MigrationLoaderInterface $loader): self
    {
        $this->loader = $loader;
        return $this;
    }

    /**
     * Set an error handler callback
     */
    public function setErrorHandler(?Closure $handler): self
    {
        $this->errorHandler = $handler;
        return $this;
    }

    /**
     * Run the pending migrations
     */
    public function run(array $options = []): array
    {
        $this->notes = [];

        // Lifecycle hook: before run
        $this->beforeRun();

        // First, ensure the repository exists
        if (!$this->repository->repositoryExists()) {
            $this->repository->createRepository();
            $this->note('Migration table created successfully.');
        }

        $this->ran = [];

        // Get all migration files and pending migrations
        $migrations = $this->getPendingMigrations(
            $this->getMigrationFiles(),
            $this->repository->getRan()
        );

        // Run the migrations
        $batch = $this->repository->getNextBatchNumber();

        if (count($migrations) === 0) {
            $this->note('Nothing to migrate.');
            $this->afterRun([]);
            return [];
        }

        $this->runPending($migrations, $batch, $options);

        // Lifecycle hook: after run
        $this->afterRun($migrations);

        return $migrations;
    }

    /**
     * Run an array of migrations
     */
    protected function runPending(array $migrations, int $batch, array $options = []): void
    {
        // First we will just make sure that there are any migrations to run
        if (count($migrations) === 0) {
            $this->note('Nothing to migrate.');
            return;
        }

        // Sort migrations by their dependencies
        $migrations = $this->resolveDependencies($migrations);

        $pretend = $options['pretend'] ?? false;

        foreach ($migrations as $file => $migration) {
            $this->runUp($file, $migration, $batch, $pretend);
        }
    }

    /**
     * Run "up" a migration instance
     */
    protected function runUp(string $file, Migration $migration, int $batch, bool $pretend = false): void
    {
        if ($pretend) {
            $this->pretendToRun($migration, 'up');
            return;
        }

        $this->note("Migrating: {$file}");

        $startTime = microtime(true);

        try {
            if ($migration->withinTransaction()) {
                $this->connection->transaction(function () use ($migration) {
                    $migration->before();
                    $migration->up();
                    $migration->after();
                });
            } else {
                $migration->before();
                $migration->up();
                $migration->after();
            }

            $this->repository->log($file, $batch);
            $this->ran[] = $file;

            $time = round((microtime(true) - $startTime) * 1000, 2);
            $this->note("Migrated:  {$file} ({$time}ms)");
        } catch (Exception $e) {
            $this->note("Migration failed: {$file}");

            // Call error handler if set
            $this->onError($e, $file);

            // Call custom error handler if provided
            if ($this->errorHandler !== null) {
                call_user_func($this->errorHandler, $e, $file);
            }

            throw $e;
        }
    }

    /**
     * Rollback the last migration operation
     */
    public function rollback(array $options = []): array
    {
        $this->notes = [];

        $rolledBack = [];

        // Get the last batch of migrations
        $migrations = $this->getMigrationsForRollback($options);

        if (count($migrations) === 0) {
            $this->note('Nothing to rollback.');
            return [];
        }

        foreach ($migrations as $migration) {
            $file = $migration->migration;
            $rolledBack[] = $file;

            $migrationInstance = $this->resolve($file);

            $this->runDown($file, $migrationInstance, $options['pretend'] ?? false);
        }

        return $rolledBack;
    }

    /**
     * Rollback all database migrations
     */
    public function reset(array $options = []): array
    {
        $this->notes = [];

        $rolledBack = [];

        // Get all migrations in reverse order
        $migrations = array_reverse($this->repository->getRan());

        if (count($migrations) === 0) {
            $this->note('Nothing to rollback.');
            return [];
        }

        foreach ($migrations as $migration) {
            $rolledBack[] = $migration;

            $migrationInstance = $this->resolve($migration);

            $this->runDown($migration, $migrationInstance, $options['pretend'] ?? false);
        }

        return $rolledBack;
    }

    /**
     * Run "down" a migration instance
     */
    protected function runDown(string $file, Migration $migration, bool $pretend = false): void
    {
        if ($pretend) {
            $this->pretendToRun($migration, 'down');
            return;
        }

        $this->note("Rolling back: {$file}");

        $startTime = microtime(true);

        try {
            if ($migration->withinTransaction()) {
                $this->connection->transaction(function () use ($migration) {
                    $migration->down();
                });
            } else {
                $migration->down();
            }

            $this->repository->delete($file);

            $time = round((microtime(true) - $startTime) * 1000, 2);
            $this->note("Rolled back:  {$file} ({$time}ms)");
        } catch (Exception $e) {
            $this->note("Rollback failed: {$file}");
            throw $e;
        }
    }

    /**
     * Rolls all migrations back then runs them again
     */
    public function refresh(array $options = []): array
    {
        $this->reset($options);

        return $this->run($options);
    }

    /**
     * Reset and re-run all migrations with optional seeding
     */
    public function fresh(array $options = []): array
    {
        // Drop all tables
        $this->dropAllTables();

        $this->note('Dropped all tables successfully.');

        // Delete the migration repository
        if ($this->repository->repositoryExists()) {
            $this->repository->deleteRepository();
        }

        return $this->run($options);
    }

    /**
     * Get the migrations for a rollback operation
     */
    protected function getMigrationsForRollback(array $options): array
    {
        if (isset($options['batch'])) {
            return $this->repository->getBatch($options['batch']);
        } elseif (isset($options['step'])) {
            return $this->repository->getMigrations($options['step']);
        } else {
            return $this->repository->getLast();
        }
    }

    /**
     * Get the migration files that have not yet run
     */
    protected function getPendingMigrations(array $files, array $ran): array
    {
        $migrations = [];

        foreach ($files as $file => $path) {
            if (!in_array($file, $ran)) {
                $migration = $this->resolve($file);

                if ($migration->shouldRun()) {
                    $migrations[$file] = $migration;
                }
            }
        }

        return $migrations;
    }

    /**
     * Get all migration files
     */
    public function getMigrationFiles(): array
    {
        $files = [];

        foreach ($this->paths as $path) {
            if (is_dir($path)) {
                foreach (glob($path . '/*.php') as $file) {
                    $files[$this->getMigrationName($file)] = $file;
                }
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Get the name of the migration from file path
     */
    protected function getMigrationName(string $path): string
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Resolve a migration instance from a file
     */
    protected function resolve(string $file): Migration
    {
        // First check if it's already a class name
        if (class_exists($file)) {
            return new $file;
        }

        // Try to find the file in our paths
        $filePath = $this->findMigrationFile($file);

        if (!$filePath) {
            throw new InvalidArgumentException("Migration [{$file}] not found.");
        }

        // Use the loader to load and get the class name
        $className = $this->loader->load($filePath);

        if (!class_exists($className)) {
            throw new InvalidArgumentException("Migration class [{$className}] not found in file [{$filePath}].");
        }

        $migration = new $className;

        if (!$migration instanceof Migration) {
            throw new InvalidArgumentException("Class [{$className}] must extend Migration.");
        }

        return $migration;
    }

    /**
     * Find the migration file path
     */
    protected function findMigrationFile(string $file): ?string
    {
        foreach ($this->paths as $path) {
            $fullPath = $path . '/' . $file . '.php';
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }

    /**
     * Get the migration class name from filename
     */
    protected function getMigrationClass(string $file): string
    {
        // Remove timestamp prefix and .php extension
        $class = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $file);
        $class = str_replace('.php', '', $class);

        // Convert to StudlyCase
        $class = str_replace(' ', '', ucwords(str_replace('_', ' ', $class)));

        return $class;
    }

    /**
     * Resolve migration dependencies and sort
     */
    protected function resolveDependencies(array $migrations): array
    {
        $sorted = [];
        $visited = [];

        foreach ($migrations as $file => $migration) {
            $this->resolveDependency($file, $migrations, $sorted, $visited);
        }

        return $sorted;
    }

    /**
     * Recursively resolve a single migration's dependencies
     */
    protected function resolveDependency(
        string $file,
        array $migrations,
        array &$sorted,
        array &$visited
    ): void {
        if (isset($visited[$file])) {
            return;
        }

        $visited[$file] = true;
        $migration = $migrations[$file];

        foreach ($migration->dependencies() as $dependency) {
            if (isset($migrations[$dependency])) {
                $this->resolveDependency($dependency, $migrations, $sorted, $visited);
            }
        }

        $sorted[$file] = $migration;
    }

    /**
     * Drop all database tables
     */
    protected function dropAllTables(): void
    {
        Schema::setConnection($this->connection);

        // This would need to be implemented per database
        $driver = $this->connection->getDriverName();

        switch ($driver) {
            case 'mysql':
                $this->dropAllMySQLTables();
                break;
            case 'pgsql':
                $this->dropAllPostgreSQLTables();
                break;
            case 'sqlite':
                $this->dropAllSQLiteTables();
                break;
            default:
                throw new InvalidArgumentException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * Drop all MySQL tables
     */
    protected function dropAllMySQLTables(): void
    {
        $this->connection->statement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = $this->connection->select("SHOW TABLES");
        $database = $this->connection->getConfig('database');

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_{$database}"} ?? array_values((array) $table)[0];
            Schema::drop($tableName);
        }

        $this->connection->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Drop all PostgreSQL tables
     */
    protected function dropAllPostgreSQLTables(): void
    {
        $tables = $this->connection->select(
            "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'"
        );

        foreach ($tables as $table) {
            Schema::drop($table->tablename);
        }
    }

    /**
     * Drop all SQLite tables
     */
    protected function dropAllSQLiteTables(): void
    {
        $this->connection->statement('PRAGMA foreign_keys = OFF');

        $tables = $this->connection->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );

        foreach ($tables as $table) {
            Schema::drop($table->name);
        }

        $this->connection->statement('PRAGMA foreign_keys = ON');
    }

    /**
     * Pretend to run the migrations
     */
    protected function pretendToRun(Migration $migration, string $method): void
    {
        $name = get_class($migration);

        $this->note("Would run: {$name}::{$method}()");

        if ($description = $migration->description()) {
            $this->note("  Description: {$description}");
        }
    }

    /**
     * Get the status of all migrations
     */
    public function status(): array
    {
        if (!$this->repository->repositoryExists()) {
            return [
                'ran' => [],
                'pending' => array_keys($this->getMigrationFiles()),
                'batches' => []
            ];
        }

        $ran = $this->repository->getRan();
        $all = array_keys($this->getMigrationFiles());
        $pending = array_diff($all, $ran);

        return [
            'ran' => $ran,
            'pending' => array_values($pending),
            'batches' => $this->repository->getMigrationBatches()
        ];
    }

    /**
     * Set the output handler
     */
    public function setOutput(Closure $output): void
    {
        $this->output = $output;
    }

    /**
     * Write a note to the output
     */
    protected function note(string $message): void
    {
        if ($this->output) {
            call_user_func($this->output, $message);
        }
    }

    /**
     * Add a migration path
     */
    public function addPath(string $path): void
    {
        if (!in_array($path, $this->paths)) {
            $this->paths[] = $path;
        }
    }

    /**
     * Get all migration paths
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Set the migration paths
     */
    public function setPaths(array $paths): void
    {
        $this->paths = $paths;
    }

    /**
     * Get the migration repository
     */
    public function getRepository(): MigrationRepository
    {
        return $this->repository;
    }

    /**
     * Set the migration repository
     */
    public function setRepository(MigrationRepository $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * Get the database connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Set the database connection
     */
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
        $this->repository->setConnection($connection);
    }

    /**
     * Get the migrations that were run
     */
    public function getRan(): array
    {
        return $this->ran;
    }

    /**
     * Lifecycle hook: Called before running migrations
     * Override this method to add custom behavior
     */
    protected function beforeRun(): void
    {
        // Extension point for subclasses
    }

    /**
     * Lifecycle hook: Called after running migrations
     * Override this method to add custom behavior
     *
     * @param array $migrations The migrations that were run
     */
    protected function afterRun(array $migrations): void
    {
        // Extension point for subclasses
    }

    /**
     * Error handler hook: Called when a migration fails
     * Override this method to add custom error handling
     *
     * @param Exception $e The exception that was thrown
     * @param string $migration The migration file that failed
     */
    protected function onError(Exception $e, string $migration): void
    {
        // Extension point for subclasses
    }
}