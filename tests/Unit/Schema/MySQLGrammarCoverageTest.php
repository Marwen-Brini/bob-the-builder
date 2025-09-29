<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Schema\Blueprint;
use Bob\Schema\Grammars\MySQLGrammar;
use Bob\Schema\Fluent;
use Bob\Database\Connection;

beforeEach(function () {
    $this->grammar = new MySQLGrammar();

    $this->connection = \Mockery::mock(Connection::class);
    $this->connection->shouldReceive('getTablePrefix')->andReturn('');
    $this->connection->shouldReceive('getConfig')->andReturn(null);
});

afterEach(function () {
    \Mockery::close();
});

/**
 * Helper method to call protected methods using reflection
 */
function callProtectedMethodMySQL($object, $method, ...$args)
{
    $reflection = new ReflectionClass($object);
    $reflectionMethod = $reflection->getMethod($method);
    $reflectionMethod->setAccessible(true);
    return $reflectionMethod->invoke($object, ...$args);
}

test('compile create with primary key algorithm (covers lines 65-71)', function () {
    $blueprint = new Blueprint('test_table');
    $createCommand = $blueprint->create();
    $blueprint->string('name');

    // Add primary key with algorithm
    $primaryCommand = $blueprint->primary(['name']);
    $primaryCommand->algorithm = 'BTREE';

    $sql = $this->grammar->compileCreate($blueprint, $createCommand, $this->connection);

    expect($sql)->toContain('primary key using btree');
});

test('compile create with charset (covers line 90)', function () {
    $blueprint = new Blueprint('test_table');
    $createCommand = $blueprint->create();
    $blueprint->charset = 'utf8mb4';
    $blueprint->string('name');

    $sql = $this->grammar->compileCreate($blueprint, $createCommand, $this->connection);

    expect($sql)->toContain('default character set utf8mb4');
});

test('compile create with collation from config (covers line 96)', function () {
    $blueprint = new Blueprint('test_table');
    $createCommand = $blueprint->create();
    $blueprint->string('name');

    // Mock connection to return collation config
    $connection = \Mockery::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->with('charset')->andReturn(null);
    $connection->shouldReceive('getConfig')->with('collation')->andReturn('utf8mb4_unicode_ci');

    $sql = $this->grammar->compileCreate($blueprint, $createCommand, $connection);

    expect($sql)->toContain("collate 'utf8mb4_unicode_ci'");
});

test('compile drop all tables (covers line 211)', function () {
    $sql = callProtectedMethodMySQL($this->grammar, 'compileDropAllTables');

    expect($sql)->toBe(
        "SELECT CONCAT('DROP TABLE IF EXISTS `', table_name, '`;') FROM information_schema.tables WHERE table_schema = DATABASE()"
    );
});

test('type float without precision (covers line 351)', function () {
    $column = new Fluent(['type' => 'float']);
    $result = callProtectedMethodMySQL($this->grammar, 'typeFloat', $column);

    expect($result)->toBe('float');
});

test('type double without precision (covers line 363)', function () {
    $column = new Fluent(['type' => 'double']);
    $result = callProtectedMethodMySQL($this->grammar, 'typeDouble', $column);

    expect($result)->toBe('double');
});

test('modify nullable with generated column (covers line 656)', function () {
    $blueprint = new Blueprint('test_table');

    // Column with virtual generated column and nullable = false
    $column = new Fluent(['nullable' => false, 'virtualAs' => 'some_expression']);
    $result = callProtectedMethodMySQL($this->grammar, 'modifyNullable', $blueprint, $column);

    expect($result)->toBe(' not null');
});

test('modify invisible with true (covers line 740)', function () {
    $blueprint = new Blueprint('test_table');
    $column = new Fluent(['invisible' => true]);
    $result = callProtectedMethodMySQL($this->grammar, 'modifyInvisible', $blueprint, $column);

    expect($result)->toBe(' invisible');
});

test('modify srid with integer (covers line 752)', function () {
    $blueprint = new Blueprint('test_table');
    $column = new Fluent(['srid' => 4326]);
    $result = callProtectedMethodMySQL($this->grammar, 'modifySrid', $blueprint, $column);

    expect($result)->toBe(' srid 4326');
});

test('wrap value with asterisk (covers line 764)', function () {
    $result = callProtectedMethodMySQL($this->grammar, 'wrapValue', '*');

    expect($result)->toBe('*');
});

test('get command by name with skipped command (covers lines 806-807, 824)', function () {
    $blueprint = new Blueprint('test_table');

    // Add primary key command
    $primaryCommand = $blueprint->primary(['id']);
    $primaryCommand->shouldBeSkipped = true; // Mark as skipped

    // Add another primary command that is not skipped
    $nonSkippedCommand = $blueprint->primary(['name']);

    $result = callProtectedMethodMySQL($this->grammar, 'getCommandByName', $blueprint, 'primary');

    // Should return the non-skipped command
    expect($result)->not->toBeNull();
    expect($result->columns)->toBe(['name']);
});

test('get column type not found (covers default case)', function () {
    $blueprint = new Blueprint('test_table');
    $blueprint->string('existing_column');

    $result = callProtectedMethodMySQL($this->grammar, 'getColumnType', $blueprint, 'nonexistent_column');

    // Should return default type when column not found
    expect($result)->toBe('varchar(255)');
});

test('get column type found (covers line 807)', function () {
    $blueprint = new Blueprint('test_table');
    $blueprint->integer('id');
    $blueprint->string('name', 100);

    $result = callProtectedMethodMySQL($this->grammar, 'getColumnType', $blueprint, 'name');

    // Should return the actual column type when found
    expect($result)->toBe('varchar(100)');
});

test('compile create with charset from config (covers line 90)', function () {
    $blueprint = new Blueprint('test_table');
    $createCommand = $blueprint->create();
    $blueprint->string('name');
    // Blueprint does not have charset set

    // Mock connection to return charset config (but not collation)
    $connection = \Mockery::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->with('charset')->andReturn('utf8');
    $connection->shouldReceive('getConfig')->with('collation')->andReturn(null);

    $sql = $this->grammar->compileCreate($blueprint, $createCommand, $connection);

    expect($sql)->toContain('default character set utf8');
});

test('comprehensive features combination', function () {
    $blueprint = new Blueprint('test_table');
    $createCommand = $blueprint->create();
    $blueprint->charset = 'utf8mb4';
    $blueprint->collation = 'utf8mb4_unicode_ci';

    // Column with SRID and invisible modifier using reflection
    $reflection = new ReflectionClass($blueprint);
    $addColumnMethod = $reflection->getMethod('addColumn');
    $addColumnMethod->setAccessible(true);
    $geomCol = $addColumnMethod->invoke($blueprint, 'geometry', 'location', ['srid' => 4326, 'invisible' => true]);

    // Column with virtual generation and nullable false
    $virtualCol = $blueprint->string('full_name')->virtualAs('CONCAT(first_name, " ", last_name)');
    $virtualCol->nullable = false;

    // Primary key with algorithm
    $primaryCommand = $blueprint->primary(['id']);
    $primaryCommand->algorithm = 'HASH';

    $sql = $this->grammar->compileCreate($blueprint, $createCommand, $this->connection);

    // Verify multiple features are present
    expect($sql)->toContain('srid 4326');
    expect($sql)->toContain('invisible');
    expect($sql)->toContain('primary key using hash');
    expect($sql)->toContain('default character set utf8mb4');
    expect($sql)->toContain("collate 'utf8mb4_unicode_ci'");
});

test('float double type precision edge cases', function () {
    // Test float without precision (line 351)
    $floatColumn = new Fluent(['type' => 'float']);
    $floatResult = callProtectedMethodMySQL($this->grammar, 'typeFloat', $floatColumn);
    expect($floatResult)->toBe('float');

    // Test double without precision (line 363)
    $doubleColumn = new Fluent(['type' => 'double']);
    $doubleResult = callProtectedMethodMySQL($this->grammar, 'typeDouble', $doubleColumn);
    expect($doubleResult)->toBe('double');
});

test('nullable modifier edge cases', function () {
    $blueprint = new Blueprint('test_table');

    // Test with stored generated column and nullable false (line 656)
    $storedColumn = new Fluent(['nullable' => false, 'storedAs' => 'some_expression']);
    $storedResult = callProtectedMethodMySQL($this->grammar, 'modifyNullable', $blueprint, $storedColumn);
    expect($storedResult)->toBe(' not null');

    // Test with generated column and nullable false (line 656)
    $generatedColumn = new Fluent(['nullable' => false, 'generatedAs' => 'some_expression']);
    $generatedResult = callProtectedMethodMySQL($this->grammar, 'modifyNullable', $blueprint, $generatedColumn);
    expect($generatedResult)->toBe(' not null');
});

test('wrap value with backtick escaping', function () {
    // Test normal value wrapping
    $normalResult = callProtectedMethodMySQL($this->grammar, 'wrapValue', 'column_name');
    expect($normalResult)->toBe('`column_name`');

    // Test asterisk (line 764)
    $asteriskResult = callProtectedMethodMySQL($this->grammar, 'wrapValue', '*');
    expect($asteriskResult)->toBe('*');

    // Test value with backticks that need escaping
    $backtickResult = callProtectedMethodMySQL($this->grammar, 'wrapValue', 'col`umn');
    expect($backtickResult)->toBe('`col``umn`');
});