<?php

declare(strict_types=1);

namespace Bob\Schema;

use Bob\Database\Connection;
use RuntimeException;

/**
 * Abstract Schema Grammar for SQL generation
 *
 * This abstract class defines the interface for database-specific
 * schema grammars. Each supported database (MySQL, PostgreSQL, SQLite)
 * extends this class to provide its specific SQL generation logic.
 */
abstract class SchemaGrammar
{
    /**
     * The grammar table prefix
     */
    protected string $tablePrefix = '';

    /**
     * The possible column modifiers
     */
    protected array $modifiers = [];

    /**
     * The commands which are fluentely defined
     */
    protected array $fluentCommands = [];

    /**
     * Compile a create table command
     */
    abstract public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection): string;

    /**
     * Compile an add column command
     */
    abstract public function compileAdd(Blueprint $blueprint, Fluent $command, Connection $connection): array|string;

    /**
     * Compile a change column command
     */
    abstract public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection): string;

    /**
     * Compile a drop table command
     */
    abstract public function compileDrop(Blueprint $blueprint, Fluent $command, Connection $connection): string;

    /**
     * Compile a drop table if exists command
     */
    abstract public function compileDropIfExists(Blueprint $blueprint, Fluent $command, Connection $connection): string;

    /**
     * Compile a drop column command
     */
    abstract public function compileDropColumn(Blueprint $blueprint, Fluent $command, Connection $connection): array|string;

    /**
     * Compile a rename table command
     */
    abstract public function compileRename(Blueprint $blueprint, Fluent $command, Connection $connection): string;

    /**
     * Compile a rename column command
     */
    abstract public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection): string;

    /**
     * Compile the SQL needed to enable foreign key constraints
     */
    abstract public function compileEnableForeignKeyConstraints(): string;

    /**
     * Compile the SQL needed to disable foreign key constraints
     */
    abstract public function compileDisableForeignKeyConstraints(): string;

    /**
     * Get the fluent commands for the grammar
     */
    public function getFluentCommands(): array
    {
        return $this->fluentCommands;
    }

    /**
     * Check if this grammar supports schema changes within a transaction
     */
    public function supportsSchemaTransactions(): bool
    {
        return true;
    }

    /**
     * Set the table prefix
     */
    public function setTablePrefix(string $prefix): void
    {
        $this->tablePrefix = $prefix;
    }

    /**
     * Get the table prefix
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Wrap a table in keyword identifiers
     */
    public function wrapTable(Blueprint|string $table): string
    {
        if ($table instanceof Blueprint) {
            $table = $table->getTable();
        }

        return $this->wrap($this->tablePrefix . $table);
    }

    /**
     * Wrap a value in keyword identifiers
     */
    public function wrap(mixed $value): string
    {
        if ($value === '*') {
            return $value;
        }

        // If the value being wrapped has a column alias we need to wrap it differently
        if (stripos($value, ' as ') !== false) {
            return $this->wrapAliasedValue($value);
        }

        // If the value contains a dot, we need to wrap the segments
        if (strpos($value, '.') !== false) {
            return $this->wrapSegments(explode('.', $value));
        }

        return $this->wrapValue($value);
    }

    /**
     * Wrap a value that has an alias
     */
    protected function wrapAliasedValue(string $value): string
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        return $this->wrap($segments[0]) . ' as ' . $this->wrapValue($segments[1]);
    }

    /**
     * Wrap the given value segments
     */
    protected function wrapSegments(array $segments): string
    {
        return implode('.', array_map([$this, 'wrapValue'], $segments));
    }

    /**
     * Wrap a single string value in keyword identifiers
     */
    protected function wrapValue(string $value): string
    {
        // @codeCoverageIgnoreStart
        if ($value === '*') {
            return $value;
        }
        // @codeCoverageIgnoreEnd

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Convert an array of column names into a delimited string
     */
    public function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * Create column definition for the given column type
     */
    protected function getColumns(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getAddedColumns() as $column) {
            $sql = $this->wrap($column->name) . ' ' . $this->getType($column);

            $columns[] = $this->addModifiers($sql, $blueprint, $column);
        }

        return $columns;
    }

    /**
     * Get the SQL for the column data type
     */
    protected function getType(Fluent $column): string
    {
        $method = 'type' . ucfirst($column->type);

        if (method_exists($this, $method)) {
            return $this->$method($column);
        }

        throw new RuntimeException("Column type [{$column->type}] is not supported.");
    }

    /**
     * Add the column modifiers to the definition
     */
    protected function addModifiers(string $sql, Blueprint $blueprint, Fluent $column): string
    {
        foreach ($this->modifiers as $modifier) {
            $method = "modify{$modifier}";

            if (method_exists($this, $method)) {
                $sql .= $this->$method($blueprint, $column);
            }
        }

        return $sql;
    }

    /**
     * Get the SQL for a nullable column modifier
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column): string
    {
        return $column->nullable ? ' null' : ' not null';
    }

    /**
     * Get the SQL for a default column modifier
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column): string
    {
        if (!is_null($column->default)) {
            return ' default ' . $this->getDefaultValue($column->default);
        }

        return '';
    }

    /**
     * Get the SQL for an auto-increment column modifier
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column): string
    {
        if (in_array($column->type, ['tinyInteger', 'smallInteger', 'mediumInteger', 'integer', 'bigInteger']) && $column->autoIncrement) {
            return ' auto_increment primary key';
        }

        return '';
    }

    /**
     * Format a value for use in a default clause
     */
    protected function getDefaultValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }

        if (is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }

    /**
     * Compile a primary key command
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s add primary key %s(%s)',
            $this->wrapTable($blueprint),
            $command->index ? 'constraint ' . $this->wrap($command->index) . ' ' : '',
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a unique key command
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s add unique %s(%s)',
            $this->wrapTable($blueprint),
            $command->index ? $this->wrap($command->index) . ' ' : '',
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
     * Compile a fulltext index command (MySQL)
     */
    public function compileFulltext(Blueprint $blueprint, Fluent $command): string
    {
        throw new RuntimeException('This database does not support fulltext indexes.');
    }

    /**
     * Compile a spatial index command
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command): string
    {
        throw new RuntimeException('This database does not support spatial indexes.');
    }

    /**
     * Compile a foreign key command
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command): string
    {
        $sql = sprintf(
            'alter table %s add constraint %s foreign key (%s) references %s (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->name ?: $this->getForeignKeyName($blueprint, $command)),
            $this->columnize($command->columns),
            $this->wrap($command->on),
            $this->columnize((array) $command->references)
        );

        if ($command->onDelete) {
            $sql .= " on delete {$command->onDelete}";
        }

        if ($command->onUpdate) {
            $sql .= " on update {$command->onUpdate}";
        }

        return $sql;
    }

    /**
     * Get the default foreign key name for the given blueprint and command
     */
    protected function getForeignKeyName(Blueprint $blueprint, Fluent $command): string
    {
        $table = str_replace('.', '_', $blueprint->getTable());
        $columns = implode('_', $command->columns);

        return "{$table}_{$columns}_foreign";
    }

    /**
     * Compile a drop primary key command
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s drop primary key',
            $this->wrapTable($blueprint)
        );
    }

    /**
     * Compile a drop unique key command
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s drop index %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->index)
        );
    }

    /**
     * Compile a drop index command
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'drop index %s on %s',
            $this->wrap($command->index),
            $this->wrapTable($blueprint)
        );
    }

    /**
     * Compile a drop fulltext index command
     */
    public function compileDropFulltext(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * Compile a drop spatial index command
     */
    public function compileDropSpatialIndex(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * Compile a drop foreign key command
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s drop foreign key %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->index)
        );
    }

    /**
     * Compile a comment command (MySQL)
     */
    public function compileComment(Blueprint $blueprint, Fluent $command): string
    {
        throw new RuntimeException('This database does not support table comments.');
    }

    /**
     * Quote string for use in SQL
     */
    protected function quoteString(string|array $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'quoteString'], $value));
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Get the SQL for the column data type
     */
    abstract protected function typeString(Fluent $column): string;
    abstract protected function typeText(Fluent $column): string;
    abstract protected function typeMediumText(Fluent $column): string;
    abstract protected function typeLongText(Fluent $column): string;
    abstract protected function typeInteger(Fluent $column): string;
    abstract protected function typeTinyInteger(Fluent $column): string;
    abstract protected function typeSmallInteger(Fluent $column): string;
    abstract protected function typeMediumInteger(Fluent $column): string;
    abstract protected function typeBigInteger(Fluent $column): string;
    abstract protected function typeFloat(Fluent $column): string;
    abstract protected function typeDouble(Fluent $column): string;
    abstract protected function typeDecimal(Fluent $column): string;
    abstract protected function typeBoolean(Fluent $column): string;
    abstract protected function typeJson(Fluent $column): string;
    abstract protected function typeDate(Fluent $column): string;
    abstract protected function typeDateTime(Fluent $column): string;
    abstract protected function typeTime(Fluent $column): string;
    abstract protected function typeTimestamp(Fluent $column): string;
    abstract protected function typeBinary(Fluent $column): string;
    abstract protected function typeUuid(Fluent $column): string;
}