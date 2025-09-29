<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Fluent;
use Bob\Schema\SchemaGrammar;

beforeEach(function () {
    $this->grammar = new TestableSchemaGrammar();
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:'
    ]);
});

test('get fluent commands', function () {
    $commands = $this->grammar->getFluentCommands();
    expect($commands)->toBeArray();
});

test('supports schema transactions', function () {
    expect($this->grammar->supportsSchemaTransactions())->toBeTrue();
});

test('set and get table prefix', function () {
    expect($this->grammar->getTablePrefix())->toBe('');

    $this->grammar->setTablePrefix('test_');
    expect($this->grammar->getTablePrefix())->toBe('test_');
});

test('wrap table with string', function () {
    expect($this->grammar->wrapTable('users'))->toBe('"users"');

    // Test with prefix
    $this->grammar->setTablePrefix('app_');
    expect($this->grammar->wrapTable('users'))->toBe('"app_users"');
});

test('wrap table with blueprint', function () {
    $blueprint = new Blueprint('posts');
    expect($this->grammar->wrapTable($blueprint))->toBe('"posts"');

    // Test with prefix
    $this->grammar->setTablePrefix('wp_');
    expect($this->grammar->wrapTable($blueprint))->toBe('"wp_posts"');
});

test('wrap star', function () {
    expect($this->grammar->wrap('*'))->toBe('*');
});

test('wrap aliased value', function () {
    expect($this->grammar->wrap('name as full_name'))->toBe('"name" as "full_name"');
    expect($this->grammar->wrap('name AS full_name'))->toBe('"name" as "full_name"');
    expect($this->grammar->wrap('table.column as alias'))->toBe('"table"."column" as "alias"');
});

test('wrap segmented value', function () {
    expect($this->grammar->wrap('table.column'))->toBe('"table"."column"');
    expect($this->grammar->wrap('schema.table.column'))->toBe('"schema"."table"."column"');
});

test('wrap simple value', function () {
    expect($this->grammar->wrap('name'))->toBe('"name"');
    expect($this->grammar->wrap('user_id'))->toBe('"user_id"');
    expect($this->grammar->wrap('count'))->toBe('"count"');
});

test('wrap value with quotes', function () {
    expect($this->grammar->wrap('"quoted"'))->toBe('"""quoted"""');
    expect($this->grammar->wrap('"double"quotes"'))->toBe('"""double""quotes"""');
});

test('columnize', function () {
    $columns = ['name', 'email', 'created_at'];
    $expected = '"name", "email", "created_at"';
    expect($this->grammar->columnize($columns))->toBe($expected);
});

test('columnize with star', function () {
    $columns = ['*'];
    expect($this->grammar->columnize($columns))->toBe('*');
});

test('columnize with table prefix', function () {
    $columns = ['table.name', 'table.email'];
    $expected = '"table"."name", "table"."email"';
    expect($this->grammar->columnize($columns))->toBe($expected);
});

test('get columns basic', function () {
    $blueprint = new Blueprint('test');
    $blueprint->string('name');
    $blueprint->integer('age');

    $columns = $this->grammar->getColumns($blueprint);

    expect($columns)->toHaveCount(2);
    expect($columns[0])->toContain('"name"');
    expect($columns[0])->toContain('varchar');
    expect($columns[1])->toContain('"age"');
    expect($columns[1])->toContain('integer');
});

test('get type with valid type', function () {
    $column = new Fluent(['type' => 'string', 'length' => 100]);
    $type = $this->grammar->getType($column);
    expect($type)->toContain('varchar');
});

test('get type with invalid type', function () {
    $column = new Fluent(['type' => 'invalidType']);

    expect(fn() => $this->grammar->getType($column))
        ->toThrow(\RuntimeException::class, 'Column type [invalidType] is not supported.');
});

test('add modifiers with nullable', function () {
    $blueprint = new Blueprint('test');
    $column = new Fluent(['nullable' => true]);

    $sql = $this->grammar->addModifiers('"name" varchar(255)', $blueprint, $column);
    expect($sql)->toContain(' null');
});

test('add modifiers with not null', function () {
    $blueprint = new Blueprint('test');
    $column = new Fluent(['nullable' => false]);

    $sql = $this->grammar->addModifiers('"name" varchar(255)', $blueprint, $column);
    expect($sql)->toContain(' not null');
});

test('modify nullable', function () {
    $blueprint = new Blueprint('test');
    $nullableColumn = new Fluent(['nullable' => true]);
    $notNullColumn = new Fluent(['nullable' => false]);

    expect($this->grammar->modifyNullable($blueprint, $nullableColumn))->toBe(' null');
    expect($this->grammar->modifyNullable($blueprint, $notNullColumn))->toBe(' not null');
});

test('modify default with value', function () {
    $blueprint = new Blueprint('test');
    $column = new Fluent(['default' => 'test_value']);

    $modifier = $this->grammar->modifyDefault($blueprint, $column);
    expect($modifier)->toBe(" default 'test_value'");
});

test('modify default with null', function () {
    $blueprint = new Blueprint('test');
    $column = new Fluent(['default' => null]);

    $modifier = $this->grammar->modifyDefault($blueprint, $column);
    expect($modifier)->toBe('');
});

test('modify increment', function () {
    $blueprint = new Blueprint('test');
    $incrementColumn = new Fluent(['type' => 'integer', 'autoIncrement' => true]);
    $regularColumn = new Fluent(['type' => 'string', 'autoIncrement' => false]);

    expect($this->grammar->modifyIncrement($blueprint, $incrementColumn))->toBe(' auto_increment primary key');
    expect($this->grammar->modifyIncrement($blueprint, $regularColumn))->toBe('');
});

test('modify increment for all integer types', function () {
    $blueprint = new Blueprint('test');
    $types = ['tinyInteger', 'smallInteger', 'mediumInteger', 'integer', 'bigInteger'];

    foreach ($types as $type) {
        $column = new Fluent(['type' => $type, 'autoIncrement' => true]);
        expect($this->grammar->modifyIncrement($blueprint, $column))->toBe(' auto_increment primary key');
    }
});

test('get default value with boolean', function () {
    expect($this->grammar->getDefaultValue(true))->toBe('1');
    expect($this->grammar->getDefaultValue(false))->toBe('0');
});

test('get default value with string', function () {
    expect($this->grammar->getDefaultValue('test'))->toBe("'test'");
    expect($this->grammar->getDefaultValue("it's working"))->toBe("'it''s working'");
});

test('get default value with null', function () {
    expect($this->grammar->getDefaultValue(null))->toBe('null');
});

test('get default value with number', function () {
    expect($this->grammar->getDefaultValue(42))->toBe('42');
    expect($this->grammar->getDefaultValue(3.14))->toBe('3.14');
});

test('compile primary', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['columns' => ['id']]);

    $sql = $this->grammar->compilePrimary($blueprint, $command);
    expect($sql)->toBe('alter table "users" add primary key ("id")');
});

test('compile primary with index', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['columns' => ['id'], 'index' => 'users_id_primary']);

    $sql = $this->grammar->compilePrimary($blueprint, $command);
    expect($sql)->toBe('alter table "users" add primary key constraint "users_id_primary" ("id")');
});

test('compile unique', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['columns' => ['email']]);

    $sql = $this->grammar->compileUnique($blueprint, $command);
    expect($sql)->toBe('alter table "users" add unique ("email")');
});

test('compile unique with index', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['columns' => ['email'], 'index' => 'users_email_unique']);

    $sql = $this->grammar->compileUnique($blueprint, $command);
    expect($sql)->toBe('alter table "users" add unique "users_email_unique" ("email")');
});

test('compile index', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['columns' => ['name'], 'index' => 'users_name_index']);

    $sql = $this->grammar->compileIndex($blueprint, $command);
    expect($sql)->toBe('create index "users_name_index" on "users" ("name")');
});

test('compile fulltext throws exception', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent(['columns' => ['content']]);

    expect(fn() => $this->grammar->compileFulltext($blueprint, $command))
        ->toThrow(\RuntimeException::class, 'This database does not support fulltext indexes.');
});

test('compile spatial index throws exception', function () {
    $blueprint = new Blueprint('places');
    $command = new Fluent(['columns' => ['location']]);

    expect(fn() => $this->grammar->compileSpatialIndex($blueprint, $command))
        ->toThrow(\RuntimeException::class, 'This database does not support spatial indexes.');
});

test('compile foreign', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent([
        'columns' => ['user_id'],
        'on' => 'users',
        'references' => ['id']
    ]);

    $sql = $this->grammar->compileForeign($blueprint, $command);
    expect($sql)->toContain('alter table "posts" add constraint');
    expect($sql)->toContain('foreign key ("user_id") references "users" ("id")');
});

test('compile foreign with name', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent([
        'columns' => ['user_id'],
        'on' => 'users',
        'references' => ['id'],
        'name' => 'posts_user_id_foreign'
    ]);

    $sql = $this->grammar->compileForeign($blueprint, $command);
    expect($sql)->toContain('"posts_user_id_foreign"');
});

test('compile foreign with actions', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent([
        'columns' => ['user_id'],
        'on' => 'users',
        'references' => ['id'],
        'onDelete' => 'cascade',
        'onUpdate' => 'restrict'
    ]);

    $sql = $this->grammar->compileForeign($blueprint, $command);
    expect($sql)->toContain('on delete cascade');
    expect($sql)->toContain('on update restrict');
});

test('get foreign key name', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent(['columns' => ['user_id']]);

    $name = $this->grammar->getForeignKeyName($blueprint, $command);
    expect($name)->toBe('posts_user_id_foreign');
});

test('get foreign key name with dots', function () {
    $blueprint = new Blueprint('schema.posts');
    $command = new Fluent(['columns' => ['user_id', 'company_id']]);

    $name = $this->grammar->getForeignKeyName($blueprint, $command);
    expect($name)->toBe('schema_posts_user_id_company_id_foreign');
});

test('compile drop primary', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent([]);

    $sql = $this->grammar->compileDropPrimary($blueprint, $command);
    expect($sql)->toBe('alter table "users" drop primary key');
});

test('compile drop unique', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['index' => 'users_email_unique']);

    $sql = $this->grammar->compileDropUnique($blueprint, $command);
    expect($sql)->toBe('alter table "users" drop index "users_email_unique"');
});

test('compile drop index', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['index' => 'users_name_index']);

    $sql = $this->grammar->compileDropIndex($blueprint, $command);
    expect($sql)->toBe('drop index "users_name_index" on "users"');
});

test('compile drop fulltext', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent(['index' => 'posts_content_fulltext']);

    $sql = $this->grammar->compileDropFulltext($blueprint, $command);
    expect($sql)->toBe('drop index "posts_content_fulltext" on "posts"');
});

test('compile drop spatial index', function () {
    $blueprint = new Blueprint('places');
    $command = new Fluent(['index' => 'places_location_spatial']);

    $sql = $this->grammar->compileDropSpatialIndex($blueprint, $command);
    expect($sql)->toBe('drop index "places_location_spatial" on "places"');
});

test('compile drop foreign', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent(['index' => 'posts_user_id_foreign']);

    $sql = $this->grammar->compileDropForeign($blueprint, $command);
    expect($sql)->toBe('alter table "posts" drop foreign key "posts_user_id_foreign"');
});

test('compile comment throws exception', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent([]);

    expect(fn() => $this->grammar->compileComment($blueprint, $command))
        ->toThrow(\RuntimeException::class, 'This database does not support table comments.');
});

test('quote string with string', function () {
    expect($this->grammar->quoteString('test'))->toBe("'test'");
    expect($this->grammar->quoteString("it's working"))->toBe("'it''s working'");
});

test('quote string with array', function () {
    $values = ['test1', 'test2', "it's working"];
    $expected = "'test1', 'test2', 'it''s working'";
    expect($this->grammar->quoteString($values))->toBe($expected);
});

/**
 * Testable concrete implementation of SchemaGrammar for testing
 */
class TestableSchemaGrammar extends SchemaGrammar
{
    protected array $modifiers = ['Nullable', 'Default', 'Increment'];

    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return 'create table test';
    }

    public function compileAdd(Blueprint $blueprint, Fluent $command, Connection $connection): array|string
    {
        return 'alter table test add column';
    }

    public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return 'alter table test modify column';
    }

    public function compileDrop(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return 'drop table test';
    }

    public function compileDropIfExists(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return 'drop table if exists test';
    }

    public function compileDropColumn(Blueprint $blueprint, Fluent $command, Connection $connection): array|string
    {
        return 'alter table test drop column';
    }

    public function compileRename(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return 'alter table test rename to new_test';
    }

    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return 'alter table test rename column old to new';
    }

    public function compileEnableForeignKeyConstraints(): string
    {
        return 'enable foreign key constraints';
    }

    public function compileDisableForeignKeyConstraints(): string
    {
        return 'disable foreign key constraints';
    }

    // Implement required abstract type methods
    protected function typeString(Fluent $column): string
    {
        return "varchar({$column->length})";
    }

    protected function typeText(Fluent $column): string
    {
        return 'text';
    }

    protected function typeMediumText(Fluent $column): string
    {
        return 'mediumtext';
    }

    protected function typeLongText(Fluent $column): string
    {
        return 'longtext';
    }

    protected function typeInteger(Fluent $column): string
    {
        return 'integer';
    }

    protected function typeTinyInteger(Fluent $column): string
    {
        return 'tinyint';
    }

    protected function typeSmallInteger(Fluent $column): string
    {
        return 'smallint';
    }

    protected function typeMediumInteger(Fluent $column): string
    {
        return 'mediumint';
    }

    protected function typeBigInteger(Fluent $column): string
    {
        return 'bigint';
    }

    protected function typeFloat(Fluent $column): string
    {
        return 'float';
    }

    protected function typeDouble(Fluent $column): string
    {
        return 'double';
    }

    protected function typeDecimal(Fluent $column): string
    {
        return 'decimal';
    }

    protected function typeBoolean(Fluent $column): string
    {
        return 'boolean';
    }

    protected function typeJson(Fluent $column): string
    {
        return 'json';
    }

    protected function typeDate(Fluent $column): string
    {
        return 'date';
    }

    protected function typeDateTime(Fluent $column): string
    {
        return 'datetime';
    }

    protected function typeTime(Fluent $column): string
    {
        return 'time';
    }

    protected function typeTimestamp(Fluent $column): string
    {
        return 'timestamp';
    }

    protected function typeBinary(Fluent $column): string
    {
        return 'blob';
    }

    protected function typeUuid(Fluent $column): string
    {
        return 'char(36)';
    }

    // Make protected methods public for testing
    public function getColumns(Blueprint $blueprint): array
    {
        return parent::getColumns($blueprint);
    }

    public function getType(Fluent $column): string
    {
        return parent::getType($column);
    }

    public function addModifiers(string $sql, Blueprint $blueprint, Fluent $column): string
    {
        return parent::addModifiers($sql, $blueprint, $column);
    }

    public function modifyNullable(Blueprint $blueprint, Fluent $column): string
    {
        return parent::modifyNullable($blueprint, $column);
    }

    public function modifyDefault(Blueprint $blueprint, Fluent $column): string
    {
        return parent::modifyDefault($blueprint, $column);
    }

    public function modifyIncrement(Blueprint $blueprint, Fluent $column): string
    {
        return parent::modifyIncrement($blueprint, $column);
    }

    public function getDefaultValue(mixed $value): string
    {
        return parent::getDefaultValue($value);
    }

    public function getForeignKeyName(Blueprint $blueprint, Fluent $command): string
    {
        return parent::getForeignKeyName($blueprint, $command);
    }

    public function quoteString(string|array $value): string
    {
        return parent::quoteString($value);
    }
}