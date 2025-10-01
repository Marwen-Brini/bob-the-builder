<?php

declare(strict_types=1);

namespace Bob\Database\Migrations;

use Bob\Database\Connection;
use InvalidArgumentException;

/**
 * Seeder Base Class
 *
 * Abstract base class for database seeders.
 */
abstract class Seeder
{
    /**
     * The database connection
     */
    protected ?Connection $connection = null;

    /**
     * The command output instance
     */
    protected mixed $command = null;

    /**
     * Run the database seeds
     */
    abstract public function run(): void;

    /**
     * Call another seeder
     */
    public function call(string|array $classes, bool $silent = false): void
    {
        $classes = is_array($classes) ? $classes : [$classes];

        foreach ($classes as $class) {
            $seeder = $this->resolve($class);

            if (! $silent && $this->command) {
                $this->command->info("Seeding: {$class}");
            }

            $startTime = microtime(true);

            $seeder->setConnection($this->connection);
            $seeder->setCommand($this->command);
            $seeder->run();

            if (! $silent && $this->command) {
                $time = round((microtime(true) - $startTime) * 1000, 2);
                $this->command->info("Seeded:  {$class} ({$time}ms)");
            }
        }
    }

    /**
     * Call another seeder silently
     */
    public function callSilent(string|array $classes): void
    {
        $this->call($classes, true);
    }

    /**
     * Resolve a seeder instance
     */
    protected function resolve(string $class): Seeder
    {
        if (! class_exists($class)) {
            throw new InvalidArgumentException("Seeder class [{$class}] does not exist.");
        }

        return new $class;
    }

    /**
     * Set the database connection
     */
    public function setConnection(?Connection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Set the command instance
     */
    public function setCommand(mixed $command): void
    {
        $this->command = $command;
    }

    /**
     * Get the database connection
     */
    protected function db(): Connection
    {
        if (! $this->connection) {
            $this->connection = Connection::getDefaultConnection();
        }

        return $this->connection;
    }

    /**
     * Helper to quickly insert data into a table
     */
    protected function table(string $table)
    {
        return $this->db()->table($table);
    }
}

/**
 * Database Seeder - Main seeder that calls other seeders
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database
     */
    public function run(): void
    {
        // Call other seeders here
        // $this->call(UserSeeder::class);
        // $this->call(PostSeeder::class);
    }
}
