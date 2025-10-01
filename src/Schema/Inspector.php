<?php

declare(strict_types=1);

namespace Bob\Schema;

use Bob\Database\Connection;
use InvalidArgumentException;

/**
 * Schema Inspector
 *
 * Provides database introspection capabilities for analyzing existing schemas.
 * Useful for reverse engineering, documentation, and migration generation.
 */
class Inspector
{
    /**
     * The database connection
     */
    protected Connection $connection;

    /**
     * The schema grammar instance (not used currently, kept for future)
     */
    protected ?object $grammar = null;

    /**
     * Create a new schema inspector
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get all tables in the database
     *
     * @return array Array of table names
     */
    public function getTables(): array
    {
        $driver = $this->connection->getDriverName();

        return match ($driver) {
            'mysql' => $this->getMySQLTables(),
            'pgsql' => $this->getPostgreSQLTables(),
            'sqlite' => $this->getSQLiteTables(),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Get columns for a specific table
     *
     * @param  string  $table  The table name
     * @return array Array of column information
     */
    public function getColumns(string $table): array
    {
        $driver = $this->connection->getDriverName();

        return match ($driver) {
            'mysql' => $this->getMySQLColumns($table),
            'pgsql' => $this->getPostgreSQLColumns($table),
            'sqlite' => $this->getSQLiteColumns($table),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Get indexes for a specific table
     *
     * @param  string  $table  The table name
     * @return array Array of index information
     */
    public function getIndexes(string $table): array
    {
        $driver = $this->connection->getDriverName();

        return match ($driver) {
            'mysql' => $this->getMySQLIndexes($table),
            'pgsql' => $this->getPostgreSQLIndexes($table),
            'sqlite' => $this->getSQLiteIndexes($table),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Get foreign keys for a specific table
     *
     * @param  string  $table  The table name
     * @return array Array of foreign key information
     */
    public function getForeignKeys(string $table): array
    {
        $driver = $this->connection->getDriverName();

        return match ($driver) {
            'mysql' => $this->getMySQLForeignKeys($table),
            'pgsql' => $this->getPostgreSQLForeignKeys($table),
            'sqlite' => $this->getSQLiteForeignKeys($table),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Generate a migration file content for a table
     *
     * @param  string  $table  The table name
     * @return string Migration file content
     */
    public function generateMigration(string $table): string
    {
        $columns = $this->getColumns($table);
        $indexes = $this->getIndexes($table);
        $foreignKeys = $this->getForeignKeys($table);

        $className = $this->getClassName($table);
        $blueprintCode = $this->generateBlueprintCode($table, $columns, $indexes, $foreignKeys);

        return $this->getMigrationStub($className, $table, $blueprintCode);
    }

    /**
     * Get MySQL tables
     */
    protected function getMySQLTables(): array
    {
        $database = $this->connection->getConfig('database');
        $results = $this->connection->select('SHOW TABLES');

        $tables = [];
        foreach ($results as $result) {
            $tableName = $result->{"Tables_in_{$database}"} ?? array_values((array) $result)[0];
            $tables[] = $tableName;
        }

        return $tables;
    }

    /**
     * Get PostgreSQL tables
     */
    protected function getPostgreSQLTables(): array
    {
        $results = $this->connection->select(
            "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'"
        );

        return array_map(fn ($r) => $r->tablename, $results);
    }

    /**
     * Get SQLite tables
     */
    protected function getSQLiteTables(): array
    {
        $results = $this->connection->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );

        return array_map(fn ($r) => $r->name, $results);
    }

    /**
     * Get MySQL columns
     */
    protected function getMySQLColumns(string $table): array
    {
        $database = $this->connection->getConfig('database');
        $results = $this->connection->select(
            'SELECT
                COLUMN_NAME as name,
                COLUMN_TYPE as type,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as default_value,
                COLUMN_KEY as key,
                EXTRA as extra,
                CHARACTER_MAXIMUM_LENGTH as length,
                NUMERIC_PRECISION as precision,
                NUMERIC_SCALE as scale,
                COLUMN_COMMENT as comment
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION',
            [$database, $table]
        );

        return array_map(function ($column) {
            return [
                'name' => $column->name,
                'type' => $column->type,
                'nullable' => $column->nullable === 'YES',
                'default' => $column->default_value,
                'auto_increment' => str_contains($column->extra ?? '', 'auto_increment'),
                'primary' => $column->key === 'PRI',
                'unique' => $column->key === 'UNI',
                'length' => $column->length,
                'precision' => $column->precision,
                'scale' => $column->scale,
                'comment' => $column->comment,
            ];
        }, $results);
    }

    /**
     * Get PostgreSQL columns
     */
    protected function getPostgreSQLColumns(string $table): array
    {
        $results = $this->connection->select(
            "SELECT
                column_name as name,
                data_type as type,
                is_nullable as nullable,
                column_default as default_value,
                character_maximum_length as length,
                numeric_precision as precision,
                numeric_scale as scale
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = ?
            ORDER BY ordinal_position",
            [$table]
        );

        return array_map(function ($column) {
            return [
                'name' => $column->name,
                'type' => $column->type,
                'nullable' => $column->nullable === 'YES',
                'default' => $column->default_value,
                'auto_increment' => str_contains($column->default_value ?? '', 'nextval'),
                'length' => $column->length,
                'precision' => $column->precision,
                'scale' => $column->scale,
            ];
        }, $results);
    }

    /**
     * Get SQLite columns
     */
    protected function getSQLiteColumns(string $table): array
    {
        $results = $this->connection->select("PRAGMA table_info({$table})");

        return array_map(function ($column) {
            return [
                'name' => $column->name,
                'type' => $column->type,
                'nullable' => $column->notnull == 0,
                'default' => $column->dflt_value,
                'primary' => $column->pk == 1,
            ];
        }, $results);
    }

    /**
     * Get MySQL indexes
     */
    protected function getMySQLIndexes(string $table): array
    {
        $results = $this->connection->select("SHOW INDEX FROM {$table}");

        $indexes = [];
        foreach ($results as $row) {
            $name = $row->Key_name;
            if (! isset($indexes[$name])) {
                $indexes[$name] = [
                    'name' => $name,
                    'columns' => [],
                    'unique' => $row->Non_unique == 0,
                    'primary' => $name === 'PRIMARY',
                ];
            }
            $indexes[$name]['columns'][] = $row->Column_name;
        }

        return array_values($indexes);
    }

    /**
     * Get PostgreSQL indexes
     */
    protected function getPostgreSQLIndexes(string $table): array
    {
        $results = $this->connection->select(
            'SELECT
                i.relname as name,
                ix.indisunique as unique,
                ix.indisprimary as primary,
                array_agg(a.attname ORDER BY a.attnum) as columns
            FROM pg_class t
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
            WHERE t.relname = ?
            GROUP BY i.relname, ix.indisunique, ix.indisprimary',
            [$table]
        );

        return array_map(function ($index) {
            // PostgreSQL returns columns as a string like "{col1,col2}"
            $columns = trim($index->columns, '{}');
            $columns = $columns ? explode(',', $columns) : [];

            return [
                'name' => $index->name,
                'columns' => $columns,
                'unique' => $index->unique,
                'primary' => $index->primary,
            ];
        }, $results);
    }

    /**
     * Get SQLite indexes
     */
    protected function getSQLiteIndexes(string $table): array
    {
        $results = $this->connection->select("PRAGMA index_list({$table})");

        $indexes = [];
        foreach ($results as $index) {
            $columns = $this->connection->select("PRAGMA index_info({$index->name})");

            $indexes[] = [
                'name' => $index->name,
                'columns' => array_map(fn ($c) => $c->name, $columns),
                'unique' => $index->unique == 1,
                'primary' => $index->origin === 'pk',
            ];
        }

        return $indexes;
    }

    /**
     * Get MySQL foreign keys
     */
    protected function getMySQLForeignKeys(string $table): array
    {
        $database = $this->connection->getConfig('database');
        $results = $this->connection->select(
            'SELECT
                CONSTRAINT_NAME as name,
                COLUMN_NAME as column,
                REFERENCED_TABLE_NAME as foreign_table,
                REFERENCED_COLUMN_NAME as foreign_column,
                UPDATE_RULE as on_update,
                DELETE_RULE as on_delete
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $table]
        );

        return array_map(function ($fk) {
            return [
                'name' => $fk->name,
                'column' => $fk->column,
                'foreign_table' => $fk->foreign_table,
                'foreign_column' => $fk->foreign_column,
                'on_update' => strtolower($fk->on_update ?? 'no action'),
                'on_delete' => strtolower($fk->on_delete ?? 'no action'),
            ];
        }, $results);
    }

    /**
     * Get PostgreSQL foreign keys
     */
    protected function getPostgreSQLForeignKeys(string $table): array
    {
        $results = $this->connection->select(
            "SELECT
                con.conname as name,
                att.attname as column,
                cl.relname as foreign_table,
                att2.attname as foreign_column,
                con.confupdtype as on_update,
                con.confdeltype as on_delete
            FROM pg_constraint con
            JOIN pg_class t ON t.oid = con.conrelid
            JOIN pg_attribute att ON att.attrelid = t.oid AND att.attnum = con.conkey[1]
            JOIN pg_class cl ON cl.oid = con.confrelid
            JOIN pg_attribute att2 ON att2.attrelid = cl.oid AND att2.attnum = con.confkey[1]
            WHERE t.relname = ? AND con.contype = 'f'",
            [$table]
        );

        $actionMap = [
            'a' => 'no action',
            'r' => 'restrict',
            'c' => 'cascade',
            'n' => 'set null',
            'd' => 'set default',
        ];

        return array_map(function ($fk) use ($actionMap) {
            return [
                'name' => $fk->name,
                'column' => $fk->column,
                'foreign_table' => $fk->foreign_table,
                'foreign_column' => $fk->foreign_column,
                'on_update' => $actionMap[$fk->on_update] ?? 'no action',
                'on_delete' => $actionMap[$fk->on_delete] ?? 'no action',
            ];
        }, $results);
    }

    /**
     * Get SQLite foreign keys
     */
    protected function getSQLiteForeignKeys(string $table): array
    {
        $results = $this->connection->select("PRAGMA foreign_key_list({$table})");

        return array_map(function ($fk) {
            return [
                'name' => "fk_{$table}_{$fk->from}",
                'column' => $fk->from,
                'foreign_table' => $fk->table,
                'foreign_column' => $fk->to,
                'on_update' => strtolower($fk->on_update ?? 'no action'),
                'on_delete' => strtolower($fk->on_delete ?? 'no action'),
            ];
        }, $results);
    }

    /**
     * Generate class name from table name
     */
    protected function getClassName(string $table): string
    {
        // Remove prefix if exists
        $prefix = $this->connection->getTablePrefix();
        if ($prefix && str_starts_with($table, $prefix)) {
            $table = substr($table, strlen($prefix));
        }

        // Convert to PascalCase
        $parts = explode('_', $table);

        return 'Create'.implode('', array_map('ucfirst', $parts)).'Table';
    }

    /**
     * Generate blueprint code
     */
    protected function generateBlueprintCode(string $table, array $columns, array $indexes, array $foreignKeys): string
    {
        $code = [];

        // Add columns
        foreach ($columns as $column) {
            $code[] = $this->generateColumnCode($column);
        }

        // Add indexes (excluding primary and auto-created unique)
        foreach ($indexes as $index) {
            if (! $index['primary'] && count($index['columns']) > 0) {
                $columns = implode("', '", $index['columns']);
                if ($index['unique']) {
                    $code[] = "\$table->unique(['{$columns}']);";
                } else {
                    $code[] = "\$table->index(['{$columns}']);";
                }
            }
        }

        // Add foreign keys
        foreach ($foreignKeys as $fk) {
            $code[] = $this->generateForeignKeyCode($fk);
        }

        return implode("\n            ", $code);
    }

    /**
     * Generate column code
     */
    protected function generateColumnCode(array $column): string
    {
        $name = $column['name'];
        $type = $this->mapColumnType($column['type']);

        // Handle auto-increment primary keys
        if ($column['auto_increment'] ?? false) {
            if ($type === 'bigInteger') {
                return "\$table->id('{$name}');";
            }

            return "\$table->increments('{$name}');";
        }

        // Build column definition
        $code = "\$table->{$type}('{$name}'";

        // Add length/precision if needed
        if (isset($column['length']) && $column['length'] && in_array($type, ['string', 'char'])) {
            $code .= ", {$column['length']}";
        } elseif (isset($column['precision']) && $column['precision'] && $type === 'decimal') {
            $precision = $column['precision'];
            $scale = $column['scale'] ?? 0;
            $code .= ", {$precision}, {$scale}";
        }

        $code .= ')';

        // Add modifiers
        if ($column['nullable'] ?? false) {
            $code .= '->nullable()';
        }

        if (array_key_exists('default', $column)) {
            $default = $this->formatDefaultValue($column['default']);
            $code .= "->default({$default})";
        }

        if ($column['unique'] ?? false) {
            $code .= '->unique()';
        }

        if (isset($column['comment']) && $column['comment']) {
            $comment = addslashes($column['comment']);
            $code .= "->comment('{$comment}')";
        }

        return $code.';';
    }

    /**
     * Map database type to Blueprint method
     */
    protected function mapColumnType(string $dbType): string
    {
        $dbType = strtolower($dbType);

        // Handle types with parameters
        if (preg_match('/^(\w+)\(/', $dbType, $matches)) {
            $baseType = $matches[1];
        } else {
            $baseType = $dbType;
        }

        return match ($baseType) {
            'int', 'integer', 'int4' => 'integer',
            'bigint', 'int8' => 'bigInteger',
            'smallint', 'int2' => 'smallInteger',
            'tinyint', 'int1' => 'tinyInteger',
            'varchar', 'character varying' => 'string',
            'char', 'character' => 'char',
            'text' => 'text',
            'longtext' => 'longText',
            'mediumtext' => 'mediumText',
            'tinytext' => 'text',
            'decimal', 'numeric' => 'decimal',
            'float', 'real' => 'float',
            'double', 'double precision' => 'double',
            'boolean', 'bool', 'tinyint(1)' => 'boolean',
            'date' => 'date',
            'datetime', 'timestamp without time zone' => 'dateTime',
            'timestamp', 'timestamp with time zone' => 'timestamp',
            'time', 'time without time zone' => 'time',
            'json', 'jsonb' => 'json',
            'blob', 'bytea' => 'binary',
            'uuid' => 'uuid',
            default => 'string',
        };
    }

    /**
     * Format default value
     */
    protected function formatDefaultValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Handle special MySQL defaults
        if (strtoupper($value) === 'CURRENT_TIMESTAMP') {
            return "DB::raw('CURRENT_TIMESTAMP')";
        }

        return "'".addslashes($value)."'";
    }

    /**
     * Generate foreign key code
     */
    protected function generateForeignKeyCode(array $fk): string
    {
        $code = "\$table->foreign('{$fk['column']}')";
        $code .= "->references('{$fk['foreign_column']}')";
        $code .= "->on('{$fk['foreign_table']}')";

        if ($fk['on_delete'] !== 'no action') {
            $action = str_replace(' ', '', ucwords($fk['on_delete']));
            $code .= "->onDelete('{$action}')";
        }

        if ($fk['on_update'] !== 'no action') {
            $action = str_replace(' ', '', ucwords($fk['on_update']));
            $code .= "->onUpdate('{$action}')";
        }

        return $code.';';
    }

    /**
     * Get migration stub
     */
    protected function getMigrationStub(string $className, string $table, string $blueprintCode): string
    {
        return <<<PHP
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Blueprint;
use Bob\Schema\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            {$blueprintCode}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;
    }
}
