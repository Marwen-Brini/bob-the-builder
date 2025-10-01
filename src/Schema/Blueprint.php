<?php

declare(strict_types=1);

namespace Bob\Schema;

use Bob\Database\Connection;
use Closure;

/**
 * Schema Blueprint for defining database table structures
 *
 * This class provides a fluent interface for defining table columns,
 * indexes, and constraints. It works with all supported databases:
 * MySQL, PostgreSQL, and SQLite.
 */
class Blueprint
{
    /**
     * The table the blueprint describes
     */
    protected string $table;

    /**
     * The prefix for the table
     */
    protected string $prefix = '';

    /**
     * The columns that should be added to the table
     */
    protected array $columns = [];

    /**
     * The commands that should be run for the table
     */
    protected array $commands = [];

    /**
     * The storage engine for the table (MySQL)
     */
    public ?string $engine = null;

    /**
     * The default character set for the table (MySQL)
     */
    public ?string $charset = null;

    /**
     * The collation for the table (MySQL)
     */
    public ?string $collation = null;

    /**
     * Whether this table is temporary
     */
    public bool $temporary = false;

    /**
     * The column to add new columns after
     */
    protected ?string $after = null;

    /**
     * Create a new schema blueprint
     */
    public function __construct(string $table, ?Closure $callback = null, string $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;

        if ($callback !== null) {
            $callback($this);
        }
    }

    /**
     * Execute the blueprint against the database
     */
    public function build(Connection $connection, SchemaGrammar $grammar): void
    {
        foreach ($this->toSql($connection, $grammar) as $statement) {
            $connection->statement($statement);
        }
    }

    /**
     * Get the raw SQL statements for the blueprint
     */
    public function toSql(Connection $connection, SchemaGrammar $grammar): array
    {
        $this->addImpliedCommands($grammar);

        $statements = [];

        foreach ($this->commands as $command) {
            $method = 'compile'.ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                $sql = $grammar->$method($this, $command, $connection);

                if ($sql !== null) {
                    $statements = array_merge($statements, (array) $sql);
                }
            }
        }

        return $statements;
    }

    /**
     * Add implied commands based on the columns
     */
    protected function addImpliedCommands(SchemaGrammar $grammar): void
    {
        if (count($this->columns) > 0 && ! $this->creating()) {
            array_unshift($this->commands, $this->createCommand('add'));
        }

        $this->addFluentIndexes();
        $this->addFluentCommands($grammar);
        $this->addAutoIncrementCommands();
    }

    /**
     * Add fluent indexes
     */
    protected function addFluentIndexes(): void
    {
        foreach ($this->columns as $column) {
            foreach (['primary', 'unique', 'index', 'fulltext', 'spatialIndex'] as $index) {
                if ($column->{$index} === true) {
                    if ($index === 'primary') {
                        $this->primary($column->name);
                    } elseif ($index === 'unique') {
                        $this->unique($column->name);
                    } elseif ($index === 'index') {
                        $this->index($column->name);
                    } elseif ($index === 'fulltext') {
                        $this->fulltext($column->name);
                        // @codeCoverageIgnoreStart
                    } elseif ($index === 'spatialIndex') {
                        $this->spatialIndex($column->name);
                        // @codeCoverageIgnoreEnd
                    }

                    unset($column->{$index});
                }
            }

            // Process constrained columns to create foreign keys
            if (isset($column->constrained)) {
                $table = $column->constrained['table'];
                $references = $column->constrained['column'] ?? 'id';

                // If no table name provided, try to infer from column name
                if ($table === null) {
                    // Convert user_id to users, post_id to posts, etc.
                    $table = str_replace('_id', '', $column->name).'s';
                }

                $foreign = $this->foreign($column->name);
                $foreign->references($references)->on($table);

                // Apply cascade options if set
                if (isset($column->onDelete)) {
                    $foreign->onDelete = $column->onDelete;
                }
                if (isset($column->onUpdate)) {
                    $foreign->onUpdate = $column->onUpdate;
                }

                unset($column->constrained);
            }
        }
    }

    /**
     * Add auto-increment commands for columns with from() values
     */
    protected function addAutoIncrementCommands(): void
    {
        foreach ($this->columns as $column) {
            if (isset($column->from) && $column->from > 1) {
                $this->addCommand('autoIncrementStartingValues', ['column' => $column]);
            }
        }
    }

    /**
     * Add fluent commands for the grammar
     */
    protected function addFluentCommands(SchemaGrammar $grammar): void
    {
        foreach ($this->columns as $column) {
            foreach ($grammar->getFluentCommands() as $commandName) {
                $attributeName = lcfirst($commandName);

                if (! isset($column->{$attributeName})) {
                    continue;
                }

                $value = $column->{$attributeName};

                $this->addCommand(
                    $commandName,
                    compact('column', 'value')
                );

                unset($column->{$attributeName});
            }
        }
    }

    /**
     * Check if the blueprint is creating a table
     */
    public function creating(): bool
    {
        return collect($this->commands)->contains(function ($command) {
            return $command->name === 'create';
        });
    }

    /**
     * Create a new auto-incrementing big integer column
     */
    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column);
    }

    /**
     * Create a new auto-incrementing big integer column
     */
    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->unsignedBigInteger($column, true);
    }

    /**
     * Create a new auto-incrementing integer column
     */
    public function increments(string $column): ColumnDefinition
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Create a new auto-incrementing tiny integer column
     */
    public function tinyIncrements(string $column): ColumnDefinition
    {
        return $this->unsignedTinyInteger($column, true);
    }

    /**
     * Create a new auto-incrementing small integer column
     */
    public function smallIncrements(string $column): ColumnDefinition
    {
        return $this->unsignedSmallInteger($column, true);
    }

    /**
     * Create a new auto-incrementing medium integer column
     */
    public function mediumIncrements(string $column): ColumnDefinition
    {
        return $this->unsignedMediumInteger($column, true);
    }

    /**
     * Create a new char column
     */
    public function char(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('char', $column, compact('length'));
    }

    /**
     * Create a new string column
     */
    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * Create a new tiny text column
     */
    public function tinyText(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyText', $column);
    }

    /**
     * Create a new text column
     */
    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a new medium text column
     */
    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Create a new long text column
     */
    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Create a new tiny integer column
     */
    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new small integer column
     */
    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new medium integer column
     */
    public function mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new integer column
     */
    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new big integer column
     */
    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new unsigned tiny integer column
     */
    public function unsignedTinyInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned small integer column
     */
    public function unsignedSmallInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned medium integer column
     */
    public function unsignedMediumInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned integer column
     */
    public function unsignedInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned big integer column
     */
    public function unsignedBigInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new float column
     */
    public function float(string $column, int $precision = 8, int $scale = 2, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('float', $column, compact('precision', 'scale', 'unsigned'));
    }

    /**
     * Create a new double column
     */
    public function double(string $column, ?int $precision = null, ?int $scale = null, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('double', $column, compact('precision', 'scale', 'unsigned'));
    }

    /**
     * Create a new decimal column
     */
    public function decimal(string $column, int $precision = 8, int $scale = 2, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, compact('precision', 'scale', 'unsigned'));
    }

    /**
     * Create a new unsigned float column
     */
    public function unsignedFloat(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->float($column, $precision, $scale, true);
    }

    /**
     * Create a new unsigned double column
     */
    public function unsignedDouble(string $column, ?int $precision = null, ?int $scale = null): ColumnDefinition
    {
        return $this->double($column, $precision, $scale, true);
    }

    /**
     * Create a new unsigned decimal column
     */
    public function unsignedDecimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->decimal($column, $precision, $scale, true);
    }

    /**
     * Create a new boolean column
     */
    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a new enum column
     */
    public function enum(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    /**
     * Create a new set column
     */
    public function set(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('set', $column, compact('allowed'));
    }

    /**
     * Create a new json column
     */
    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a new jsonb column
     */
    public function jsonb(string $column): ColumnDefinition
    {
        return $this->addColumn('jsonb', $column);
    }

    /**
     * Create a new date column
     */
    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a new datetime column
     */
    public function dateTime(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    /**
     * Create a new datetime with timezone column
     */
    public function dateTimeTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('dateTimeTz', $column, compact('precision'));
    }

    /**
     * Create a new time column
     */
    public function time(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    /**
     * Create a new time with timezone column
     */
    public function timeTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('timeTz', $column, compact('precision'));
    }

    /**
     * Create a new timestamp column
     */
    public function timestamp(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    /**
     * Create a new timestamp with timezone column
     */
    public function timestampTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('timestampTz', $column, compact('precision'));
    }

    /**
     * Add nullable creation and update timestamps
     */
    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Add nullable creation and update timestamps with timezone
     */
    public function timestampsTz(int $precision = 0): void
    {
        $this->timestampTz('created_at', $precision)->nullable();
        $this->timestampTz('updated_at', $precision)->nullable();
    }

    /**
     * Add a soft delete column
     */
    public function softDeletes(string $column = 'deleted_at', int $precision = 0): ColumnDefinition
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    /**
     * Add a soft delete column with timezone
     */
    public function softDeletesTz(string $column = 'deleted_at', int $precision = 0): ColumnDefinition
    {
        return $this->timestampTz($column, $precision)->nullable();
    }

    /**
     * Create a new year column
     */
    public function year(string $column): ColumnDefinition
    {
        return $this->addColumn('year', $column);
    }

    /**
     * Create a new binary column
     */
    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a new uuid column
     */
    public function uuid(string $column = 'uuid'): ColumnDefinition
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Create a new foreign ID column
     */
    public function foreignId(string $column): ColumnDefinition
    {
        return $this->unsignedBigInteger($column);
    }

    /**
     * Create a new foreign UUID column
     */
    public function foreignUuid(string $column): ColumnDefinition
    {
        return $this->uuid($column);
    }

    /**
     * Create a new IP address column
     */
    public function ipAddress(string $column = 'ip_address'): ColumnDefinition
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * Create a new MAC address column
     */
    public function macAddress(string $column = 'mac_address'): ColumnDefinition
    {
        return $this->addColumn('macAddress', $column);
    }

    /**
     * Create a new geometry column
     */
    public function geometry(string $column): ColumnDefinition
    {
        return $this->addColumn('geometry', $column);
    }

    /**
     * Create a new point column
     */
    public function point(string $column): ColumnDefinition
    {
        return $this->addColumn('point', $column);
    }

    /**
     * Create a new linestring column
     */
    public function lineString(string $column): ColumnDefinition
    {
        return $this->addColumn('lineString', $column);
    }

    /**
     * Create a new polygon column
     */
    public function polygon(string $column): ColumnDefinition
    {
        return $this->addColumn('polygon', $column);
    }

    /**
     * Create a new geometrycollection column
     */
    public function geometryCollection(string $column): ColumnDefinition
    {
        return $this->addColumn('geometryCollection', $column);
    }

    /**
     * Create a new multipoint column
     */
    public function multiPoint(string $column): ColumnDefinition
    {
        return $this->addColumn('multiPoint', $column);
    }

    /**
     * Create a new multilinestring column
     */
    public function multiLineString(string $column): ColumnDefinition
    {
        return $this->addColumn('multiLineString', $column);
    }

    /**
     * Create a new multipolygon column
     */
    public function multiPolygon(string $column): ColumnDefinition
    {
        return $this->addColumn('multiPolygon', $column);
    }

    /**
     * Create a new generated column
     */
    public function computed(string $column, string $expression): ColumnDefinition
    {
        return $this->addColumn('computed', $column, compact('expression'));
    }

    /**
     * Add a new column to the blueprint
     */
    protected function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition(
            array_merge(compact('type', 'name'), $parameters)
        );

        $this->columns[] = $column;

        if ($this->after !== null) {
            $column->after($this->after);
            $this->after = $column->name;
        }

        return $column;
    }

    /**
     * Remove a column from the schema blueprint
     */
    public function removeColumn(string $name): self
    {
        $this->columns = array_filter($this->columns, function ($column) use ($name) {
            return $column->name !== $name;
        });

        return $this;
    }

    /**
     * Indicate that the table needs to be created
     */
    public function create(): Fluent
    {
        return $this->addCommand('create');
    }

    /**
     * Indicate that the table should be dropped
     */
    public function drop(): Fluent
    {
        return $this->addCommand('drop');
    }

    /**
     * Indicate that the table should be dropped if it exists
     */
    public function dropIfExists(): Fluent
    {
        return $this->addCommand('dropIfExists');
    }

    /**
     * Specify the primary key(s) for the table
     */
    public function primary(string|array $columns, ?string $name = null, ?string $algorithm = null): Fluent
    {
        return $this->indexCommand('primary', $columns, $name, $algorithm);
    }

    /**
     * Specify a unique index for the table
     */
    public function unique(string|array $columns, ?string $name = null, ?string $algorithm = null): Fluent
    {
        return $this->indexCommand('unique', $columns, $name, $algorithm);
    }

    /**
     * Specify an index for the table
     */
    public function index(string|array $columns, ?string $name = null, ?string $algorithm = null): Fluent
    {
        return $this->indexCommand('index', $columns, $name, $algorithm);
    }

    /**
     * Specify a fulltext index for the table
     */
    public function fulltext(string|array $columns, ?string $name = null, ?string $algorithm = null): Fluent
    {
        return $this->indexCommand('fulltext', $columns, $name, $algorithm);
    }

    /**
     * Specify a spatial index for the table
     */
    public function spatialIndex(string|array $columns, ?string $name = null): Fluent
    {
        return $this->indexCommand('spatialIndex', $columns, $name);
    }

    /**
     * Specify a raw index for the table
     */
    public function rawIndex(string $expression, string $name): Fluent
    {
        return $this->addCommand('rawIndex', compact('expression', 'name'));
    }

    /**
     * Specify a foreign key for the table
     */
    public function foreign(string|array $columns, ?string $name = null): ForeignKeyDefinition
    {
        $command = new ForeignKeyDefinition([
            'name' => 'foreign',
            'index' => $name,
            'columns' => (array) $columns,
        ]);

        $this->commands[] = $command;

        return $command;
    }

    /**
     * Drop columns from the table
     */
    public function dropColumn(string|array $columns): Fluent
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return $this->addCommand('dropColumn', compact('columns'));
    }

    /**
     * Rename a column on the table
     */
    public function renameColumn(string $from, string $to): Fluent
    {
        return $this->addCommand('renameColumn', compact('from', 'to'));
    }

    /**
     * Drop the primary key from the table
     */
    public function dropPrimary(?string $index = null): Fluent
    {
        return $this->dropIndexCommand('dropPrimary', 'primary', $index);
    }

    /**
     * Drop a unique index from the table
     */
    public function dropUnique(string|array $index): Fluent
    {
        return $this->dropIndexCommand('dropUnique', 'unique', $index);
    }

    /**
     * Drop an index from the table
     */
    public function dropIndex(string|array $index): Fluent
    {
        return $this->dropIndexCommand('dropIndex', 'index', $index);
    }

    /**
     * Drop a fulltext index from the table
     */
    public function dropFulltext(string|array $index): Fluent
    {
        return $this->dropIndexCommand('dropFulltext', 'fulltext', $index);
    }

    /**
     * Drop a spatial index from the table
     */
    public function dropSpatialIndex(string|array $index): Fluent
    {
        return $this->dropIndexCommand('dropSpatialIndex', 'spatialIndex', $index);
    }

    /**
     * Drop a foreign key from the table
     */
    public function dropForeign(string|array $index): Fluent
    {
        return $this->dropIndexCommand('dropForeign', 'foreign', $index);
    }

    /**
     * Drop the timestamps from the table
     */
    public function dropTimestamps(): void
    {
        $this->dropColumn('created_at', 'updated_at');
    }

    /**
     * Drop the soft delete column from the table
     */
    public function dropSoftDeletes(string $column = 'deleted_at'): void
    {
        $this->dropColumn($column);
    }

    /**
     * Rename the table
     */
    public function rename(string $to): Fluent
    {
        return $this->addCommand('rename', compact('to'));
    }

    /**
     * Specify the table comment (MySQL)
     */
    public function comment(string $comment): Fluent
    {
        return $this->addCommand('comment', compact('comment'));
    }

    /**
     * Add a new index command to the blueprint
     */
    protected function indexCommand(string $type, string|array $columns, ?string $index, ?string $algorithm = null): Fluent
    {
        $columns = (array) $columns;

        $index = $index ?: $this->createIndexName($type, $columns);

        return $this->addCommand(
            $type,
            compact('index', 'columns', 'algorithm')
        );
    }

    /**
     * Create a new drop index command on the blueprint
     */
    protected function dropIndexCommand(string $command, string $type, string|array $index): Fluent
    {
        $columns = [];

        if (is_array($index)) {
            $index = $this->createIndexName($type, $columns = $index);
        }

        return $this->addCommand($command, compact('index', 'columns'));
    }

    /**
     * Create a default index name for the table
     */
    protected function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->prefix.$this->table.'_'.implode('_', $columns).'_'.$type);

        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * Add a new command to the blueprint
     */
    protected function addCommand(string $name, array $parameters = []): Fluent
    {
        $command = $this->createCommand($name, $parameters);
        $this->commands[] = $command;

        return $command;
    }

    /**
     * Create a new command
     */
    protected function createCommand(string $name, array $parameters = []): Fluent
    {
        return new Fluent(array_merge(['name' => $name], $parameters));
    }

    /**
     * Get the table the blueprint describes
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the columns on the blueprint
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the commands on the blueprint
     */
    public function getCommands(): array
    {
        // Process fluent indexes before returning commands
        $this->addFluentIndexes();

        return $this->commands;
    }

    /**
     * Get the columns that have been added
     */
    public function getAddedColumns(): array
    {
        return array_filter($this->columns, function ($column) {
            return ! $column->change;
        });
    }

    /**
     * Get the columns that have been changed
     */
    public function getChangedColumns(): array
    {
        return array_values(array_filter($this->columns, function ($column) {
            return isset($column->change) && $column->change === true;
        }));
    }

    /**
     * Set the table to add columns after a specific column
     */
    public function after(string $column, Closure $callback): void
    {
        $this->after = $column;
        $callback($this);
        $this->after = null;
    }
}

/**
 * Helper function to create an array collection
 */
function collect(array $items): Collection
{
    return new Collection($items);
}

/**
 * Minimal collection implementation for Blueprint
 */
class Collection
{
    private array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function contains(callable $callback): bool
    {
        foreach ($this->items as $item) {
            if ($callback($item)) {
                return true;
            }
        }

        return false;
    }
}
