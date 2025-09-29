<?php

declare(strict_types=1);

namespace Bob\Database\Migrations;

/**
 * Migration event constants for event-driven architectures
 *
 * This interface provides standardized event names that can be used
 * when building event systems around the migration process. Framework
 * adapters can use these constants to trigger their own event systems.
 *
 * Example usage:
 * ```php
 * // In a WordPress adapter
 * do_action(MigrationEvents::BEFORE_RUN, $migrations);
 *
 * // In a Laravel adapter
 * event(MigrationEvents::AFTER_RUN, [$migrations]);
 *
 * // In a custom event system
 * $dispatcher->dispatch(MigrationEvents::ERROR, new MigrationErrorEvent($e, $file));
 * ```
 */
interface MigrationEvents
{
    /**
     * Triggered before migrations start running
     *
     * Payload: None
     * Use case: Initialize resources, logging, notifications
     */
    const BEFORE_RUN = 'migration.before_run';

    /**
     * Triggered after migrations complete successfully
     *
     * Payload: array $migrations - The migrations that were run
     * Use case: Cleanup, notifications, cache clearing
     */
    const AFTER_RUN = 'migration.after_run';

    /**
     * Triggered when a migration fails
     *
     * Payload: Exception $exception, string $migrationFile
     * Use case: Error logging, rollback triggers, alerts
     */
    const ERROR = 'migration.error';

    /**
     * Triggered before a single migration runs
     *
     * Payload: string $migrationFile, Migration $instance
     * Use case: Per-migration logging, validation
     */
    const BEFORE_MIGRATION = 'migration.before_migration';

    /**
     * Triggered after a single migration completes
     *
     * Payload: string $migrationFile, Migration $instance, float $executionTime
     * Use case: Performance monitoring, logging
     */
    const AFTER_MIGRATION = 'migration.after_migration';

    /**
     * Triggered before a rollback operation
     *
     * Payload: array $migrations - The migrations to be rolled back
     * Use case: Backup creation, confirmation prompts
     */
    const BEFORE_ROLLBACK = 'migration.before_rollback';

    /**
     * Triggered after a rollback completes
     *
     * Payload: array $migrations - The migrations that were rolled back
     * Use case: Cleanup, notifications
     */
    const AFTER_ROLLBACK = 'migration.after_rollback';

    /**
     * Triggered when the migration repository is created
     *
     * Payload: None
     * Use case: Initial setup, first-run operations
     */
    const REPOSITORY_CREATED = 'migration.repository_created';

    /**
     * Triggered when checking migration status
     *
     * Payload: array $status - Contains 'ran', 'pending', 'batches'
     * Use case: Dashboard updates, monitoring
     */
    const STATUS_CHECK = 'migration.status_check';

    /**
     * Triggered when migrations are run in pretend/dry-run mode
     *
     * Payload: array $migrations - The migrations that would be run
     * Use case: Preview operations, validation
     */
    const PRETEND = 'migration.pretend';
}