<?php

declare(strict_types=1);

namespace Bob\Schema\Grammars;

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Fluent;
use Bob\Schema\SchemaGrammar;

/**
 * PostgreSQL Schema Grammar
 *
 * Generates PostgreSQL-specific SQL statements for schema operations.
 * Supports PostgreSQL 10+ features including JSONB, arrays, and advanced types.
 */
class PostgreSQLGrammar extends SchemaGrammar
{
    /**
     * The possible column modifiers
     */
    protected array $modifiers = [
        'Increment', 'Nullable', 'Default', 'VirtualAs', 'StoredAs', 'GeneratedAs', 'Comment'
    ];

    /**
     * The columns that support serials
     */
    protected array $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger'];

    /**
     * If this grammar supports schema changes wrapped in a transaction
     */
    protected bool $transactions = true;

    /**
     * Compile a create table command
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return sprintf(
            '%s table %s%s (%s)',
            $blueprint->temporary ? 'create temporary' : 'create',
            $blueprint->temporary ? '' : 'if not exists ',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint))
        );
    }

    /**
     * Compile an add column command
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command, Connection $connection): array|string
    {
        return sprintf(
            'alter table %s %s',
            $this->wrapTable($blueprint),
            implode(', ', $this->prefixArray('add column', $this->getColumns($blueprint)))
        );
    }

    /**
     * Compile a change column command
     */
    public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        $changes = [];

        foreach ($blueprint->getChangedColumns() as $column) {
            $changes[] = sprintf(
                'alter column %s type %s',
                $this->wrap($column->name),
                $this->getType($column)
            );

            if (!$column->nullable) {
                $changes[] = sprintf(
                    'alter column %s set not null',
                    $this->wrap($column->name)
                );
            } else {
                $changes[] = sprintf(
                    'alter column %s drop not null',
                    $this->wrap($column->name)
                );
            }

            if (!is_null($column->default)) {
                $changes[] = sprintf(
                    'alter column %s set default %s',
                    $this->wrap($column->name),
                    $this->getDefaultValue($column->default)
                );
            }
        }

        return sprintf(
            'alter table %s %s',
            $this->wrapTable($blueprint),
            implode(', ', $changes)
        );
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
        $columns = $this->prefixArray('drop column', $this->wrapArray($command->columns));

        return sprintf(
            'alter table %s %s',
            $this->wrapTable($blueprint),
            implode(', ', $columns)
        );
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
        return sprintf(
            'alter table %s rename column %s to %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile the SQL needed to enable foreign key constraints
     */
    public function compileEnableForeignKeyConstraints(): string
    {
        return 'SET CONSTRAINTS ALL IMMEDIATE';
    }

    /**
     * Compile the SQL needed to disable foreign key constraints
     */
    public function compileDisableForeignKeyConstraints(): string
    {
        return 'SET CONSTRAINTS ALL DEFERRED';
    }

    /**
     * Compile the query to determine if a table exists
     */
    public function compileTableExists(): string
    {
        return "select * from information_schema.tables where table_catalog = current_database() and table_schema = current_schema() and table_name = ?";
    }

    /**
     * Compile the query to determine column listing
     */
    public function compileColumnListing(string $table): string
    {
        return "select column_name from information_schema.columns where table_catalog = current_database() and table_schema = current_schema() and table_name = '{$table}'";
    }

    /**
     * Compile the query to determine column type
     */
    public function compileColumnType(string $table, string $column): string
    {
        return "select data_type from information_schema.columns where table_catalog = current_database() and table_schema = current_schema() and table_name = ? and column_name = ?";
    }

    /**
     * Compile a comment command
     */
    public function compileComment(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'comment on table %s is %s',
            $this->wrapTable($blueprint),
            "'" . str_replace("'", "''", $command->comment) . "'"
        );
    }

    /**
     * Create the column definition for a char type
     */
    protected function typeChar(Fluent $column): string
    {
        if ($column->length) {
            return "char({$column->length})";
        }

        return 'char';
    }

    /**
     * Create the column definition for a string type
     */
    protected function typeString(Fluent $column): string
    {
        if ($column->length) {
            return "varchar({$column->length})";
        }

        return 'varchar';
    }

    /**
     * Create the column definition for a tiny text type
     */
    protected function typeTinyText(Fluent $column): string
    {
        return 'varchar(255)';
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
        return $column->autoIncrement && !$column->change ? 'smallserial' : 'smallint';
    }

    /**
     * Create the column definition for a small integer type
     */
    protected function typeSmallInteger(Fluent $column): string
    {
        return $column->autoIncrement && !$column->change ? 'smallserial' : 'smallint';
    }

    /**
     * Create the column definition for a medium integer type
     */
    protected function typeMediumInteger(Fluent $column): string
    {
        return $column->autoIncrement && !$column->change ? 'serial' : 'integer';
    }

    /**
     * Create the column definition for an integer type
     */
    protected function typeInteger(Fluent $column): string
    {
        return $column->autoIncrement && !$column->change ? 'serial' : 'integer';
    }

    /**
     * Create the column definition for a big integer type
     */
    protected function typeBigInteger(Fluent $column): string
    {
        return $column->autoIncrement && !$column->change ? 'bigserial' : 'bigint';
    }

    /**
     * Create the column definition for a float type
     */
    protected function typeFloat(Fluent $column): string
    {
        return $column->precision ? "float({$column->precision})" : 'real';
    }

    /**
     * Create the column definition for a double type
     */
    protected function typeDouble(Fluent $column): string
    {
        return 'double precision';
    }

    /**
     * Create the column definition for a decimal type
     */
    protected function typeDecimal(Fluent $column): string
    {
        return "decimal({$column->precision}, {$column->scale})";
    }

    /**
     * Create the column definition for a boolean type
     */
    protected function typeBoolean(Fluent $column): string
    {
        return 'boolean';
    }

    /**
     * Create the column definition for an enum type
     */
    protected function typeEnum(Fluent $column): string
    {
        return sprintf(
            'varchar(255) check ("%s" in (%s))',
            $column->name,
            $this->quoteString($column->allowed)
        );
    }

    /**
     * Create the column definition for a set type
     */
    protected function typeSet(Fluent $column): string
    {
        // PostgreSQL doesn't have a set type, use array instead
        return 'text[]';
    }

    /**
     * Create the column definition for a json type
     */
    protected function typeJson(Fluent $column): string
    {
        return 'json';
    }

    /**
     * Create the column definition for a jsonb type
     */
    protected function typeJsonb(Fluent $column): string
    {
        return 'jsonb';
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
        $precision = isset($column->precision) ? "({$column->precision})" : '';
        return 'timestamp' . $precision . ' without time zone';
    }

    /**
     * Create the column definition for a datetime with timezone type
     */
    protected function typeDateTimeTz(Fluent $column): string
    {
        $precision = isset($column->precision) ? "({$column->precision})" : '';
        return 'timestamp' . $precision . ' with time zone';
    }

    /**
     * Create the column definition for a time type
     */
    protected function typeTime(Fluent $column): string
    {
        $precision = isset($column->precision) ? "({$column->precision})" : '';
        return 'time' . $precision . ' without time zone';
    }

    /**
     * Create the column definition for a time with timezone type
     */
    protected function typeTimeTz(Fluent $column): string
    {
        $precision = isset($column->precision) ? "({$column->precision})" : '';
        return 'time' . $precision . ' with time zone';
    }

    /**
     * Create the column definition for a timestamp type
     */
    protected function typeTimestamp(Fluent $column): string
    {
        $precision = isset($column->precision) ? "({$column->precision})" : '';

        if ($column->useCurrent) {
            return 'timestamp' . $precision . ' without time zone default CURRENT_TIMESTAMP';
        }

        return 'timestamp' . $precision . ' without time zone';
    }

    /**
     * Create the column definition for a timestamp with timezone type
     */
    protected function typeTimestampTz(Fluent $column): string
    {
        $precision = isset($column->precision) ? "({$column->precision})" : '';

        if ($column->useCurrent) {
            return 'timestamp' . $precision . ' with time zone default CURRENT_TIMESTAMP';
        }

        return 'timestamp' . $precision . ' with time zone';
    }

    /**
     * Create the column definition for a year type
     */
    protected function typeYear(Fluent $column): string
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a binary type
     */
    protected function typeBinary(Fluent $column): string
    {
        return 'bytea';
    }

    /**
     * Create the column definition for a uuid type
     */
    protected function typeUuid(Fluent $column): string
    {
        return 'uuid';
    }

    /**
     * Create the column definition for an IP address type
     */
    protected function typeIpAddress(Fluent $column): string
    {
        return 'inet';
    }

    /**
     * Create the column definition for a MAC address type
     */
    protected function typeMacAddress(Fluent $column): string
    {
        return 'macaddr';
    }

    /**
     * Create the column definition for a spatial geometry type
     */
    protected function typeGeometry(Fluent $column): string
    {
        return $this->formatPostGisType('geometry', $column);
    }

    /**
     * Create the column definition for a spatial point type
     */
    protected function typePoint(Fluent $column): string
    {
        return $this->formatPostGisType('point', $column);
    }

    /**
     * Create the column definition for a spatial linestring type
     */
    protected function typeLineString(Fluent $column): string
    {
        return $this->formatPostGisType('linestring', $column);
    }

    /**
     * Create the column definition for a spatial polygon type
     */
    protected function typePolygon(Fluent $column): string
    {
        return $this->formatPostGisType('polygon', $column);
    }

    /**
     * Create the column definition for a spatial geometrycollection type
     */
    protected function typeGeometryCollection(Fluent $column): string
    {
        return $this->formatPostGisType('geometrycollection', $column);
    }

    /**
     * Create the column definition for a spatial multipoint type
     */
    protected function typeMultiPoint(Fluent $column): string
    {
        return $this->formatPostGisType('multipoint', $column);
    }

    /**
     * Create the column definition for a spatial multilinestring type
     */
    protected function typeMultiLineString(Fluent $column): string
    {
        return $this->formatPostGisType('multilinestring', $column);
    }

    /**
     * Create the column definition for a spatial multipolygon type
     */
    protected function typeMultiPolygon(Fluent $column): string
    {
        return $this->formatPostGisType('multipolygon', $column);
    }

    /**
     * Create the column definition for a generated/computed column type
     */
    protected function typeComputed(Fluent $column): string
    {
        throw new \RuntimeException('Computed columns are not supported by PostgreSQL.');
    }

    /**
     * Format the PostGIS data type
     */
    protected function formatPostGisType(string $type, Fluent $column): string
    {
        if ($column->isGeography) {
            return sprintf(
                'geography(%s, %s)',
                strtoupper($type),
                $column->srid ?? 4326
            );
        }

        if ($column->srid) {
            return sprintf('geometry(%s, %s)', strtoupper($type), $column->srid);
        }

        return 'geometry';
    }

    /**
     * Get the SQL for an auto-increment column modifier
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column): string
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' primary key';
        }

        return '';
    }

    /**
     * Get the SQL for a nullable column modifier
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column): string
    {
        if (is_null($column->virtualAs) && is_null($column->storedAs) && is_null($column->generatedAs)) {
            return $column->nullable ? ' null' : ' not null';
        }

        // @codeCoverageIgnoreStart
        if ($column->nullable === false) {
            return ' not null';
        }
        // @codeCoverageIgnoreEnd

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

        return '';
    }

    /**
     * Get the SQL for a virtual as column modifier
     */
    protected function modifyVirtualAs(Blueprint $blueprint, Fluent $column): string
    {
        if (!is_null($column->virtualAs)) {
            // PostgreSQL doesn't support virtual columns directly
            // Would need to use a view or function
            return '';
        }

        return '';
    }

    /**
     * Get the SQL for a stored as column modifier
     */
    protected function modifyStoredAs(Blueprint $blueprint, Fluent $column): string
    {
        if (!is_null($column->storedAs)) {
            return " generated always as ({$column->storedAs}) stored";
        }

        return '';
    }

    /**
     * Get the SQL for a generated as column modifier
     */
    protected function modifyGeneratedAs(Blueprint $blueprint, Fluent $column): string
    {
        if (!is_null($column->generatedAs)) {
            $identity = $column->always ? 'always' : 'by default';
            return " generated {$identity} as identity";
        }

        return '';
    }

    /**
     * Get the SQL for a comment column modifier
     */
    protected function modifyComment(Blueprint $blueprint, Fluent $column): string
    {
        // Comments are handled separately in PostgreSQL
        return '';
    }

    /**
     * Compile a primary key command
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->columnize($command->columns);

        return sprintf(
            'alter table %s add constraint %s primary key (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $columns
        );
    }

    /**
     * Compile a unique key command
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s add constraint %s unique (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a plain index command
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'create index %s on %s%s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $command->algorithm ? ' using ' . strtolower($command->algorithm) : '',
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a fulltext index command
     */
    public function compileFulltext(Blueprint $blueprint, Fluent $command): string
    {
        // PostgreSQL uses different approach for full text search
        return sprintf(
            'create index %s on %s using gin (to_tsvector(\'english\', %s))',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            implode(" || ' ' || ", $this->wrapArray($command->columns))
        );
    }

    /**
     * Compile a spatial index command
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'create index %s on %s using gist (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a drop primary key command
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command): string
    {
        $table = $blueprint->getTable();
        $index = $command->index ?: "{$table}_pkey";

        return sprintf(
            'alter table %s drop constraint %s',
            $this->wrapTable($blueprint),
            $this->wrap($index)
        );
    }

    /**
     * Compile a drop unique key command
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s drop constraint %s',
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
            'drop index %s',
            $this->wrap($command->index)
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
            'alter table %s drop constraint %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->index)
        );
    }

    /**
     * Wrap an array of values
     */
    protected function wrapArray(array $values): array
    {
        return array_map([$this, 'wrap'], $values);
    }

    /**
     * Add a prefix to each value in an array
     */
    protected function prefixArray(string $prefix, array $values): array
    {
        return array_map(function ($value) use ($prefix) {
            return $prefix . ' ' . $value;
        }, $values);
    }
}