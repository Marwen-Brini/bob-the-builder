<?php

declare(strict_types=1);

namespace Bob\Database\Migrations;

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Schema;

/**
 * Migration Repository
 *
 * Manages the migrations table and tracks which migrations have been run.
 */
class MigrationRepository
{
    /**
     * The database connection
     */
    protected Connection $connection;

    /**
     * The name of the migrations table
     */
    protected string $table;

    /**
     * Create a new migration repository instance
     */
    public function __construct(Connection $connection, string $table = 'migrations')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Get the completed migrations
     */
    public function getRan(): array
    {
        return $this->table()
            ->orderBy('batch')
            ->orderBy('migration')
            ->pluck('migration');
    }

    /**
     * Get the completed migrations with batch numbers
     */
    public function getMigrations(int $steps = -1): array
    {
        $query = $this->table()->where('batch', '>=', '1');

        if ($steps > 0) {
            $batch = $this->getLastBatchNumber();
            $query->where('batch', '>=', $batch - $steps + 1);
        }

        return $query->orderBy('batch', 'desc')
            ->orderBy('migration', 'desc')
            ->get();
    }

    /**
     * Get the last migration batch
     */
    public function getLast(): array
    {
        return $this->table()
            ->where('batch', $this->getLastBatchNumber())
            ->orderBy('migration', 'desc')
            ->get();
    }

    /**
     * Get the completed migrations for a batch
     */
    public function getMigrationBatches(): array
    {
        return $this->table()
            ->orderBy('batch')
            ->orderBy('migration')
            ->pluck('batch', 'migration');
    }

    /**
     * Log that a migration was run
     */
    public function log(string $file, int $batch): void
    {
        $this->table()->insert([
            'migration' => $file,
            'batch' => $batch,
            'executed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Remove a migration from the log
     */
    public function delete(string $migration): void
    {
        $this->table()->where('migration', $migration)->delete();
    }

    /**
     * Get the next migration batch number
     */
    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last migration batch number
     */
    public function getLastBatchNumber(): int
    {
        return (int) $this->table()->max('batch');
    }

    /**
     * Get the migrations for a given batch
     */
    public function getBatch(int $batch): array
    {
        return $this->table()
            ->where('batch', $batch)
            ->orderBy('migration', 'desc')
            ->pluck('migration');
    }

    /**
     * Create the migration repository data store
     */
    public function createRepository(): void
    {
        Schema::setConnection($this->connection);

        Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
            $table->timestamp('executed_at')->nullable();

            $table->index('batch');
            $table->index('migration');
        });
    }

    /**
     * Determine if the migration repository exists
     */
    public function repositoryExists(): bool
    {
        Schema::setConnection($this->connection);

        return Schema::hasTable($this->table);
    }

    /**
     * Delete the migration repository data store
     */
    public function deleteRepository(): void
    {
        Schema::setConnection($this->connection);

        Schema::dropIfExists($this->table);
    }

    /**
     * Get a query builder for the migration table
     */
    protected function table()
    {
        return $this->connection->table($this->table);
    }

    /**
     * Set the connection to use
     */
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Get the connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Set the migrations table name
     */
    public function setTable(string $table): void
    {
        $this->table = $table;
    }

    /**
     * Get the migrations table name
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
