<?php

declare(strict_types=1);

namespace Bob\Database\Migrations;

use Bob\Database\Connection;

/**
 * Migration Base Class
 *
 * Abstract base class for all database migrations.
 * Provides the structure for up/down migrations with optional dependencies.
 */
abstract class Migration
{
    /**
     * The name of the database connection to use
     */
    protected ?string $connection = null;

    /**
     * Whether to wrap the migration in a transaction
     */
    protected bool $withinTransaction = true;

    /**
     * Run the migrations (apply changes)
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations (rollback changes)
     */
    abstract public function down(): void;

    /**
     * Get the migration connection name
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the migration connection
     */
    public function setConnection(?string $name): void
    {
        $this->connection = $name;
    }

    /**
     * Determine if the migration should be run within a transaction
     */
    public function withinTransaction(): bool
    {
        return $this->withinTransaction;
    }

    /**
     * Get migration dependencies
     *
     * Return an array of migration class names that must be run before this migration
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * Check if the migration should run
     *
     * Can be overridden to add conditional logic
     */
    public function shouldRun(): bool
    {
        return true;
    }

    /**
     * Get the migration description
     *
     * Optional method to provide a human-readable description
     */
    public function description(): string
    {
        return '';
    }

    /**
     * Callback to run before the migration
     */
    public function before(): void
    {
        // Can be overridden
    }

    /**
     * Callback to run after the migration
     */
    public function after(): void
    {
        // Can be overridden
    }

    /**
     * Get the migration version
     *
     * Useful for tracking migration compatibility
     */
    public function version(): string
    {
        return '1.0.0';
    }
}
