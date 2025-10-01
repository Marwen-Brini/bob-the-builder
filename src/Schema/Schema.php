<?php

declare(strict_types=1);

namespace Bob\Schema;

use Bob\Database\Connection;
use Closure;
use InvalidArgumentException;

/**
 * Schema Facade - Main interface for database schema operations
 *
 * This class provides static methods for creating, modifying, and dropping
 * database tables. It automatically detects the database type and uses
 * the appropriate grammar.
 */
class Schema
{
    /**
     * The default connection instance
     */
    protected static ?Connection $connection = null;

    /**
     * Set the default connection for schema operations
     */
    public static function setConnection(Connection $connection): void
    {
        static::$connection = $connection;
    }

    /**
     * Get the connection instance
     */
    public static function getConnection(): Connection
    {
        if (static::$connection === null) {
            static::$connection = Connection::getDefaultConnection();
        }

        return static::$connection;
    }

    /**
     * Create a new database table
     */
    public static function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->create();
        $callback($blueprint);

        static::build($blueprint);
    }

    /**
     * Create a new WordPress-style database table
     */
    public static function createWordPress(string $table, Closure $callback): void
    {
        $blueprint = new WordPressBlueprint($table);
        $blueprint->create();
        $callback($blueprint);

        static::build($blueprint);
    }

    /**
     * Modify an existing database table
     */
    public static function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        static::build($blueprint);
    }

    /**
     * Modify an existing WordPress-style database table
     */
    public static function tableWordPress(string $table, Closure $callback): void
    {
        $blueprint = new WordPressBlueprint($table);
        $callback($blueprint);

        static::build($blueprint);
    }

    /**
     * Drop a database table
     */
    public static function drop(string $table): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->drop();

        static::build($blueprint);
    }

    /**
     * Drop a database table if it exists
     */
    public static function dropIfExists(string $table): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->dropIfExists();

        static::build($blueprint);
    }

    /**
     * Drop all tables from the database
     */
    public static function dropAllTables(): void
    {
        $connection = static::getConnection();
        $driver = $connection->getDriverName();

        switch ($driver) {
            case 'mysql':
                static::dropAllMySQLTables($connection);
                break;
            case 'pgsql':
                static::dropAllPostgreSQLTables($connection);
                break;
            case 'sqlite':
                static::dropAllSQLiteTables($connection);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * Drop all MySQL tables
     */
    protected static function dropAllMySQLTables(Connection $connection): void
    {
        $connection->statement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = $connection->select('SHOW TABLES');
        $database = $connection->getConfig('database');

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_{$database}"} ?? array_values((array) $table)[0];
            static::drop($tableName);
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Drop all PostgreSQL tables
     */
    protected static function dropAllPostgreSQLTables(Connection $connection): void
    {
        $tables = $connection->select(
            "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'"
        );

        foreach ($tables as $table) {
            static::drop($table->tablename);
        }
    }

    /**
     * Drop all SQLite tables
     */
    protected static function dropAllSQLiteTables(Connection $connection): void
    {
        $connection->statement('PRAGMA foreign_keys = OFF');

        $tables = $connection->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );

        foreach ($tables as $table) {
            static::drop($table->name);
        }

        $connection->statement('PRAGMA foreign_keys = ON');
    }

    /**
     * Rename a database table
     */
    public static function rename(string $from, string $to): void
    {
        $blueprint = new Blueprint($from);
        $blueprint->rename($to);

        static::build($blueprint);
    }

    /**
     * Check if a table exists
     */
    public static function hasTable(string $table): bool
    {
        $connection = static::getConnection();
        $grammar = static::getGrammar($connection);

        $table = $connection->getTablePrefix().$table;

        // Different databases need different parameter structures
        if ($grammar instanceof \Bob\Schema\Grammars\SQLiteGrammar) {
            $bindings = [$table, $table]; // SQLite needs to check both regular and temp tables
        } elseif ($grammar instanceof \Bob\Schema\Grammars\MySQLGrammar) {
            $bindings = [$connection->getDatabaseName(), $table]; // MySQL needs schema and table name
        } else {
            $bindings = [$table]; // Default for other databases
        }

        return count($connection->select(
            $grammar->compileTableExists(),
            $bindings
        )) > 0;
    }

    /**
     * Check if a table has a column
     */
    public static function hasColumn(string $table, string $column): bool
    {
        return in_array(
            strtolower($column),
            array_map('strtolower', static::getColumnListing($table))
        );
    }

    /**
     * Check if a table has columns
     */
    public static function hasColumns(string $table, array $columns): bool
    {
        $tableColumns = array_map('strtolower', static::getColumnListing($table));

        foreach ($columns as $column) {
            if (! in_array(strtolower($column), $tableColumns)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the column listing for a table
     */
    public static function getColumnListing(string $table): array
    {
        $connection = static::getConnection();
        $grammar = static::getGrammar($connection);

        $table = $connection->getTablePrefix().$table;

        // Handle different parameter binding requirements
        if ($grammar instanceof \Bob\Schema\Grammars\MySQLGrammar) {
            $results = $connection->select(
                $grammar->compileColumnListing($table),
                [$connection->getDatabaseName(), $table]
            );
        } else {
            $results = $connection->select($grammar->compileColumnListing($table));
        }

        return array_map(function ($result) {
            return ((array) $result)['column_name'] ?? ((array) $result)['name'];
        }, $results);
    }

    /**
     * Get the data type for a column
     */
    public static function getColumnType(string $table, string $column): string
    {
        $connection = static::getConnection();
        $grammar = static::getGrammar($connection);

        $table = $connection->getTablePrefix().$table;

        $results = $connection->select(
            $grammar->compileColumnType($table, $column),
            [$table, $column]
        );

        if (empty($results)) {
            throw new InvalidArgumentException("Column {$column} doesn't exist on table {$table}.");
        }

        return ((array) $results[0])['data_type'] ?? ((array) $results[0])['type'];
    }

    /**
     * Enable foreign key constraints
     */
    public static function enableForeignKeyConstraints(): void
    {
        $connection = static::getConnection();
        $connection->statement(
            static::getGrammar($connection)->compileEnableForeignKeyConstraints()
        );
    }

    /**
     * Disable foreign key constraints
     */
    public static function disableForeignKeyConstraints(): void
    {
        $connection = static::getConnection();
        $connection->statement(
            static::getGrammar($connection)->compileDisableForeignKeyConstraints()
        );
    }

    /**
     * Execute a callback with foreign key constraints disabled
     */
    public static function withoutForeignKeyConstraints(Closure $callback): mixed
    {
        static::disableForeignKeyConstraints();

        try {
            return $callback();
        } finally {
            static::enableForeignKeyConstraints();
        }
    }

    /**
     * Build and execute the blueprint
     */
    protected static function build(Blueprint $blueprint): void
    {
        $connection = static::getConnection();
        $grammar = static::getGrammar($connection);

        if ($grammar->supportsSchemaTransactions() && $connection->getConfig('schema_transactions', true)) {
            $connection->transaction(function () use ($blueprint, $connection, $grammar) {
                $blueprint->build($connection, $grammar);
            });
        } else {
            $blueprint->build($connection, $grammar);
        }
    }

    /**
     * Get the schema grammar for the connection
     */
    protected static function getGrammar(Connection $connection): SchemaGrammar
    {
        $driver = $connection->getDriverName();

        switch ($driver) {
            case 'mysql':
                $grammar = new Grammars\MySQLGrammar;
                break;
            case 'pgsql':
                $grammar = new Grammars\PostgreSQLGrammar;
                break;
            case 'sqlite':
                $grammar = new Grammars\SQLiteGrammar;
                break;
            default:
                throw new InvalidArgumentException("Unsupported driver [{$driver}].");
        }

        $grammar->setTablePrefix($connection->getTablePrefix());

        return $grammar;
    }

    /**
     * Create a new command set with a closure
     *
     * @codeCoverageIgnore
     */
    public static function blueprintResolver(Closure $resolver): void
    {
        // This would allow customizing the Blueprint class
        // For now, we'll keep it simple
    }
}
