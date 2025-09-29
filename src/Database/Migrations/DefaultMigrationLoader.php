<?php

declare(strict_types=1);

namespace Bob\Database\Migrations;

use RuntimeException;

/**
 * Default implementation of MigrationLoaderInterface
 *
 * Uses standard PHP require_once and assumes PSR-4 naming conventions
 */
class DefaultMigrationLoader implements MigrationLoaderInterface
{
    /**
     * Load a migration file and return the fully qualified class name
     *
     * @param string $file The file path to load
     * @return string The fully qualified class name
     * @throws RuntimeException If the file cannot be loaded or class cannot be determined
     */
    public function load(string $file): string
    {
        if (!file_exists($file)) {
            throw new RuntimeException("Migration file not found: {$file}");
        }

        if (!$this->isValidMigration($file)) {
            throw new RuntimeException("Invalid migration file: {$file}");
        }

        // Get classes before and after loading
        $classesBefore = get_declared_classes();

        require_once $file;

        $classesAfter = get_declared_classes();
        $newClasses = array_diff($classesAfter, $classesBefore);

        if (empty($newClasses)) {
            // Fallback to extracting class name from file
            $className = $this->extractClassName($file);

            // Try with common namespace patterns
            $possibleClasses = [
                $className,
                "App\\Database\\Migrations\\{$className}",
                "Database\\Migrations\\{$className}",
                "Migrations\\{$className}",
            ];

            foreach ($possibleClasses as $class) {
                if (class_exists($class)) {
                    return $class;
                }
            }

            throw new RuntimeException("Could not determine class name from migration file: {$file}");
        }

        // Find the migration class (should extend Migration)
        foreach ($newClasses as $class) {
            if (is_subclass_of($class, Migration::class)) {
                return $class;
            }
        }

        // If no Migration subclass found, return the last loaded class
        return end($newClasses);
    }

    /**
     * Extract the class name from a migration file
     *
     * @param string $file The file path
     * @return string The class name (without namespace)
     */
    public function extractClassName(string $file): string
    {
        $fileName = basename($file, '.php');

        // Remove date prefix if present (e.g., "2024_01_01_000000_create_users_table")
        if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.+)$/', $fileName, $matches)) {
            $fileName = $matches[1];
        }

        // Convert to PascalCase
        $parts = explode('_', $fileName);
        $className = implode('', array_map('ucfirst', $parts));

        return $className;
    }

    /**
     * Determine if a migration file is valid
     *
     * @param string $file The file path to validate
     * @return bool True if the file is a valid migration
     */
    public function isValidMigration(string $file): bool
    {
        // Check if it's a PHP file
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
            return false;
        }

        // Check if file is readable
        if (!is_readable($file)) {
            return false;
        }

        // Optionally check file content for class definition
        $content = file_get_contents($file);
        // @codeCoverageIgnoreStart
        if ($content === false) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        // Check for class definition (not just in comments)
        // Remove single-line comments
        $contentWithoutComments = preg_replace('!//.*$!m', '', $content);
        // Remove multi-line comments
        $contentWithoutComments = preg_replace('!/\*.*?\*/!s', '', $contentWithoutComments);

        if (!preg_match('/\bclass\s+\w+/i', $contentWithoutComments)) {
            return false;
        }

        return true;
    }
}