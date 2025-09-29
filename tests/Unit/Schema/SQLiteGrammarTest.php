<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Fluent;
use Bob\Schema\Grammars\SQLiteGrammar;

beforeEach(function () {
    $this->grammar = new SQLiteGrammar();
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:'
    ]);
});

function callProtectedMethodSQLite($object, string $method, array $args = [])
{
    $reflection = new ReflectionClass($object);
    $method = $reflection->getMethod($method);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $args);
}

test('compile create', function () {
    $blueprint = new Blueprint('users');
    $blueprint->create();
    $blueprint->id();
    $blueprint->string('name');

    $command = new Fluent(['name' => 'create']);
    $sql = $this->grammar->compileCreate($blueprint, $command, $this->connection);

    expect($sql)->toContain('create table "users"');
    expect($sql)->toContain('"id" integer');
    expect($sql)->toContain('primary key autoincrement');
    expect($sql)->toContain('"name" text not null');
})->group('unit', 'sqlite-grammar');

test('compile create temporary', function () {
    $blueprint = new Blueprint('temp_users');
    $blueprint->temporary = true;
    $blueprint->create();
    $blueprint->string('name');

    $command = new Fluent(['name' => 'create']);
    $sql = $this->grammar->compileCreate($blueprint, $command, $this->connection);

    expect($sql)->toContain('create temporary table "temp_users"');
})->group('unit', 'sqlite-grammar');

test('compile create with foreign keys', function () {
    $blueprint = new Blueprint('posts');
    $blueprint->create();
    $blueprint->id();
    $blueprint->unsignedBigInteger('user_id');
    $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

    $command = new Fluent(['name' => 'create']);
    $sql = $this->grammar->compileCreate($blueprint, $command, $this->connection);

    expect($sql)->toContain('foreign key("user_id") references "users"("id")');
    expect($sql)->toContain('on delete cascade');
})->group('unit', 'sqlite-grammar');

test('compile create with primary key', function () {
    $blueprint = new Blueprint('test');
    $blueprint->create();
    $blueprint->string('code');
    $blueprint->string('name');
    $blueprint->primary(['code', 'name']);

    $command = new Fluent(['name' => 'create']);
    $sql = $this->grammar->compileCreate($blueprint, $command, $this->connection);

    expect($sql)->toContain('primary key ("code", "name")');
})->group('unit', 'sqlite-grammar');

test('add foreign keys', function () {
    $blueprint = new Blueprint('posts');
    $blueprint->foreign('user_id')->references('id')->on('users');
    $blueprint->foreign('category_id')->references('id')->on('categories')->onUpdate('restrict');

    $foreignKeys = callProtectedMethodSQLite($this->grammar, 'addForeignKeys', [$blueprint]);

    expect($foreignKeys)->toContain('foreign key("user_id") references "users"("id")');
    expect($foreignKeys)->toContain('foreign key("category_id") references "categories"("id")');
    expect($foreignKeys)->toContain('on update restrict');
})->group('unit', 'sqlite-grammar');

test('get foreign key', function () {
    $foreign = new Fluent([
        'name' => 'foreign',
        'columns' => ['user_id'],
        'on' => 'users',
        'references' => ['id'],
        'onDelete' => 'cascade',
        'onUpdate' => 'restrict'
    ]);

    $sql = callProtectedMethodSQLite($this->grammar, 'getForeignKey', [$foreign]);

    expect($sql)->toBe(', foreign key("user_id") references "users"("id") on delete cascade on update restrict');
})->group('unit', 'sqlite-grammar');

test('get foreign key without actions', function () {
    $foreign = new Fluent([
        'name' => 'foreign',
        'columns' => ['user_id'],
        'on' => 'users',
        'references' => ['id']
    ]);

    $sql = callProtectedMethodSQLite($this->grammar, 'getForeignKey', [$foreign]);

    expect($sql)->toBe(', foreign key("user_id") references "users"("id")');
})->group('unit', 'sqlite-grammar');

test('add primary keys', function () {
    $blueprint = new Blueprint('test');
    $blueprint->primary(['id', 'code']);

    $primaryKeys = callProtectedMethodSQLite($this->grammar, 'addPrimaryKeys', [$blueprint]);

    expect($primaryKeys)->toBe(', primary key ("id", "code")');
})->group('unit', 'sqlite-grammar');

test('add primary keys with no primary', function () {
    $blueprint = new Blueprint('test');

    $primaryKeys = callProtectedMethodSQLite($this->grammar, 'addPrimaryKeys', [$blueprint]);

    expect($primaryKeys)->toBe('');
})->group('unit', 'sqlite-grammar');

test('compile add', function () {
    $blueprint = new Blueprint('users');
    $blueprint->string('email');
    $blueprint->integer('age')->nullable();

    $command = new Fluent(['name' => 'add']);
    $result = $this->grammar->compileAdd($blueprint, $command, $this->connection);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);
    expect($result[0])->toBe('alter table "users" add column "email" text not null');
    expect($result[1])->toBe('alter table "users" add column "age" integer');
})->group('unit', 'sqlite-grammar');

test('compile change', function () {
    $blueprint = new Blueprint('users');
    $blueprint->string('name', 100)->change();

    $command = new Fluent(['name' => 'change']);
    $sql = $this->grammar->compileChange($blueprint, $command, $this->connection);

    // Should contain table recreation statements
    expect($sql)->toContain('pragma foreign_keys = off');
    expect($sql)->toContain('pragma foreign_keys = on');
    expect($sql)->toContain('__temp__users');
})->group('unit', 'sqlite-grammar');

test('compile drop', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'drop']);
    $sql = $this->grammar->compileDrop($blueprint, $command, $this->connection);

    expect($sql)->toBe('drop table "users"');
})->group('unit', 'sqlite-grammar');

test('compile drop if exists', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'dropIfExists']);
    $sql = $this->grammar->compileDropIfExists($blueprint, $command, $this->connection);

    expect($sql)->toBe('drop table if exists "users"');
})->group('unit', 'sqlite-grammar');

test('compile drop column with new sqlite', function () {
    // Mock connection to return newer SQLite version
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('select')
        ->andReturn([(object) ['version' => '3.36.0']]);

    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'dropColumn', 'columns' => ['name', 'email']]);
    $result = $this->grammar->compileDropColumn($blueprint, $command, $connection);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);
    expect($result[0])->toBe('alter table "users" drop column "name"');
    expect($result[1])->toBe('alter table "users" drop column "email"');
})->group('unit', 'sqlite-grammar');

test('compile drop column with old sqlite', function () {
    // Mock connection to return older SQLite version
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('select')
        ->andReturn([(object) ['version' => '3.30.0']]);

    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'dropColumn', 'columns' => ['name']]);
    $result = $this->grammar->compileDropColumn($blueprint, $command, $connection);

    // Should return table recreation statements
    expect($result)->toBeArray();
    expect($result)->toContain('pragma foreign_keys = off');
    expect($result)->toContain('pragma foreign_keys = on');
})->group('unit', 'sqlite-grammar');

test('compile rename', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'rename', 'to' => 'customers']);
    $sql = $this->grammar->compileRename($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table "users" rename to "customers"');
})->group('unit', 'sqlite-grammar');

test('compile rename column with new sqlite', function () {
    // Mock connection to return newer SQLite version
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('select')
        ->andReturn([(object) ['version' => '3.26.0']]);

    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'renameColumn', 'from' => 'name', 'to' => 'full_name']);
    $sql = $this->grammar->compileRenameColumn($blueprint, $command, $connection);

    expect($sql)->toBe('alter table "users" rename column "name" to "full_name"');
})->group('unit', 'sqlite-grammar');

test('compile rename column with old sqlite', function () {
    // Mock connection to return older SQLite version
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('select')
        ->andReturn([(object) ['version' => '3.20.0']]);

    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'renameColumn', 'from' => 'name', 'to' => 'full_name']);
    $result = $this->grammar->compileRenameColumn($blueprint, $command, $connection);

    // Should return table recreation statements as a joined string
    expect($result)->toBeString();
    expect($result)->toContain('pragma foreign_keys = off');
    expect($result)->toContain('pragma foreign_keys = on');
})->group('unit', 'sqlite-grammar');

test('recreate table', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'change']);

    $statements = callProtectedMethodSQLite($this->grammar, 'recreateTable', [$blueprint, $command, $this->connection]);

    expect($statements)->toBeArray();
    expect($statements)->toHaveCount(6);
    expect($statements[0])->toBe('pragma foreign_keys = off');
    expect($statements[1])->toContain('create table "__temp__users"');
    expect($statements[2])->toContain('insert into "__temp__users" select * from "users"');
    expect($statements[3])->toBe('drop table "users"');
    expect($statements[4])->toContain('rename to "users"');
    expect($statements[5])->toBe('pragma foreign_keys = on');
})->group('unit', 'sqlite-grammar');

test('get create table sql', function () {
    $blueprint = new Blueprint('users');
    $tempTable = '"__temp__users"';

    $sql = callProtectedMethodSQLite($this->grammar, 'getCreateTableSql', [$blueprint, $tempTable, $this->connection]);

    expect($sql)->toBe('create table "__temp__users" as select * from "users" where 0');
})->group('unit', 'sqlite-grammar');

test('compile enable foreign key constraints', function () {
    $sql = $this->grammar->compileEnableForeignKeyConstraints();
    expect($sql)->toBe('PRAGMA foreign_keys = ON');
})->group('unit', 'sqlite-grammar');

test('compile disable foreign key constraints', function () {
    $sql = $this->grammar->compileDisableForeignKeyConstraints();
    expect($sql)->toBe('PRAGMA foreign_keys = OFF');
})->group('unit', 'sqlite-grammar');

test('compile table exists', function () {
    $sql = $this->grammar->compileTableExists();
    $expected = "select * from sqlite_master where type = 'table' and name = ? union all select * from sqlite_temp_master where type = 'table' and name = ?";
    expect($sql)->toBe($expected);
})->group('unit', 'sqlite-grammar');

test('compile column listing', function () {
    $sql = $this->grammar->compileColumnListing('users');
    expect($sql)->toBe('pragma table_info(users)');
})->group('unit', 'sqlite-grammar');

test('compile column type', function () {
    $sql = $this->grammar->compileColumnType('users', 'name');
    expect($sql)->toBe("select type from pragma_table_info('users') where name = ?");
})->group('unit', 'sqlite-grammar');

test('all column types', function () {
    $blueprint = new Blueprint('test_table');

    // Test all SQLite column types
    $blueprint->char('char_col', 10);
    $blueprint->string('string_col');
    $blueprint->tinyText('tinytext_col');
    $blueprint->text('text_col');
    $blueprint->mediumText('mediumtext_col');
    $blueprint->longText('longtext_col');
    $blueprint->integer('int_col');
    $blueprint->bigInteger('bigint_col');
    $blueprint->mediumInteger('mediumint_col');
    $blueprint->tinyInteger('tinyint_col');
    $blueprint->smallInteger('smallint_col');
    $blueprint->float('float_col');
    $blueprint->double('double_col');
    $blueprint->decimal('decimal_col', 10, 2);
    $blueprint->boolean('bool_col');
    $blueprint->enum('enum_col', ['yes', 'no']);
    $blueprint->set('set_col', ['a', 'b', 'c']);
    $blueprint->json('json_col');
    $blueprint->jsonb('jsonb_col');
    $blueprint->date('date_col');
    $blueprint->dateTime('datetime_col');
    $blueprint->dateTimeTz('datetimetz_col');
    $blueprint->time('time_col');
    $blueprint->timeTz('timetz_col');
    $blueprint->timestamp('timestamp_col');
    $blueprint->timestampTz('timestamptz_col');
    $blueprint->year('year_col');
    $blueprint->binary('binary_col');
    $blueprint->uuid('uuid_col');
    $blueprint->ipAddress('ip_col');
    $blueprint->macAddress('mac_col');
    $blueprint->geometry('geometry_col');
    $blueprint->point('point_col');
    $blueprint->lineString('linestring_col');
    $blueprint->polygon('polygon_col');
    $blueprint->geometryCollection('geomcollection_col');
    $blueprint->multiPoint('multipoint_col');
    $blueprint->multiLineString('multilinestring_col');
    $blueprint->multiPolygon('multipolygon_col');
    $blueprint->computed('computed_col', 'price * quantity');

    $command = new Fluent(['name' => 'create']);
    $sql = $this->grammar->compileCreate($blueprint, $command, $this->connection);

    expect($sql)->toBeString();
    expect($sql)->toContain('create table');
    expect($sql)->toContain('"enum_col" text check ("enum_col" in (\'yes\', \'no\'))');
    expect($sql)->toContain('"computed_col" text as (price * quantity)');
})->group('unit', 'sqlite-grammar');

test('column modifiers', function () {
    $blueprint = new Blueprint('test_table');
    $blueprint->string('name')->nullable()->default('test');
    $blueprint->integer('count')->autoIncrement();
    $blueprint->timestamp('created_at')->useCurrent();

    $command = new Fluent(['name' => 'create']);
    $sql = $this->grammar->compileCreate($blueprint, $command, $this->connection);

    expect($sql)->toContain('"name" text default \'test\'');
    expect($sql)->toContain('"count" integer');
    expect($sql)->toContain('primary key autoincrement');
    expect($sql)->toContain('"created_at" datetime');
    expect($sql)->toContain('default current_timestamp');
})->group('unit', 'sqlite-grammar');

test('modify nullable for auto increment', function () {
    $blueprint = new Blueprint('test');
    $column = new Fluent(['type' => 'integer', 'autoIncrement' => true]);

    $modifier = callProtectedMethodSQLite($this->grammar, 'modifyNullable', [$blueprint, $column]);
    expect($modifier)->toBe('');
})->group('unit', 'sqlite-grammar');

test('modify nullable for regular column', function () {
    $blueprint = new Blueprint('test');
    $nullableColumn = new Fluent(['type' => 'string', 'nullable' => true]);
    $notNullColumn = new Fluent(['type' => 'string', 'nullable' => false]);

    expect(callProtectedMethodSQLite($this->grammar, 'modifyNullable', [$blueprint, $nullableColumn]))->toBe('');
    expect(callProtectedMethodSQLite($this->grammar, 'modifyNullable', [$blueprint, $notNullColumn]))->toBe(' not null');
})->group('unit', 'sqlite-grammar');

test('modify default with use current', function () {
    $blueprint = new Blueprint('test');
    $column = new Fluent(['type' => 'timestamp', 'useCurrent' => true]);

    $modifier = callProtectedMethodSQLite($this->grammar, 'modifyDefault', [$blueprint, $column]);
    expect($modifier)->toBe(' default current_timestamp');
})->group('unit', 'sqlite-grammar');

test('modify increment for serial types', function () {
    $blueprint = new Blueprint('test');
    $types = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    foreach ($types as $type) {
        $column = new Fluent(['type' => $type, 'autoIncrement' => true]);
        $modifier = callProtectedMethodSQLite($this->grammar, 'modifyIncrement', [$blueprint, $column]);
        expect($modifier)->toBe(' primary key autoincrement');
    }
})->group('unit', 'sqlite-grammar');

test('compile primary', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['columns' => ['id']]);

    $sql = $this->grammar->compilePrimary($blueprint, $command);
    expect($sql)->toBe('');
})->group('unit', 'sqlite-grammar');

test('compile unique', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['columns' => ['email'], 'index' => 'users_email_unique']);

    $sql = $this->grammar->compileUnique($blueprint, $command);
    expect($sql)->toBe('create unique index "users_email_unique" on "users" ("email")');
})->group('unit', 'sqlite-grammar');

test('compile index', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['columns' => ['name'], 'index' => 'users_name_index']);

    $sql = $this->grammar->compileIndex($blueprint, $command);
    expect($sql)->toBe('create index "users_name_index" on "users" ("name")');
})->group('unit', 'sqlite-grammar');

test('compile fulltext throws exception', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent(['columns' => ['content']]);

    expect(fn() => $this->grammar->compileFulltext($blueprint, $command))
        ->toThrow(RuntimeException::class, 'SQLite does not support fulltext indexes. Use FTS virtual tables instead.');
})->group('unit', 'sqlite-grammar');

test('compile spatial index throws exception', function () {
    $blueprint = new Blueprint('places');
    $command = new Fluent(['columns' => ['location']]);

    expect(fn() => $this->grammar->compileSpatialIndex($blueprint, $command))
        ->toThrow(RuntimeException::class, 'SQLite does not support spatial indexes.');
})->group('unit', 'sqlite-grammar');

test('compile foreign', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent(['columns' => ['user_id']]);

    $sql = $this->grammar->compileForeign($blueprint, $command);
    expect($sql)->toBe('');
})->group('unit', 'sqlite-grammar');

test('compile drop primary throws exception', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent([]);

    expect(fn() => $this->grammar->compileDropPrimary($blueprint, $command))
        ->toThrow(RuntimeException::class, 'SQLite does not support dropping primary keys.');
})->group('unit', 'sqlite-grammar');

test('compile drop unique', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['index' => 'users_email_unique']);

    $sql = $this->grammar->compileDropUnique($blueprint, $command);
    expect($sql)->toBe('drop index "users_email_unique"');
})->group('unit', 'sqlite-grammar');

test('compile drop index', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['index' => 'users_name_index']);

    $sql = $this->grammar->compileDropIndex($blueprint, $command);
    expect($sql)->toBe('drop index "users_name_index"');
})->group('unit', 'sqlite-grammar');

test('compile drop foreign throws exception', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent(['index' => 'posts_user_id_foreign']);

    expect(fn() => $this->grammar->compileDropForeign($blueprint, $command))
        ->toThrow(RuntimeException::class, 'SQLite does not support dropping foreign keys.');
})->group('unit', 'sqlite-grammar');

test('get command by name', function () {
    $blueprint = new Blueprint('users');
    $blueprint->primary(['id']);
    $blueprint->unique(['email']);

    $primaryCommand = callProtectedMethodSQLite($this->grammar, 'getCommandByName', [$blueprint, 'primary']);
    $uniqueCommand = callProtectedMethodSQLite($this->grammar, 'getCommandByName', [$blueprint, 'unique']);
    $nonExistentCommand = callProtectedMethodSQLite($this->grammar, 'getCommandByName', [$blueprint, 'nonexistent']);

    expect($primaryCommand)->toBeInstanceOf(Fluent::class);
    expect($primaryCommand->name)->toBe('primary');
    expect($uniqueCommand)->toBeInstanceOf(Fluent::class);
    expect($uniqueCommand->name)->toBe('unique');
    expect($nonExistentCommand)->toBeNull();
})->group('unit', 'sqlite-grammar');

test('wrap value', function () {
    expect(callProtectedMethodSQLite($this->grammar, 'wrapValue', ['*']))->toBe('*');
    expect(callProtectedMethodSQLite($this->grammar, 'wrapValue', ['column']))->toBe('"column"');
    expect(callProtectedMethodSQLite($this->grammar, 'wrapValue', ['"quoted"']))->toBe('"""quoted"""');
})->group('unit', 'sqlite-grammar');

test('supports schema transactions', function () {
    expect($this->grammar->supportsSchemaTransactions())->toBeFalse();
})->group('unit', 'sqlite-grammar');

test('wrap array', function () {
    $values = ['column1', 'column2', 'table.column3'];
    $expected = ['"column1"', '"column2"', '"table"."column3"'];

    $result = callProtectedMethodSQLite($this->grammar, 'wrapArray', [$values]);
    expect($result)->toBe($expected);
})->group('unit', 'sqlite-grammar');

test('table prefix', function () {
    $this->grammar->setTablePrefix('app_');
    $blueprint = new Blueprint('users');

    expect($this->grammar->wrapTable($blueprint))->toBe('"app_users"');
})->group('unit', 'sqlite-grammar');

afterEach(function () {
    Mockery::close();
});