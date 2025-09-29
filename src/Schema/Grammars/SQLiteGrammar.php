<?php

declare(strict_types=1);

namespace Bob\Schema\Grammars;

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Fluent;
use Bob\Schema\SchemaGrammar;
use RuntimeException;

/**
 * SQLite Schema Grammar
 *
 * Generates SQLite-specific SQL statements for schema operations.
 * Handles SQLite's limitations by recreating tables for unsupported operations.
 */
class SQLiteGrammar extends SchemaGrammar
{
    /**
     * The possible column modifiers
     */
    protected array $modifiers = ['Nullable', 'Default', 'Increment'];

    /**
     * The columns that support serials
     */
    protected array $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * Compile a create table command
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        $sql = sprintf(
            'create %stable %s (%s',
            $blueprint->temporary ? 'temporary ' : '',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint))
        );

        // Add foreign keys if any
        $sql .= $this->addForeignKeys($blueprint);

        // Add primary keys if specified
        $sql .= $this->addPrimaryKeys($blueprint);

        $sql .= ')';

        // SQLite doesn't support setting table-level options like engine or charset
        return $sql;
    }

    /**
     * Get the foreign key syntax for the table
     */
    protected function addForeignKeys(Blueprint $blueprint): string
    {
        $foreigns = '';

        foreach ($blueprint->getCommands() as $command) {
            if ($command->name === 'foreign') {
                $foreigns .= $this->getForeignKey($command);
            }
        }

        return $foreigns;
    }

    /**
     * Get the foreign key syntax
     */
    protected function getForeignKey(Fluent $foreign): string
    {
        $on = $foreign->on;
        $references = (array) $foreign->references;
        $columns = $foreign->columns;

        $sql = sprintf(
            ', foreign key(%s) references %s(%s)',
            $this->columnize($columns),
            $this->wrap($on),
            $this->columnize($references)
        );

        if ($foreign->onDelete) {
            $sql .= " on delete {$foreign->onDelete}";
        }

        if ($foreign->onUpdate) {
            $sql .= " on update {$foreign->onUpdate}";
        }

        return $sql;
    }

    /**
     * Get the primary key syntax for the table
     */
    protected function addPrimaryKeys(Blueprint $blueprint): string
    {
        $primary = $this->getCommandByName($blueprint, 'primary');

        if ($primary) {
            return sprintf(
                ', primary key (%s)',
                $this->columnize($primary->columns)
            );
        }

        return '';
    }

    /**
     * Compile an add column command
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command, Connection $connection): array|string
    {
        $columns = $this->getColumns($blueprint);
        $statements = [];

        foreach ($columns as $column) {
            $statements[] = sprintf(
                'alter table %s add column %s',
                $this->wrapTable($blueprint),
                $column
            );
        }

        // Return multiple ALTER statements as an array for proper execution
        return $statements;
    }

    /**
     * Compile a change column command
     */
    public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        // SQLite doesn't support modifying columns, need to recreate table
        $statements = $this->recreateTable($blueprint, $command, $connection);
        return implode('; ', $statements);
    }

    /**
     * Compile a drop table command
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return 'drop table ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table if exists command
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return 'drop table if exists ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile a drop column command
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command, Connection $connection): array|string
    {
        // Check SQLite version for DROP COLUMN support (3.35.0+)
        $version = $connection->select('select sqlite_version() as version')[0]->version ?? '0.0.0';

        if (version_compare($version, '3.35.0', '>=')) {
            // SQLite 3.35+ supports DROP COLUMN
            $columns = $command->columns;
            $statements = [];

            foreach ($columns as $column) {
                $statements[] = sprintf(
                    'alter table %s drop column %s',
                    $this->wrapTable($blueprint),
                    $this->wrap($column)
                );
            }

            return $statements;
        }

        // Older versions need table recreation
        return $this->recreateTable($blueprint, $command, $connection);
    }

    /**
     * Compile a rename table command
     */
    public function compileRename(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return sprintf(
            'alter table %s rename to %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile a rename column command
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        // Check SQLite version for RENAME COLUMN support (3.25.0+)
        $version = $connection->select('select sqlite_version() as version')[0]->version ?? '0.0.0';

        if (version_compare($version, '3.25.0', '>=')) {
            return sprintf(
                'alter table %s rename column %s to %s',
                $this->wrapTable($blueprint),
                $this->wrap($command->from),
                $this->wrap($command->to)
            );
        }

        // Older versions need table recreation
        return implode('; ', $this->recreateTable($blueprint, $command, $connection));
    }

    /**
     * Recreate the table with modifications
     */
    protected function recreateTable(Blueprint $blueprint, Fluent $command, Connection $connection): array
    {
        $table = $this->wrapTable($blueprint);
        $tempTable = $this->wrap('__temp__' . $blueprint->getTable());

        return [
            // Disable foreign keys
            'pragma foreign_keys = off',

            // Get the current table structure and create temporary table
            $this->getCreateTableSql($blueprint, $tempTable, $connection),

            // Copy data from old table to new
            sprintf(
                'insert into %s select * from %s',
                $tempTable,
                $table
            ),

            // Drop the original table
            sprintf('drop table %s', $table),

            // Rename temporary table to original
            sprintf('alter table %s rename to %s', $tempTable, $this->wrap($blueprint->getTable())),

            // Re-enable foreign keys
            'pragma foreign_keys = on',
        ];
    }

    /**
     * Get the CREATE TABLE SQL for table recreation
     */
    protected function getCreateTableSql(Blueprint $blueprint, string $tempTable, Connection $connection): string
    {
        // This would need to introspect the existing table and apply changes
        // For now, return a simplified version
        return sprintf(
            'create table %s as select * from %s where 0',
            $tempTable,
            $this->wrapTable($blueprint)
        );
    }

    /**
     * Compile the SQL needed to enable foreign key constraints
     */
    public function compileEnableForeignKeyConstraints(): string
    {
        return 'PRAGMA foreign_keys = ON';
    }

    /**
     * Compile the SQL needed to disable foreign key constraints
     */
    public function compileDisableForeignKeyConstraints(): string
    {
        return 'PRAGMA foreign_keys = OFF';
    }

    /**
     * Compile the query to determine if a table exists
     */
    public function compileTableExists(): string
    {
        return "select * from sqlite_master where type = 'table' and name = ? union all select * from sqlite_temp_master where type = 'table' and name = ?";
    }

    /**
     * Compile the query to determine column listing
     */
    public function compileColumnListing(string $table): string
    {
        return "pragma table_info({$table})";
    }

    /**
     * Compile the query to determine column type
     */
    public function compileColumnType(string $table, string $column): string
    {
        return "select type from pragma_table_info('{$table}') where name = ?";
    }

    /**
     * Create the column definition for a char type
     */
    protected function typeChar(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a string type
     */
    protected function typeString(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a tiny text type
     */
    protected function typeTinyText(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a text type
     */
    protected function typeText(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a medium text type
     */
    protected function typeMediumText(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a long text type
     */
    protected function typeLongText(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a tiny integer type
     */
    protected function typeTinyInteger(Fluent $column): string
    {
        return 'integer';
    }

    /**
     * Create the column definition for a small integer type
     */
    protected function typeSmallInteger(Fluent $column): string
    {
        return 'integer';
    }

    /**
     * Create the column definition for a medium integer type
     */
    protected function typeMediumInteger(Fluent $column): string
    {
        return 'integer';
    }

    /**
     * Create the column definition for an integer type
     */
    protected function typeInteger(Fluent $column): string
    {
        return 'integer';
    }

    /**
     * Create the column definition for a big integer type
     */
    protected function typeBigInteger(Fluent $column): string
    {
        return 'integer';
    }

    /**
     * Create the column definition for a float type
     */
    protected function typeFloat(Fluent $column): string
    {
        return 'real';
    }

    /**
     * Create the column definition for a double type
     */
    protected function typeDouble(Fluent $column): string
    {
        return 'real';
    }

    /**
     * Create the column definition for a decimal type
     */
    protected function typeDecimal(Fluent $column): string
    {
        return 'numeric';
    }

    /**
     * Create the column definition for a boolean type
     */
    protected function typeBoolean(Fluent $column): string
    {
        return 'integer';
    }

    /**
     * Create the column definition for an enum type
     */
    protected function typeEnum(Fluent $column): string
    {
        return sprintf(
            'text check ("%s" in (%s))',
            $column->name,
            $this->quoteString($column->allowed)
        );
    }

    /**
     * Create the column definition for a set type
     */
    protected function typeSet(Fluent $column): string
    {
        // SQLite doesn't have a set type, use text
        return 'text';
    }

    /**
     * Create the column definition for a json type
     */
    protected function typeJson(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a jsonb type
     */
    protected function typeJsonb(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a date type
     */
    protected function typeDate(Fluent $column): string
    {
        return 'date';
    }

    /**
     * Create the column definition for a datetime type
     */
    protected function typeDateTime(Fluent $column): string
    {
        return 'datetime';
    }

    /**
     * Create the column definition for a datetime with timezone type
     */
    protected function typeDateTimeTz(Fluent $column): string
    {
        return 'datetime';
    }

    /**
     * Create the column definition for a time type
     */
    protected function typeTime(Fluent $column): string
    {
        return 'time';
    }

    /**
     * Create the column definition for a time with timezone type
     */
    protected function typeTimeTz(Fluent $column): string
    {
        return 'time';
    }

    /**
     * Create the column definition for a timestamp type
     */
    protected function typeTimestamp(Fluent $column): string
    {
        return 'datetime';
    }

    /**
     * Create the column definition for a timestamp with timezone type
     */
    protected function typeTimestampTz(Fluent $column): string
    {
        return 'datetime';
    }

    /**
     * Create the column definition for a year type
     */
    protected function typeYear(Fluent $column): string
    {
        return 'integer';
    }

    /**
     * Create the column definition for a binary type
     */
    protected function typeBinary(Fluent $column): string
    {
        return 'blob';
    }

    /**
     * Create the column definition for a uuid type
     */
    protected function typeUuid(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for an IP address type
     */
    protected function typeIpAddress(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a MAC address type
     */
    protected function typeMacAddress(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a spatial geometry type
     */
    protected function typeGeometry(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a spatial point type
     */
    protected function typePoint(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a spatial linestring type
     */
    protected function typeLineString(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a spatial polygon type
     */
    protected function typePolygon(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a spatial geometrycollection type
     */
    protected function typeGeometryCollection(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a spatial multipoint type
     */
    protected function typeMultiPoint(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a spatial multilinestring type
     */
    protected function typeMultiLineString(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a spatial multipolygon type
     */
    protected function typeMultiPolygon(Fluent $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a generated/computed column type
     */
    protected function typeComputed(Fluent $column): string
    {
        // SQLite supports generated columns from version 3.31.0
        return sprintf('%s as (%s)', 'text', $column->expression);
    }

    /**
     * Get the SQL for a nullable column modifier
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column): string
    {
        if ($column->type !== 'integer' || !$column->autoIncrement) {
            return $column->nullable ? '' : ' not null';
        }

        return '';
    }

    /**
     * Get the SQL for a default column modifier
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column): string
    {
        if (!is_null($column->default)) {
            return ' default ' . $this->getDefaultValue($column->default);
        }

        if ($column->useCurrent && in_array($column->type, ['dateTime', 'dateTimeTz', 'timestamp', 'timestampTz'])) {
            return ' default current_timestamp';
        }

        return '';
    }

    /**
     * Get the SQL for an auto-increment column modifier
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column): string
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' primary key autoincrement';
        }

        return '';
    }

    /**
     * Compile a primary key command
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command): string
    {
        // SQLite handles primary keys during table creation
        // Return empty string instead of null to match interface
        return '';
    }

    /**
     * Compile a unique key command
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'create unique index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a plain index command
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'create index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a fulltext index command
     */
    public function compileFulltext(Blueprint $blueprint, Fluent $command): string
    {
        throw new RuntimeException('SQLite does not support fulltext indexes. Use FTS virtual tables instead.');
    }

    /**
     * Compile a spatial index command
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command): string
    {
        throw new RuntimeException('SQLite does not support spatial indexes.');
    }

    /**
     * Compile a foreign key command
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command): string
    {
        // Foreign keys are handled during table creation in SQLite
        // Return empty string instead of null to match interface
        return '';
    }

    /**
     * Compile a drop primary key command
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command): string
    {
        throw new RuntimeException('SQLite does not support dropping primary keys.');
    }

    /**
     * Compile a drop unique key command
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'drop index %s',
            $this->wrap($command->index)
        );
    }

    /**
     * Compile a drop index command
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'drop index %s',
            $this->wrap($command->index)
        );
    }

    /**
     * Compile a drop foreign key command
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command): string
    {
        throw new RuntimeException('SQLite does not support dropping foreign keys.');
    }

    /**
     * Get a command by name from the blueprint
     */
    protected function getCommandByName(Blueprint $blueprint, string $name): ?Fluent
    {
        $commands = $blueprint->getCommands();

        foreach ($commands as $command) {
            if ($command->name === $name) {
                return $command;
            }
        }

        return null;
    }

    /**
     * Wrap a single string value in keyword identifiers
     */
    protected function wrapValue(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        // SQLite supports both double quotes and backticks
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Does this grammar support schema changes wrapped in a transaction
     */
    public function supportsSchemaTransactions(): bool
    {
        return false;
    }

    /**
     * Wrap an array of values
     */
    protected function wrapArray(array $values): array
    {
        return array_map([$this, 'wrap'], $values);
    }
}