<?php

declare(strict_types=1);

namespace Bob\Database\Migrations;

/**
 * Interface for pluggable migration class loading strategies
 *
 * This allows different environments (WordPress, Laravel, etc.) to implement
 * their own class loading mechanisms while maintaining compatibility with
 * Bob's migration system.
 */
interface MigrationLoaderInterface
{
    /**
     * Load a migration file and return the fully qualified class name
     *
     * @param string $file The file path to load
     * @return string The fully qualified class name
     * @throws \RuntimeException If the file cannot be loaded or class cannot be determined
     */
    public function load(string $file): string;

    /**
     * Extract the class name from a migration file
     *
     * @param string $file The file path
     * @return string The class name (without namespace)
     */
    public function extractClassName(string $file): string;

    /**
     * Determine if a migration file is valid
     *
     * @param string $file The file path to validate
     * @return bool True if the file is a valid migration
     */
    public function isValidMigration(string $file): bool;
}