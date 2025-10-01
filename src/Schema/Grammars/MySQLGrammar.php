<?php

declare(strict_types=1);

namespace Bob\Schema\Grammars;

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Fluent;
use Bob\Schema\SchemaGrammar;

/**
 * MySQL Schema Grammar
 *
 * Generates MySQL-specific SQL statements for schema operations.
 * Supports MySQL 5.7+ and MySQL 8.0+ features.
 */
class MySQLGrammar extends SchemaGrammar
{
    /**
     * The possible column modifiers
     */
    protected array $modifiers = [
        'Unsigned', 'Charset', 'Collate', 'VirtualAs', 'StoredAs',
        'Nullable', 'Default', 'OnUpdate', 'UseCurrent', 'Increment', 'Comment', 'After', 'First',
        'Invisible', 'Srid', 'AutoIncrement',
    ];

    /**
     * The fluent command methods on the Blueprint
     */
    protected array $fluentCommands = [
        'Comment', 'Charset', 'Collation', 'Engine', 'Temporary',
    ];

    /**
     * The possible column serials
     */
    protected array $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * Compile a create table command
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        $sql = $this->compileCreateTable($blueprint, $command);

        // Add the encoding if specified
        $sql = $this->compileCreateEncoding($sql, $connection, $blueprint);

        // Add the engine if specified
        $sql = $this->compileCreateEngine($sql, $connection, $blueprint);

        return $sql;
    }

    /**
     * Create the main create table clause
     */
    protected function compileCreateTable(Blueprint $blueprint, Fluent $command): string
    {
        $tableStructure = $this->getColumns($blueprint);

        if ($primaryKey = $this->getCommandByName($blueprint, 'primary')) {
            $tableStructure[] = sprintf(
                'primary key %s(%s)',
                $primaryKey->algorithm ? 'using '.strtolower($primaryKey->algorithm).' ' : '',
                $this->columnize($primaryKey->columns)
            );

            $primaryKey->shouldBeSkipped = true;
        }

        return sprintf(
            '%screate table %s (%s)',
            $blueprint->temporary ? 'create temporary table ' : '',
            $this->wrapTable($blueprint),
            implode(', ', $tableStructure)
        );
    }

    /**
     * Append the character set specifications
     */
    protected function compileCreateEncoding(string $sql, Connection $connection, Blueprint $blueprint): string
    {
        if (isset($blueprint->charset)) {
            $sql .= ' default character set '.$blueprint->charset;
        } elseif (! is_null($charset = $connection->getConfig('charset'))) {
            $sql .= ' default character set '.$charset;
        }

        if (isset($blueprint->collation)) {
            $sql .= " collate '{$blueprint->collation}'";
        } elseif (! is_null($collation = $connection->getConfig('collation'))) {
            $sql .= " collate '{$collation}'";
        }

        return $sql;
    }

    /**
     * Append the engine specifications
     */
    protected function compileCreateEngine(string $sql, Connection $connection, Blueprint $blueprint): string
    {
        if (isset($blueprint->engine)) {
            return $sql.' engine = '.$blueprint->engine;
        }

        return $sql;
    }

    /**
     * Compile an add column command
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command, Connection $connection): array|string
    {
        $columns = $this->prefixArray('add', $this->getColumns($blueprint));

        return sprintf(
            'alter table %s %s',
            $this->wrapTable($blueprint),
            implode(', ', $columns)
        );
    }

    /**
     * Compile a change column command
     */
    public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        $columns = [];

        foreach ($blueprint->getChangedColumns() as $column) {
            $columns[] = sprintf(
                'change %s %s',
                $this->wrap($column->name),
                $this->getColumn($blueprint, $column)
            );
        }

        return sprintf(
            'alter table %s %s',
            $this->wrapTable($blueprint),
            implode(', ', $columns)
        );
    }

    /**
     * Compile a drop table command
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table if exists command
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return 'drop table if exists '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop column command
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command, Connection $connection): array|string
    {
        $columns = $this->prefixArray('drop', $this->wrapArray($command->columns));

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
            'rename table %s to %s',
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
            'alter table %s change %s %s %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to),
            $this->getColumnType($blueprint, $command->to)
        );
    }

    /**
     * Compile the SQL needed to drop all tables
     */
    public function compileDropAllTables(): string
    {
        return "SELECT CONCAT('DROP TABLE IF EXISTS `', table_name, '`;') FROM information_schema.tables WHERE table_schema = DATABASE()";
    }

    /**
     * Compile a fulltext index command
     */
    public function compileFulltext(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->columnize($command->columns);

        return sprintf(
            'create fulltext index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $columns
        );
    }

    /**
     * Compile a spatial index command
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->columnize($command->columns);

        return sprintf(
            'create spatial index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $columns
        );
    }

    /**
     * Compile the SQL needed to enable foreign key constraints
     */
    public function compileEnableForeignKeyConstraints(): string
    {
        return 'SET FOREIGN_KEY_CHECKS=1';
    }

    /**
     * Compile the SQL needed to disable foreign key constraints
     */
    public function compileDisableForeignKeyConstraints(): string
    {
        return 'SET FOREIGN_KEY_CHECKS=0';
    }

    /**
     * Compile the query to determine if a table exists
     */
    public function compileTableExists(): string
    {
        return "select * from information_schema.tables where table_schema = ? and table_name = ? and table_type = 'BASE TABLE'";
    }

    /**
     * Compile the query to determine column listing
     */
    public function compileColumnListing(string $table): string
    {
        return 'select column_name as `column_name` from information_schema.columns where table_schema = ? and table_name = ?';
    }

    /**
     * Compile the query to determine column type
     */
    public function compileColumnType(string $table, string $column): string
    {
        return 'select data_type from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?';
    }

    /**
     * Create the column definition for a char type
     */
    protected function typeChar(Fluent $column): string
    {
        return "char({$column->length})";
    }

    /**
     * Create the column definition for a string type
     */
    protected function typeString(Fluent $column): string
    {
        return "varchar({$column->length})";
    }

    /**
     * Create the column definition for a tiny text type
     */
    protected function typeTinyText(Fluent $column): string
    {
        return 'tinytext';
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
        return 'mediumtext';
    }

    /**
     * Create the column definition for a long text type
     */
    protected function typeLongText(Fluent $column): string
    {
        return 'longtext';
    }

    /**
     * Create the column definition for a tiny integer type
     */
    protected function typeTinyInteger(Fluent $column): string
    {
        return 'tinyint';
    }

    /**
     * Create the column definition for a small integer type
     */
    protected function typeSmallInteger(Fluent $column): string
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a medium integer type
     */
    protected function typeMediumInteger(Fluent $column): string
    {
        return 'mediumint';
    }

    /**
     * Create the column definition for a integer type
     */
    protected function typeInteger(Fluent $column): string
    {
        return 'int';
    }

    /**
     * Create the column definition for a big integer type
     */
    protected function typeBigInteger(Fluent $column): string
    {
        return 'bigint';
    }

    /**
     * Create the column definition for a float type
     */
    protected function typeFloat(Fluent $column): string
    {
        if ($column->precision) {
            return "float({$column->precision}, {$column->scale})";
        }

        return 'float';
    }

    /**
     * Create the column definition for a double type
     */
    protected function typeDouble(Fluent $column): string
    {
        if ($column->precision) {
            return "double({$column->precision}, {$column->scale})";
        }

        return 'double';
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
        return 'tinyint(1)';
    }

    /**
     * Create the column definition for an enum type
     */
    protected function typeEnum(Fluent $column): string
    {
        return sprintf('enum(%s)', $this->quoteString($column->allowed));
    }

    /**
     * Create the column definition for a set type
     */
    protected function typeSet(Fluent $column): string
    {
        return sprintf('set(%s)', $this->quoteString($column->allowed));
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
        return 'json';
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

        return 'datetime'.$precision;
    }

    /**
     * Create the column definition for a datetime with timezone type
     */
    protected function typeDateTimeTz(Fluent $column): string
    {
        return $this->typeDateTime($column);
    }

    /**
     * Create the column definition for a time type
     */
    protected function typeTime(Fluent $column): string
    {
        $precision = isset($column->precision) ? "({$column->precision})" : '';

        return 'time'.$precision;
    }

    /**
     * Create the column definition for a time with timezone type
     */
    protected function typeTimeTz(Fluent $column): string
    {
        return $this->typeTime($column);
    }

    /**
     * Create the column definition for a timestamp type
     */
    protected function typeTimestamp(Fluent $column): string
    {
        $precision = isset($column->precision) ? "({$column->precision})" : '';

        return 'timestamp'.$precision;
    }

    /**
     * Create the column definition for a timestamp with timezone type
     */
    protected function typeTimestampTz(Fluent $column): string
    {
        return $this->typeTimestamp($column);
    }

    /**
     * Create the column definition for a year type
     */
    protected function typeYear(Fluent $column): string
    {
        return 'year';
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
        return 'char(36)';
    }

    /**
     * Create the column definition for an IP address type
     */
    protected function typeIpAddress(Fluent $column): string
    {
        return 'varchar(45)';
    }

    /**
     * Create the column definition for a MAC address type
     */
    protected function typeMacAddress(Fluent $column): string
    {
        return 'varchar(17)';
    }

    /**
     * Create the column definition for a spatial geometry type
     */
    protected function typeGeometry(Fluent $column): string
    {
        return 'geometry';
    }

    /**
     * Create the column definition for a spatial point type
     */
    protected function typePoint(Fluent $column): string
    {
        return 'point';
    }

    /**
     * Create the column definition for a spatial linestring type
     */
    protected function typeLineString(Fluent $column): string
    {
        return 'linestring';
    }

    /**
     * Create the column definition for a spatial polygon type
     */
    protected function typePolygon(Fluent $column): string
    {
        return 'polygon';
    }

    /**
     * Create the column definition for a spatial geometrycollection type
     */
    protected function typeGeometryCollection(Fluent $column): string
    {
        return 'geometrycollection';
    }

    /**
     * Create the column definition for a spatial multipoint type
     */
    protected function typeMultiPoint(Fluent $column): string
    {
        return 'multipoint';
    }

    /**
     * Create the column definition for a spatial multilinestring type
     */
    protected function typeMultiLineString(Fluent $column): string
    {
        return 'multilinestring';
    }

    /**
     * Create the column definition for a spatial multipolygon type
     */
    protected function typeMultiPolygon(Fluent $column): string
    {
        return 'multipolygon';
    }

    /**
     * Create the column definition for a generated/computed column type
     */
    protected function typeComputed(Fluent $column): string
    {
        // For computed columns, return an appropriate base type
        return 'int';
    }

    /**
     * Get the SQL for an unsigned column modifier
     */
    protected function modifyUnsigned(Blueprint $blueprint, Fluent $column): string
    {
        if ($column->unsigned) {
            return ' unsigned';
        }

        return '';
    }

    /**
     * Get the SQL for a character set column modifier
     */
    protected function modifyCharset(Blueprint $blueprint, Fluent $column): string
    {
        if (! is_null($column->charset)) {
            return ' character set '.$column->charset;
        }

        return '';
    }

    /**
     * Get the SQL for a collation column modifier
     */
    protected function modifyCollate(Blueprint $blueprint, Fluent $column): string
    {
        if (! is_null($column->collation)) {
            return " collate {$column->collation}";
        }

        return '';
    }

    /**
     * Get the SQL for a virtual as column modifier
     */
    protected function modifyVirtualAs(Blueprint $blueprint, Fluent $column): string
    {
        if (! is_null($column->virtualAs)) {
            return " as ({$column->virtualAs}) virtual";
        }

        return '';
    }

    /**
     * Get the SQL for a stored as column modifier
     */
    protected function modifyStoredAs(Blueprint $blueprint, Fluent $column): string
    {
        if (! is_null($column->storedAs)) {
            return " as ({$column->storedAs}) stored";
        }

        return '';
    }

    /**
     * Get the SQL for a nullable column modifier
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column): string
    {
        if (is_null($column->virtualAs) && is_null($column->storedAs)) {
            return $column->nullable ? ' null' : ' not null';
        }

        if ($column->nullable === false) {
            return ' not null';
        }

        return '';
    }

    /**
     * Get the SQL for a default column modifier
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column): string
    {
        if (! is_null($column->default)) {
            return ' default '.$this->getDefaultValue($column->default);
        }

        return '';
    }

    /**
     * Get the SQL for an auto-increment column modifier
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column): string
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' auto_increment primary key';
        }

        return '';
    }

    /**
     * Get the SQL for an auto-increment column modifier
     */
    protected function modifyAutoIncrement(Blueprint $blueprint, Fluent $column): string
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' auto_increment';
        }

        return '';
    }

    /**
     * Get the SQL for a comment column modifier
     */
    protected function modifyComment(Blueprint $blueprint, Fluent $column): string
    {
        if (! is_null($column->comment)) {
            return " comment '".addslashes($column->comment)."'";
        }

        return '';
    }

    /**
     * Get the SQL for an after column modifier
     */
    protected function modifyAfter(Blueprint $blueprint, Fluent $column): string
    {
        if (! is_null($column->after)) {
            return ' after '.$this->wrap($column->after);
        }

        return '';
    }

    /**
     * Get the SQL for a first column modifier
     */
    protected function modifyFirst(Blueprint $blueprint, Fluent $column): string
    {
        if ($column->first) {
            return ' first';
        }

        return '';
    }

    /**
     * Get the SQL for an invisible column modifier
     */
    protected function modifyInvisible(Blueprint $blueprint, Fluent $column): string
    {
        if ($column->invisible) {
            return ' invisible';
        }

        return '';
    }

    /**
     * Get the SQL for a SRID column modifier
     */
    protected function modifySrid(Blueprint $blueprint, Fluent $column): string
    {
        if (! is_null($column->srid) && is_int($column->srid)) {
            return ' srid '.$column->srid;
        }

        return '';
    }

    /**
     * Wrap a single string value in keyword identifiers
     */
    protected function wrapValue(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        return '`'.str_replace('`', '``', $value).'`';
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
            return $prefix.' '.$value;
        }, $values);
    }

    /**
     * Get the column data for a single column
     */
    protected function getColumn(Blueprint $blueprint, Fluent $column): string
    {
        $sql = $this->wrap($column->name).' '.$this->getType($column);

        return $this->addModifiers($sql, $blueprint, $column);
    }

    /**
     * Get the column type for a given column name
     */
    protected function getColumnType(Blueprint $blueprint, string $columnName): string
    {
        $columns = $blueprint->getColumns();

        foreach ($columns as $column) {
            if ($column->name === $columnName) {
                return $this->getType($column);
            }
        }

        // If column not found in blueprint, return a default type
        return 'varchar(255)';
    }

    /**
     * Get a command by name from the blueprint
     */
    protected function getCommandByName(Blueprint $blueprint, string $name): ?Fluent
    {
        $commands = $blueprint->getCommands();

        foreach ($commands as $command) {
            if ($command->name === $name && ! isset($command->shouldBeSkipped)) {
                return $command;
            }
        }

        return null;
    }

    /**
     * Get the SQL for a use current column modifier
     */
    protected function modifyUseCurrent(Blueprint $blueprint, Fluent $column): string
    {
        if ($column->useCurrent) {
            return ' default current_timestamp';
        }

        return '';
    }

    /**
     * Get the SQL for an on update column modifier
     */
    protected function modifyOnUpdate(Blueprint $blueprint, Fluent $column): string
    {
        if ($column->useCurrentOnUpdate) {
            return ' on update current_timestamp';
        }

        return '';
    }

    /**
     * Compile a rename index command
     */
    public function compileRenameIndex(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return sprintf(
            'alter table %s rename index %s to %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile an auto-increment starting value command
     */
    public function compileAutoIncrementStartingValues(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        $startingValue = $command->column->from ?? 1;

        return sprintf(
            'alter table %s auto_increment = %d',
            $this->wrapTable($blueprint),
            $startingValue
        );
    }

    /**
     * Compile a comment command
     */
    public function compileComment(Blueprint $blueprint, Fluent $command): string
    {
        $column = $command->column;
        $comment = $command->value ?? $column->comment;

        // Find the column type from blueprint if not set
        if (! isset($column->type)) {
            $column->type = 'string';
            $column->length = 255;
        }

        // MySQL requires modifying the whole column to change a comment
        $sql = sprintf(
            'alter table %s modify %s %s',
            $this->wrapTable($blueprint),
            $this->wrap($column->name),
            $this->getType($column)
        );

        // Add not null modifier if needed
        $sql .= $column->nullable ? ' null' : ' not null';

        // Add the comment
        if ($comment) {
            $sql .= " comment '".addslashes($comment)."'";
        }

        return $sql;
    }

    /**
     * Check if this grammar supports schema transactions
     */
    public function supportsSchemaTransactions(): bool
    {
        return false;
    }

    /**
     * Get the fluent commands for the grammar
     */
    public function getFluentCommands(): array
    {
        return $this->fluentCommands;
    }
}
