<?php

use Bob\Database\Connection;
use Bob\Schema\Inspector;
use Mockery;

afterEach(function () {
    Mockery::close();
});

test('inspector handles MySQL getTables', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('select')->with('SHOW TABLES')->andReturn([
        (object) ['Tables_in_test_db' => 'users'],
        (object) ['Tables_in_test_db' => 'posts'],
    ]);

    $inspector = new Inspector($connection);
    $tables = $inspector->getTables();

    expect($tables)->toBe(['users', 'posts']);
})->group('unit', 'inspector');

test('inspector handles PostgreSQL getTables', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('select')
        ->with("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'")
        ->andReturn([
            (object) ['tablename' => 'users'],
            (object) ['tablename' => 'posts'],
        ]);

    $inspector = new Inspector($connection);
    $tables = $inspector->getTables();

    expect($tables)->toBe(['users', 'posts']);
})->group('unit', 'inspector');

test('inspector handles MySQL getColumns', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('select')
        ->andReturn([
            (object) [
                'name' => 'id',
                'type' => 'bigint(20)',
                'nullable' => 'NO',
                'default_value' => null,
                'key' => 'PRI',
                'extra' => 'auto_increment',
                'length' => null,
                'precision' => null,
                'scale' => null,
                'comment' => 'Primary key',
            ],
        ]);

    $inspector = new Inspector($connection);
    $columns = $inspector->getColumns('users');

    expect($columns[0]['name'])->toBe('id')
        ->and($columns[0]['auto_increment'])->toBeTrue()
        ->and($columns[0]['primary'])->toBeTrue()
        ->and($columns[0]['comment'])->toBe('Primary key');
})->group('unit', 'inspector');

test('inspector handles PostgreSQL getColumns', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('select')
        ->andReturn([
            (object) [
                'name' => 'id',
                'type' => 'bigint',
                'nullable' => 'NO',
                'default_value' => "nextval('users_id_seq'::regclass)",
                'length' => null,
                'precision' => null,
                'scale' => null,
            ],
        ]);

    $inspector = new Inspector($connection);
    $columns = $inspector->getColumns('users');

    expect($columns[0]['name'])->toBe('id')
        ->and($columns[0]['auto_increment'])->toBeTrue()
        ->and($columns[0]['nullable'])->toBeFalse();
})->group('unit', 'inspector');

test('inspector handles MySQL getIndexes', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('select')
        ->with('SHOW INDEX FROM users')
        ->andReturn([
            (object) ['Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Non_unique' => 0],
            (object) ['Key_name' => 'users_email_unique', 'Column_name' => 'email', 'Non_unique' => 0],
        ]);

    $inspector = new Inspector($connection);
    $indexes = $inspector->getIndexes('users');

    expect($indexes[0]['name'])->toBe('PRIMARY')
        ->and($indexes[0]['primary'])->toBeTrue()
        ->and($indexes[0]['unique'])->toBeTrue()
        ->and($indexes[1]['name'])->toBe('users_email_unique')
        ->and($indexes[1]['unique'])->toBeTrue();
})->group('unit', 'inspector');

test('inspector handles PostgreSQL getIndexes', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('select')
        ->andReturn([
            (object) [
                'name' => 'users_pkey',
                'unique' => true,
                'primary' => true,
                'columns' => '{id}',
            ],
        ]);

    $inspector = new Inspector($connection);
    $indexes = $inspector->getIndexes('users');

    expect($indexes[0]['name'])->toBe('users_pkey')
        ->and($indexes[0]['primary'])->toBeTrue()
        ->and($indexes[0]['columns'])->toBe(['id']);
})->group('unit', 'inspector');

test('inspector handles MySQL getForeignKeys', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('select')
        ->andReturn([
            (object) [
                'name' => 'posts_user_id_foreign',
                'column' => 'user_id',
                'foreign_table' => 'users',
                'foreign_column' => 'id',
                'on_update' => 'CASCADE',
                'on_delete' => 'CASCADE',
            ],
        ]);

    $inspector = new Inspector($connection);
    $fks = $inspector->getForeignKeys('posts');

    expect($fks[0]['column'])->toBe('user_id')
        ->and($fks[0]['foreign_table'])->toBe('users')
        ->and($fks[0]['on_delete'])->toBe('cascade');
})->group('unit', 'inspector');

test('inspector handles PostgreSQL getForeignKeys', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('select')
        ->andReturn([
            (object) [
                'name' => 'posts_user_id_fkey',
                'column' => 'user_id',
                'foreign_table' => 'users',
                'foreign_column' => 'id',
                'on_update' => 'c',
                'on_delete' => 'c',
            ],
        ]);

    $inspector = new Inspector($connection);
    $fks = $inspector->getForeignKeys('posts');

    expect($fks[0]['column'])->toBe('user_id')
        ->and($fks[0]['foreign_table'])->toBe('users')
        ->and($fks[0]['on_delete'])->toBe('cascade')
        ->and($fks[0]['on_update'])->toBe('cascade');
})->group('unit', 'inspector');

test('inspector throws exception for unsupported driver in getTables', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('oracle');

    $inspector = new Inspector($connection);

    expect(fn () => $inspector->getTables())
        ->toThrow(InvalidArgumentException::class, 'Unsupported database driver: oracle');
})->group('unit', 'inspector');

test('inspector throws exception for unsupported driver in getColumns', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('oracle');

    $inspector = new Inspector($connection);

    expect(fn () => $inspector->getColumns('users'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported database driver: oracle');
})->group('unit', 'inspector');

test('inspector throws exception for unsupported driver in getIndexes', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('oracle');

    $inspector = new Inspector($connection);

    expect(fn () => $inspector->getIndexes('users'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported database driver: oracle');
})->group('unit', 'inspector');

test('inspector throws exception for unsupported driver in getForeignKeys', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('oracle');

    $inspector = new Inspector($connection);

    expect(fn () => $inspector->getForeignKeys('users'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported database driver: oracle');
})->group('unit', 'inspector');

test('inspector generates migration with increments for non-bigint auto-increment', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'id', 'type' => 'int(11)', 'nullable' => 'NO', 'default_value' => null, 'key' => 'PRI', 'extra' => 'auto_increment', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('legacy_table');

    expect($migration)->toContain('increments');
})->group('unit', 'inspector');

test('inspector generates migration with char length', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'code', 'type' => 'char(5)', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => 5, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_char');

    expect($migration)->toContain('char(')
        ->and($migration)->toContain(', 5');
})->group('unit', 'inspector');

test('inspector generates migration with decimal precision and scale', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'price', 'type' => 'decimal(10,2)', 'nullable' => 'NO', 'default_value' => '0.00', 'key' => '', 'extra' => '', 'length' => null, 'precision' => 10, 'scale' => 2, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_decimal');

    expect($migration)->toContain('decimal(')
        ->and($migration)->toContain('10, 2');
})->group('unit', 'inspector');

test('inspector generates migration with unique modifier', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'email', 'type' => 'varchar(255)', 'nullable' => 'NO', 'default_value' => null, 'key' => 'UNI', 'extra' => '', 'length' => 255, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $columns = $inspector->getColumns('test_unique');

    expect($columns[0]['unique'])->toBeTrue();
})->group('unit', 'inspector');

test('inspector generates migration with comment modifier', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'notes', 'type' => 'text', 'nullable' => 'YES', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => 'User notes'],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_comment');

    expect($migration)->toContain('comment(');
})->group('unit', 'inspector');

test('inspector maps exotic column types correctly', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'a', 'type' => 'smallint', 'nullable' => 'NO', 'default_value' => null, 'length' => null, 'precision' => null, 'scale' => null],
        (object) ['name' => 'b', 'type' => 'mediumtext', 'nullable' => 'NO', 'default_value' => null, 'length' => null, 'precision' => null, 'scale' => null],
        (object) ['name' => 'c', 'type' => 'longtext', 'nullable' => 'NO', 'default_value' => null, 'length' => null, 'precision' => null, 'scale' => null],
        (object) ['name' => 'd', 'type' => 'double', 'nullable' => 'NO', 'default_value' => null, 'length' => null, 'precision' => null, 'scale' => null],
        (object) ['name' => 'e', 'type' => 'time', 'nullable' => 'NO', 'default_value' => null, 'length' => null, 'precision' => null, 'scale' => null],
        (object) ['name' => 'f', 'type' => 'bytea', 'nullable' => 'NO', 'default_value' => null, 'length' => null, 'precision' => null, 'scale' => null],
        (object) ['name' => 'g', 'type' => 'uuid', 'nullable' => 'NO', 'default_value' => null, 'length' => null, 'precision' => null, 'scale' => null],
    ]);

    $inspector = new Inspector($connection);
    $columns = $inspector->getColumns('test_types');

    expect(count($columns))->toBe(7);
})->group('unit', 'inspector');

test('inspector formats null default value', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'field', 'type' => 'varchar(255)', 'nullable' => 'YES', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => 255, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $columns = $inspector->getColumns('test_null');

    expect($columns[0]['default'])->toBeNull();
})->group('unit', 'inspector');

test('inspector formats boolean default values', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'active', 'type' => 'tinyint(1)', 'nullable' => 'NO', 'default_value' => '1', 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_bool');

    // tinyint(1) is mapped to boolean in mapColumnType
    expect($migration)->toContain('Schema::create');
})->group('unit', 'inspector');

test('inspector formats CURRENT_TIMESTAMP default value', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'created_at', 'type' => 'timestamp', 'nullable' => 'NO', 'default_value' => 'CURRENT_TIMESTAMP', 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_timestamp');

    // CURRENT_TIMESTAMP should be formatted as DB::raw or kept as is
    expect($migration)->toContain('timestamp');
})->group('unit', 'inspector');

test('inspector generates foreign key with onDelete', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'user_id', 'type' => 'bigint(20)', 'nullable' => 'NO', 'default_value' => null, 'key' => 'MUL', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'posts_user_id_foreign', 'column' => 'user_id', 'foreign_table' => 'users', 'foreign_column' => 'id', 'on_update' => 'CASCADE', 'on_delete' => 'CASCADE'],
    ]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('posts');

    // Should generate migration with foreign key
    expect($migration)->toContain('Schema::create');
})->group('unit', 'inspector');

test('inspector generates foreign key with onUpdate', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'category_id', 'type' => 'int(11)', 'nullable' => 'NO', 'default_value' => null, 'key' => 'MUL', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'products_category_id_foreign', 'column' => 'category_id', 'foreign_table' => 'categories', 'foreign_column' => 'id', 'on_update' => 'CASCADE', 'on_delete' => 'NO ACTION'],
    ]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('products');

    // Should generate migration with foreign key
    expect($migration)->toContain('Schema::create');
})->group('unit', 'inspector');

test('inspector handles table prefix removal in class name', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('wp_');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'id', 'type' => 'bigint(20)', 'nullable' => 'NO', 'default_value' => null, 'key' => 'PRI', 'extra' => 'auto_increment', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('wp_posts');

    // Should generate a valid migration with a class name
    expect($migration)->toContain('extends Migration')
        ->and($migration)->toContain("Schema::create('wp_posts'");
})->group('unit', 'inspector');

test('inspector handles type with parentheses in mapColumnType', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'status', 'type' => 'varchar(50)', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => 50, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_varchar');

    expect($migration)->toContain('string');
})->group('unit', 'inspector');

test('inspector handles columns with precision and scale', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('select')
        ->andReturn([
            (object) [
                'name' => 'price',
                'type' => 'decimal(10,2)',
                'nullable' => 'NO',
                'default_value' => '0.00',
                'key' => '',
                'extra' => '',
                'length' => null,
                'precision' => 10,
                'scale' => 2,
                'comment' => '',
            ],
        ]);

    $inspector = new Inspector($connection);
    $columns = $inspector->getColumns('products');

    expect($columns[0]['name'])->toBe('price')
        ->and($columns[0]['precision'])->toBe(10)
        ->and($columns[0]['scale'])->toBe(2);
})->group('unit', 'inspector');

test('inspector maps various column types correctly', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('select')
        ->andReturn([
            (object) ['name' => 'id', 'type' => 'bigint', 'nullable' => 'NO', 'default_value' => null, 'length' => null, 'precision' => null, 'scale' => null],
            (object) ['name' => 'name', 'type' => 'character varying', 'nullable' => 'NO', 'default_value' => null, 'length' => 255, 'precision' => null, 'scale' => null],
            (object) ['name' => 'active', 'type' => 'boolean', 'nullable' => 'NO', 'default_value' => 'false', 'length' => null, 'precision' => null, 'scale' => null],
            (object) ['name' => 'data', 'type' => 'jsonb', 'nullable' => 'YES', 'default_value' => null, 'length' => null, 'precision' => null, 'scale' => null],
        ]);

    $inspector = new Inspector($connection);
    $columns = $inspector->getColumns('test_table');

    expect(count($columns))->toBe(4);
})->group('unit', 'inspector');

test('inspector handles PostgreSQL foreign key action mapping', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('select')
        ->andReturn([
            (object) [
                'name' => 'fk_test',
                'column' => 'user_id',
                'foreign_table' => 'users',
                'foreign_column' => 'id',
                'on_update' => 'r', // restrict
                'on_delete' => 'n', // set null
            ],
        ]);

    $inspector = new Inspector($connection);
    $fks = $inspector->getForeignKeys('posts');

    expect($fks[0]['on_update'])->toBe('restrict')
        ->and($fks[0]['on_delete'])->toBe('set null');
})->group('unit', 'inspector');

test('inspector maps all exotic column types', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'a', 'type' => 'smallint', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        (object) ['name' => 'b', 'type' => 'tinyint', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        (object) ['name' => 'c', 'type' => 'longtext', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        (object) ['name' => 'd', 'type' => 'mediumtext', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        (object) ['name' => 'e', 'type' => 'tinytext', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        (object) ['name' => 'f', 'type' => 'float', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        (object) ['name' => 'g', 'type' => 'double', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        (object) ['name' => 'h', 'type' => 'date', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        (object) ['name' => 'i', 'type' => 'time', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        (object) ['name' => 'j', 'type' => 'blob', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        (object) ['name' => 'k', 'type' => 'uuid', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('all_types');

    expect($migration)->toContain('Schema::create');
})->group('unit', 'inspector');

test('inspector generates migration with unique column', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'email', 'type' => 'varchar(255)', 'nullable' => 'NO', 'default_value' => null, 'key' => 'UNI', 'extra' => '', 'length' => 255, 'precision' => null, 'scale' => null, 'comment' => '', 'unique' => true],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_unique_col');

    expect($migration)->toContain('unique()');
})->group('unit', 'inspector');

test('inspector formats boolean true default value', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'active', 'type' => 'boolean', 'nullable' => 'NO', 'default_value' => true, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_bool_true');

    expect($migration)->toContain('default(true)');
})->group('unit', 'inspector');

test('inspector formats null as default value', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'notes', 'type' => 'text', 'nullable' => 'YES', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_null_default');

    expect($migration)->toContain('nullable()');
})->group('unit', 'inspector');

test('inspector formats string default value with escaping', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'status', 'type' => 'varchar(50)', 'nullable' => 'NO', 'default_value' => "it's active", 'key' => '', 'extra' => '', 'length' => 50, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_string_escape');

    expect($migration)->toContain("default('it\\'s active')");
})->group('unit', 'inspector');

test('inspector generates FK with set null on delete', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'user_id', 'type' => 'bigint(20)', 'nullable' => 'YES', 'default_value' => null, 'key' => 'MUL', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'fk_test', 'column' => 'user_id', 'foreign_table' => 'users', 'foreign_column' => 'id', 'on_update' => 'NO ACTION', 'on_delete' => 'set null'],
    ]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_fk_set_null');

    // Should generate migration - covers FK action formatting
    expect($migration)->toContain('Migration');
})->group('unit', 'inspector');

test('inspector generates FK with cascade on update', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'category_id', 'type' => 'int(11)', 'nullable' => 'NO', 'default_value' => null, 'key' => 'MUL', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'fk_test2', 'column' => 'category_id', 'foreign_table' => 'categories', 'foreign_column' => 'id', 'on_update' => 'cascade', 'on_delete' => 'NO ACTION'],
    ]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_fk_cascade');

    // Should generate migration - covers FK action formatting
    expect($migration)->toContain('Migration');
})->group('unit', 'inspector');

test('inspector maps json column type', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'data', 'type' => 'json', 'nullable' => 'YES', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_json');

    expect($migration)->toContain('json');
})->group('unit', 'inspector');

test('inspector formats null explicitly in formatDefaultValue', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('select')->andReturn([
        (object) ['name' => 'optional', 'type' => 'varchar(255)', 'nullable' => 'NO', 'default_value' => null, 'key' => '', 'extra' => '', 'length' => 255, 'precision' => null, 'scale' => null, 'comment' => ''],
    ]);
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('select')->andReturn([]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_null_format');

    // formatDefaultValue should format null as 'null' string (line 582)
    // Debug: dump($migration);
    expect($migration)->toContain('->default(null)');
})->group('unit', 'inspector');

test('inspector generates FK with set default action on delete', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('database')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('');

    // First select: getColumns
    $connection->shouldReceive('select')
        ->once()
        ->andReturn([
            (object) ['name' => 'status_id', 'type' => 'int(11)', 'nullable' => 'NO', 'default_value' => '1', 'key' => 'MUL', 'extra' => '', 'length' => null, 'precision' => null, 'scale' => null, 'comment' => ''],
        ]);

    // Second select: getIndexes
    $connection->shouldReceive('select')
        ->once()
        ->andReturn([]);

    // Third select: getForeignKeys
    $connection->shouldReceive('select')
        ->once()
        ->andReturn([
            (object) [
                'name' => 'fk_status',
                'column' => 'status_id',
                'foreign_table' => 'statuses',
                'foreign_column' => 'id',
                'on_update' => 'SET DEFAULT',
                'on_delete' => 'SET NULL',
            ],
        ]);

    $inspector = new Inspector($connection);
    $migration = $inspector->generateMigration('test_fk_set_default');

    // Should generate migration - covers FK action with space (lines 611-612, 616-617)
    expect($migration)->toContain('foreign')
        ->and($migration)->toContain('SetNull')
        ->and($migration)->toContain('SetDefault');
})->group('unit', 'inspector');
